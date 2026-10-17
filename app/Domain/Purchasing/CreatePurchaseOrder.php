<?php

namespace App\Domain\Purchasing;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Shared\DocumentNumber;
use App\Models\Merchant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderLineResolver $resolver, private readonly PurchaseOrderRecorder $recorder) {}

    /** @param list<array{variant_id: string, purchase_unit_id: string, quantity: string, expected_unit_cost: string, tax_reference?: string|null}> $lines */
    public function handle(Merchant $merchant, Store $store, Supplier $supplier, User $actor, string $orderDate, ?string $expectedDate, ?string $sourceReference, ?string $notes, array $lines): PurchaseOrder
    {
        abort_unless($supplier->merchant_id === $merchant->id, 404);
        abort_if($supplier->archived_at !== null, 409, 'An archived supplier cannot be used for a new purchase order.');

        return DB::transaction(function () use ($merchant, $store, $supplier, $actor, $orderDate, $expectedDate, $sourceReference, $notes, $lines): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $supplier->id, 'created_by_user_id' => $actor->id,
                'number' => app(DocumentNumber::class)->next($merchant->id, 'PO', $orderDate), 'state' => PurchaseOrderState::Draft,
                'order_date' => $orderDate, 'expected_date' => $expectedDate, 'source_reference' => $sourceReference, 'notes' => $notes,
            ]);
            $dimensions = [];
            foreach ($lines as $input) {
                $resolved = $this->resolver->resolve($merchant, $store, $input['variant_id'], $input['purchase_unit_id'], $input['quantity'], $input['expected_unit_cost']);
                $dimension = $resolved['variant']->id.':'.$resolved['unit']->id;
                abort_if(isset($dimensions[$dimension]), 409, 'A product and purchase-unit combination may appear only once.');
                $dimensions[$dimension] = true;
                PurchaseOrderLine::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_order_id' => $order->id,
                    'product_variant_id' => $resolved['variant']->id, 'purchase_unit_id' => $resolved['unit']->id,
                    'product_name_snapshot' => $resolved['variant']->product->name, 'sku_snapshot' => $resolved['variant']->sku,
                    'unit_symbol_snapshot' => $resolved['unit']->symbol, 'ordered_quantity_uom' => $resolved['quantity_uom'],
                    'factor_to_base' => $resolved['factor'], 'ordered_quantity_base' => $resolved['quantity_base'],
                    'expected_unit_cost' => $resolved['unit_cost'], 'tax_reference' => $input['tax_reference'] ?? null,
                ]);
            }
            $order->refresh()->load(['supplier', 'store', 'lines.variant.product', 'lines.purchaseUnit']);
            $this->recorder->record($order, $actor, 'created');

            return $order->load('history');
        });
    }
}
