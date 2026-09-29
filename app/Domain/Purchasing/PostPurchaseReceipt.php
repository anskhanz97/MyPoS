<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\BatchExpiryPolicy;
use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostInventoryValuation;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Domain\Purchasing\Enums\PurchaseReceiptStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\SupplierChargeEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostPurchaseReceipt
{
    public function __construct(
        private readonly PostInventoryValuation $valuation,
        private readonly PostStockMovement $stock,
        private readonly BatchExpiryPolicy $expiryPolicy,
        private readonly PurchaseOrderRecorder $recorder,
    ) {}

    /**
     * @param  list<array{purchase_order_line_id: string, quantity: string, unit_cost: string, landed_cost_total?: string, recoverable_tax_total?: string, batch_code?: string|null, expiry_date?: string|null}>  $lines
     * @return array{receipt: PurchaseReceipt, order: PurchaseOrder, replayed: bool}
     */
    public function handle(Merchant $merchant, Store $store, PurchaseOrder $order, InventoryLocation $location, User $actor, string $idempotencyKey, int $expectedVersion, string $receivedAt, ?string $supplierReference, ?string $notes, array $lines): array
    {
        abort_unless($location->merchant_id === $merchant->id && $location->store_id === $store->id && $location->archived_at === null, 404);
        $effectiveAt = CarbonImmutable::parse($receivedAt);
        abort_if($effectiveAt->isAfter(now()->addMinutes(5)), 422, 'Receipt time cannot be in the future.');
        $normalized = $this->normalize($lines);
        $payloadHash = hash('sha256', json_encode(['order' => $order->public_id, 'location' => $location->public_id, 'expected_version' => $expectedVersion, 'received_at' => $effectiveAt->toIso8601String(), 'supplier_reference' => $supplierReference, 'notes' => $notes, 'lines' => $normalized], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $order, $location, $actor, $idempotencyKey, $expectedVersion, $effectiveAt, $supplierReference, $notes, $normalized, $payloadHash): array {
            $locked = PurchaseOrder::query()->with(['lines.variant.product', 'lines.purchaseUnit', 'supplier'])->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->merchant_id === $merchant->id && $locked->store_id === $store->id, 404);
            $existing = PurchaseReceipt::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('idempotency_key', $idempotencyKey)->with($this->relations())->lockForUpdate()->first();
            if ($existing instanceof PurchaseReceipt) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('The receipt idempotency key was reused with different data.');
                }

                return ['receipt' => $existing, 'order' => $locked, 'replayed' => true];
            }
            if (! in_array($locked->state, [PurchaseOrderState::Issued, PurchaseOrderState::PartiallyReceived], true) || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The purchase order state or version changed before receiving.');
            }

            $resolved = $this->resolveLines($locked, $location, $normalized, $effectiveAt);
            $merchandise = BigDecimal::zero();
            $landed = BigDecimal::zero();
            $tax = BigDecimal::zero();
            foreach ($resolved as $line) {
                $merchandise = $merchandise->plus($line['merchandise']);
                $landed = $landed->plus($line['landed']);
                $tax = $tax->plus($line['tax']);
            }
            $charge = $merchandise->plus($landed)->plus($tax);
            $receipt = PurchaseReceipt::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_order_id' => $locked->id, 'inventory_location_id' => $location->id,
                'received_by_user_id' => $actor->id, 'number' => 'GRN-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'status' => PurchaseReceiptStatus::Posted,
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'expected_order_version' => $expectedVersion,
                'supplier_reference' => $supplierReference, 'notes' => $notes, 'received_at' => $effectiveAt, 'posted_at' => now(),
                'merchandise_total' => Decimal::fixed($merchandise, 6), 'landed_cost_total' => Decimal::fixed($landed, 6),
                'recoverable_tax_total' => Decimal::fixed($tax, 6), 'supplier_charge_total' => Decimal::fixed($charge, 6),
                'valuation_timing' => $effectiveAt->isBefore(now()->subMinute()) ? 'backdated_current_sequence' : 'current_sequence',
            ]);

            $receivedByOrderLine = [];
            foreach ($resolved as $index => $input) {
                $orderLine = $input['order_line'];
                $batchReference = $input['batch'] instanceof ProductBatch ? $input['batch']->public_id : 'untracked';
                $allocationReference = $orderLine->public_id.':'.$batchReference.':'.$index;
                $valuation = $this->valuation->inbound(
                    $store, $orderLine->variant, InventoryCostEntryKind::PurchaseReceipt, $input['quantity_base'], $input['inventory_unit_cost'],
                    'purchase_receipt', $receipt->public_id, $allocationReference, $effectiveAt->toIso8601String(),
                    fn (string $unitCost, InventoryCostState $costState) => $this->stock->handle(new StockMovementCommand(
                        location: $location, variant: $orderLine->variant, batch: $input['batch'], kind: InventoryMovementKind::PurchaseReceipt,
                        quantityBase: $input['quantity_base'], sourceType: 'purchase_receipt', sourceReference: $receipt->public_id,
                        sourceLineReference: $allocationReference, actor: $actor, reasonText: 'Receive purchase order '.$locked->number,
                        unitCost: $unitCost, costState: $costState, occurredAt: $effectiveAt,
                    ))['movement'],
                );
                PurchaseReceiptLine::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'purchase_receipt_id' => $receipt->id, 'purchase_order_id' => $locked->id, 'purchase_order_line_id' => $orderLine->id,
                    'product_variant_id' => $orderLine->product_variant_id, 'purchase_unit_id' => $orderLine->purchase_unit_id, 'product_batch_id' => $input['batch']?->id,
                    'stock_movement_id' => $valuation['movement']->id, 'inventory_cost_entry_id' => $valuation['entry']->id,
                    'product_name_snapshot' => $orderLine->product_name_snapshot, 'sku_snapshot' => $orderLine->sku_snapshot, 'unit_symbol_snapshot' => $orderLine->unit_symbol_snapshot,
                    'received_quantity_uom' => $input['quantity_uom'], 'factor_to_base' => $orderLine->factor_to_base, 'received_quantity_base' => $input['quantity_base'],
                    'unit_cost_uom' => $input['unit_cost_uom'], 'unit_cost_base' => $input['unit_cost_base'], 'landed_cost_total' => Decimal::fixed($input['landed'], 6),
                    'recoverable_tax_total' => Decimal::fixed($input['tax'], 6), 'inventory_unit_cost_base' => $input['inventory_unit_cost'],
                    'inventory_value' => Decimal::fixed($input['merchandise']->plus($input['landed']), 6),
                ]);
                $receivedByOrderLine[$orderLine->id] = ($receivedByOrderLine[$orderLine->id] ?? BigDecimal::zero())->plus($input['quantity_base']);
            }
            foreach ($receivedByOrderLine as $lineId => $quantity) {
                $line = $locked->lines->firstWhere('id', $lineId);
                abort_unless($line instanceof PurchaseOrderLine, 500);
                $line->update(['received_quantity_base' => Decimal::fixed(BigDecimal::of($line->received_quantity_base)->plus($quantity), 6)]);
            }
            $locked->load('lines');
            $complete = $locked->lines->every(fn (PurchaseOrderLine $line): bool => ! BigDecimal::of($line->ordered_quantity_base)->minus($line->received_quantity_base)->minus($line->cancelled_quantity_base)->isPositive());
            $fromState = $locked->state;
            $locked->update(['state' => $complete ? PurchaseOrderState::Received : PurchaseOrderState::PartiallyReceived, 'version' => $locked->version + 1]);
            $locked->refresh()->load(['lines.variant.product', 'lines.purchaseUnit', 'supplier', 'store']);
            $this->recorder->record($locked, $actor, 'receipt_posted', $fromState->value, 'Goods receipt '.$receipt->number);

            SupplierChargeEntry::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $locked->supplier_id, 'purchase_receipt_id' => $receipt->id,
                'entry_kind' => 'charge', 'currency' => 'PKR', 'amount' => (string) $charge->toScale(2, RoundingMode::HalfUp),
                'source_reference' => $supplierReference ?? $receipt->number, 'occurred_at' => $effectiveAt,
            ]);
            OutboxEvent::query()->create([
                'event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'store_id' => $store->id, 'event_type' => 'purchasing.receipt.posted',
                'aggregate_type' => 'purchase_receipt', 'aggregate_id' => $receipt->public_id,
                'payload' => ['receipt_id' => $receipt->public_id, 'purchase_order_id' => $locked->public_id, 'order_version' => $locked->version],
                'occurred_at' => now(), 'available_at' => now(),
            ]);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.receipt.posted', 'outcome' => 'succeeded', 'metadata' => ['receipt_id' => $receipt->public_id, 'purchase_order_id' => $locked->public_id, 'line_count' => count($resolved)], 'occurred_at' => now()]);

            return ['receipt' => $receipt->load($this->relations()), 'order' => $locked->load('history'), 'replayed' => false];
        }, attempts: 3);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{purchase_order_line_id: string, quantity: string, unit_cost: string, landed_cost_total: string, recoverable_tax_total: string, batch_code: string|null, expiry_date: string|null}>
     */
    private function normalize(array $lines): array
    {
        $normalized = array_map(fn (array $line): array => [
            'purchase_order_line_id' => (string) $line['purchase_order_line_id'], 'quantity' => Decimal::fixed(BigDecimal::of((string) $line['quantity']), 6),
            'unit_cost' => Decimal::fixed(BigDecimal::of((string) $line['unit_cost']), 6), 'landed_cost_total' => Decimal::fixed(BigDecimal::of((string) ($line['landed_cost_total'] ?? '0')), 6),
            'recoverable_tax_total' => Decimal::fixed(BigDecimal::of((string) ($line['recoverable_tax_total'] ?? '0')), 6), 'batch_code' => isset($line['batch_code']) ? trim((string) $line['batch_code']) : null,
            'expiry_date' => isset($line['expiry_date']) ? (string) $line['expiry_date'] : null,
        ], $lines);
        usort($normalized, fn (array $a, array $b): int => [$a['purchase_order_line_id'], $a['batch_code'] ?? ''] <=> [$b['purchase_order_line_id'], $b['batch_code'] ?? '']);

        return $normalized;
    }

    /**
     * @param  list<array{purchase_order_line_id: string, quantity: string, unit_cost: string, landed_cost_total: string, recoverable_tax_total: string, batch_code: string|null, expiry_date: string|null}>  $lines
     * @return list<array{order_line: PurchaseOrderLine, quantity_uom: string, quantity_base: string, unit_cost_uom: string, unit_cost_base: string, merchandise: BigDecimal, landed: BigDecimal, tax: BigDecimal, inventory_unit_cost: string, batch: ProductBatch|null}>
     */
    private function resolveLines(PurchaseOrder $order, InventoryLocation $location, array $lines, CarbonImmutable $effectiveAt): array
    {
        $byPublicId = $order->lines->keyBy('public_id');
        $totals = [];
        $dimensions = [];
        $resolved = [];
        foreach ($lines as $input) {
            $line = $byPublicId->get($input['purchase_order_line_id']);
            abort_unless($line instanceof PurchaseOrderLine, 422, 'A receipt line does not belong to this purchase order.');
            $dimension = $line->public_id.':'.($input['batch_code'] ?? '');
            abort_if(isset($dimensions[$dimension]), 422, 'A purchase-order line and batch may appear only once per receipt.');
            $dimensions[$dimension] = true;
            $quantityUom = BigDecimal::of((string) $input['quantity']);
            $quantityBase = $quantityUom->multipliedBy($line->factor_to_base);
            $totals[$line->id] = ($totals[$line->id] ?? BigDecimal::zero())->plus($quantityBase);
            $remaining = BigDecimal::of($line->ordered_quantity_base)->minus($line->received_quantity_base)->minus($line->cancelled_quantity_base);
            if ($totals[$line->id]->isGreaterThan($remaining)) {
                throw new ConflictHttpException('Received quantity exceeds the purchase-order remainder. Amend the order before receiving verified excess.');
            }
            $batch = $this->batch($order, $line, $location, $input['batch_code'], $input['expiry_date'], $effectiveAt);
            $unitCostUom = BigDecimal::of((string) $input['unit_cost']);
            $unitCostBase = $unitCostUom->dividedBy($line->factor_to_base, Decimal::CalculatorScale, RoundingMode::HalfUp);
            $merchandise = $quantityUom->multipliedBy($unitCostUom);
            $landed = BigDecimal::of((string) $input['landed_cost_total']);
            $tax = BigDecimal::of((string) $input['recoverable_tax_total']);
            $inventoryUnitCost = $merchandise->plus($landed)->dividedBy($quantityBase, Decimal::CalculatorScale, RoundingMode::HalfUp);
            $resolved[] = ['order_line' => $line, 'quantity_uom' => Decimal::fixed($quantityUom, 6), 'quantity_base' => Decimal::fixed($quantityBase, 6), 'unit_cost_uom' => Decimal::fixed($unitCostUom, 6), 'unit_cost_base' => Decimal::fixed($unitCostBase, 6), 'merchandise' => $merchandise, 'landed' => $landed, 'tax' => $tax, 'inventory_unit_cost' => Decimal::fixed($inventoryUnitCost, 6), 'batch' => $batch];
        }

        return $resolved;
    }

    private function batch(PurchaseOrder $order, PurchaseOrderLine $line, InventoryLocation $location, ?string $code, ?string $expiryDate, CarbonImmutable $effectiveAt): ?ProductBatch
    {
        $tracked = $line->variant->product->batch_tracking_enabled;
        abort_if($tracked && ($code === null || $code === '' || $expiryDate === null), 422, 'Batch-tracked products require a batch code and expiry date.');
        abort_if(! $tracked && ($code !== null || $expiryDate !== null), 422, 'Batch details are not allowed for an untracked product.');
        if (! $tracked) {
            return null;
        }
        $batch = ProductBatch::query()->where('merchant_id', $order->merchant_id)->where('product_variant_id', $line->product_variant_id)->where('code', $code)->first();
        if ($batch instanceof ProductBatch && $batch->expiry_date->toDateString() !== $expiryDate) {
            throw new ConflictHttpException('The batch code already has a different expiry date.');
        }
        $batch ??= ProductBatch::query()->create(['merchant_id' => $order->merchant_id, 'product_variant_id' => $line->product_variant_id, 'origin_store_id' => $order->store_id, 'code' => $code, 'expiry_date' => $expiryDate, 'received_on' => $effectiveAt->setTimezone($order->store->timezone)->toDateString(), 'supplier_reference' => null]);
        if ($this->expiryPolicy->isExpired($batch, $order->store, $effectiveAt) && $location->disposition->value === 'sellable') {
            throw new ConflictHttpException('An expired batch must be received into quarantine or unavailable stock.');
        }

        return $batch;
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['purchaseOrder.supplier', 'location', 'lines.purchaseOrderLine', 'lines.batch', 'lines.stockMovement', 'lines.costEntry'];
    }
}
