<?php

namespace Tests\Feature;

use App\Models\PriceBookVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class PricingVersionTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_owner_and_manager_publish_append_only_versions_but_cashier_cannot(): void
    {
        $fixture = $this->pricingFixture();
        $url = $this->pricingUrl($fixture).'/versions';
        $first = $this->actingAs($fixture['owner'])->postJson($url, $this->pricingPayload($fixture))->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.mode', 'manual');
        $this->actingAs($fixture['cashier'])->postJson($url, $this->pricingPayload($fixture, ['expected_version' => 1]))->assertForbidden();
        $this->actingAs($fixture['manager'])->postJson($url, $this->pricingPayload($fixture, ['expected_version' => 1, 'mode' => 'advanced']))->assertCreated()->assertJsonPath('data.version', 2);
        $this->actingAs($fixture['owner'])->postJson($url, $this->pricingPayload($fixture, ['expected_version' => 1]))->assertConflict();
        $this->assertSame(2, PriceBookVersion::query()->count());
        $this->assertNotSame($first->json('data.content_hash'), PriceBookVersion::query()->latest('version')->value('content_hash'));
    }

    public function test_manual_mode_rejects_rules_and_future_versions_do_not_apply_early(): void
    {
        $fixture = $this->pricingFixture();
        $url = $this->pricingUrl($fixture).'/versions';
        $rule = ['type' => 'coupon', 'name' => 'Ten off', 'code' => 'SAVE10', 'priority' => 10, 'stackable' => false, 'conditions' => [], 'reward' => ['type' => 'percentage', 'value' => '10']];
        $this->actingAs($fixture['owner'])->postJson($url, $this->pricingPayload($fixture, ['rules' => [$rule]]))->assertUnprocessable();
        $this->actingAs($fixture['owner'])->postJson($url, $this->pricingPayload($fixture))->assertCreated();
        $this->actingAs($fixture['owner'])->postJson($url, $this->pricingPayload($fixture, ['expected_version' => 1, 'mode' => 'advanced', 'effective_from' => now()->addDay()->toAtomString(), 'rules' => [$rule]]))->assertCreated();
        $this->actingAs($fixture['cashier'])->getJson($url)->assertOk()->assertJsonPath('data.current_version', 'm1-s0')->assertJsonCount(2, 'data.versions');
    }

    public function test_store_override_isolated_and_independent_catalog_requires_store_scope(): void
    {
        $fixture = $this->pricingFixture();
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['scope' => 'store', 'prices' => [['variant_id' => $fixture['first']->public_id, 'unit_price' => '80.0000']]]))->assertCreated();
        $this->actingAs($fixture['cashier'])->getJson($this->pricingUrl($fixture).'/versions')->assertJsonPath('data.current_version', 'm1-s1');
        $line = ['lines' => [['line_id' => '00000000-0000-7000-8000-000000000010', 'variant_id' => $fixture['first']->public_id, 'quantity' => '1']]];
        $this->actingAs($fixture['cashier'])->postJson($this->pricingUrl($fixture).'/quote', $line)->assertOk()->assertJsonPath('data.lines.0.unit_price', '80.0000');
        $otherUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['otherStore']->public_id}/pricing";
        $this->actingAs($fixture['cashier'])->postJson($otherUrl.'/quote', $line)->assertOk()->assertJsonPath('data.lines.0.unit_price', '100.0000');

        $independent = $this->pricingFixture(independent: true);
        $this->actingAs($independent['owner'])->postJson($this->pricingUrl($independent).'/versions', $this->pricingPayload($independent))->assertUnprocessable();
        $this->actingAs($independent['owner'])->postJson($this->pricingUrl($independent).'/versions', $this->pricingPayload($independent, ['scope' => 'store']))->assertCreated();
    }
}
