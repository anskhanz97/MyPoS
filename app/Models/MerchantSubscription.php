<?php

namespace App\Models;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MerchantSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property SubscriptionStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable $current_period_ends_at
 * @property CarbonImmutable|null $grace_ends_at
 * @property int $version
 * @property-read Merchant $merchant
 * @property-read Plan $plan
 */
#[Fillable(['merchant_id', 'plan_id', 'status', 'starts_at', 'trial_ends_at', 'current_period_ends_at', 'grace_ends_at', 'restricted_at', 'cancelled_at', 'version'])]
class MerchantSubscription extends Model
{
    /** @use HasFactory<MerchantSubscriptionFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SubscriptionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'starts_at' => 'immutable_datetime', 'trial_ends_at' => 'immutable_datetime', 'current_period_ends_at' => 'immutable_datetime', 'grace_ends_at' => 'immutable_datetime', 'restricted_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
