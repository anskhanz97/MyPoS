<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $option_name_snapshot
 * @property string $unit_price_delta
 */
#[Fillable(['merchant_id', 'store_id', 'sale_line_id', 'extra_option_id', 'group_name_snapshot', 'option_name_snapshot', 'unit_price_delta', 'stock_variant_id', 'stock_quantity_base', 'sequence'])]
class SaleLineExtra extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['unit_price_delta' => 'decimal:2', 'stock_quantity_base' => 'decimal:6', 'sequence' => 'integer'];
    }
}
