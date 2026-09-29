<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CashMovement;
use App\Models\CouponUsage;
use App\Models\InventoryLocation;
use App\Models\OutboxEvent;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleTender;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class SaleFinalizationTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_split_tender_sale_posts_every_effect_once_and_replays_after_lost_response(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $cashUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}/cash-sessions";
        $session = $this->actingAs($fixture['cashier'])->postJson($cashUrl, ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cartUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}/carts";
        $cart = $this->actingAs($fixture['cashier'])->postJson($cartUrl, ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $detail = $cartUrl.'/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $salesUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}/sales";
        $key = (string) Str::uuid();
        $client = (string) Str::uuid();
        $payload = ['client_transaction_id' => $client, 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [
            ['tender_id' => (string) Str::uuid(), 'type' => 'external_card', 'amount' => '30.00', 'external_reference' => 'MANUAL-APPROVAL-42', 'confirmation_source' => 'staff_declaration'],
            ['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '100.00', 'confirmation_source' => 'not_applicable'],
        ], 'cash_change' => '12.00'];
        $posted = $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($salesUrl, $payload)->assertCreated()->assertJsonPath('data.totals.gross', '118.00')->assertJsonPath('data.totals.cash_change', '12.00')->assertJsonFragment(['external_reference' => 'MANUAL-APPROVAL-42', 'confirmation_source' => 'staff_declaration'])->assertJsonPath('data.printing_state', 'not_requested');
        $saleId = $posted->json('data.id');
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(2, SaleTender::query()->count());
        $this->assertSame(1, ReceiptSnapshot::query()->count());
        $this->assertSame('-1.000000', StockMovement::query()->where('movement_kind', 'sale')->value('quantity_base'));
        $this->assertSame('88.00', CashMovement::query()->where('kind', 'sale_cash')->value('signed_amount'));
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'sales.sale.finalized')->count());
        $this->assertSame('converted', Cart::query()->firstOrFail()->status->value);
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($salesUrl, $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $saleId);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_kind', 'sale')->count());
        $this->assertSame(1, CashMovement::query()->where('kind', 'sale_cash')->count());
        $this->actingAs($fixture['cashier'])->getJson($salesUrl.'/client-transactions/'.$client)->assertOk()->assertJsonPath('data.id', $saleId);
        $changed = $payload;
        $changed['cash_change'] = '11.00';
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($salesUrl, $changed)->assertConflict();
    }

    public function test_underpayment_and_cross_register_session_are_rejected_without_partial_effects(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $other = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $otherStoreRegister = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['otherStore']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers";
        $otherSession = $this->actingAs($fixture['cashier'])->postJson($base."/{$other->public_id}/cash-sessions", ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '0.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cartUrl = $base."/{$register->public_id}/carts";
        $cart = $this->actingAs($fixture['cashier'])->postJson($cartUrl, ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $detail = $cartUrl.'/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $payload = ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => 1, 'register_session_id' => $otherSession['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '117.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'];
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base."/{$register->public_id}/sales", $payload)->assertUnprocessable();
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_kind', 'sale')->count());
        $otherStoreUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['otherStore']->public_id}/registers/{$otherStoreRegister->public_id}/sales";
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($otherStoreUrl, $payload)->assertUnprocessable();
        $this->assertSame(0, Sale::query()->count());

        $session = $this->actingAs($fixture['cashier'])->postJson($base."/{$register->public_id}/cash-sessions", ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '0.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $payload['client_transaction_id'] = (string) Str::uuid();
        $payload['register_session_id'] = $session['id'];
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base."/{$register->public_id}/sales", $payload)->assertUnprocessable()->assertJsonPath('detail', 'Tenders minus cash change must exactly equal the sale total.');
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_kind', 'sale')->count());
        $this->assertSame(0, CashMovement::query()->where('kind', 'sale_cash')->count());
        $this->assertSame(0, OutboxEvent::query()->where('event_type', 'sales.sale.finalized')->count());
    }

    public function test_coupon_is_claimed_only_when_the_sale_commits_and_not_again_on_retry(): void
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $rule = ['type' => 'coupon', 'name' => 'Five rupees', 'code' => 'SAVE5', 'priority' => 10, 'stackable' => true, 'conditions' => [], 'reward' => ['type' => 'fixed_cart', 'value' => '5'], 'maximum_redemptions' => 1];
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture, ['mode' => 'advanced', 'rules' => [$rule]]))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '0.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $detail = $base.'/carts/'.$cart['id'];
        $this->actingAs($fixture['cashier'])->putJson($detail, ['expected_version' => 1, 'coupon_codes' => ['save5'], 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($detail.'/quotes', ['expected_version' => 2])->assertCreated()->assertJsonPath('quote.result.totals.discount', '5.00')->json('quote');
        $this->assertSame(0, CouponUsage::query()->count());
        $key = (string) Str::uuid();
        $payload = ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '112.10', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'];
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base.'/sales', $payload)->assertCreated()->assertJsonPath('data.totals.gross', '112.10');
        $this->assertSame(1, CouponUsage::query()->where('status', 'finalized')->count());
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base.'/sales', $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, CouponUsage::query()->where('status', 'finalized')->count());
    }
}
