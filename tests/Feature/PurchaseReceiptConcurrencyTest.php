<?php

namespace Tests\Feature;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Purchasing\PostPurchaseReceipt;
use App\Models\InventoryCostBalance;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierChargeEntry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PurchaseReceiptConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_parallel_receipts_cannot_both_consume_the_same_order_remainder(): void
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $supplier = Supplier::factory()->create(['merchant_id' => $merchant->id]);
        $actor = User::factory()->create();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id]);
        $product = Product::factory()->create(['merchant_id' => $merchant->id]);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'base_unit_id' => $unit->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $order = PurchaseOrder::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $supplier->id, 'created_by_user_id' => $actor->id, 'number' => 'PO-CONCURRENT', 'state' => PurchaseOrderState::Issued, 'version' => 2, 'order_date' => now()->toDateString(), 'issued_at' => now()]);
        $line = PurchaseOrderLine::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_order_id' => $order->id, 'product_variant_id' => $variant->id, 'purchase_unit_id' => $unit->id, 'product_name_snapshot' => $product->name, 'sku_snapshot' => $variant->sku, 'unit_symbol_snapshot' => $unit->symbol, 'ordered_quantity_uom' => '12.000000', 'factor_to_base' => '1.000000', 'ordered_quantity_base' => '12.000000', 'expected_unit_cost' => '100.000000']);
        $ids = ['merchant' => $merchant->public_id, 'store' => $store->public_id, 'order' => $order->public_id, 'location' => $location->public_id, 'actor' => $actor->id, 'line' => $line->public_id];

        $tasks = [];
        foreach ([1, 2] as $sequence) {
            $tasks[] = static function () use ($ids, $sequence): string {
                $resolvedMerchant = (new Merchant)->resolveRouteBinding($ids['merchant']);
                $resolvedStore = (new Store)->resolveRouteBinding($ids['store']);
                $resolvedOrder = (new PurchaseOrder)->resolveRouteBinding($ids['order']);
                $resolvedLocation = (new InventoryLocation)->resolveRouteBinding($ids['location']);
                $resolvedActor = User::query()->find($ids['actor']);
                if (! $resolvedMerchant instanceof Merchant || ! $resolvedStore instanceof Store || ! $resolvedOrder instanceof PurchaseOrder || ! $resolvedLocation instanceof InventoryLocation || ! $resolvedActor instanceof User) {
                    throw new \RuntimeException('Concurrent receipt fixture could not be resolved.');
                }
                try {
                    app(PostPurchaseReceipt::class)->handle($resolvedMerchant, $resolvedStore, $resolvedOrder, $resolvedLocation, $resolvedActor, '00000000-0000-7000-8000-00000000019'.$sequence, 2, now()->toIso8601String(), 'CONCURRENT-'.$sequence, null, [['purchase_order_line_id' => $ids['line'], 'quantity' => '12', 'unit_cost' => '100']]);

                    return 'posted';
                } catch (ConflictHttpException) {
                    return 'conflict';
                }
            };
        }

        $results = Concurrency::run($tasks, timeout: 30);
        sort($results);
        $this->assertSame(['conflict', 'posted'], $results);
        $this->assertSame(PurchaseOrderState::Received, $order->refresh()->state);
        $this->assertSame('12.000000', $order->lines()->sole()->received_quantity_base);
        $this->assertSame('12.000000', InventoryCostBalance::query()->sole()->quantity_base);
        $this->assertSame(1, PurchaseReceipt::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(1, SupplierChargeEntry::query()->count());
        $this->assertSame(1, OutboxEvent::query()->count());
    }
}
