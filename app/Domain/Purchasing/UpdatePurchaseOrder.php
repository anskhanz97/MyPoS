<?php

namespace App\Domain\Purchasing;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Models\Merchant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdatePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderLineResolver $resolver, private readonly PurchaseOrderRecorder $recorder) {}

    /** @param list<array{id?: string|null, variant_id: string, purchase_unit_id: string, quantity: string, expected_unit_cost: string, tax_reference?: string|null}> $lines */
    public function handle(PurchaseOrder $order, Merchant $merchant, Store $store, Supplier $supplier, User $actor, int $expectedVersion, string $orderDate, ?string $expectedDate, ?string $sourceReference, ?string $notes, ?string $amendmentReason, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $merchant, $store, $supplier, $actor, $expectedVersion, $orderDate, $expectedDate, $sourceReference, $notes, $amendmentReason, $lines): PurchaseOrder {
            $locked = PurchaseOrder::query()->with(['lines.variant.product', 'supplier'])->lockForUpdate()->findOrFail($order->id);
            if ($locked->version !== $expectedVersion || in_array($locked->state, [PurchaseOrderState::Received, PurchaseOrderState::Cancelled], true)) {
                throw new ConflictHttpException('The purchase order state or version no longer permits amendment.');
            }
            abort_unless($supplier->merchant_id === $merchant->id, 404);
            if ($supplier->archived_at !== null && $supplier->id !== $locked->supplier_id) {
                throw new ConflictHttpException('An archived supplier cannot replace the purchase-order supplier.');
            }
            if ($locked->state !== PurchaseOrderState::Draft && ($amendmentReason === null || mb_strlen(trim($amendmentReason)) < 3)) {
                throw new ConflictHttpException('An issued purchase-order amendment requires a reason.');
            }
            $existing = $locked->lines->keyBy('public_id');
            $retained = [];
            $dimensions = [];
            foreach ($lines as $input) {
                $resolved = $this->resolver->resolve($merchant, $store, $input['variant_id'], $input['purchase_unit_id'], $input['quantity'], $input['expected_unit_cost']);
                $dimension = $resolved['variant']->id.':'.$resolved['unit']->id;
                if (isset($dimensions[$dimension])) {
                    throw new ConflictHttpException('A product and purchase-unit combination may appear only once.');
                }
                $dimensions[$dimension] = true;
                $line = isset($input['id']) ? $existing->get($input['id']) : null;
                abort_unless(! isset($input['id']) || $line instanceof PurchaseOrderLine, 422, 'A purchase-order line ID is invalid.');
                if ($line instanceof PurchaseOrderLine) {
                    if ($line->product_variant_id !== $resolved['variant']->id || $line->purchase_unit_id !== $resolved['unit']->id) {
                        throw new ConflictHttpException('An existing purchase-order line cannot change product or unit identity.');
                    }
                    if (BigDecimal::of($resolved['quantity_base'])->isLessThan(BigDecimal::of($line->received_quantity_base))) {
                        throw new ConflictHttpException('Ordered quantity cannot be reduced below the quantity already received.');
                    }
                    $line->update([
                        'ordered_quantity_uom' => $resolved['quantity_uom'], 'factor_to_base' => $resolved['factor'], 'ordered_quantity_base' => $resolved['quantity_base'],
                        'expected_unit_cost' => $resolved['unit_cost'], 'tax_reference' => $input['tax_reference'] ?? null,
                    ]);
                    $retained[] = $line->id;
                } else {
                    $created = PurchaseOrderLine::query()->create([
                        'merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_order_id' => $locked->id,
                        'product_variant_id' => $resolved['variant']->id, 'purchase_unit_id' => $resolved['unit']->id,
                        'product_name_snapshot' => $resolved['variant']->product->name, 'sku_snapshot' => $resolved['variant']->sku,
                        'unit_symbol_snapshot' => $resolved['unit']->symbol, 'ordered_quantity_uom' => $resolved['quantity_uom'],
                        'factor_to_base' => $resolved['factor'], 'ordered_quantity_base' => $resolved['quantity_base'],
                        'expected_unit_cost' => $resolved['unit_cost'], 'tax_reference' => $input['tax_reference'] ?? null,
                    ]);
                    $retained[] = $created->id;
                }
            }
            foreach ($locked->lines->whereNotIn('id', $retained) as $removed) {
                if (BigDecimal::of($removed->received_quantity_base)->isPositive()) {
                    throw new ConflictHttpException('A fulfilled purchase-order line cannot be removed.');
                }
                $removed->delete();
            }
            $fromState = $locked->state->value;
            $locked->update([
                'supplier_id' => $supplier->id, 'order_date' => $orderDate, 'expected_date' => $expectedDate,
                'source_reference' => $sourceReference, 'notes' => $notes, 'version' => $locked->version + 1,
            ]);
            $locked->load(['supplier', 'store', 'lines.variant.product', 'lines.purchaseUnit']);
            $this->recorder->record($locked, $actor, 'amended', $fromState, $amendmentReason);

            return $locked->load('history');
        });
    }
}
