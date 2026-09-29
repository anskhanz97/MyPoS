<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property string|null $reference
 * @property string $request_hash
 * @property string $amount
 * @property string $currency
 * @property CarbonImmutable $paid_on
 * @property CarbonImmutable $period_ends_before
 * @property CarbonImmutable $period_ends_after
 * @property-read MerchantSubscription $subscription
 */
#[Fillable(['merchant_id', 'merchant_subscription_id', 'recorded_by_platform_operator_id', 'idempotency_key', 'request_hash', 'reference', 'amount', 'currency', 'paid_on', 'period_ends_before', 'period_ends_after', 'note'])]
class SubscriptionPayment extends Model
{
    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<MerchantSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MerchantSubscription::class, 'merchant_subscription_id');
    }

    protected function casts(): array
    {
        return ['idempotency_key' => BinaryUuid::class, 'amount' => 'decimal:2', 'paid_on' => 'immutable_date', 'period_ends_before' => 'immutable_datetime', 'period_ends_after' => 'immutable_datetime'];
    }
}
