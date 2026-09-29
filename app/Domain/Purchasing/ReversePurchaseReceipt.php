<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Purchasing\Enums\PurchaseReceiptStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\BatchStockBalance;
use App\Models\OutboxEvent;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\SupplierChargeEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReversePurchaseReceipt
{
    public function __construct(private readonly PostInventoryValuation $valuation, private readonly PostStockMovement $stock, private readonly PurchaseOrderRecorder $recorder) {}

    /** @return array{receipt: PurchaseReceipt, order: PurchaseOrder, replayed: bool} */
    public function handle(PurchaseReceipt $receipt, User $actor, string $reason): array
    {
        return DB::transaction(function () use ($receipt, $actor, $reason): array {
            $locked = PurchaseReceipt::query()->with(['purchaseOrder.lines.variant', 'location.store', 'lines.purchaseOrderLine.variant', 'lines.batch', 'lines.stockMovement', 'lines.costEntry'])->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->status === PurchaseReceiptStatus::Reversed) {
                if ($locked->reversal_reason !== $reason) {
                    throw new ConflictHttpException('This receipt was already reversed with a different reason.');
                }

                return ['receipt' => $locked, 'order' => $locked->purchaseOrder, 'replayed' => true];
            }
            $order = PurchaseOrder::query()->with(['lines.variant', 'supplier', 'store'])->lockForUpdate()->findOrFail($locked->purchase_order_id);
            $lines = $locked->lines->sortByDesc('inventory_cost_entry_id');
            foreach ($lines as $line) {
                $available = $line->product_batch_id === null
                    ? StockBalance::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->store_id)->where('inventory_location_id', $locked->inventory_location_id)->where('product_variant_id', $line->product_variant_id)->lockForUpdate()->value('quantity_base')
                    : BatchStockBalance::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->store_id)->where('inventory_location_id', $locked->inventory_location_id)->where('product_variant_id', $line->product_variant_id)->where('product_batch_id', $line->product_batch_id)->lockForUpdate()->value('quantity_base');
                if ($available === null || BigDecimal::of((string) $available)->isLessThan(BigDecimal::of($line->received_quantity_base))) {
                    throw new ConflictHttpException('Receipt reversal requires its received stock to remain available in the original location and batch.');
                }
                $variant = $line->purchaseOrderLine->variant;
                $this->valuation->reverseLatest(
                    $line->costEntry, $locked->public_id, now()->toIso8601String(),
                    fn (?string $unitCost, InventoryCostState $costState) => $this->stock->handle(new StockMovementCommand(
                        location: $locked->location, variant: $variant, batch: $line->batch, kind: InventoryMovementKind::Correction,
                        quantityBase: Decimal::fixed(BigDecimal::of($line->received_quantity_base)->negated(), 6), sourceType: 'purchase_receipt_reversal',
                        sourceReference: $locked->public_id, sourceLineReference: $line->public_id, actor: $actor, reverses: $line->stockMovement,
                        reasonText: $reason, unitCost: $unitCost, costState: $costState,
                    ))['movement'],
                );
            }
            foreach ($locked->lines->groupBy('purchase_order_line_id') as $orderLineId => $receiptLines) {
                $orderLine = $order->lines->firstWhere('id', $orderLineId);
                abort_unless($orderLine instanceof PurchaseOrderLine, 500);
                $quantity = $receiptLines->reduce(fn (BigDecimal $sum, $line): BigDecimal => $sum->plus($line->received_quantity_base), BigDecimal::zero());
                $orderLine->update(['received_quantity_base' => Decimal::fixed(BigDecimal::of($orderLine->received_quantity_base)->minus($quantity), 6)]);
            }
            $order->load('lines');
            $hasReceived = $order->lines->contains(fn (PurchaseOrderLine $line): bool => BigDecimal::of($line->received_quantity_base)->isPositive());
            $fromState = $order->state;
            $nextState = $fromState === PurchaseOrderState::Cancelled ? PurchaseOrderState::Cancelled : ($hasReceived ? PurchaseOrderState::PartiallyReceived : PurchaseOrderState::Issued);
            $order->update(['state' => $nextState, 'version' => $order->version + 1]);
            $order->refresh()->load(['lines.variant', 'supplier', 'store']);
            $this->recorder->record($order, $actor, 'receipt_reversed', $fromState->value, $reason);
            $locked->update(['status' => PurchaseReceiptStatus::Reversed, 'reversed_at' => now(), 'reversed_by_user_id' => $actor->id, 'reversal_reason' => $reason]);
            $charge = SupplierChargeEntry::query()->where('purchase_receipt_id', $locked->id)->where('entry_kind', 'charge')->sole();
            SupplierChargeEntry::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'supplier_id' => $order->supplier_id, 'purchase_receipt_id' => $locked->id, 'reverses_entry_id' => $charge->id, 'entry_kind' => 'reversal', 'currency' => 'PKR', 'amount' => Decimal::fixed(BigDecimal::of($charge->amount)->negated(), 2), 'source_reference' => $locked->number, 'occurred_at' => now()]);
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'purchasing.receipt.reversed', 'aggregate_type' => 'purchase_receipt', 'aggregate_id' => $locked->public_id, 'payload' => ['receipt_id' => $locked->public_id, 'purchase_order_id' => $order->public_id, 'order_version' => $order->version], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.receipt.reversed', 'outcome' => 'succeeded', 'metadata' => ['receipt_id' => $locked->public_id, 'purchase_order_id' => $order->public_id, 'reason' => $reason], 'occurred_at' => now()]);

            return ['receipt' => $locked->refresh()->load(['purchaseOrder.supplier', 'location', 'lines.purchaseOrderLine', 'lines.batch', 'lines.stockMovement', 'lines.costEntry']), 'order' => $order->load('history'), 'replayed' => false];
        }, attempts: 3);
    }
}
