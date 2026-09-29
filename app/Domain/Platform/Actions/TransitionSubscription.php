<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\MerchantSubscription;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SubscriptionLifecycleEvent;
use Illuminate\Support\Facades\DB;

final class TransitionSubscription
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'trial' => ['active', 'grace', 'restricted', 'cancelled'],
        'active' => ['grace', 'restricted', 'cancelled'],
        'grace' => ['active', 'restricted', 'cancelled'],
        'restricted' => ['active', 'cancelled'],
        'cancelled' => [],
    ];

    public function handle(PlatformOperator $operator, MerchantSubscription $subscription, SubscriptionStatus $to, string $reason): MerchantSubscription
    {
        return DB::transaction(function () use ($operator, $subscription, $to, $reason): MerchantSubscription {
            $locked = MerchantSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            abort_unless(in_array($to->value, self::ALLOWED[$locked->status->value], true), 409, 'The requested subscription transition is not allowed.');
            $from = $locked->status;
            $changes = ['status' => $to, 'version' => $locked->version + 1];
            if ($to === SubscriptionStatus::Grace) {
                $changes['grace_ends_at'] = now()->addDays($locked->plan->grace_days);
            }
            if ($to === SubscriptionStatus::Restricted) {
                $changes['restricted_at'] = now();
                $locked->merchant()->update(['status' => 'restricted']);
            }
            if ($to === SubscriptionStatus::Active) {
                $changes['grace_ends_at'] = null;
                $changes['restricted_at'] = null;
                $locked->merchant()->update(['status' => 'active']);
            }
            if ($to === SubscriptionStatus::Cancelled) {
                $changes['cancelled_at'] = now();
            }
            $locked->update($changes);
            SubscriptionLifecycleEvent::query()->create([
                'merchant_id' => $locked->merchant_id, 'merchant_subscription_id' => $locked->id,
                'actor_platform_operator_id' => $operator->id, 'from_status' => $from, 'to_status' => $to,
                'reason_code' => 'manual_transition', 'reason' => $reason, 'occurred_at' => now(),
            ]);
            PlatformAuditEvent::query()->create([
                'platform_operator_id' => $operator->id, 'merchant_id' => $locked->merchant_id,
                'event_code' => 'subscription.state_changed', 'outcome' => 'succeeded',
                'aggregate_type' => 'merchant_subscription', 'aggregate_public_id' => $locked->public_id,
                'reason' => $reason, 'metadata' => ['from' => $from->value, 'to' => $to->value], 'occurred_at' => now(),
            ]);

            return $locked->refresh()->load(['merchant', 'plan.entitlements']);
        });
    }
}
