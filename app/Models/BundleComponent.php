<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\BundleComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $merchant_id
 * @property int $bundle_variant_id
 * @property int $component_variant_id
 * @property string $quantity_base
 */
#[Fillable(['merchant_id', 'bundle_variant_id', 'component_variant_id', 'quantity_base'])]
class BundleComponent extends Model
{
    /** @use HasFactory<BundleComponentFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<ProductVariant, $this> */
    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id');
    }

    protected function casts(): array
    {
        return ['quantity_base' => 'decimal:6'];
    }
}
