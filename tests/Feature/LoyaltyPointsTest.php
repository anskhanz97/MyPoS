<?php

namespace Tests\Feature;

use App\Domain\Loyalty\LoyaltyService;
use App\Models\InventoryLocation;
use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointBalance;
use App\Models\LoyaltyPointEntry;
use App\Models\OperationalCustomer;
use App\Models\Register;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class LoyaltyPointsTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_online_reward_commits_once_and_return_can_make_points_negative(): void
    {
        $context = $this->context();
        $first = $this->sell($context, '118.00');
        $this->assertSame('10.0000', LoyaltyPointBalance::query()->firstOrFail()->balance);
        $this->assertSame(1, LoyaltyPointEntry::query()->where('kind', 'earn')->count());

        $cart = $this->cart($context);
        $rewardId = $context['reward_id'];
        $reservationKey = (string) Str::uuid();
        $reservation = $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $reservationKey)
            ->postJson($cart['url'].'/loyalty-redemptions', ['reward_id' => $rewardId, 'expected_cart_version' => 2])
            ->assertCreated()->assertJsonPath('data.points', '10.0000')->json('data');
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $reservationKey)
            ->postJson($cart['url'].'/loyalty-redemptions', ['reward_id' => $rewardId, 'expected_cart_version' => 2])
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $reservation['id']);
        $quote = $this->actingAs($context['fixture']['cashier'])->postJson($cart['url'].'/quotes', ['expected_version' => 3])
            ->assertCreated()->assertJsonPath('quote.result.loyalty_redemption.points', '10.0000')
            ->assertJsonPath('quote.result.totals.gross', '11.80')->json('quote');
        $key = (string) Str::uuid();
        $payload = $this->salePayload($context, $quote, '11.80');
        $second = $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $key)->postJson($context['base'].'/sales', $payload)
            ->assertCreated()->json('data');
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', $key)->postJson($context['base'].'/sales', $payload)
            ->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame('1.0000', LoyaltyPointBalance::query()->firstOrFail()->balance);
        $this->assertSame(1, LoyaltyPointEntry::query()->where('kind', 'redeem')->count());
        $this->assertSame(2, LoyaltyPointEntry::query()->where('kind', 'earn')->count());

        $line = $first['lines'][0];
        $return = ['register_session_id' => $context['session']['id'], 'reason' => 'Returned after points were spent', 'lines' => [['sale_line_id' => $line['id'], 'quantity' => '1.000000', 'disposition' => 'discard']], 'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '118.00', 'confirmation_source' => 'not_applicable']]];
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($context['base'].'/sales/'.$first['id'].'/returns', $return)->assertCreated();
        $this->assertSame('-9.0000', LoyaltyPointBalance::query()->firstOrFail()->balance);
        $this->assertSame('-10.0000', LoyaltyPointEntry::query()->where('kind', 'refund_reversal')->value('points'));
        $this->assertSame('10.0000', LoyaltyPointEntry::query()->where('source_reference', $first['id'])->where('kind', 'earn')->value('points'));
        $this->assertSame('1.0000', LoyaltyPointEntry::query()->where('source_reference', $second['id'])->where('kind', 'earn')->value('points'));
    }

    public function test_expiry_is_replay_safe_and_member_search_is_tenant_scoped(): void
    {
        $context = $this->context();
        $sale = $this->sell($context, '118.00');
        $earn = LoyaltyPointEntry::query()->where('source_reference', $sale['id'])->firstOrFail();
        $earn->forceFill(['expires_at' => now()->subMinute()])->save();
        $service = app(LoyaltyService::class);
        $this->assertSame(1, $service->expire());
        $this->assertSame(0, $service->expire());
        $this->assertSame(1, LoyaltyPointEntry::query()->where('kind', 'expire')->count());
        $this->assertSame('0.0000', LoyaltyPointBalance::query()->firstOrFail()->balance);
        LoyaltyPointBalance::query()->firstOrFail()->forceFill(['balance' => '99.0000'])->save();
        $this->assertSame(['checked' => 1, 'mismatched' => 1, 'repaired' => 0], $service->reconcile());
        $this->assertSame(['checked' => 1, 'mismatched' => 1, 'repaired' => 1], $service->reconcile(true));
        $this->assertSame('0.0000', LoyaltyPointBalance::query()->firstOrFail()->balance);
        $adjustmentUrl = "/api/v1/merchants/{$context['fixture']['merchant']->public_id}/loyalty/members/{$context['member']->public_id}/adjustments";
        $adjustmentKey = (string) Str::uuid();
        $adjustment = ['points' => '2.5000', 'reason' => 'Service recovery goodwill points'];
        $created = $this->actingAs($context['fixture']['manager'])->withHeader('Idempotency-Key', $adjustmentKey)->postJson($adjustmentUrl, $adjustment)->assertCreated()->json('data');
        $this->actingAs($context['fixture']['manager'])->withHeader('Idempotency-Key', $adjustmentKey)->postJson($adjustmentUrl, $adjustment)->assertCreated()->assertJsonPath('data.id', $created['id']);
        $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($adjustmentUrl, $adjustment)->assertForbidden();
        $changedAdjustment = $adjustment;
        $changedAdjustment['points'] = '3.0000';
        $this->actingAs($context['fixture']['manager'])->withHeader('Idempotency-Key', $adjustmentKey)->postJson($adjustmentUrl, $changedAdjustment)->assertConflict();
        $this->assertSame('2.5000', LoyaltyPointBalance::query()->firstOrFail()->balance);

        $other = $this->pricingFixture();
        LoyaltyMember::factory()->create(['merchant_id' => $other['merchant']->id, 'canonical_phone' => $context['phone']]);
        $url = "/api/v1/merchants/{$context['fixture']['merchant']->public_id}/loyalty/members?phone=".urlencode($context['phone']);
        $this->actingAs($context['fixture']['cashier'])->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $context['member']->public_id);
    }

    private function context(): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'LOYALTY', 'name' => 'Loyalty till', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $programUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/loyalty/program/versions";
        $program = $this->actingAs($fixture['owner'])->postJson($programUrl, ['name' => 'Everyday Rewards', 'slug' => 'everyday-rewards-'.Str::lower(Str::random(5)), 'points_per_pkr' => '0.10000000', 'expiry_days' => 365, 'rewards' => [['name' => 'Rs 90 off', 'points_required' => '10.0000', 'discount_amount' => '90.00']]])->assertCreated()->json('data');
        $phone = '+923001234567';
        $member = LoyaltyMember::factory()->create(['merchant_id' => $fixture['merchant']->id, 'canonical_phone' => $phone]);
        LoyaltyPointBalance::query()->create(['merchant_id' => $fixture['merchant']->id, 'loyalty_member_id' => $member->id, 'balance' => '0.0000', 'version' => 0]);
        $customer = OperationalCustomer::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'phone' => $phone, 'normalized_phone' => $phone]);
        LoyaltyCustomerLink::query()->create(['merchant_id' => $fixture['merchant']->id, 'loyalty_member_id' => $member->id, 'operational_customer_id' => $customer->id]);
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');

        return compact('fixture', 'location', 'customer', 'member', 'base', 'session', 'phone') + ['reward_id' => $program['rewards'][0]['id']];
    }

    private function cart(array $context): array
    {
        $cart = $this->actingAs($context['fixture']['cashier'])->postJson($context['base'].'/carts', ['inventory_location_id' => $context['location']->public_id])->assertCreated()->json('data');
        $url = $context['base'].'/carts/'.$cart['id'];
        $this->actingAs($context['fixture']['cashier'])->putJson($url, ['expected_version' => 1, 'customer_id' => $context['customer']->public_id, 'lines' => [['variant_id' => $context['fixture']['first']->public_id, 'quantity' => '1.000000']]])->assertOk();

        return compact('url');
    }

    private function sell(array $context, string $amount): array
    {
        $cart = $this->cart($context);
        $quote = $this->actingAs($context['fixture']['cashier'])->postJson($cart['url'].'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');

        return $this->actingAs($context['fixture']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($context['base'].'/sales', $this->salePayload($context, $quote, $amount))->assertCreated()->json('data');
    }

    private function salePayload(array $context, array $quote, string $amount): array
    {
        return ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $context['session']['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => $amount, 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'];
    }
}
