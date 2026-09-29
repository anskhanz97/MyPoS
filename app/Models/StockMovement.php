<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $inventory_location_id
 * @property int $product_variant_id
 * @property int|null $product_batch_id
 * @property int|null $inventory_adjustment_reason_id
 * @property int|null $actor_user_id
 * @property int|null $reverses_stock_movement_id
 * @property InventoryMovementKind $movement_kind
 * @property string $quantity_base
 * @property string|null $unit_cost
 * @property InventoryCostState $cost_state
 * @property string $source_type
 * @property string $source_reference
 * @property string $source_line_reference
 * @property string $source_effect
 * @property string $source_key
 * @property string $payload_hash
 * @property string|null $reason_text
 * @property CarbonImmutable $occurred_at
 * @property-read InventoryLocation $location
 * @property-read ProductVariant $variant
 * @property-read ProductBatch|null $batch
 */
#[Fillable(['merchant_id', 'store_id', 'inventory_location_id', 'product_variant_id', 'product_batch_id', 'inventory_adjustment_reason_id', 'actor_user_id', 'reverses_stock_movement_id', 'movement_kind', 'quantity_base', 'unit_cost', 'cost_state', 'source_type', 'source_reference', 'source_line_reference', 'source_effect', 'source_key', 'payload_hash', 'reason_text', 'occurred_at'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasBinaryPublicId, HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Stock movements are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Stock movements cannot be deleted.'));
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    /** @return BelongsTo<InventoryAdjustmentReason, $this> */
    public function adjustmentReason(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentReason::class, 'inventory_adjustment_reason_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_stock_movement_id');
    }

    /** @return HasOne<StockMovement, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_stock_movement_id');
    }

    protected function casts(): array
    {
        return ['movement_kind' => InventoryMovementKind::class, 'quantity_base' => 'decimal:6', 'unit_cost' => 'decimal:6', 'cost_state' => InventoryCostState::class, 'occurred_at' => 'immutable_datetime'];
    }
}
