<?php

namespace Tests\Feature;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\Supplier;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SupplierAndPurchaseOrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_supplier_records_are_scoped_editable_and_archived_without_erasing_orders(): void
    {
        $fixture = $this->fixture();
        $url = $this->supplierUrl($fixture);
        $created = $this->actingAs($fixture['manager'])->postJson($url, $this->supplierPayload())->assertCreated()
            ->assertJsonPath('data.code', 'SUP-001')->assertJsonPath('data.name', 'Karachi Tea Traders');
        $supplierId = $created->json('data.id');
        $this->actingAs($fixture['manager'])->putJson($url.'/'.$supplierId, $this->supplierPayload() + ['name' => 'ignored'])->assertOk();
        $update = $this->supplierPayload();
        $update['name'] = 'Karachi Tea and Dry Goods';
        $this->actingAs($fixture['manager'])->putJson($url.'/'.$supplierId, $update)->assertOk()->assertJsonPath('data.name', 'Karachi Tea and Dry Goods');
        $this->actingAs($fixture['cashier'])->getJson($url)->assertForbidden();

        $supplier = Supplier::query()->where('merchant_id', $fixture['merchant']->id)->sole();
        $order = $this->createOrder($fixture, $supplier);
        $this->actingAs($fixture['manager'])->deleteJson($url.'/'.$supplierId)->assertOk()->assertJsonPath('data.archived_at', fn ($value) => is_string($value));
        $this->actingAs($fixture['manager'])->getJson($this->orderUrl($fixture).'/'.$order->json('data.id'))->assertOk()
            ->assertJsonPath('data.supplier.name', 'Karachi Tea and Dry Goods')->assertJsonPath('data.supplier.archived_at', fn ($value) => is_string($value));
        $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture), $this->orderPayload($fixture, $supplier))->assertConflict();
    }

    public function test_purchase_order_validates_scope_and_uom_then_issues_exactly_once_without_stock_effects(): void
    {
        $fixture = $this->fixture();
        $supplier = Supplier::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'SUP-ISSUE']);
        $badUnit = UnitOfMeasure::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'PALLET', 'symbol' => 'plt']);
        $payload = $this->orderPayload($fixture, $supplier);
        $payload['lines'][0]['purchase_unit_id'] = $badUnit->public_id;
        $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture), $payload)->assertConflict();

        $otherMerchant = Merchant::factory()->create();
        $otherUnit = UnitOfMeasure::factory()->create(['merchant_id' => $otherMerchant->id]);
        $otherProduct = Product::factory()->create(['merchant_id' => $otherMerchant->id]);
        $otherVariant = ProductVariant::factory()->create(['merchant_id' => $otherMerchant->id, 'product_id' => $otherProduct->id, 'base_unit_id' => $otherUnit->id]);
        $payload = $this->orderPayload($fixture, $supplier);
        $payload['lines'][0]['variant_id'] = $otherVariant->public_id;
        $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture), $payload)->assertNotFound();

        $created = $this->createOrder($fixture, $supplier)->assertJsonPath('data.lines.0.ordered_quantity_base', '24.000000')
            ->assertJsonPath('data.expected_total', '2500.000000')->assertJsonPath('data.supplier_balance_state', 'operational_ledger_available');
        $url = $this->orderUrl($fixture).'/'.$created->json('data.id');
        $command = ['idempotency_key' => '00000000-0000-7000-8000-000000000181', 'expected_version' => 1];
        $this->actingAs($fixture['manager'])->postJson($url.'/issue', $command)->assertCreated()->assertJsonPath('data.state', 'issued')->assertJsonPath('replayed', false);
        $this->actingAs($fixture['manager'])->postJson($url.'/issue', $command)->assertOk()->assertJsonPath('replayed', true);
        $this->actingAs($fixture['manager'])->postJson($url.'/issue', array_replace($command, ['expected_version' => 2]))->assertConflict();
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('purchase_order_histories', 2);
    }

    public function test_issued_amendment_cannot_reduce_or_remove_fulfilled_quantities_and_preserves_history(): void
    {
        $fixture = $this->fixture();
        $supplier = Supplier::factory()->create(['merchant_id' => $fixture['merchant']->id, 'code' => 'SUP-AMEND']);
        $created = $this->createOrder($fixture, $supplier);
        $orderId = $created->json('data.id');
        $lineId = $created->json('data.lines.0.id');
        $url = $this->orderUrl($fixture).'/'.$orderId;
        $this->actingAs($fixture['manager'])->postJson($url.'/issue', ['idempotency_key' => '00000000-0000-7000-8000-000000000182', 'expected_version' => 1])->assertCreated();
        $order = (new PurchaseOrder)->resolveRouteBinding($orderId);
        $this->assertInstanceOf(PurchaseOrder::class, $order);
        $order->lines()->firstOrFail()->update(['received_quantity_base' => '12.000000']);
        $order->update(['state' => PurchaseOrderState::PartiallyReceived]);

        $amendment = $this->orderPayload($fixture, $supplier);
        $amendment['expected_version'] = 2;
        $amendment['amendment_reason'] = 'Supplier revised available carton quantity';
        $amendment['lines'][0]['id'] = $lineId;
        $amendment['lines'][0]['quantity'] = '0.5';
        $this->actingAs($fixture['manager'])->putJson($url, $amendment)->assertConflict();
        $amendment['lines'][0]['quantity'] = '1';
        unset($amendment['amendment_reason']);
        $this->actingAs($fixture['manager'])->putJson($url, $amendment)->assertConflict();
        $amendment['amendment_reason'] = 'Supplier confirmed one carton only';
        $this->actingAs($fixture['manager'])->putJson($url, $amendment)->assertOk()->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.lines.0.ordered_quantity_base', '12.000000')->assertJsonPath('data.lines.0.received_quantity_base', '12.000000')
            ->assertJsonPath('data.lines.0.remaining_quantity_base', '0.000000')->assertJsonPath('data.history.2.action', 'amended');
    }

    public function test_cancellation_closes_only_outstanding_quantity_and_pdf_is_scoped_and_print_ready(): void
    {
        $fixture = $this->fixture();
        $supplier = Supplier::factory()->create([
            'merchant_id' => $fixture['merchant']->id, 'code' => 'SUP-PDF', 'name' => 'Sindh Wholesale Foods',
            'tax_reference' => 'NTN-778899', 'address_line_1' => '12 Shahrah-e-Faisal', 'city' => 'Karachi',
        ]);
        $created = $this->createOrder($fixture, $supplier);
        $url = $this->orderUrl($fixture).'/'.$created->json('data.id');
        $this->actingAs($fixture['manager'])->postJson($url.'/issue', ['idempotency_key' => '00000000-0000-7000-8000-000000000183', 'expected_version' => 1])->assertCreated();
        $pdf = $this->actingAs($fixture['manager'])->get($url.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $content = (string) $pdf->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(5000, strlen($content));
        if (getenv('CAPTURE_PDF_EVIDENCE') === '1') {
            if (! is_dir(base_path('output/pdf'))) {
                mkdir(base_path('output/pdf'), 0777, true);
            }
            file_put_contents(base_path('output/pdf/t017-sample-purchase-order.pdf'), $content);
        }
        $order = (new PurchaseOrder)->resolveRouteBinding($created->json('data.id'));
        $this->assertInstanceOf(PurchaseOrder::class, $order);
        $order->lines()->firstOrFail()->update(['received_quantity_base' => '12.000000']);
        $order->update(['state' => PurchaseOrderState::PartiallyReceived]);
        $this->actingAs($fixture['manager'])->postJson($url.'/cancel', ['expected_version' => 2, 'reason' => 'Supplier cannot fulfil the remaining carton'])
            ->assertOk()->assertJsonPath('data.state', 'cancelled')->assertJsonPath('data.lines.0.received_quantity_base', '12.000000')
            ->assertJsonPath('data.lines.0.cancelled_quantity_base', '12.000000')->assertJsonPath('data.lines.0.remaining_quantity_base', '0.000000');

        $other = $this->fixture();
        $this->actingAs($other['manager'])->get($url.'/pdf')->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $merchant = Merchant::factory()->create(['name' => 'Sample Merchant']);
        $store = Store::factory()->for($merchant)->create(['name' => 'Lahore Test Store', 'code' => 'LHR-01']);
        $manager = User::factory()->create();
        $managerMembership = MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $managerMembership->id, 'store_id' => $store->id]);
        $cashier = User::factory()->create();
        $cashierMembership = MerchantMembership::factory()->for($merchant)->for($cashier)->create(['role' => MembershipRole::Cashier]);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $cashierMembership->id, 'store_id' => $store->id]);
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'PIECE', 'name' => 'Piece', 'symbol' => 'pc']);
        $carton = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'CARTON', 'name' => 'Carton', 'symbol' => 'ctn']);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'name' => 'Green tea 250g']);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'TEA-250']);
        UnitConversion::factory()->create(['merchant_id' => $merchant->id, 'product_variant_id' => $variant->id, 'from_unit_id' => $carton->id, 'factor_to_base' => '12.000000']);

        return compact('merchant', 'store', 'manager', 'cashier', 'unit', 'carton', 'product', 'variant');
    }

    /** @return array<string, string|null> */
    private function supplierPayload(): array
    {
        return ['code' => 'sup-001', 'name' => 'Karachi Tea Traders', 'contact_name' => 'Sana Ahmed', 'phone' => '021-5550123', 'email' => 'orders@example.test', 'tax_reference' => 'NTN-12345', 'address_line_1' => 'Market Road', 'address_line_2' => null, 'city' => 'Karachi', 'notes' => 'Deliver during business hours'];
    }

    /** @param array<string, mixed> $fixture */
    private function orderPayload(array $fixture, Supplier $supplier): array
    {
        return [
            'supplier_id' => $supplier->public_id, 'order_date' => '2026-09-29', 'expected_date' => '2026-10-03', 'source_reference' => 'BUY-PLAN-41', 'notes' => 'Keep cartons dry.',
            'lines' => [['variant_id' => $fixture['variant']->public_id, 'purchase_unit_id' => $fixture['carton']->public_id, 'quantity' => '2', 'expected_unit_cost' => '1250', 'tax_reference' => 'EXPECTED-EXCLUSIVE']],
        ];
    }

    /** @param array<string, mixed> $fixture */
    private function createOrder(array $fixture, Supplier $supplier): TestResponse
    {
        return $this->actingAs($fixture['manager'])->postJson($this->orderUrl($fixture), $this->orderPayload($fixture, $supplier))->assertCreated();
    }

    /** @param array<string, mixed> $fixture */
    private function supplierUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/suppliers";
    }

    /** @param array<string, mixed> $fixture */
    private function orderUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/purchase-orders";
    }
}
