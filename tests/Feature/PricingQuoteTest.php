<?php

namespace Tests\Feature;

use App\Models\CouponUsage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class PricingQuoteTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_advanced_quote_applies_quantity_coupon_and_buy_x_get_y_without_consuming_coupon(): void
    {
        $fixture = $this->pricingFixture();
        $rules = [
            ['type' => 'quantity_discount', 'name' => 'Tea pair 10%', 'priority' => 10, 'stackable' => true, 'conditions' => ['variant_ids' => [$fixture['first']->public_id], 'minimum_quantity' => '2'], 'reward' => ['type' => 'percentage', 'value' => '10']],
            ['type' => 'buy_x_get_y', 'name' => 'Buy two get snack', 'priority' => 20, 'stackable' => true, 'conditions' => ['qualifying_variant_ids' => [$fixture['first']->public_id], 'reward_variant_ids' => [$fixture['second']->public_id], 'buy_quantity' => '2', 'get_quantity' => '1'], 'reward' => ['type' => 'free', 'value' => '0']],
            ['type' => 'coupon', 'name' => 'Five rupees', 'code' => 'SAVE5', 'priority' => 30, 'stackable' => true, 'conditions' => [], 'reward' => ['type' => 'fixed_cart', 'value' => '5']],
        ];
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['mode' => 'advanced', 'rules' => $rules]))->assertCreated();
        $payload = ['coupon_codes' => ['save5'], 'lines' => [
            ['line_id' => (string) Str::uuid7(), 'variant_id' => $fixture['first']->public_id, 'quantity' => '2.000000'],
            ['line_id' => (string) Str::uuid7(), 'variant_id' => $fixture['second']->public_id, 'quantity' => '1.000000'],
        ]];
        $quote = $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $payload)->assertOk()
            ->assertJsonPath('data.pricing_version', 'm1-s0')->assertJsonPath('data.coupon_consumed', false)->assertJsonPath('data.totals.pre_discount', '250.00');
        $this->assertSame('75.00', $quote->json('data.totals.discount'));
        $this->assertSame(0, CouponUsage::query()->count());
        $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $payload)->assertOk()->assertJsonPath('data.totals.discount', '75.00');
    }

    public function test_threshold_fractional_quantity_tax_and_stale_version_are_deterministic(): void
    {
        $fixture = $this->pricingFixture();
        $rule = ['type' => 'quantity_discount', 'name' => 'Weight threshold', 'priority' => 1, 'stackable' => false, 'conditions' => ['variant_ids' => [$fixture['first']->public_id], 'minimum_quantity' => '2.5'], 'reward' => ['type' => 'fixed_unit', 'value' => '1.00']];
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['mode' => 'advanced', 'rules' => [$rule]]))->assertCreated();
        $lineId = (string) Str::uuid7();
        $below = ['lines' => [['line_id' => $lineId, 'variant_id' => $fixture['first']->public_id, 'quantity' => '2.499999']]];
        $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $below)->assertOk()->assertJsonPath('data.totals.discount', '0.00');
        $at = ['pricing_version' => 'm1-s0', 'lines' => [['line_id' => $lineId, 'variant_id' => $fixture['first']->public_id, 'quantity' => '2.500000']]];
        $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $at)->assertOk()->assertJsonPath('data.totals.discount', '2.50')->assertJsonPath('data.totals.tax', '44.55')->assertJsonPath('data.totals.gross', '292.05');
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['expected_version' => 1, 'mode' => 'advanced', 'prices' => [['variant_id' => $fixture['first']->public_id, 'unit_price' => '110.0000']], 'rules' => [$rule]]))->assertCreated();
        $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $at)->assertConflict();
    }

    public function test_manual_discount_requires_permission_and_cart_allocation_conserves_paisa(): void
    {
        $fixture = $this->pricingFixture();
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $payload = ['manual_cart_discount' => '0.05', 'lines' => [
            ['line_id' => '00000000-0000-7000-8000-000000000001', 'variant_id' => $fixture['second']->public_id, 'quantity' => '1'],
            ['line_id' => '00000000-0000-7000-8000-000000000002', 'variant_id' => $fixture['second']->public_id, 'quantity' => '1'],
            ['line_id' => '00000000-0000-7000-8000-000000000003', 'variant_id' => $fixture['second']->public_id, 'quantity' => '1'],
        ]];
        $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $payload)->assertForbidden();
        $response = $this->actingAs($fixture['manager'])->postJson($this->pricingUrl($fixture).'/quote', $payload)->assertOk()->assertJsonPath('data.totals.discount', '0.05');
        $this->assertSame(['0.02', '0.02', '0.01'], array_column($response->json('data.lines'), 'discount'));
    }

    public function test_scheduled_and_mix_match_rules_choose_best_exclusive_offer_and_honour_time_windows(): void
    {
        $fixture = $this->pricingFixture();
        $rules = [
            ['type' => 'scheduled_price', 'name' => 'Price 90', 'priority' => 10, 'stackable' => true, 'exclusive_group' => 'tea-price', 'conditions' => ['variant_ids' => [$fixture['first']->public_id]], 'reward' => ['type' => 'fixed_price', 'value' => '90']],
            ['type' => 'scheduled_price', 'name' => 'Price 80', 'priority' => 10, 'stackable' => true, 'exclusive_group' => 'tea-price', 'conditions' => ['variant_ids' => [$fixture['first']->public_id]], 'reward' => ['type' => 'fixed_price', 'value' => '80']],
            ['type' => 'mix_and_match', 'name' => 'Mixed pair', 'priority' => 20, 'stackable' => true, 'conditions' => ['variant_ids' => [$fixture['first']->public_id, $fixture['second']->public_id], 'minimum_quantity' => '2'], 'reward' => ['type' => 'percentage', 'value' => '10']],
            ['type' => 'quantity_discount', 'name' => 'Expired giveaway', 'priority' => 30, 'stackable' => true, 'ends_at' => now()->subMinute()->toAtomString(), 'conditions' => ['variant_ids' => [$fixture['second']->public_id], 'minimum_quantity' => '1'], 'reward' => ['type' => 'percentage', 'value' => '100']],
        ];
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['mode' => 'advanced', 'rules' => $rules]))->assertCreated();
        $response = $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', ['lines' => [
            ['line_id' => (string) Str::uuid7(), 'variant_id' => $fixture['first']->public_id, 'quantity' => '1'],
            ['line_id' => (string) Str::uuid7(), 'variant_id' => $fixture['second']->public_id, 'quantity' => '1'],
        ]])->assertOk()->assertJsonPath('data.totals.discount', '33.00');
        $this->assertSame(['Price 80', 'Mixed pair'], array_column($response->json('data.lines.0.allocations'), 'rule_name'));
    }
}
