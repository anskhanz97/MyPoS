<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property string $slug
 * @property string $name
 * @property bool $is_enabled
 * @property int $version
 * @property-read Collection<int,LoyaltyProgramVersion> $versions
 * @property-read Collection<int,LoyaltyReward> $rewards
 */
#[Fillable(['merchant_id', 'slug', 'name', 'is_enabled', 'version'])]
class LoyaltyProgram extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<LoyaltyProgramVersion,$this> */
    public function versions(): HasMany
    {
        return $this->hasMany(LoyaltyProgramVersion::class);
    }

    /** @return HasMany<LoyaltyReward,$this> */
    public function rewards(): HasMany
    {
        return $this->hasMany(LoyaltyReward::class);
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'version' => 'integer'];
    }
}
