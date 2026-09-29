<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\LoyaltyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property string $name
 * @property string $canonical_phone
 * @property-read LoyaltyPointBalance|null $pointBalance
 * @property-read Collection<int,LoyaltyPointEntry> $pointEntries
 */
#[Fillable(['merchant_id', 'name', 'canonical_phone'])]
class LoyaltyMember extends Model
{
    /** @use HasFactory<LoyaltyMemberFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return HasMany<LoyaltyCustomerLink, $this> */
    public function customerLinks(): HasMany
    {
        return $this->hasMany(LoyaltyCustomerLink::class);
    }

    /** @return HasOne<LoyaltyPointBalance,$this> */
    public function pointBalance(): HasOne
    {
        return $this->hasOne(LoyaltyPointBalance::class);
    }

    /** @return HasMany<LoyaltyPointEntry,$this> */
    public function pointEntries(): HasMany
    {
        return $this->hasMany(LoyaltyPointEntry::class);
    }
}
