<?php

namespace Tests\Feature;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Shared\Math\Decimal;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\BatchStockBalance;
use App\Models\InventoryCostBalance;
use App\Models\InventoryCostEntry;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\Supplier;
use App\Models\SupplierChargeEntry;
use App\Models\SupplierCredit;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseReceivingAndValuationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partial_carton_receipt_posts_stock_cost_charge_and_outbox_exactly_once(): void
    {
        $fixture = $this->fixture();
        $order = $this->issuedOrder($fixture);
        $payload = $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000191');
        $url = $this->orderUrl($fixture).'/'.$order->public_id.'/receipts';

        $created = $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertCreated()
            ->assertJsonPath('data.lines.0.quantity_base', '12.000000')
            ->assertJsonPath('data.lines.0.inventory_unit_cost_base', '110.000000')
            ->assertJsonPath('data.lines.0.valuation.resulting_average', '110.000000')
            ->assertJsonPath('data.merchandise_total', '1200.000000')
            ->assertJsonPath('data.landed_cost_total', '120.000000')
            ->assertJsonPath('data.recoverable_tax_total', '180.000000')
            ->assertJsonPath('data.supplier_charge_total', '1500.000000')
            ->assertJsonPath('purchase_order.state', 'partially_received')
            ->assertJsonPath('purchase_order.version', 3)
            ->assertJsonPath('replayed', false);
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertOk()->assertJsonPath('data.id', $created->json('data.id'))->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($url, [...$payload, 'notes' => 'Changed duplicate'])->assertConflict();
        $this->assertSame('12.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame('12.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->assertSame('110.000000', InventoryCostBalance::query()->sole()->average_unit_cost);
        $this->assertSame('1500.00', SupplierChargeEntry::query()->sole()->amount);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('inventory_cost_entries', 1);
        $this->assertDatabaseCount('outbox_events', 1);
        $this->actingAs($fixture['cashier'])->getJson($this->baseUrl($fixture).'/inventory/valuations')
            ->assertOk()->assertJsonPath('data.0.average_unit_cost', '110.000000')->assertJsonPath('data.0.entries.0.kind', 'purchase_receipt');

        $over = $this->receiptPayload($fixture, $order->refresh(), '00000000-0000-7000-8000-000000000192');
        $over['lines'][0]['quantity'] = '2';
        $over['expected_version'] = 3;
        $this->actingAs($fixture['manager'])->postJson($url, $over)->assertConflict();
        $this->actingAs($fixture['cashier'])->postJson($url, $payload)->assertForbidden();
    }

    public function test_moving_average_and_negative_resolution_are_exact_and_append_only(): void
    {
        $fixture = $this->fixture();
        $valuation = app(PostInventoryValuation::class);
        $stock = app(PostStockMovement::class);
        $postInbound = function (string $reference, string $quantity, string $cost, ?string $effectiveAt = null) use ($fixture, $valuation, $stock): void {
            $valuation->inbound($fixture['store'], $fixture['variant'], InventoryCostEntryKind::AdjustmentIn, $quantity, $cost, 'valuation_fixture', $reference, 'main', $effectiveAt ?? now()->toIso8601String(), fn (string $unitCost, InventoryCostState $state) => $stock->handle(new StockMovementCommand($fixture['location'], $fixture['variant'], InventoryMovementKind::Adjustment, $quantity, 'valuation_fixture', $reference, unitCost: $unitCost, costState: $state))['movement']);
        };
        $postOutbound = function (string $reference, string $quantity) use ($fixture, $valuation, $stock): void {
            $valuation->outbound($fixture['store'], $fixture['variant'], InventoryCostEntryKind::SaleIssue, $quantity, 'valuation_fixture', $reference, 'main', now()->toIso8601String(), fn (?string $unitCost, InventoryCostState $state) => $stock->handle(new StockMovementCommand($fixture['location'], $fixture['variant'], InventoryMovementKind::Sale, '-'.$quantity, 'valuation_fixture', $reference, unitCost: $unitCost, costState: $state))['movement']);
        };

        $postInbound('IN-10', '10', '100');
        $postInbound('IN-5', '5', '130');
        $balance = InventoryCostBalance::query()->sole();
        $this->assertSame('15.000000', $balance->quantity_base);
        $this->assertSame('1650.000000', $balance->inventory_value);
        $this->assertSame('110.000000', $balance->average_unit_cost);

        $postOutbound('OUT-18', '18');
        $this->assertSame('-3.000000', $balance->refresh()->quantity_base);
        $this->assertSame(InventoryCostState::Provisional, $balance->cost_state);
        $postInbound('RESOLVE-5', '5', '80');
        $resolved = InventoryCostEntry::query()->where('source_reference', 'RESOLVE-5')->sole();
        $this->assertSame('3.000000', $resolved->resolved_negative_quantity);
        $this->assertSame('-90.000000', $resolved->cost_adjustment_value);
        $this->assertSame('2.000000', $balance->refresh()->quantity_base);
        $this->assertSame('80.000000', $balance->average_unit_cost);
        $this->assertSame('160.000000', $balance->inventory_value);
        $this->assertSame(4, $balance->projection_version);
        $reports = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/';
        $range = '?store_id='.$fixture['store']->public_id;
        $owner = User::factory()->create();
        MerchantMembership::factory()->owner()->for($fixture['merchant'])->for($owner)->create();
        $this->actingAs($owner)->getJson($reports.'cost-adjustments'.$range)->assertOk()->assertJsonPath('data.summary.amount', '-90.00');
        $this->getJson($reports.'stock-valuation'.$range)->assertOk()->assertJsonPath('data.summary.inventory_value', '160.00');
        $this->getJson($reports.'sales-summary'.$range)->assertOk()->assertJsonPath('data.summary.revenue', '0.00');
        $postInbound('BACKDATED', '1', '50', now()->subDays(40)->toIso8601String());
        $this->getJson($reports.'cost-adjustments'.$range)->assertOk()->assertJsonFragment(['source_reference' => 'BACKDATED'])->assertJsonPath('data.pagination.total', 5);
        $this->getJson($reports.'stock-valuation'.$range)->assertOk()->assertJsonPath('data.summary.inventory_value', '210.00');
    }

    public function test_batch_receiving_requires_expiry_and_routes_expired_goods_away_from_sellable_stock(): void
    {
        $fixture = $this->fixture(batchTracked: true);
        $order = $this->issuedOrder($fixture);
        $url = $this->orderUrl($fixture).'/'.$order->public_id.'/receipts';
        $payload = $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000193');
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertUnprocessable();

        $payload['lines'][0]['batch_code'] = 'OLD-LOT';
        $payload['lines'][0]['expiry_date'] = '2026-09-01';
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertConflict();
        $quarantine = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'QUAR', 'name' => 'Quarantine', 'disposition' => 'quarantine']);
        $payload['location_id'] = $quarantine->public_id;
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertCreated()->assertJsonPath('data.lines.0.batch.code', 'OLD-LOT');
        $this->assertDatabaseHas('batch_stock_balances', ['inventory_location_id' => $quarantine->id, 'quantity_base' => '12.000000']);
        $reports = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/';
        $range = '?store_id='.$fixture['store']->public_id;
        $this->getJson($reports.'batch-stock'.$range)->assertOk()->assertJsonPath('data.rows.0.disposition', 'quarantine')->assertJsonPath('data.rows.0.expiry_date', '2026-09-01');
        $this->getJson($reports.'stock-warnings'.$range)->assertOk()->assertJsonPath('data.pagination.total', 1);
    }

    public function test_latest_receipt_reversal_restores_stock_cost_order_progress_and_supplier_charge(): void
    {
        $fixture = $this->fixture();
        $order = $this->issuedOrder($fixture);
        $payload = $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000194');
        $receiptResponse = $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture).'/'.$order->public_id.'/receipts', $payload)->assertCreated();
        $receiptId = $receiptResponse->json('data.id');
        $url = $this->baseUrl($fixture).'/purchase-receipts/'.$receiptId.'/reverse';
        $reversal = ['reason' => 'Supplier delivery was recorded against the wrong store'];
        $this->actingAs($fixture['manager'])->postJson($url, $reversal)->assertOk()->assertJsonPath('data.status', 'reversed')->assertJsonPath('purchase_order.state', 'issued')->assertJsonPath('replayed', false);
        $this->actingAs($fixture['manager'])->postJson($url, $reversal)->assertOk()->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($url, ['reason' => 'Different reversal explanation'])->assertConflict();
        $this->assertSame('0.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame('0.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->assertSame('0.00', Decimal::fixed(Decimal::of((string) SupplierChargeEntry::query()->sum('amount')), 2));
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('inventory_cost_entries', 2);
        $this->assertDatabaseCount('outbox_events', 2);
        $this->assertSame('0.000000', PurchaseOrder::query()->sole()->lines()->sole()->received_quantity_base);
        $this->assertSame('reversed', PurchaseReceipt::query()->sole()->status->value);
        $this->actingAs($fixture['manager'])->getJson('/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/purchase-receipts?store_id='.$fixture['store']->public_id)->assertOk()->assertJsonPath('data.summary.amount', '0.00')->assertJsonPath('data.rows.0.status', 'reversed');
    }

    public function test_receipt_reversal_rejects_intervening_valuation_activity(): void
    {
        $fixture = $this->fixture();
        $order = $this->issuedOrder($fixture);
        $first = $this->actingAs($fixture['manager'])->postJson(
            $this->orderUrl($fixture).'/'.$order->public_id.'/receipts',
            $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000195'),
        )->assertCreated();
        $order->refresh()->load('lines');
        $secondPayload = $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000196');
        $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture).'/'.$order->public_id.'/receipts', $secondPayload)
            ->assertCreated()->assertJsonPath('purchase_order.state', 'received');

        $this->actingAs($fixture['manager'])->postJson(
            $this->baseUrl($fixture).'/purchase-receipts/'.$first->json('data.id').'/reverse',
            ['reason' => 'Attempting unsafe historical reversal'],
        )->assertConflict();
        $this->assertSame('24.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame('24.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->assertDatabaseCount('supplier_charge_entries', 2);
    }

    public function test_multi_batch_receipt_reverses_one_variant_in_reverse_valuation_order(): void
    {
        $fixture = $this->fixture(batchTracked: true);
        $order = $this->issuedOrder($fixture);
        $payload = $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000197');
        $payload['lines'] = [
            [...$payload['lines'][0], 'batch_code' => 'LOT-A', 'expiry_date' => '2027-01-31'],
            [...$payload['lines'][0], 'batch_code' => 'LOT-B', 'expiry_date' => '2027-02-28'],
        ];
        $posted = $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture).'/'.$order->public_id.'/receipts', $payload)
            ->assertCreated()->assertJsonPath('purchase_order.state', 'received');
        $this->actingAs($fixture['manager'])->postJson($this->baseUrl($fixture).'/purchase-receipts/'.$posted->json('data.id').'/reverse', ['reason' => 'Both batch allocations belong to another delivery'])
            ->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->assertSame('0.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->assertSame(4, InventoryCostEntry::query()->count());
        $this->assertSame(2, BatchStockBalance::query()->where('quantity_base', '0.000000')->count());
    }

    public function test_partial_purchase_returns_preserve_source_credit_and_reject_excess(): void
    {
        $fixture = $this->fixture();
        $order = $this->issuedOrder($fixture);
        $receipt = $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture).'/'.$order->public_id.'/receipts', $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000198'))->assertCreated();
        $receiptId = $receipt->json('data.id');
        $receiptLineId = $receipt->json('data.lines.0.id');
        $url = $this->baseUrl($fixture).'/purchase-receipts/'.$receiptId.'/returns';
        $firstPayload = ['idempotency_key' => '00000000-0000-7000-8000-000000000199', 'returned_at' => now()->toIso8601String(), 'reason' => 'Outer cartons arrived damaged', 'credit_status' => 'expected', 'lines' => [['purchase_receipt_line_id' => $receiptLineId, 'quantity' => '0.25']]];
        $first = $this->actingAs($fixture['manager'])->postJson($url, $firstPayload)->assertCreated()
            ->assertJsonPath('data.lines.0.quantity_base', '3.000000')->assertJsonPath('data.lines.0.supplier_credit_amount', '375.00')
            ->assertJsonPath('data.credit.status', 'expected')->assertJsonPath('replayed', false);
        $this->actingAs($fixture['manager'])->postJson($url, $firstPayload)->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($url, [...$firstPayload, 'reason' => 'Changed'])->assertConflict();

        $secondPayload = ['idempotency_key' => '00000000-0000-7000-8000-000000000200', 'returned_at' => now()->toIso8601String(), 'reason' => 'Additional damaged cartons verified', 'credit_status' => 'confirmed', 'supplier_credit_reference' => 'CN-442', 'lines' => [['purchase_receipt_line_id' => $receiptLineId, 'quantity' => '0.25']]];
        $second = $this->actingAs($fixture['manager'])->postJson($url, $secondPayload)->assertCreated()->assertJsonPath('data.credit.amount', '375.00')->assertJsonPath('data.credit.status', 'confirmed');
        $this->assertSame('6.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame('6.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->assertSame('110.000000', InventoryCostBalance::query()->sole()->average_unit_cost);
        $this->assertDatabaseCount('purchase_returns', 2);
        $this->assertDatabaseCount('supplier_credits', 2);

        $excess = [...$firstPayload, 'idempotency_key' => '00000000-0000-7000-8000-000000000201', 'lines' => [['purchase_receipt_line_id' => $receiptLineId, 'quantity' => '0.75']]];
        $this->actingAs($fixture['manager'])->postJson($url, $excess)->assertConflict();
        $this->actingAs($fixture['cashier'])->postJson($url, $firstPayload)->assertForbidden();

        $this->actingAs($fixture['manager'])->postJson($this->baseUrl($fixture).'/purchase-returns/'.$second->json('data.id').'/reverse', ['reason' => 'Supplier accepted goods locally; shipment cancelled'])->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->assertSame('9.000000', StockBalance::query()->sole()->quantity_base);
        $this->assertSame('9.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->getJson('/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/purchase-returns?store_id='.$fixture['store']->public_id)->assertOk()->assertJsonPath('data.summary.amount', '375.00');
        $this->assertSame('0.00', Decimal::fixed(Decimal::of((string) SupplierCredit::query()->where('purchase_return_id', PurchaseReturn::query()->orderByDesc('id')->firstOrFail()->id)->sum('amount')), 2));
    }

    public function test_supplier_credit_confirmation_payments_allocations_statement_and_reversal_are_exact(): void
    {
        $fixture = $this->fixture();
        $order = $this->issuedOrder($fixture);
        $receipt = $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture).'/'.$order->public_id.'/receipts', $this->receiptPayload($fixture, $order, '00000000-0000-7000-8000-000000000202'))->assertCreated();
        $return = $this->actingAs($fixture['manager'])->postJson($this->baseUrl($fixture).'/purchase-receipts/'.$receipt->json('data.id').'/returns', ['idempotency_key' => '00000000-0000-7000-8000-000000000203', 'returned_at' => now()->toIso8601String(), 'reason' => 'Supplier requested one quarter carton back', 'credit_status' => 'expected', 'lines' => [['purchase_receipt_line_id' => $receipt->json('data.lines.0.id'), 'quantity' => '0.25']]])->assertCreated();
        $credit = SupplierCredit::query()->sole();
        $reportBase = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/';
        $reportRange = '?store_id='.$fixture['store']->public_id;
        $this->getJson($reportBase.'supplier-statement'.$reportRange)->assertOk()->assertJsonPath('data.summary.amount', '1500.00');
        $this->getJson($reportBase.'supplier-credits'.$reportRange)->assertOk()->assertJsonPath('data.rows.0.status', 'expected');
        $this->actingAs($fixture['manager'])->postJson($this->baseUrl($fixture).'/supplier-credits/'.$credit->public_id.'/confirm', ['supplier_reference' => 'CN-900'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $charge = SupplierChargeEntry::query()->where('entry_kind', 'charge')->sole();
        $paymentsUrl = $this->baseUrl($fixture).'/suppliers/'.$fixture['supplier']->public_id.'/payments';
        $payload = ['idempotency_key' => '00000000-0000-7000-8000-000000000204', 'amount' => '500.00', 'payment_method' => 'bank_transfer', 'reference' => 'BANK-7788', 'paid_at' => now()->toIso8601String(), 'notes' => 'Recorded from bank advice', 'allocations' => [['supplier_charge_id' => $charge->public_id, 'amount' => '400.00']]];
        $payment = $this->actingAs($fixture['manager'])->postJson($paymentsUrl, $payload)->assertCreated()->assertJsonPath('data.allocated_amount', '400.00')->assertJsonPath('data.unallocated_amount', '100.00');
        $this->actingAs($fixture['manager'])->postJson($paymentsUrl, $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($paymentsUrl, [...$payload, 'amount' => '501.00'])->assertConflict();

        $statementUrl = $this->baseUrl($fixture).'/suppliers/'.$fixture['supplier']->public_id.'/statement';
        $this->actingAs($fixture['manager'])->getJson($statementUrl)->assertOk()->assertJsonPath('data.charges', '1500.00')->assertJsonPath('data.confirmed_credits', '375.00')->assertJsonPath('data.payments', '500.00')->assertJsonPath('data.balance', '625.00')->assertJsonPath('data.unallocated_payments', '100.00');
        $reports = '/api/v1/merchants/'.$fixture['merchant']->public_id.'/reporting/reports/';
        $range = '?store_id='.$fixture['store']->public_id;
        $this->getJson($reports.'supplier-statement'.$range)->assertOk()->assertJsonPath('data.summary.amount', '625.00');
        $this->getJson($reports.'supplier-credits'.$range)->assertOk()->assertJsonPath('data.summary.amount', '375.00');
        $this->getJson($reports.'supplier-allocations'.$range)->assertOk()->assertJsonPath('data.summary.amount', '400.00');
        $this->actingAs($fixture['manager'])->postJson($paymentsUrl, [...$payload, 'idempotency_key' => '00000000-0000-7000-8000-000000000205', 'amount' => '1200.00', 'reference' => 'BANK-OVER', 'allocations' => [['supplier_charge_id' => $charge->public_id, 'amount' => '1200.00']]])->assertConflict();

        $reversePayload = ['idempotency_key' => '00000000-0000-7000-8000-000000000206', 'reason' => 'Bank advice was matched to another supplier', 'reversed_at' => now()->toIso8601String()];
        $reverseUrl = $this->baseUrl($fixture).'/supplier-payments/'.$payment->json('data.id').'/reverse';
        $this->actingAs($fixture['manager'])->postJson($reverseUrl, $reversePayload)->assertCreated()->assertJsonPath('data.amount', '-500.00')->assertJsonPath('data.allocated_amount', '-400.00');
        $this->actingAs($fixture['manager'])->postJson($reverseUrl, $reversePayload)->assertOk()->assertJsonPath('replayed', true);
        $this->getJson($reports.'supplier-statement'.$range)->assertOk()->assertJsonPath('data.summary.amount', '1125.00');
        $this->getJson($reports.'supplier-payments'.$range)->assertOk()->assertJsonPath('data.summary.amount', '0.00');
        $this->getJson($reports.'supplier-allocations'.$range)->assertOk()->assertJsonPath('data.summary.amount', '0.00');
        $this->actingAs($fixture['manager'])->getJson($statementUrl)->assertOk()->assertJsonPath('data.balance', '1125.00')->assertJsonPath('data.unallocated_payments', '0.00');
        $this->assertDatabaseCount('supplier_payments', 2);
        $this->assertDatabaseCount('supplier_payment_allocations', 2);
        $this->assertFalse(Schema::hasTable('customer_debts'));
        $this->assertSame($return->json('data.id'), PurchaseReturn::query()->sole()->public_id);
    }

    /** @return array<string, mixed> */
    private function fixture(bool $batchTracked = false): array
    {
        $merchant = Merchant::factory()->create(['name' => 'Valuation Merchant']);
        $store = Store::factory()->for($merchant)->create(['name' => 'Lahore Cost Store', 'code' => 'LCS-01', 'timezone' => 'Asia/Karachi']);
        $manager = User::factory()->create();
        $membership = MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $store->id]);
        $cashier = User::factory()->create();
        $cashierMembership = MerchantMembership::factory()->for($merchant)->for($cashier)->create(['role' => MembershipRole::Cashier]);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $cashierMembership->id, 'store_id' => $store->id]);
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'PIECE', 'name' => 'Piece', 'symbol' => 'pc']);
        $carton = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'CARTON', 'name' => 'Carton', 'symbol' => 'ctn']);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'name' => 'Green tea 250g', 'batch_tracking_enabled' => $batchTracked]);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'TEA-250']);
        UnitConversion::factory()->create(['merchant_id' => $merchant->id, 'product_variant_id' => $variant->id, 'from_unit_id' => $carton->id, 'factor_to_base' => '12.000000']);
        $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        $supplier = Supplier::factory()->create(['merchant_id' => $merchant->id, 'code' => 'SUP-COST']);

        return compact('merchant', 'store', 'manager', 'cashier', 'unit', 'carton', 'product', 'variant', 'location', 'supplier');
    }

    /** @param array<string, mixed> $fixture */
    private function issuedOrder(array $fixture): PurchaseOrder
    {
        $created = $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture), [
            'supplier_id' => $fixture['supplier']->public_id, 'order_date' => '2026-09-29',
            'lines' => [['variant_id' => $fixture['variant']->public_id, 'purchase_unit_id' => $fixture['carton']->public_id, 'quantity' => '2', 'expected_unit_cost' => '1250']],
        ])->assertCreated();
        $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture).'/'.$created->json('data.id').'/issue', ['idempotency_key' => (string) fake()->uuid(), 'expected_version' => 1])->assertCreated();

        return PurchaseOrder::query()->with('lines')->sole();
    }

    /** @param array<string, mixed> $fixture @return array<string, mixed> */
    private function receiptPayload(array $fixture, PurchaseOrder $order, string $key): array
    {
        return ['idempotency_key' => $key, 'expected_version' => $order->version, 'location_id' => $fixture['location']->public_id, 'received_at' => now()->toIso8601String(), 'supplier_reference' => 'INV-1001', 'notes' => 'Checked at receiving bay', 'lines' => [['purchase_order_line_id' => $order->lines->sole()->public_id, 'quantity' => '1', 'unit_cost' => '1200', 'landed_cost_total' => '120', 'recoverable_tax_total' => '180']]];
    }

    /** @param array<string, mixed> $fixture */
    private function baseUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}";
    }

    /** @param array<string, mixed> $fixture */
    private function orderUrl(array $fixture): string
    {
        return $this->baseUrl($fixture).'/purchase-orders';
    }
}
