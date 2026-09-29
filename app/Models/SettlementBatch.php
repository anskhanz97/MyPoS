<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $public_id @property int $merchant_id @property int $store_id */
#[Fillable(['merchant_id', 'store_id', 'provider', 'statement_reference', 'period_start', 'period_end', 'provenance', 'state', 'recorded_by_user_id'])] class SettlementBatch extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<SettlementLine,$this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class);
    }

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date'];
    }
}
