<?php

namespace Tests\Feature;

use App\Models\BatchSelectionSnapshot;
use App\Models\ExtraGroup;
use App\Models\ExtraOption;
use App\Models\InventoryLocation;
use App\Models\ProductBarcode;
use App\Models\Register;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class CheckoutCartTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_cashier_scans_repeated_item_quotes_and_suspends_with_version_guards(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        ProductBarcode::query()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => null, 'product_variant_id' => $fixture['first']->id, 'code' => '89010001', 'kind' => 'unit', 'is_primary' => true]);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}/carts";

        $created = $this->actingAs($fixture['cashier'])->postJson($url, ['inventory_location_id' => $location->public_id])->assertCreated()->assertJsonPath('data.version', 1);
        $cartUrl = $url.'/'.$created->json('data.id');
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/scan', ['expected_version' => 1, 'code' => '89010001'])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.lines.0.quantity', '1.000000');
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/scan', ['expected_version' => 2, 'code' => '89010001'])->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.lines.0.quantity', '2.000000')->assertJsonPath('data.lines.0.scan_count', 2);
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/scan', ['expected_version' => 2, 'code' => '89010001'])->assertConflict();

        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/quotes', ['expected_version' => 3])->assertCreated()->assertJsonPath('quote.cart_version', 3)->assertJsonPath('quote.result.totals.gross', '236.00');
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/suspend', ['expected_version' => 3])->assertOk()->assertJsonPath('data.state', 'suspended')->assertJsonPath('data.version', 4);
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/scan', ['expected_version' => 4, 'code' => '89010001'])->assertConflict();
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/resume', ['expected_version' => 4])->assertOk()->assertJsonPath('data.state', 'active')->assertJsonPath('data.version', 5);
    }

    public function test_cart_scope_and_discount_permissions_are_enforced(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $otherRegister = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['otherStore']->id]);
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}/carts";
        $cart = $this->actingAs($fixture['cashier'])->postJson($url, ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $payload = ['expected_version' => 1, 'manual_cart_discount' => '1.00', 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]];
        $this->actingAs($fixture['cashier'])->putJson($url.'/'.$cart['id'], $payload)->assertForbidden();
        $this->actingAs($fixture['manager'])->putJson($url.'/'.$cart['id'], $payload)->assertOk()->assertJsonPath('data.manual_cart_discount', '1.00');
        $wrong = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['otherStore']->public_id}/registers/{$otherRegister->public_id}/carts/{$cart['id']}";
        $this->actingAs($fixture['manager'])->getJson($wrong)->assertNotFound();
    }

    public function test_extras_are_server_priced_and_expired_batch_selections_are_blocked(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $group = ExtraGroup::factory()->create(['merchant_id' => $fixture['merchant']->id, 'product_id' => $fixture['first']->product_id, 'name' => 'Milk', 'minimum_selected' => 1, 'maximum_selected' => 1]);
        $option = ExtraOption::factory()->create(['merchant_id' => $fixture['merchant']->id, 'extra_group_id' => $group->id, 'name' => 'Oat milk', 'price_delta' => '20.00', 'is_active' => true]);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}/carts";
        $cart = $this->actingAs($fixture['cashier'])->postJson($url, ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $cartUrl = $url.'/'.$cart['id'];
        $line = ['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000', 'extra_option_ids' => [$option->public_id]];
        $this->actingAs($fixture['cashier'])->putJson($cartUrl, ['expected_version' => 1, 'lines' => [$line]])->assertOk()->assertJsonPath('data.lines.0.extras.0.price_delta', '20.00');
        $this->actingAs($fixture['cashier'])->postJson($cartUrl.'/quotes', ['expected_version' => 2])->assertCreated()->assertJsonPath('quote.result.lines.0.unit_price', '120.0000')->assertJsonPath('quote.result.totals.gross', '141.60');

        $snapshot = BatchSelectionSnapshot::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'inventory_location_id' => $location->id, 'product_variant_id' => $fixture['first']->id, 'actor_user_id' => $fixture['cashier']->id, 'strategy' => 'fefo', 'requested_quantity_base' => '1.000000', 'business_date' => now()->toDateString(), 'content_hash' => hash('sha256', 'expired-test'), 'expires_at' => now()->subMinute()]);
        $fixture['first']->product()->update(['batch_tracking_enabled' => true]);
        $line['batch_selection_id'] = $snapshot->public_id;
        $this->actingAs($fixture['cashier'])->putJson($cartUrl, ['expected_version' => 2, 'lines' => [$line]])->assertUnprocessable();
    }
}
