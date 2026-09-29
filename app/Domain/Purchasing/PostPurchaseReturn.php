<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Purchasing\Enums\PurchaseReceiptStatus;
use App\Domain\Purchasing\Enums\PurchaseReturnStatus;
use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\BatchStockBalance;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\SupplierCredit;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostPurchaseReturn
{
    public function __construct(private readonly PostInventoryValuation $valuation, private readonly PostStockMovement $stock) {}

    /**
     * @param  list<array{purchase_receipt_line_id: string, quantity: string}>  $lines
     * @return array{return: PurchaseReturn, replayed: bool}
     */
    public function handle(Merchant $merchant, Store $store, PurchaseReceipt $receipt, User $actor, string $idempotencyKey, string $returnedAt, string $reason, SupplierCreditStatus $creditStatus, ?string $creditReference, array $lines): array
    {
        $effectiveAt = CarbonImmutable::parse($returnedAt);
        abort_if($effectiveAt->isAfter(now()->addMinutes(5)), 422, 'Purchase return time cannot be in the future.');
        abort_if($creditStatus === SupplierCreditStatus::Confirmed && ($creditReference === null || trim($creditReference) === ''), 422, 'A confirmed supplier credit requires the supplier document reference.');
        $normalized = array_map(fn (array $line): array => ['purchase_receipt_line_id' => (string) $line['purchase_receipt_line_id'], 'quantity' => Decimal::fixed(BigDecimal::of($line['quantity']), 6)], $lines);
        usort($normalized, fn (array $a, array $b): int => $a['purchase_receipt_line_id'] <=> $b['purchase_receipt_line_id']);
        $payloadHash = hash('sha256', json_encode(['receipt' => $receipt->public_id, 'returned_at' => $effectiveAt->toIso8601String(), 'reason' => $reason, 'credit_status' => $creditStatus->value, 'credit_reference' => $creditReference, 'lines' => $normalized], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $receipt, $actor, $idempotencyKey, $effectiveAt, $reason, $creditStatus, $creditReference, $normalized, $payloadHash): array {
            $locked = PurchaseReceipt::query()->with(['purchaseOrder.supplier', 'location', 'lines.purchaseOrderLine.variant', 'lines.batch'])->lockForUpdate()->findOrFail($receipt->id);
            abort_unless($locked->merchant_id === $merchant->id && $locked->store_id === $store->id, 404);
            if ($locked->status !== PurchaseReceiptStatus::Posted) {
                throw new ConflictHttpException('Only a posted purchase receipt can be returned.');
            }
            $existing = PurchaseReturn::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('idempotency_key', $idempotencyKey)->with($this->relations())->lockForUpdate()->first();
            if ($existing instanceof PurchaseReturn) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('The purchase-return idempotency key was reused with different data.');
                }

                return ['return' => $existing, 'replayed' => true];
            }

            $sourceById = $locked->lines->keyBy('public_id');
            $seen = [];
            $resolved = [];
            $creditTotal = BigDecimal::zero();
            foreach ($normalized as $input) {
                abort_if(isset($seen[$input['purchase_receipt_line_id']]), 422, 'A receipt line may appear only once per purchase return.');
                $seen[$input['purchase_receipt_line_id']] = true;
                $source = $sourceById->get($input['purchase_receipt_line_id']);
                abort_unless($source instanceof PurchaseReceiptLine, 422, 'A purchase-return line does not belong to this receipt.');
                $quantityUom = BigDecimal::of($input['quantity']);
                $quantityBase = $quantityUom->multipliedBy($source->factor_to_base);
                $prior = PurchaseReturnLine::query()->where('purchase_receipt_line_id', $source->id)->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturnStatus::Posted->value))->lockForUpdate()->get();
                $priorBase = $prior->reduce(fn (BigDecimal $sum, PurchaseReturnLine $line): BigDecimal => $sum->plus((string) $line->returned_quantity_base), BigDecimal::zero());
                if ($priorBase->plus($quantityBase)->isGreaterThan(BigDecimal::of($source->received_quantity_base))) {
                    throw new ConflictHttpException('Returned quantity exceeds the unreturned receipt quantity.');
                }
                $available = $source->product_batch_id === null
                    ? StockBalance::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('inventory_location_id', $locked->inventory_location_id)->where('product_variant_id', $source->product_variant_id)->lockForUpdate()->value('quantity_base')
                    : BatchStockBalance::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('inventory_location_id', $locked->inventory_location_id)->where('product_variant_id', $source->product_variant_id)->where('product_batch_id', $source->product_batch_id)->lockForUpdate()->value('quantity_base');
                if ($available === null || BigDecimal::of((string) $available)->isLessThan($quantityBase)) {
                    throw new ConflictHttpException('The original location and batch do not contain enough stock for this supplier return.');
                }

                $sourceCredit = BigDecimal::of($source->received_quantity_uom)->multipliedBy($source->unit_cost_uom)->plus($source->landed_cost_total)->plus($source->recoverable_tax_total);
                $cumulativeCredit = $sourceCredit->multipliedBy($priorBase->plus($quantityBase))->dividedBy($source->received_quantity_base, 2, RoundingMode::HalfUp);
                $priorCredit = $prior->reduce(fn (BigDecimal $sum, PurchaseReturnLine $line): BigDecimal => $sum->plus((string) $line->supplier_credit_amount), BigDecimal::zero());
                $lineCredit = $cumulativeCredit->minus($priorCredit);
                $creditTotal = $creditTotal->plus($lineCredit);
                $resolved[] = ['source' => $source, 'quantity_uom' => $quantityUom, 'quantity_base' => $quantityBase, 'credit' => $lineCredit, 'credit_unit' => $sourceCredit->dividedBy($source->received_quantity_base, 20, RoundingMode::HalfUp)];
            }

            $purchaseReturn = PurchaseReturn::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $locked->purchaseOrder->supplier_id, 'purchase_receipt_id' => $locked->id,
                'inventory_location_id' => $locked->inventory_location_id, 'posted_by_user_id' => $actor->id, 'number' => 'PRN-'.mb_strtoupper(substr((string) Str::ulid(), -12)),
                'status' => PurchaseReturnStatus::Posted, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'reason' => $reason,
                'supplier_credit_reference' => $creditReference, 'credit_status' => $creditStatus, 'supplier_credit_total' => Decimal::fixed($creditTotal, 2),
                'returned_at' => $effectiveAt, 'posted_at' => now(),
            ]);
            foreach ($resolved as $resolvedLine) {
                $source = $resolvedLine['source'];
                $lineReference = $source->public_id;
                $valuation = $this->valuation->outbound(
                    $store, $source->purchaseOrderLine->variant, InventoryCostEntryKind::PurchaseReturn, Decimal::fixed($resolvedLine['quantity_base'], 6),
                    'purchase_return', $purchaseReturn->public_id, $lineReference, $effectiveAt->toIso8601String(),
                    fn (?string $unitCost, InventoryCostState $costState) => $this->stock->handle(new StockMovementCommand(
                        location: $locked->location, variant: $source->purchaseOrderLine->variant, batch: $source->batch, kind: InventoryMovementKind::PurchaseReturn,
                        quantityBase: Decimal::fixed($resolvedLine['quantity_base']->negated(), 6), sourceType: 'purchase_return', sourceReference: $purchaseReturn->public_id,
                        sourceLineReference: $lineReference, actor: $actor, reasonText: $reason, unitCost: $unitCost, costState: $costState, occurredAt: $effectiveAt,
                    ))['movement'],
                );
                PurchaseReturnLine::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_return_id' => $purchaseReturn->id, 'purchase_receipt_line_id' => $source->id,
                    'product_variant_id' => $source->product_variant_id, 'product_batch_id' => $source->product_batch_id, 'stock_movement_id' => $valuation['movement']->id,
                    'inventory_cost_entry_id' => $valuation['entry']->id, 'product_name_snapshot' => $source->product_name_snapshot, 'sku_snapshot' => $source->sku_snapshot,
                    'unit_symbol_snapshot' => $source->unit_symbol_snapshot, 'returned_quantity_uom' => Decimal::fixed($resolvedLine['quantity_uom'], 6),
                    'factor_to_base' => $source->factor_to_base, 'returned_quantity_base' => Decimal::fixed($resolvedLine['quantity_base'], 6),
                    'source_credit_unit_base' => Decimal::fixed($resolvedLine['credit_unit'], 6), 'supplier_credit_amount' => Decimal::fixed($resolvedLine['credit'], 2),
                    'valuation_unit_cost' => $valuation['entry']->unit_cost,
                ]);
            }
            SupplierCredit::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $locked->purchaseOrder->supplier_id, 'purchase_return_id' => $purchaseReturn->id,
                'entry_kind' => 'credit', 'status' => $creditStatus, 'currency' => 'PKR', 'amount' => Decimal::fixed($creditTotal, 2),
                'supplier_reference' => $creditReference, 'occurred_at' => $effectiveAt,
            ]);
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'store_id' => $store->id, 'event_type' => 'purchasing.return.posted', 'aggregate_type' => 'purchase_return', 'aggregate_id' => $purchaseReturn->public_id, 'payload' => ['purchase_return_id' => $purchaseReturn->public_id, 'receipt_id' => $locked->public_id, 'credit_status' => $creditStatus->value], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.return.posted', 'outcome' => 'succeeded', 'metadata' => ['purchase_return_id' => $purchaseReturn->public_id, 'receipt_id' => $locked->public_id, 'line_count' => count($resolved)], 'occurred_at' => now()]);

            return ['return' => $purchaseReturn->load($this->relations()), 'replayed' => false];
        }, attempts: 3);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['receipt.purchaseOrder', 'location', 'supplier', 'credit', 'lines.receiptLine', 'lines.batch', 'lines.stockMovement', 'lines.costEntry'];
    }
}
