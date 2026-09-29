<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $sale_return_line_id
 * @property int $original_stock_movement_id
 * @property int $return_stock_movement_id
 * @property string $quantity_base
 */
#[Fillable(['merchant_id', 'store_id', 'sale_return_line_id', 'original_stock_movement_id', 'return_stock_movement_id', 'quantity_base'])]
class SaleReturnStockEffect extends Model
{
    protected function casts(): array
    {
        return ['quantity_base' => 'decimal:6'];
    }
}
