<?php

namespace Tests\Feature;

use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Offline\CanonicalPayload;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\DeviceEnrollment;
use App\Models\InventoryLocation;
use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointBalance;
use App\Models\LoyaltyPointEntry;
use App\Models\MerchantMembership;
use App\Models\OfflineCommand;
use App\Models\OperationalCustomer;
use App\Models\Register;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class OfflineReconciliationTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_scoped_bootstrap_and_cash_sale_reconciliation_are_retry_safe(): void
    {
        $fixture = $this->offlineFixture();
        app(LoyaltyService::class)->publish($fixture['merchant'], $fixture['owner'], 'Offline earn', 'offline-earn', '0.01000000', 365, []);
        $member = LoyaltyMember::factory()->create(['merchant_id' => $fixture['merchant']->id]);
        LoyaltyPointBalance::query()->create(['merchant_id' => $fixture['merchant']->id, 'loyalty_member_id' => $member->id, 'balance' => '0.0000', 'version' => 0]);
        $customer = OperationalCustomer::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'phone' => $member->canonical_phone, 'normalized_phone' => $member->canonical_phone]);
        LoyaltyCustomerLink::query()->create(['merchant_id' => $fixture['merchant']->id, 'loyalty_member_id' => $member->id, 'operational_customer_id' => $customer->id]);
        $bootstrap = $this->bootstrap($fixture)
            ->assertOk()
            ->assertJsonPath('data.payload.policy.cash_only', true)
            ->assertJsonPath('data.payload.policy.external_tenders', false)
            ->assertJsonPath('data.payload.tenant.store_id', $fixture['store']->public_id)
            ->assertJsonCount(2, 'data.payload.catalog')
            ->json('data');
        $this->assertNotEmpty($bootstrap['signed_envelope']);
        $command = $this->saleCommand($fixture, $bootstrap);
        $command['payload']['cart']['customer_id'] = $customer->public_id;
        $command['payload_hash'] = app(CanonicalPayload::class)->hash($command['payload']);

        $first = $this->sync($fixture, [$command])->assertOk()
            ->assertJsonPath('data.results.0.state', 'acknowledged')
            ->assertJsonPath('data.results.0.replayed', false);
        $saleId = $first->json('data.results.0.resource.id');
        $this->assertSame('offline', Sale::query()->sole()->source);
        $this->assertSame(1, StockMovement::query()->where('movement_kind', 'sale')->count());
        $this->assertSame('118.00', CashMovement::query()->where('kind', 'sale_cash')->value('signed_amount'));
        $this->assertSame('1.0000', LoyaltyPointEntry::query()->where('kind', 'earn')->value('points'));

        OfflineCommand::query()->sole()->forceFill(['state' => 'pending', 'resource_type' => null, 'resource_public_id' => null, 'processed_at' => null])->save();
        $this->sync($fixture, [$command])->assertOk()
            ->assertJsonPath('data.results.0.state', 'acknowledged')
            ->assertJsonPath('data.results.0.resource.id', $saleId);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_kind', 'sale')->count());
        $this->assertSame(1, LoyaltyPointEntry::query()->where('kind', 'earn')->count());

        $this->sync($fixture, [$command])->assertOk()
            ->assertJsonPath('data.results.0.state', 'acknowledged')
            ->assertJsonPath('data.results.0.replayed', true)
            ->assertJsonPath('data.results.0.resource.id', $saleId);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_kind', 'sale')->count());
        $this->assertSame(1, LoyaltyPointEntry::query()->where('kind', 'earn')->count());
        $this->assertSame(3, OfflineCommand::query()->sole()->attempt_count);
        $this->assertSame(0, $fixture['session']->refresh()->pending_offline_sale_count);
        $reports = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/';
        $range = '?store_id='.$fixture['store']->public_id;
        $this->actingAs($fixture['owner'])->getJson($reports.'sales-summary'.$range)->assertOk()->assertJsonPath('data.summary.revenue', '100.00')->assertJsonPath('data.summary.transactions', 1);
        $this->getJson($reports.'loyalty-activity'.$range)->assertOk()->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.points', fn ($points) => (float) $points === 1.0);
    }

    public function test_identity_reuse_and_stale_fence_are_preserved_as_conflicts(): void
    {
        $fixture = $this->offlineFixture();
        $bootstrap = $this->bootstrap($fixture)->assertOk()->json('data');
        $command = $this->saleCommand($fixture, $bootstrap);
        $changed = $command;
        $changed['payload']['expected_total'] = '117.00';
        $changed['payload_hash'] = app(CanonicalPayload::class)->hash($changed['payload']);

        $this->sync($fixture, [$command])->assertOk()->assertJsonPath('data.results.0.state', 'acknowledged');
        $this->sync($fixture, [$changed])->assertOk()
            ->assertJsonPath('data.results.0.state', 'conflict')
            ->assertJsonPath('data.results.0.conflict.code', 'command_identity_reused');
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame('acknowledged', OfflineCommand::query()->sole()->state);

        $stale = $this->saleCommand($fixture, $bootstrap);
        $stale['fencing_epoch'] = $fixture['device']->fencing_epoch + 1;
        $this->sync($fixture, [$stale])->assertOk()
            ->assertJsonPath('data.results.0.state', 'conflict')
            ->assertJsonPath('data.results.0.conflict.code', 'state_conflict');
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, OfflineCommand::query()->where('state', 'conflict')->count());
        $this->assertSame(1, $fixture['session']->refresh()->pending_offline_sale_count);
    }

    public function test_limited_coupon_is_not_authorized_for_offline_use_and_bad_hash_is_not_recorded(): void
    {
        $fixture = $this->offlineFixture(true);
        $bootstrap = $this->bootstrap($fixture)->assertOk()
            ->assertJsonPath('data.payload.pricing.versions.0.rules', [])
            ->json('data');
        $command = $this->saleCommand($fixture, $bootstrap);
        $command['payload_hash'] = str_repeat('0', 64);

        $this->sync($fixture, [$command])->assertOk()
            ->assertJsonPath('data.results.0.state', 'conflict')
            ->assertJsonPath('data.results.0.conflict.code', 'payload_hash_mismatch');
        $this->assertSame(0, OfflineCommand::query()->count());
        $this->assertSame(0, Sale::query()->count());
    }

    /** @return array<string, mixed> */
    private function offlineFixture(bool $limitedCoupon = false): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'fencing_epoch' => 1]);
        $device = DeviceEnrollment::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'register_id' => $register->id, 'fencing_epoch' => 1]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $rules = $limitedCoupon ? [['type' => 'coupon', 'name' => 'Limited', 'code' => 'ONE', 'priority' => 1, 'stackable' => true, 'conditions' => [], 'reward' => ['type' => 'fixed_cart', 'value' => '1'], 'maximum_redemptions' => 1]] : [];
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['mode' => $limitedCoupon ? 'advanced' : 'manual', 'rules' => $rules]))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $sessionData = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '0.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $membership = MerchantMembership::query()->where('user_id', $fixture['cashier']->id)->firstOrFail();
        $fixture['register'] = $register;
        $fixture['device'] = $device;
        $fixture['location'] = $location;
        $fixture['membership'] = $membership;
        $fixture['session'] = (new CashSession)->resolveRouteBinding($sessionData['id']);

        return $fixture;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return TestResponse<Response>
     */
    private function bootstrap(array $fixture): TestResponse
    {
        return $this->withHeader('X-Device-Key', 'test-device-key')->postJson('/api/v1/offline/bootstrap', ['device_id' => $fixture['device']->public_id, 'register_id' => $fixture['register']->public_id, 'supported_schema_versions' => [1]]);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<array<string, mixed>>  $commands
     * @return TestResponse<Response>
     */
    private function sync(array $fixture, array $commands): TestResponse
    {
        return $this->withHeader('X-Device-Key', 'test-device-key')->postJson('/api/v1/offline/transactions', ['device_id' => $fixture['device']->public_id, 'register_id' => $fixture['register']->public_id, 'commands' => $commands]);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $bootstrap
     * @return array<string, mixed>
     */
    private function saleCommand(array $fixture, array $bootstrap): array
    {
        $payload = [
            'membership_id' => $fixture['membership']->public_id,
            'register_session_id' => $fixture['session']->public_id,
            'inventory_location_id' => $fixture['location']->public_id,
            'client_transaction_id' => (string) Str::uuid(),
            'cash_tender_id' => (string) Str::uuid(),
            'cash_received' => '118.00',
            'cash_change' => '0.00',
            'expected_total' => '118.00',
            'cart' => ['lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]],
        ];

        return [
            'command_id' => (string) Str::uuid(), 'command_type' => 'finalize_cash_sale', 'schema_version' => 1,
            'committed_at_local' => now()->toAtomString(), 'snapshot_version' => $bootstrap['snapshot_version'],
            'fencing_epoch' => $fixture['device']->fencing_epoch, 'payload_hash' => app(CanonicalPayload::class)->hash($payload), 'payload' => $payload,
        ];
    }
}
