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
 * @property string $client_tender_id
 * @property string $type
 * @property string $state
 * @property string $amount
 * @property string|null $external_reference
 * @property string $confirmation_source
 * @property-read SaleReturn $saleReturn
 */
#[Fillable(['merchant_id', 'store_id', 'sale_return_id', 'client_tender_id', 'type', 'state', 'amount', 'external_reference', 'confirmation_source', 'confirmed_by_user_id', 'confirmed_at'])]
class SaleReturnTender extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<SaleReturn, $this> */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_at' => 'immutable_datetime'];
    }
}
