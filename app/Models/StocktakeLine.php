<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\StocktakeLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $stocktake_id
 * @property int $product_variant_id
 * @property int|null $product_batch_id
 * @property string $baseline_quantity_base
 * @property string|null $counted_quantity_base
 * @property int|null $count_cutoff_movement_id
 * @property string|null $expected_at_count_quantity_base
 * @property string|null $discrepancy_quantity_base
 * @property int|null $posted_stock_movement_id
 * @property-read ProductVariant $variant
 * @property-read ProductBatch|null $batch
 */
#[Fillable(['merchant_id', 'store_id', 'stocktake_id', 'product_variant_id', 'product_batch_id', 'baseline_quantity_base', 'counted_quantity_base', 'count_cutoff_movement_id', 'expected_at_count_quantity_base', 'discrepancy_quantity_base', 'posted_stock_movement_id', 'counted_at'])]

class StocktakeLine extends Model
{
    /** @use HasFactory<StocktakeLineFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Stocktake, $this> */
    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
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

    protected function casts(): array
    {
        return ['baseline_quantity_base' => 'decimal:6', 'counted_quantity_base' => 'decimal:6', 'expected_at_count_quantity_base' => 'decimal:6', 'discrepancy_quantity_base' => 'decimal:6', 'count_cutoff_movement_id' => 'integer', 'counted_at' => 'immutable_datetime'];
    }
}
