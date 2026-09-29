<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\ExtraOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $name
 * @property string $price_delta
 * @property int|null $stock_variant_id
 * @property string|null $stock_quantity_base
 * @property bool $is_active
 * @property-read ExtraGroup $group
 */
#[Fillable(['merchant_id', 'extra_group_id', 'name', 'price_delta', 'stock_variant_id', 'stock_quantity_base', 'sort_order', 'is_active'])]
class ExtraOption extends Model
{
    /** @use HasFactory<ExtraOptionFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<ExtraGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ExtraGroup::class, 'extra_group_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function stockVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'stock_variant_id');
    }

    protected function casts(): array
    {
        return ['price_delta' => 'decimal:2', 'stock_quantity_base' => 'decimal:6', 'is_active' => 'boolean'];
    }
}
