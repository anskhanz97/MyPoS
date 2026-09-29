<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $type
 * @property string $public_id
 * @property string $client_tender_id
 * @property string $state
 * @property string $amount
 * @property string|null $external_reference
 * @property string $confirmation_source
 */
#[Fillable(['merchant_id', 'store_id', 'sale_id', 'client_tender_id', 'type', 'state', 'amount', 'external_reference', 'confirmation_source', 'confirmed_by_user_id', 'confirmed_at'])]
class SaleTender extends Model
{
    use HasBinaryPublicId;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_at' => 'immutable_datetime'];
    }
}
