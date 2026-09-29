<?php

namespace App\Domain\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\User;

final class PurchaseOrderRecorder
{
    public function record(PurchaseOrder $order, User $actor, string $action, ?string $fromState = null, ?string $reason = null): void
    {
        $order->loadMissing(['supplier', 'lines']);
        PurchaseOrderHistory::query()->create([
            'merchant_id' => $order->merchant_id, 'store_id' => $order->store_id, 'purchase_order_id' => $order->id, 'actor_user_id' => $actor->id,
            'action' => $action, 'from_state' => $fromState, 'to_state' => $order->state->value, 'version' => $order->version, 'reason' => $reason,
            'snapshot' => [
                'supplier_id' => $order->supplier->public_id, 'supplier_name' => $order->supplier->name,
                'order_date' => $order->order_date->toDateString(), 'expected_date' => $order->expected_date?->toDateString(),
                'source_reference' => $order->source_reference, 'notes' => $order->notes,
                'lines' => $order->lines->map(fn ($line): array => [
                    'line_id' => $line->public_id, 'variant_id' => $line->variant->public_id, 'product_name' => $line->product_name_snapshot,
                    'sku' => $line->sku_snapshot, 'unit' => $line->unit_symbol_snapshot, 'ordered_quantity_uom' => $line->ordered_quantity_uom,
                    'factor_to_base' => $line->factor_to_base, 'ordered_quantity_base' => $line->ordered_quantity_base,
                    'received_quantity_base' => $line->received_quantity_base, 'returned_quantity_base' => $line->returned_quantity_base,
                    'cancelled_quantity_base' => $line->cancelled_quantity_base, 'expected_unit_cost' => $line->expected_unit_cost, 'tax_reference' => $line->tax_reference,
                ])->all(),
            ],
            'occurred_at' => now(),
        ]);
    }
}
