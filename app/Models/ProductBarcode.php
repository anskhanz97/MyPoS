<?php

namespace App\Models;

use App\Domain\Catalog\Enums\BarcodeKind;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\ProductBarcodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $store_id
 * @property int $product_variant_id
 * @property string $code
 * @property BarcodeKind $kind
 * @property bool $is_primary
 */
#[Fillable(['merchant_id', 'store_id', 'product_variant_id', 'code', 'kind', 'is_primary'])]
class ProductBarcode extends Model
{
    /** @use HasFactory<ProductBarcodeFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function casts(): array
    {
        return ['kind' => BarcodeKind::class, 'is_primary' => 'boolean'];
    }
}
