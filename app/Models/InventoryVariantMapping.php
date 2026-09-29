<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\InventoryVariantMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $source_store_id
 * @property int $destination_store_id
 * @property int $source_product_variant_id
 * @property int $destination_product_variant_id
 * @property string $factor_to_destination_base
 * @property bool $is_active
 */
#[Fillable(['merchant_id', 'source_store_id', 'destination_store_id', 'source_product_variant_id', 'destination_product_variant_id', 'factor_to_destination_base', 'is_active'])]

class InventoryVariantMapping extends Model
{
    /** @use HasFactory<InventoryVariantMappingFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<ProductVariant, $this> */
    public function sourceVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'source_product_variant_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function destinationVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'destination_product_variant_id');
    }

    protected function casts(): array
    {
        return ['factor_to_destination_base' => 'decimal:6', 'is_active' => 'boolean'];
    }
}
