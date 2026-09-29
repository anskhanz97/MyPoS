<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $merchant_id
 * @property int $store_id
 * @property int $batch_selection_snapshot_id
 * @property int $product_batch_id
 * @property string $quantity_base
 * @property CarbonImmutable $expiry_date_snapshot
 * @property int $sequence
 */
#[Fillable(['merchant_id', 'store_id', 'batch_selection_snapshot_id', 'product_batch_id', 'quantity_base', 'expiry_date_snapshot', 'sequence'])]

class BatchSelectionLine extends Model
{
    /** @return BelongsTo<BatchSelectionSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(BatchSelectionSnapshot::class, 'batch_selection_snapshot_id');
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    protected function casts(): array
    {
        return ['quantity_base' => 'decimal:6', 'expiry_date_snapshot' => 'immutable_date', 'sequence' => 'integer'];
    }
}
