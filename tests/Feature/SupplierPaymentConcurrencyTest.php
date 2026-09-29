<?php

namespace Tests\Feature;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Purchasing\PostPurchaseReceipt;
use App\Domain\Purchasing\PostSupplierPayment;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierChargeEntry;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class SupplierPaymentConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_parallel_payments_cannot_overallocate_one_supplier_charge(): void
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $supplier = Supplier::factory()->create(['merchant_id' => $merchant->id]);
        $actor = User::factory()->create();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id]);
        $product = Product::factory()->create(['merchant_id' => $merchant->id]);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'base_unit_id' => $unit->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $order = PurchaseOrder::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $supplier->id, 'created_by_user_id' => $actor->id, 'number' => 'PO-PAY-RACE', 'state' => PurchaseOrderState::Issued, 'version' => 2, 'order_date' => now()->toDateString(), 'issued_at' => now()]);
        $line = PurchaseOrderLine::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_order_id' => $order->id, 'product_variant_id' => $variant->id, 'purchase_unit_id' => $unit->id, 'product_name_snapshot' => $product->name, 'sku_snapshot' => $variant->sku, 'unit_symbol_snapshot' => $unit->symbol, 'ordered_quantity_uom' => '1.000000', 'factor_to_base' => '1.000000', 'ordered_quantity_base' => '1.000000', 'expected_unit_cost' => '1500.000000']);
        app(PostPurchaseReceipt::class)->handle($merchant, $store, $order, $location, $actor, '00000000-0000-7000-8000-000000000220', 2, now()->toIso8601String(), 'INV-RACE', null, [['purchase_order_line_id' => $line->public_id, 'quantity' => '1', 'unit_cost' => '1500']]);
        $charge = SupplierChargeEntry::query()->where('entry_kind', 'charge')->sole();
        $ids = ['merchant' => $merchant->public_id, 'store' => $store->public_id, 'supplier' => $supplier->public_id, 'actor' => $actor->id, 'charge' => $charge->public_id];

        $tasks = [];
        foreach ([1, 2] as $sequence) {
            $tasks[] = static function () use ($ids, $sequence): string {
                $resolvedMerchant = (new Merchant)->resolveRouteBinding($ids['merchant']);
                $resolvedStore = (new Store)->resolveRouteBinding($ids['store']);
                $resolvedSupplier = (new Supplier)->resolveRouteBinding($ids['supplier']);
                $resolvedActor = User::query()->find($ids['actor']);
                if (! $resolvedMerchant instanceof Merchant || ! $resolvedStore instanceof Store || ! $resolvedSupplier instanceof Supplier || ! $resolvedActor instanceof User) {
                    throw new \RuntimeException('Concurrent supplier-payment fixture could not be resolved.');
                }
                try {
                    app(PostSupplierPayment::class)->handle($resolvedMerchant, $resolvedStore, $resolvedSupplier, $resolvedActor, '00000000-0000-7000-8000-00000000022'.$sequence, '800.00', 'bank_transfer', 'RACE-'.$sequence, now()->toIso8601String(), null, [['supplier_charge_id' => $ids['charge'], 'amount' => '800.00']]);

                    return 'posted';
                } catch (ConflictHttpException) {
                    return 'conflict';
                }
            };
        }

        $results = Concurrency::run($tasks, timeout: 30);
        sort($results);
        $this->assertSame(['conflict', 'posted'], $results);
        $this->assertSame(1, SupplierPayment::query()->where('entry_kind', 'payment')->count());
        $this->assertSame('800.00', SupplierPaymentAllocation::query()->sum('amount'));
        $this->assertSame('700.00', (string) BigDecimal::of($charge->amount)->minus((string) SupplierPaymentAllocation::query()->sum('amount'))->toScale(2));
    }
}
