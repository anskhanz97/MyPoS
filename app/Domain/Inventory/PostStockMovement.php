<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryDisposition;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Shared\Math\Decimal;
use App\Models\BatchStockBalance;
use App\Models\Merchant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostStockMovement
{
    public function __construct(private readonly InventoryScope $scope, private readonly BatchExpiryPolicy $expiryPolicy) {}

    /** @return array{movement: StockMovement, replayed: bool, balance: StockBalance, batch_balance: BatchStockBalance|null} */
    public function handle(StockMovementCommand $command, ?Closure $beforeCreate = null): array
    {
        $merchant = Merchant::query()->findOrFail($command->location->merchant_id);
        $store = Store::query()->findOrFail($command->location->store_id);
        $this->scope->assertLocation($merchant, $store, $command->location);
        $this->scope->assertVariant($merchant, $store, $command->variant);
        $quantity = Decimal::fixed(BigDecimal::of($command->quantityBase), 6);
        if (BigDecimal::of($quantity)->isZero()) {
            throw ValidationException::withMessages(['quantity' => 'Inventory quantity cannot be zero.']);
        }
        if ($command->adjustmentReason !== null) {
            abort_unless($command->adjustmentReason->merchant_id === $merchant->id, 422, 'The adjustment reason belongs to another merchant.');
        }
        if ($command->reverses !== null) {
            abort_unless($command->reverses->merchant_id === $merchant->id && $command->reverses->store_id === $store->id, 422, 'The reversed movement belongs to another store.');
        }
        $isConversion = $command->kind === InventoryMovementKind::BatchAllocation;
        if ($command->batch !== null) {
            abort_unless($command->batch->merchant_id === $merchant->id && $command->batch->product_variant_id === $command->variant->id && $command->batch->archived_at === null, 422, 'The batch does not belong to this product.');
        }
        if (! $isConversion) {
            abort_if($command->variant->product->batch_tracking_enabled && $command->batch === null, 409, 'This tracked product requires a batch for every stock movement.');
            abort_if(! $command->variant->product->batch_tracking_enabled && $command->batch !== null, 409, 'Batch tracking is not enabled for this product.');
        }
        if ($command->kind === InventoryMovementKind::Sale) {
            abort_unless($command->location->disposition === InventoryDisposition::Sellable, 409, 'Sales can only consume a sellable inventory location.');
            if ($command->batch !== null && $this->expiryPolicy->isExpired($command->batch, $store, $command->occurredAt)) {
                throw new ConflictHttpException('The selected batch is expired in the store business timezone.');
            }
        }
        $sourceKey = hash('sha256', implode("\x1f", [$command->sourceType, $command->sourceReference, $command->sourceLineReference, $command->sourceEffect]));
        $payloadHash = hash('sha256', json_encode([
            'location' => $command->location->public_id,
            'variant' => $command->variant->public_id,
            'batch' => $command->batch?->public_id,
            'kind' => $command->kind->value,
            'quantity' => $quantity,
            'cost' => $command->unitCost,
            'cost_state' => $command->costState->value,
            'reason' => $command->adjustmentReason?->public_id,
            'reverses' => $command->reverses?->public_id,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($command, $merchant, $store, $quantity, $sourceKey, $payloadHash, $beforeCreate): array {
            // Lock the projection dimension first so every writer uses the same lock order.
            $balance = $this->lockedBalance($merchant->id, $store->id, $command);
            $batchBalance = $command->batch === null ? null : $this->lockedBatchBalance($merchant->id, $store->id, $command);
            $existing = StockMovement::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('source_key', $sourceKey)->lockForUpdate()->first();
            if ($existing instanceof StockMovement) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('The inventory source was already used with different movement data.');
                }

                return ['movement' => $existing->load(['location', 'variant.product', 'variant.baseUnit', 'batch', 'adjustmentReason']), 'replayed' => true, 'balance' => $balance, 'batch_balance' => $batchBalance];
            }

            if ($command->kind === InventoryMovementKind::Sale && $batchBalance instanceof BatchStockBalance && BigDecimal::of($batchBalance->quantity_base)->plus($quantity)->isNegative()) {
                throw new ConflictHttpException('The selected non-expired batches do not cover the requested quantity.');
            }

            $beforeCreate?->__invoke();
            $movement = StockMovement::query()->create([
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
                'inventory_location_id' => $command->location->id,
                'product_variant_id' => $command->variant->id,
                'product_batch_id' => $command->batch?->id,
                'inventory_adjustment_reason_id' => $command->adjustmentReason?->id,
                'actor_user_id' => $command->actor?->id,
                'reverses_stock_movement_id' => $command->reverses?->id,
                'movement_kind' => $command->kind,
                'quantity_base' => $quantity,
                'unit_cost' => $command->unitCost,
                'cost_state' => $command->costState,
                'source_type' => $command->sourceType,
                'source_reference' => $command->sourceReference,
                'source_line_reference' => $command->sourceLineReference,
                'source_effect' => $command->sourceEffect,
                'source_key' => $sourceKey,
                'payload_hash' => $payloadHash,
                'reason_text' => $command->reasonText,
                'occurred_at' => $command->occurredAt ?? now(),
            ]);
            $balance->update([
                'quantity_base' => Decimal::fixed(BigDecimal::of($balance->quantity_base)->plus($quantity), 6),
                'movement_count' => $balance->movement_count + 1,
                'last_stock_movement_id' => $movement->id,
                'projection_version' => $balance->projection_version + 1,
            ]);
            if ($batchBalance instanceof BatchStockBalance) {
                $batchBalance->update([
                    'quantity_base' => Decimal::fixed(BigDecimal::of($batchBalance->quantity_base)->plus($quantity), 6),
                    'movement_count' => $batchBalance->movement_count + 1,
                    'last_stock_movement_id' => $movement->id,
                    'projection_version' => $batchBalance->projection_version + 1,
                ]);
            }

            return ['movement' => $movement->load(['location', 'variant.product', 'variant.baseUnit', 'batch', 'adjustmentReason']), 'replayed' => false, 'balance' => $balance->refresh(), 'batch_balance' => $batchBalance?->refresh()];
        }, attempts: 3);
    }

    private function lockedBalance(int $merchantId, int $storeId, StockMovementCommand $command): StockBalance
    {
        $query = fn () => StockBalance::query()
            ->where('merchant_id', $merchantId)
            ->where('store_id', $storeId)
            ->where('inventory_location_id', $command->location->id)
            ->where('product_variant_id', $command->variant->id);
        $balance = $query()->lockForUpdate()->first();
        if (! $balance instanceof StockBalance) {
            try {
                StockBalance::query()->create([
                    'merchant_id' => $merchantId,
                    'store_id' => $storeId,
                    'inventory_location_id' => $command->location->id,
                    'product_variant_id' => $command->variant->id,
                    'quantity_base' => '0.000000',
                    'movement_count' => 0,
                    'projection_version' => 0,
                ]);
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'stock_balances_dimension_unique')) {
                    throw $exception;
                }
            }
            $balance = $query()->lockForUpdate()->firstOrFail();
        }

        return $balance;
    }

    private function lockedBatchBalance(int $merchantId, int $storeId, StockMovementCommand $command): BatchStockBalance
    {
        $batch = $command->batch;
        abort_unless($batch !== null, 500);
        $query = fn () => BatchStockBalance::query()
            ->where('merchant_id', $merchantId)
            ->where('store_id', $storeId)
            ->where('inventory_location_id', $command->location->id)
            ->where('product_variant_id', $command->variant->id)
            ->where('product_batch_id', $batch->id);
        $balance = $query()->lockForUpdate()->first();
        if (! $balance instanceof BatchStockBalance) {
            try {
                BatchStockBalance::query()->create([
                    'merchant_id' => $merchantId,
                    'store_id' => $storeId,
                    'inventory_location_id' => $command->location->id,
                    'product_variant_id' => $command->variant->id,
                    'product_batch_id' => $batch->id,
                    'quantity_base' => '0.000000',
                    'movement_count' => 0,
                    'projection_version' => 0,
                ]);
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'batch_stock_balances_dimension_unique')) {
                    throw $exception;
                }
            }
            $balance = $query()->lockForUpdate()->firstOrFail();
        }

        return $balance;
    }
}
