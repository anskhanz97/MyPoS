<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\StockBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $inventory_location_id
 * @property int $product_variant_id
 * @property string $quantity_base
 * @property int $movement_count
 * @property int|null $last_stock_movement_id
 * @property int $projection_version
 * @property CarbonImmutable|null $last_reconciled_at
 */
#[Fillable(['merchant_id', 'store_id', 'inventory_location_id', 'product_variant_id', 'quantity_base', 'movement_count', 'last_stock_movement_id', 'projection_version', 'last_reconciled_at'])]
class StockBalance extends Model
{
    /** @use HasFactory<StockBalanceFactory> */
    use HasBinaryPublicId, HasFactory;

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

    /** @return BelongsTo<StockMovement, $this> */
    public function lastMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'last_stock_movement_id');
    }

    protected function casts(): array
    {
        return ['quantity_base' => 'decimal:6', 'movement_count' => 'integer', 'projection_version' => 'integer', 'last_reconciled_at' => 'immutable_datetime'];
    }
}
