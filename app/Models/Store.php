<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $id
 * @property int $merchant_id
 * @property string $name
 * @property string $code
 * @property string $timezone
 * @property bool $is_active
 * @property-read Merchant $merchant
 */
#[Fillable(['merchant_id', 'name', 'code', 'timezone', 'is_active'])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return HasMany<StoreAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(StoreAssignment::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
