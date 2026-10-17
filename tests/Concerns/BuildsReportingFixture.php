<?php

namespace Tests\Concerns;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Models\InventoryLocation;
use App\Models\Register;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;

trait BuildsReportingFixture
{
    use BuildsPricingFixture;

    /**
     * @param  array<string, mixed>  $discounts
     * @return array<string, mixed>
     */
    private function soldFixture(array $discounts = [], ?string $timezone = null): array
    {
        $this->freezeTime();
        $f = $this->pricingFixture();
        if ($timezone !== null) {
            $f['store']->update(['timezone' => $timezone]);
        }
        $this->actingAs($f['owner']);
        $reportBase = '/api/v1/merchants/'.$f['merchant']->public_id.'/reporting';
        $this->postJson($reportBase.'/setup/brands', ['name' => 'Original brand', 'code' => 'BRAND'])->assertCreated();
        $brand = $this->getJson($reportBase.'/catalog')->assertOk()->json('data.lookups.product_brands.0.id');
        $this->putJson($reportBase.'/products/'.$f['first']->product->public_id, ['product_brand_id' => $brand])->assertOk();
        $register = Register::factory()->create(['merchant_id' => $f['merchant']->id, 'store_id' => $f['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $f['merchant']->id, 'store_id' => $f['store']->id, 'disposition' => 'sellable', 'code' => 'MAIN', 'name' => 'Main']);
        app(PostInventoryValuation::class)->inbound($f['store'], $f['first'], InventoryCostEntryKind::AdjustmentIn, '100.000000', '40.000000', 'opening', 'test-opening', 'main', now()->toAtomString(), fn (string $cost, InventoryCostState $state) => app(PostStockMovement::class)->handle(new StockMovementCommand(location: $location, variant: $f['first'], kind: InventoryMovementKind::OpeningBalance, quantityBase: '100.000000', sourceType: 'opening', sourceReference: 'test-opening', unitCost: $cost, costState: $state, actor: $f['owner']))['movement']);
        $this->postJson($this->pricingUrl($f).'/versions', $this->pricingPayload($f))->assertCreated();
        $base = '/api/v1/merchants/'.$f['merchant']->public_id.'/stores/'.$f['store']->public_id.'/registers/'.$register->public_id;
        $session = $this->actingAs($f['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $this->actingAs($f['owner'])->putJson($base.'/carts/'.$cart['id'], ['expected_version' => 1, 'manual_cart_discount' => $discounts['cart'] ?? '0.00', 'lines' => [['variant_id' => $f['first']->public_id, 'quantity' => '2.000000', 'manual_discount' => $discounts['line'] ?? '0.00']]])->assertOk();
        $quote = $this->actingAs($f['cashier'])->postJson($base.'/carts/'.$cart['id'].'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $change = (string) BigDecimal::of('250.00')->minus($quote['result']['totals']['gross']);
        $sale = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '250.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => $change])->assertCreated()->json('data');
        $date = substr($session['business_date'], 0, 10);
        $range = '?start='.$date.'&end='.$date.'&store_id='.$f['store']->public_id;

        return compact('f', 'base', 'location', 'session', 'sale', 'reportBase', 'range');
    }

    /** @param array<string, mixed> $context
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function postReportingSale(array $context, array $lines, ?string $customerId = null, string $cartDiscount = '0.00'): array
    {
        $cart = $this->actingAs($context['f']['cashier'])->postJson($context['base'].'/carts', ['inventory_location_id' => $context['location']->public_id])->assertCreated()->json('data');
        $url = $context['base'].'/carts/'.$cart['id'];
        $this->actingAs($context['f']['owner'])->putJson($url, ['expected_version' => 1, 'lines' => $lines, 'customer_id' => $customerId, 'manual_cart_discount' => $cartDiscount])->assertOk();
        $quote = $this->actingAs($context['f']['cashier'])->postJson($url.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');

        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($context['base'].'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $context['session']['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => $quote['result']['totals']['gross'], 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated()->json('data');
    }
}
