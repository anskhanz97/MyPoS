<?php

namespace App\Models;

use App\Domain\Tenancy\Enums\MerchantStatus;
use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Domain\Tenancy\Exceptions\DataModeLocked;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property StoreDataMode $store_data_mode
 * @property string $public_id
 * @property string $name
 * @property MerchantStatus $status
 * @property CarbonImmutable|null $store_data_mode_locked_at
 */
#[Fillable(['name', 'slug', 'store_data_mode', 'status', 'timezone', 'currency', 'store_data_mode_locked_at'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasBinaryPublicId, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Merchant $merchant): void {
            if ($merchant->isDirty('store_data_mode') && $merchant->getOriginal('store_data_mode_locked_at') !== null) {
                throw new DataModeLocked;
            }
        });
    }

    /** @return HasMany<Store, $this> */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /** @return HasMany<MerchantMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(MerchantMembership::class);
    }

    /** @return HasOne<MerchantSubscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(MerchantSubscription::class);
    }

    protected function casts(): array
    {
        return [
            'store_data_mode' => StoreDataMode::class,
            'status' => MerchantStatus::class,
            'store_data_mode_locked_at' => 'immutable_datetime',
        ];
    }
}
