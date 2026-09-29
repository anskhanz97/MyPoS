<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $sale_return_id
 * @property int $new_sale_id
 * @property string $number
 * @property string $payload_hash
 * @property string $difference_amount
 * @property string $difference_direction
 * @property CarbonImmutable $posted_at
 * @property-read SaleReturn $saleReturn
 * @property-read Sale $newSale
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'sale_return_id', 'new_sale_id', 'created_by_user_id', 'number', 'idempotency_key', 'payload_hash', 'difference_amount', 'difference_direction', 'posted_at'])]
class SaleExchange extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<SaleReturn, $this> */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function newSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'new_sale_id');
    }

    protected function casts(): array
    {
        return ['difference_amount' => 'decimal:2', 'posted_at' => 'immutable_datetime'];
    }
}
