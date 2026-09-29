<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property int $product_id
 * @property int $base_unit_id
 * @property string $sku
 * @property string $name
 * @property array<string, string>|null $attributes
 * @property bool $is_weighted
 * @property bool $is_default
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['merchant_id', 'store_id', 'product_id', 'base_unit_id', 'sku', 'name', 'attributes', 'is_weighted', 'is_default', 'archived_at'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    /** @return HasMany<ProductBarcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /** @return HasMany<UnitConversion, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class);
    }

    /** @return HasMany<BundleComponent, $this> */
    public function bundleComponents(): HasMany
    {
        return $this->hasMany(BundleComponent::class, 'bundle_variant_id');
    }

    /** @return HasMany<ProductBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_variant_id');
    }

    protected function casts(): array
    {
        return ['attributes' => 'array', 'is_weighted' => 'boolean', 'is_default' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }
}
