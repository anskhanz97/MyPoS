<?php

namespace App\Models;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\SubscriptionLifecycleEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'merchant_subscription_id', 'actor_platform_operator_id', 'from_status', 'to_status', 'reason_code', 'reason', 'metadata', 'occurred_at'])]
class SubscriptionLifecycleEvent extends Model
{
    /** @use HasFactory<SubscriptionLifecycleEventFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['from_status' => SubscriptionStatus::class, 'to_status' => SubscriptionStatus::class, 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
