<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Shared\Math\Decimal;
use App\Models\InventoryCostBalance;
use App\Models\InventoryCostEntry;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostInventoryValuation
{
    /**
     * @param  Closure(string, InventoryCostState): StockMovement  $postStock
     * @return array{entry: InventoryCostEntry, balance: InventoryCostBalance, movement: StockMovement, replayed: bool}
     */
    public function inbound(Store $store, ProductVariant $variant, InventoryCostEntryKind $kind, string $quantityBase, string $unitCost, string $sourceType, string $sourceReference, string $sourceLineReference, string $effectiveAt, Closure $postStock): array
    {
        $quantity = BigDecimal::of(Decimal::fixed(BigDecimal::of($quantityBase), 6));
        $cost = BigDecimal::of(Decimal::fixed(BigDecimal::of($unitCost), 6));
        abort_unless($quantity->isPositive(), 422, 'Inbound valuation quantity must be positive.');
        abort_if($cost->isNegative(), 422, 'Inventory unit cost cannot be negative.');
        $sourceKey = $this->sourceKey($sourceType, $sourceReference, $sourceLineReference);
        $payloadHash = $this->payloadHash($store, $variant, $kind, $quantity, $cost, $effectiveAt);

        return DB::transaction(function () use ($store, $variant, $kind, $quantity, $cost, $sourceType, $sourceReference, $sourceLineReference, $effectiveAt, $sourceKey, $payloadHash, $postStock): array {
            $balance = $this->lockedBalance($store, $variant);
            $existing = $this->existing($store, $sourceKey, $payloadHash);
            if ($existing instanceof InventoryCostEntry) {
                $movement = StockMovement::query()->findOrFail($existing->stock_movement_id);

                return ['entry' => $existing, 'balance' => $balance, 'movement' => $movement, 'replayed' => true];
            }

            $previousQuantity = BigDecimal::of($balance->quantity_base);
            $previousAverage = $balance->average_unit_cost === null ? null : BigDecimal::of($balance->average_unit_cost);
            $previousValue = $balance->inventory_value === null ? null : BigDecimal::of($balance->inventory_value);
            $result = $this->calculateInbound($previousQuantity, $previousAverage, $previousValue, $quantity, $cost);
            $movement = $postStock(Decimal::fixed($cost, 6), $result['state']);
            $timing = $this->valuationTiming($balance, $effectiveAt);
            $entry = InventoryCostEntry::query()->create([
                'merchant_id' => $store->merchant_id, 'store_id' => $store->id, 'product_variant_id' => $variant->id,
                'inventory_cost_balance_id' => $balance->id, 'stock_movement_id' => $movement->id, 'entry_kind' => $kind,
                'quantity_delta' => Decimal::fixed($quantity, 6), 'unit_cost' => Decimal::fixed($cost, 6),
                'value_delta' => Decimal::fixed($quantity->multipliedBy($cost), 6),
                'previous_quantity' => Decimal::fixed($previousQuantity, 6), 'previous_value' => $this->fixedOrNull($previousValue), 'previous_average' => $this->fixedOrNull($previousAverage),
                'resulting_quantity' => Decimal::fixed($result['quantity'], 6), 'resulting_value' => $this->fixedOrNull($result['value']), 'resulting_average' => $this->fixedOrNull($result['average']),
                'cost_state' => $result['state'], 'resolved_negative_quantity' => Decimal::fixed($result['resolved'], 6), 'cost_adjustment_value' => Decimal::fixed($result['adjustment'], 6),
                'valuation_timing' => $timing, 'source_type' => $sourceType, 'source_reference' => $sourceReference, 'source_line_reference' => $sourceLineReference,
                'source_key' => $sourceKey, 'payload_hash' => $payloadHash, 'effective_at' => $effectiveAt, 'applied_at' => now(),
            ]);
            $balance->update([
                'quantity_base' => Decimal::fixed($result['quantity'], 6), 'inventory_value' => $this->fixedOrNull($result['value']),
                'average_unit_cost' => $this->fixedOrNull($result['average']), 'cost_state' => $result['state'],
                'pending_negative_quantity_base' => Decimal::fixed($result['quantity']->isNegative() ? $result['quantity']->abs() : BigDecimal::zero(), 6),
                'last_cost_entry_id' => $entry->id, 'projection_version' => $balance->projection_version + 1,
            ]);

            return ['entry' => $entry, 'balance' => $balance->refresh(), 'movement' => $movement, 'replayed' => false];
        }, attempts: 3);
    }

    /**
     * @param  Closure(string|null, InventoryCostState): StockMovement  $postStock
     * @return array{entry: InventoryCostEntry, balance: InventoryCostBalance, movement: StockMovement, replayed: bool}
     */
    public function outbound(Store $store, ProductVariant $variant, InventoryCostEntryKind $kind, string $quantityBase, string $sourceType, string $sourceReference, string $sourceLineReference, string $effectiveAt, Closure $postStock): array
    {
        $quantity = BigDecimal::of(Decimal::fixed(BigDecimal::of($quantityBase), 6));
        abort_unless($quantity->isPositive(), 422, 'Outbound valuation quantity must be positive.');
        $sourceKey = $this->sourceKey($sourceType, $sourceReference, $sourceLineReference);

        return DB::transaction(function () use ($store, $variant, $kind, $quantity, $sourceType, $sourceReference, $sourceLineReference, $effectiveAt, $sourceKey, $postStock): array {
            $balance = $this->lockedBalance($store, $variant);
            $average = $balance->average_unit_cost === null ? null : BigDecimal::of($balance->average_unit_cost);
            $payloadHash = $this->payloadHash($store, $variant, $kind, $quantity->negated(), $average, $effectiveAt);
            $existing = $this->existing($store, $sourceKey, $payloadHash);
            if ($existing instanceof InventoryCostEntry) {
                return ['entry' => $existing, 'balance' => $balance, 'movement' => StockMovement::query()->findOrFail($existing->stock_movement_id), 'replayed' => true];
            }
            $previousQuantity = BigDecimal::of($balance->quantity_base);
            $previousValue = $balance->inventory_value === null ? null : BigDecimal::of($balance->inventory_value);
            $resultingQuantity = $previousQuantity->minus($quantity);
            $resultingValue = $average === null || $previousValue === null ? null : $previousValue->minus($quantity->multipliedBy($average));
            $state = $average === null || $resultingQuantity->isNegative() ? InventoryCostState::Provisional : InventoryCostState::Final;
            $movement = $postStock($this->fixedOrNull($average), $state);
            $entry = InventoryCostEntry::query()->create([
                'merchant_id' => $store->merchant_id, 'store_id' => $store->id, 'product_variant_id' => $variant->id, 'inventory_cost_balance_id' => $balance->id,
                'stock_movement_id' => $movement->id, 'entry_kind' => $kind, 'quantity_delta' => Decimal::fixed($quantity->negated(), 6),
                'unit_cost' => $this->fixedOrNull($average), 'value_delta' => $average === null ? null : Decimal::fixed($quantity->multipliedBy($average)->negated(), 6),
                'previous_quantity' => Decimal::fixed($previousQuantity, 6), 'previous_value' => $this->fixedOrNull($previousValue), 'previous_average' => $this->fixedOrNull($average),
                'resulting_quantity' => Decimal::fixed($resultingQuantity, 6), 'resulting_value' => $this->fixedOrNull($resultingValue), 'resulting_average' => $this->fixedOrNull($average),
                'cost_state' => $state, 'valuation_timing' => $this->valuationTiming($balance, $effectiveAt),
                'source_type' => $sourceType, 'source_reference' => $sourceReference, 'source_line_reference' => $sourceLineReference,
                'source_key' => $sourceKey, 'payload_hash' => $payloadHash, 'effective_at' => $effectiveAt, 'applied_at' => now(),
            ]);
            $balance->update(['quantity_base' => Decimal::fixed($resultingQuantity, 6), 'inventory_value' => $this->fixedOrNull($resultingValue), 'cost_state' => $state, 'pending_negative_quantity_base' => Decimal::fixed($resultingQuantity->isNegative() ? $resultingQuantity->abs() : BigDecimal::zero(), 6), 'last_cost_entry_id' => $entry->id, 'projection_version' => $balance->projection_version + 1]);

            return ['entry' => $entry, 'balance' => $balance->refresh(), 'movement' => $movement, 'replayed' => false];
        }, attempts: 3);
    }

    /**
     * @param  Closure(string|null, InventoryCostState): StockMovement  $postStock
     * @return array{entry: InventoryCostEntry, balance: InventoryCostBalance, movement: StockMovement}
     */
    public function reverseLatest(InventoryCostEntry $source, string $sourceReference, string $effectiveAt, Closure $postStock, InventoryCostEntryKind $reversalKind = InventoryCostEntryKind::ReceiptReversal, string $sourceType = 'purchase_receipt_reversal'): array
    {
        return DB::transaction(function () use ($source, $sourceReference, $effectiveAt, $postStock, $reversalKind, $sourceType): array {
            $lockedSource = InventoryCostEntry::query()->lockForUpdate()->findOrFail($source->id);
            $balance = InventoryCostBalance::query()->lockForUpdate()->findOrFail($lockedSource->inventory_cost_balance_id);
            $lastEntry = $balance->last_cost_entry_id === null ? null : InventoryCostEntry::query()->lockForUpdate()->find($balance->last_cost_entry_id);
            $sameReceiptReversalChain = $lastEntry instanceof InventoryCostEntry
                && $lastEntry->entry_kind === $reversalKind
                && $lastEntry->source_reference === $sourceReference
                && $balance->quantity_base === $lockedSource->resulting_quantity
                && $balance->inventory_value === $lockedSource->resulting_value
                && $balance->average_unit_cost === $lockedSource->resulting_average;
            if ($balance->last_cost_entry_id !== $lockedSource->id && ! $sameReceiptReversalChain) {
                throw new ConflictHttpException('This receipt cannot be reversed after later valuation activity for the product. Post a documented cost adjustment instead.');
            }
            $store = Store::query()->findOrFail($lockedSource->store_id);
            $sourceKey = $this->sourceKey($sourceType, $sourceReference, $lockedSource->public_id);
            $payloadHash = hash('sha256', $lockedSource->public_id."\x1f".$effectiveAt);
            $existing = $this->existing($store, $sourceKey, $payloadHash);
            if ($existing instanceof InventoryCostEntry) {
                return ['entry' => $existing, 'balance' => $balance, 'movement' => StockMovement::query()->findOrFail($existing->stock_movement_id)];
            }
            $previousQuantity = BigDecimal::of($lockedSource->previous_quantity);
            $state = $lockedSource->previous_average === null ? InventoryCostState::Pending : ($previousQuantity->isNegative() ? InventoryCostState::Provisional : InventoryCostState::Final);
            $movement = $postStock($lockedSource->unit_cost, $state);
            $entry = InventoryCostEntry::query()->create([
                'merchant_id' => $lockedSource->merchant_id, 'store_id' => $lockedSource->store_id, 'product_variant_id' => $lockedSource->product_variant_id,
                'inventory_cost_balance_id' => $balance->id, 'stock_movement_id' => $movement->id, 'adjusts_cost_entry_id' => $lockedSource->id,
                'entry_kind' => $reversalKind, 'quantity_delta' => Decimal::fixed(BigDecimal::of($lockedSource->quantity_delta)->negated(), 6),
                'unit_cost' => $lockedSource->unit_cost, 'value_delta' => $lockedSource->value_delta === null ? null : Decimal::fixed(BigDecimal::of($lockedSource->value_delta)->negated(), 6),
                'previous_quantity' => $lockedSource->resulting_quantity, 'previous_value' => $lockedSource->resulting_value, 'previous_average' => $lockedSource->resulting_average,
                'resulting_quantity' => $lockedSource->previous_quantity, 'resulting_value' => $lockedSource->previous_value, 'resulting_average' => $lockedSource->previous_average,
                'cost_state' => $state, 'valuation_timing' => 'current_sequence', 'source_type' => $sourceType, 'source_reference' => $sourceReference,
                'source_line_reference' => $lockedSource->public_id, 'source_key' => $sourceKey, 'payload_hash' => $payloadHash,
                'effective_at' => $effectiveAt, 'applied_at' => now(),
            ]);
            $balance->update(['quantity_base' => $lockedSource->previous_quantity, 'inventory_value' => $lockedSource->previous_value, 'average_unit_cost' => $lockedSource->previous_average, 'cost_state' => $state, 'pending_negative_quantity_base' => Decimal::fixed($previousQuantity->isNegative() ? $previousQuantity->abs() : BigDecimal::zero(), 6), 'last_cost_entry_id' => $entry->id, 'projection_version' => $balance->projection_version + 1]);

            return ['entry' => $entry, 'balance' => $balance->refresh(), 'movement' => $movement];
        }, attempts: 3);
    }

    /** @return array{quantity: BigDecimal, value: BigDecimal|null, average: BigDecimal|null, state: InventoryCostState, resolved: BigDecimal, adjustment: BigDecimal} */
    private function calculateInbound(BigDecimal $oldQuantity, ?BigDecimal $oldAverage, ?BigDecimal $oldValue, BigDecimal $quantity, BigDecimal $cost): array
    {
        $newQuantity = $oldQuantity->plus($quantity);
        $resolved = $oldQuantity->isNegative() ? ($quantity->isLessThan($oldQuantity->abs()) ? $quantity : $oldQuantity->abs()) : BigDecimal::zero();
        $adjustment = $oldAverage === null ? BigDecimal::zero() : $cost->minus($oldAverage)->multipliedBy($resolved);
        if ($oldQuantity->isPositive() && ($oldAverage === null || $oldValue === null)) {
            return ['quantity' => $newQuantity, 'value' => null, 'average' => null, 'state' => InventoryCostState::Provisional, 'resolved' => $resolved, 'adjustment' => $adjustment];
        }
        if ($oldQuantity->isPositive()) {
            $value = $oldValue->plus($quantity->multipliedBy($cost));
            $average = $value->dividedBy($newQuantity, Decimal::CalculatorScale, RoundingMode::HalfUp);

            return ['quantity' => $newQuantity, 'value' => $value, 'average' => $average, 'state' => InventoryCostState::Final, 'resolved' => $resolved, 'adjustment' => $adjustment];
        }
        if ($oldQuantity->isZero()) {
            return ['quantity' => $newQuantity, 'value' => $quantity->multipliedBy($cost), 'average' => $cost, 'state' => InventoryCostState::Final, 'resolved' => $resolved, 'adjustment' => $adjustment];
        }
        if ($newQuantity->isPositive()) {
            return ['quantity' => $newQuantity, 'value' => $newQuantity->multipliedBy($cost), 'average' => $cost, 'state' => InventoryCostState::Final, 'resolved' => $resolved, 'adjustment' => $adjustment];
        }
        $provisionalAverage = $oldAverage ?? $cost;

        return ['quantity' => $newQuantity, 'value' => $newQuantity->multipliedBy($provisionalAverage), 'average' => $provisionalAverage, 'state' => InventoryCostState::Provisional, 'resolved' => $resolved, 'adjustment' => $adjustment];
    }

    private function lockedBalance(Store $store, ProductVariant $variant): InventoryCostBalance
    {
        $query = fn () => InventoryCostBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->where('product_variant_id', $variant->id);
        $balance = $query()->lockForUpdate()->first();
        if (! $balance instanceof InventoryCostBalance) {
            $existingQuantity = StockBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->where('product_variant_id', $variant->id)->sum('quantity_base');
            try {
                InventoryCostBalance::query()->create(['merchant_id' => $store->merchant_id, 'store_id' => $store->id, 'product_variant_id' => $variant->id, 'quantity_base' => Decimal::fixed(BigDecimal::of((string) $existingQuantity), 6), 'cost_state' => InventoryCostState::Pending]);
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'cost_balances_dimension_unique')) {
                    throw $exception;
                }
            }
            $balance = $query()->lockForUpdate()->firstOrFail();
        }

        return $balance;
    }

    private function existing(Store $store, string $sourceKey, string $payloadHash): ?InventoryCostEntry
    {
        $existing = InventoryCostEntry::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->where('source_key', $sourceKey)->lockForUpdate()->first();
        if ($existing instanceof InventoryCostEntry && ! hash_equals($existing->payload_hash, $payloadHash)) {
            throw new ConflictHttpException('The valuation source was already used with different data.');
        }

        return $existing;
    }

    private function valuationTiming(InventoryCostBalance $balance, string $effectiveAt): string
    {
        $lastAppliedAt = $balance->lastEntry?->applied_at;

        return $lastAppliedAt !== null && $lastAppliedAt->isAfter($effectiveAt) ? 'backdated_current_sequence' : 'current_sequence';
    }

    private function sourceKey(string $type, string $reference, string $line): string
    {
        return hash('sha256', implode("\x1f", [$type, $reference, $line]));
    }

    private function payloadHash(Store $store, ProductVariant $variant, InventoryCostEntryKind $kind, BigDecimal $quantity, ?BigDecimal $cost, string $effectiveAt): string
    {
        return hash('sha256', json_encode(['store' => $store->public_id, 'variant' => $variant->public_id, 'kind' => $kind->value, 'quantity' => Decimal::fixed($quantity, 6), 'cost' => $cost === null ? null : Decimal::fixed($cost, 6), 'effective_at' => $effectiveAt], JSON_THROW_ON_ERROR));
    }

    private function fixedOrNull(?BigDecimal $value): ?string
    {
        return $value === null ? null : Decimal::fixed($value, 6);
    }
}
