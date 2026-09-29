<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\Enums\StockTransferState;
use App\Domain\Shared\Math\Decimal;
use App\Models\BatchStockBalance;
use App\Models\InventoryCostBalance;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DispatchStockTransfer
{
    public function __construct(private readonly PostStockMovement $post, private readonly PostInventoryValuation $valuation) {}

    /** @return array{transfer: StockTransfer, replayed: bool} */
    public function handle(StockTransfer $transfer, User $actor, string $key, int $expectedVersion): array
    {
        return DB::transaction(function () use ($transfer, $actor, $key, $expectedVersion): array {
            $locked = StockTransfer::query()->with(['sourceStore', 'sourceLocation', 'lines.sourceVariant.product', 'lines.destinationVariant', 'lines.sourceBatch'])->lockForUpdate()->findOrFail($transfer->id);
            $hash = hash('sha256', json_encode(['expected_version' => $expectedVersion, 'lines' => $locked->lines->map(fn ($line): array => ['line' => $line->public_id, 'quantity' => $line->requested_source_quantity_base, 'batch' => $line->sourceBatch?->public_id])->all()], JSON_THROW_ON_ERROR));
            if ($locked->dispatch_key !== null) {
                if ($locked->dispatch_key === $key && hash_equals((string) $locked->dispatch_hash, $hash)) {
                    return ['transfer' => $locked, 'replayed' => true];
                }
                throw new ConflictHttpException('This transfer was already dispatched with different command evidence.');
            }
            if ($locked->state !== StockTransferState::Draft || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The transfer state or version changed before dispatch.');
            }
            foreach ($locked->lines as $line) {
                $quantity = BigDecimal::of($line->requested_source_quantity_base);
                if ($line->sourceBatch !== null) {
                    $available = BatchStockBalance::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->source_store_id)->where('inventory_location_id', $locked->source_inventory_location_id)->where('product_variant_id', $line->source_product_variant_id)->where('product_batch_id', $line->source_product_batch_id)->lockForUpdate()->value('quantity_base');
                } else {
                    $available = StockBalance::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->source_store_id)->where('inventory_location_id', $locked->source_inventory_location_id)->where('product_variant_id', $line->source_product_variant_id)->lockForUpdate()->value('quantity_base');
                }
                if ($available === null || BigDecimal::of((string) $available)->isLessThan($quantity)) {
                    throw new ConflictHttpException('Source stock no longer covers the transfer dispatch.');
                }
                $costEntry = null;
                $sourceCost = null;
                $costBalanceExists = InventoryCostBalance::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->source_store_id)->where('product_variant_id', $line->source_product_variant_id)->exists();
                if ($costBalanceExists) {
                    $costResult = $this->valuation->outbound(
                        $locked->sourceStore, $line->sourceVariant, InventoryCostEntryKind::TransferOut, (string) $quantity,
                        'stock_transfer_dispatch', $locked->public_id, $line->public_id, now()->toIso8601String(),
                        fn (?string $unitCost, $costState) => $this->post->handle(new StockMovementCommand(
                            location: $locked->sourceLocation, variant: $line->sourceVariant, batch: $line->sourceBatch,
                            kind: InventoryMovementKind::TransferOut, quantityBase: Decimal::fixed($quantity->negated(), 6),
                            sourceType: 'stock_transfer_dispatch', sourceReference: $locked->public_id, sourceLineReference: $line->public_id,
                            actor: $actor, reasonText: 'Dispatch transfer '.$locked->number, unitCost: $unitCost, costState: $costState,
                        ))['movement'],
                    );
                    $costEntry = $costResult['entry'];
                    $sourceCost = $costEntry->unit_cost;
                } else {
                    $this->post->handle(new StockMovementCommand(
                        location: $locked->sourceLocation, variant: $line->sourceVariant, batch: $line->sourceBatch,
                        kind: InventoryMovementKind::TransferOut, quantityBase: Decimal::fixed($quantity->negated(), 6),
                        sourceType: 'stock_transfer_dispatch', sourceReference: $locked->public_id, sourceLineReference: $line->public_id,
                        actor: $actor, reasonText: 'Dispatch transfer '.$locked->number,
                    ));
                }
                $line->update([
                    'dispatched_source_quantity_base' => $line->requested_source_quantity_base,
                    'expected_destination_quantity_base' => Decimal::fixed($quantity->multipliedBy($line->factor_to_destination_base), 6),
                    'source_unit_cost' => $sourceCost,
                    'destination_unit_cost' => $sourceCost === null ? null : Decimal::fixed(BigDecimal::of($sourceCost)->dividedBy($line->factor_to_destination_base, Decimal::CalculatorScale, RoundingMode::HalfUp), 6),
                    'source_cost_entry_id' => $costEntry?->id,
                ]);
            }
            $locked->update(['state' => StockTransferState::Dispatched, 'version' => $locked->version + 1, 'dispatch_key' => $key, 'dispatch_hash' => $hash, 'dispatched_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->source_store_id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.transfer.dispatched', 'outcome' => 'succeeded', 'metadata' => ['transfer_id' => $locked->public_id, 'line_count' => $locked->lines->count()], 'occurred_at' => now()]);

            return ['transfer' => $locked->refresh()->load(['sourceStore', 'destinationStore', 'sourceLocation', 'destinationLocation', 'lines.sourceVariant.product', 'lines.destinationVariant.product', 'lines.sourceBatch']), 'replayed' => false];
        });
    }
}
