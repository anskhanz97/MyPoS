<?php

namespace App\Models;

use Database\Factories\VariantPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $merchant_id
 * @property int $price_book_version_id
 * @property int $product_variant_id
 * @property string|null $customer_group
 * @property string $unit_price
 * @property string $tax_rate_percent
 * @property bool $tax_inclusive
 */
#[Fillable(['merchant_id', 'price_book_version_id', 'product_variant_id', 'customer_group', 'unit_price', 'tax_rate_percent', 'tax_inclusive'])]
class VariantPrice extends Model
{
    /** @use HasFactory<VariantPriceFactory> */
    use HasFactory;

    /** @return BelongsTo<PriceBookVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceBookVersion::class, 'price_book_version_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:4', 'tax_rate_percent' => 'decimal:6', 'tax_inclusive' => 'boolean'];
    }
}
