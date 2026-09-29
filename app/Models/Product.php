<?php

namespace App\Models;

use App\Domain\Catalog\Enums\ProductType;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property ProductType $type
 * @property string $name
 * @property string|null $description
 * @property string|null $tax_classification_reference
 * @property bool $batch_tracking_enabled
 * @property string|null $batch_conversion_key
 * @property string|null $batch_conversion_hash
 * @property int $structure_version
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['merchant_id', 'store_id', 'type', 'name', 'description', 'tax_classification_reference', 'batch_tracking_enabled', 'batch_conversion_key', 'batch_conversion_hash', 'structure_version', 'archived_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ExtraGroup, $this> */
    public function extraGroups(): HasMany
    {
        return $this->hasMany(ExtraGroup::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /** @return HasMany<ProductStoreAvailability, $this> */
    public function storeAvailabilities(): HasMany
    {
        return $this->hasMany(ProductStoreAvailability::class);
    }

    protected function casts(): array
    {
        return ['type' => ProductType::class, 'batch_tracking_enabled' => 'boolean', 'structure_version' => 'integer', 'archived_at' => 'immutable_datetime'];
    }
}
