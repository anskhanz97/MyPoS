<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $sale_return_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $sale_line_id
 * @property int $sequence
 * @property string $disposition
 * @property string $quantity_uom
 * @property string $quantity_base
 * @property string $cumulative_quantity_uom
 * @property string $pre_discount
 * @property string $discount
 * @property string $net
 * @property string $tax
 * @property string $gross
 * @property array<int,array<string,mixed>>|null $original_allocations
 * @property-read SaleReturn $saleReturn
 * @property-read SaleLine $saleLine
 */
#[Fillable(['merchant_id', 'store_id', 'sale_return_id', 'sale_line_id', 'sequence', 'disposition', 'quantity_uom', 'quantity_base', 'cumulative_quantity_uom', 'pre_discount', 'discount', 'net', 'tax', 'gross', 'original_allocations'])]
class SaleReturnLine extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<SaleReturn, $this> */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /** @return BelongsTo<SaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'quantity_uom' => 'decimal:6', 'quantity_base' => 'decimal:6', 'cumulative_quantity_uom' => 'decimal:6', 'pre_discount' => 'decimal:2', 'discount' => 'decimal:2', 'net' => 'decimal:2', 'tax' => 'decimal:2', 'gross' => 'decimal:2', 'original_allocations' => 'array'];
    }
}
