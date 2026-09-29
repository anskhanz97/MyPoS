<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $public_id @property int $merchant_id @property int|null $store_id @property string $audience_role @property string $category @property string $severity @property string $title @property string $message */
#[Fillable(['merchant_id', 'store_id', 'audience_role', 'category', 'severity', 'title', 'message', 'source_type', 'source_reference', 'deduplication_key', 'resolved_at'])] class InAppAlert extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<InAppAlertRead,$this> */
    public function reads(): HasMany
    {
        return $this->hasMany(InAppAlertRead::class);
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }
}
