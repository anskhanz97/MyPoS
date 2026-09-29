<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $id
 * @property int $sale_id
 * @property string $product_name_snapshot
 * @property string $sku_snapshot
 * @property string $unit_symbol_snapshot
 * @property string $quantity_uom
 * @property string $factor_to_base
 * @property string $quantity_base
 * @property string $unit_price
 * @property string $pre_discount
 * @property string $discount
 * @property string $net
 * @property string $tax
 * @property string $gross
 * @property array<int, array<string, mixed>>|null $allocations
 * @property-read Collection<int, SaleLineExtra> $extras
 */
#[Fillable(['merchant_id', 'store_id', 'sale_id', 'product_variant_id', 'unit_id', 'batch_selection_snapshot_id', 'sequence', 'product_name_snapshot', 'variant_name_snapshot', 'sku_snapshot', 'unit_symbol_snapshot', 'quantity_uom', 'factor_to_base', 'quantity_base', 'unit_price', 'pre_discount', 'discount', 'net', 'tax', 'gross', 'allocations'])]
class SaleLine extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return HasMany<SaleLineExtra, $this> */
    public function extras(): HasMany
    {
        return $this->hasMany(SaleLineExtra::class)->orderBy('sequence');
    }

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'quantity_uom' => 'decimal:6', 'factor_to_base' => 'decimal:6', 'quantity_base' => 'decimal:6', 'unit_price' => 'decimal:4', 'pre_discount' => 'decimal:2', 'discount' => 'decimal:2', 'net' => 'decimal:2', 'tax' => 'decimal:2', 'gross' => 'decimal:2', 'allocations' => 'array'];
    }
}
