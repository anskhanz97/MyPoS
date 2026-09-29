<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $extra_option_id
 * @property string $group_name_snapshot
 * @property string $option_name_snapshot
 * @property string $unit_price_delta
 * @property int $sequence
 */
#[Fillable(['merchant_id', 'store_id', 'cart_line_id', 'extra_option_id', 'group_name_snapshot', 'option_name_snapshot', 'unit_price_delta', 'sequence'])]
class CartLineExtra extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<CartLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(CartLine::class, 'cart_line_id');
    }

    /** @return BelongsTo<ExtraOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ExtraOption::class, 'extra_option_id');
    }

    protected function casts(): array
    {
        return ['unit_price_delta' => 'decimal:2', 'sequence' => 'integer'];
    }
}
