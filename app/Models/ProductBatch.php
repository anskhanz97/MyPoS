<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ProductBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $product_variant_id
 * @property int|null $origin_store_id
 * @property string $code
 * @property CarbonImmutable $expiry_date
 * @property CarbonImmutable|null $received_on
 * @property string|null $supplier_reference
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['merchant_id', 'product_variant_id', 'origin_store_id', 'code', 'expiry_date', 'received_on', 'supplier_reference', 'archived_at'])]

class ProductBatch extends Model
{
    /** @use HasFactory<ProductBatchFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function originStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'origin_store_id');
    }

    /** @return HasMany<BatchStockBalance, $this> */
    public function balances(): HasMany
    {
        return $this->hasMany(BatchStockBalance::class);
    }

    protected function casts(): array
    {
        return ['expiry_date' => 'immutable_date', 'received_on' => 'immutable_date', 'archived_at' => 'immutable_datetime'];
    }
}
