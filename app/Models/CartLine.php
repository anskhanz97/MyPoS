<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $cart_id
 * @property int $product_variant_id
 * @property int $unit_id
 * @property int|null $batch_selection_snapshot_id
 * @property int $sequence
 * @property string $product_name_snapshot
 * @property string|null $variant_name_snapshot
 * @property string $sku_snapshot
 * @property string $unit_symbol_snapshot
 * @property string $quantity_uom
 * @property string $factor_to_base
 * @property string $quantity_base
 * @property string $manual_discount
 * @property string|null $scan_code
 * @property int $scan_count
 * @property string $configuration_hash
 */
#[Fillable(['merchant_id', 'store_id', 'cart_id', 'product_variant_id', 'unit_id', 'batch_selection_snapshot_id', 'sequence', 'product_name_snapshot', 'variant_name_snapshot', 'sku_snapshot', 'unit_symbol_snapshot', 'quantity_uom', 'factor_to_base', 'quantity_base', 'manual_discount', 'scan_code', 'scan_count', 'configuration_hash'])]
class CartLine extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /** @return BelongsTo<BatchSelectionSnapshot, $this> */
    public function batchSelection(): BelongsTo
    {
        return $this->belongsTo(BatchSelectionSnapshot::class, 'batch_selection_snapshot_id');
    }

    /** @return HasMany<CartLineExtra, $this> */
    public function extras(): HasMany
    {
        return $this->hasMany(CartLineExtra::class)->orderBy('sequence');
    }

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'quantity_uom' => 'decimal:6', 'factor_to_base' => 'decimal:6', 'quantity_base' => 'decimal:6', 'manual_discount' => 'decimal:2', 'scan_count' => 'integer'];
    }
}
