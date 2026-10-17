<?php

namespace Tests\Feature;

use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\PostCashMovement;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Register;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_opens_exactly_one_session_and_opening_is_idempotent(): void
    {
        $fixture = $this->fixture();
        $payload = $this->openPayload();
        $created = $this->actingAs($fixture['cashier'])->postJson($this->sessionsUrl($fixture), $payload)->assertCreated()
            ->assertJsonPath('data.state', 'open')->assertJsonPath('data.opening_amount', '100.00')
            ->assertJsonPath('data.expected_amount', null)->assertJsonPath('replayed', false);
        $this->actingAs($fixture['cashier'])->postJson($this->sessionsUrl($fixture), $payload)->assertOk()->assertJsonPath('data.id', $created->json('data.id'))->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($this->sessionsUrl($fixture), [...$this->openPayload(), 'idempotency_key' => (string) Str::uuid()])->assertConflict();
        $this->actingAs($fixture['cashier'])->postJson($this->sessionsUrl($fixture), [...$payload, 'opening_amount' => '200.00'])->assertConflict();
        $this->assertDatabaseCount('cash_sessions', 1);
        $this->assertSame('100.00', CashMovement::query()->sole()->signed_amount);
    }

    public function test_paid_movements_permissions_external_tender_exclusion_and_supplier_source_identity(): void
    {
        $fixture = $this->fixture();
        $session = $this->open($fixture);
        $url = $this->sessionUrl($fixture, $session).'/movements';
        $this->actingAs($fixture['cashier'])->postJson($url, $this->movementPayload('paid_in', '50.00'))->assertCreated();
        $this->actingAs($fixture['cashier'])->postJson($url, $this->movementPayload('paid_out', '20.00'))->assertForbidden();
        $this->actingAs($fixture['manager'])->postJson($url, $this->movementPayload('paid_out', '20.00'))->assertCreated();
        $supplier = Supplier::factory()->create(['merchant_id' => $fixture['merchant']->id]);
        $payment = SupplierPayment::query()->create([
            'merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'supplier_id' => $supplier->id,
            'recorded_by_user_id' => $fixture['manager']->id, 'number' => 'SPY-CASH-1', 'entry_kind' => 'payment',
            'idempotency_key' => (string) Str::uuid(), 'payload_hash' => hash('sha256', 'payment'), 'amount' => '30.00',
            'payment_method' => 'cash', 'reference' => 'SUP-CASH', 'paid_at' => now(),
        ]);
        $supplierPayload = $this->movementPayload('supplier_payment', '30.00') + ['source_type' => 'supplier_payment', 'source_id' => $payment->public_id];
        $this->actingAs($fixture['manager'])->postJson($url, [...$supplierPayload, 'idempotency_key' => (string) Str::uuid(), 'amount' => '29.00'])->assertUnprocessable();
        $this->actingAs($fixture['manager'])->postJson($url, $supplierPayload)->assertCreated()->assertJsonPath('data.signed_amount', '-30.00');
        $this->actingAs($fixture['manager'])->postJson($url, [...$supplierPayload, 'idempotency_key' => (string) Str::uuid()])->assertConflict();

        $close = $this->actingAs($fixture['cashier'])->postJson($this->sessionUrl($fixture, $session).'/close', $this->closePayload($session, '100.00'))->assertOk();
        $close->assertJsonPath('data.expected_amount', '100.00')->assertJsonPath('data.variance', '0.00')->assertJsonPath('data.state', 'closed');
        $this->assertDatabaseCount('cash_movements', 4);
    }

    public function test_blind_count_pending_sync_variance_review_handover_and_late_cash_are_explicit(): void
    {
        $fixture = $this->fixture();
        $session = $this->open($fixture);
        $handover = ['idempotency_key' => (string) Str::uuid(), 'to_membership_id' => $fixture['managerMembership']->public_id, 'note' => 'Lunch handover'];
        $this->actingAs($fixture['cashier'])->postJson($this->sessionUrl($fixture, $session).'/handover', $handover)->assertCreated()->assertJsonPath('data.handover_count', 1);
        $pending = $this->closePayload($session->refresh(), '100.00');
        $pending['pending_offline_sale_count'] = 1;
        $this->actingAs($fixture['manager'])->postJson($this->sessionUrl($fixture, $session).'/close', $pending)->assertOk()->assertJsonPath('data.state', 'close_pending_sync')->assertJsonPath('data.expected_amount', null);
        $report = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/eod-variance?store_id='.$fixture['store']->public_id;
        $this->getJson($report)->assertOk()->assertJsonPath('data.rows.0.expected_amount', null)->assertJsonPath('data.summary.expected_amount', null);
        $resolved = $this->closePayload($session->refresh(), '95.00');
        $this->actingAs($fixture['manager'])->postJson($this->sessionUrl($fixture, $session).'/close', $resolved)->assertOk()->assertJsonPath('data.state', 'variance_review')->assertJsonPath('data.expected_amount', '100.00')->assertJsonPath('data.variance', '-5.00');
        $this->getJson($report)->assertOk()->assertJsonPath('data.rows.0.expected_amount', '100.00')->assertJsonPath('data.summary.expected_amount', '100.00');

        app(PostCashMovement::class)->handle($session->refresh(), $fixture['manager'], (string) Str::uuid(), CashMovementKind::SaleCash, '10.00', 'Late server sale', now()->toAtomString(), 'sale', (string) Str::uuid(), true);
        $this->assertSame('110.00', $session->refresh()->expected_amount);
        $this->actingAs($fixture['cashier'])->postJson($this->sessionUrl($fixture, $session).'/review', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $session->version, 'decision' => 'approve', 'reason' => 'Attempt'])->assertForbidden();
        $review = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $session->refresh()->version, 'decision' => 'approve', 'reason' => 'Reviewed shortage and late sale evidence'];
        $this->actingAs($fixture['manager'])->postJson($this->sessionUrl($fixture, $session).'/review', $review)->assertOk()->assertJsonPath('data.state', 'closed')->assertJsonPath('data.variance', '-15.00');
        $this->actingAs($fixture['manager'])->postJson($this->sessionsUrl($fixture), [...$this->openPayload(), 'idempotency_key' => (string) Str::uuid()])->assertCreated();
    }

    public function test_register_store_substitution_and_stale_versions_are_rejected(): void
    {
        $fixture = $this->fixture();
        $session = $this->open($fixture);
        $otherStore = Store::factory()->for($fixture['merchant'])->create();
        $this->actingAs($fixture['manager'])->getJson("/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$otherStore->public_id}/registers/{$fixture['register']->public_id}/cash-sessions/{$session->public_id}")->assertNotFound();
        $payload = $this->closePayload($session, '100.00');
        $payload['expected_version'] = 99;
        $this->actingAs($fixture['cashier'])->postJson($this->sessionUrl($fixture, $session).'/close', $payload)->assertConflict();
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create(['timezone' => 'Asia/Karachi']);
        $register = Register::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'DRAWER-01']);
        $cashier = User::factory()->create();
        $cashierMembership = MerchantMembership::factory()->for($merchant)->for($cashier)->create(['role' => MembershipRole::Cashier]);
        $manager = User::factory()->create();
        $managerMembership = MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);
        foreach ([$cashierMembership, $managerMembership] as $membership) {
            StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $store->id]);
        }

        return compact('merchant', 'store', 'register', 'cashier', 'cashierMembership', 'manager', 'managerMembership');
    }

    /** @return array<string, mixed> */
    private function openPayload(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '100.00', 'opened_at' => now()->toAtomString()];
    }

    /** @param array<string, mixed> $fixture */
    private function open(array $fixture): CashSession
    {
        $id = $this->actingAs($fixture['cashier'])->postJson($this->sessionsUrl($fixture), $this->openPayload())->assertCreated()->json('data.id');
        $session = (new CashSession)->resolveRouteBinding($id);
        if (! $session instanceof CashSession) {
            throw new \RuntimeException('Cash session fixture could not be resolved.');
        }

        return $session;
    }

    /** @return array<string, mixed> */
    private function movementPayload(string $kind, string $amount): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'kind' => $kind, 'amount' => $amount, 'reason' => 'Documented drawer movement', 'occurred_at' => now()->toAtomString()];
    }

    /** @return array<string, mixed> */
    private function closePayload(CashSession $session, string $counted): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $session->version, 'counted_amount' => $counted, 'pending_server_sale_count' => 0, 'pending_offline_sale_count' => 0];
    }

    /** @param array<string, mixed> $fixture */
    private function sessionsUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$fixture['register']->public_id}/cash-sessions";
    }

    /** @param array<string, mixed> $fixture */
    private function sessionUrl(array $fixture, CashSession $session): string
    {
        return $this->sessionsUrl($fixture)."/{$session->public_id}";
    }
}
