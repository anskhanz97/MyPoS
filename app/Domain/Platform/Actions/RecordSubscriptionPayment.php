<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\MerchantSubscription;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SubscriptionLifecycleEvent;
use App\Models\SubscriptionPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class RecordSubscriptionPayment
{
    /** @param array{idempotency_key: string, reference?: string|null, amount: string, paid_on: string, periods: int, note?: string|null} $data */
    public function handle(PlatformOperator $operator, MerchantSubscription $subscription, array $data): SubscriptionPayment
    {
        return DB::transaction(function () use ($operator, $subscription, $data): SubscriptionPayment {
            $locked = MerchantSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $key = Uuid::fromString($data['idempotency_key'])->getBytes();
            $requestHash = hash('sha256', json_encode([
                'reference' => $data['reference'] ?? null, 'amount' => $data['amount'], 'paid_on' => $data['paid_on'],
                'periods' => $data['periods'], 'note' => $data['note'] ?? null,
            ], JSON_THROW_ON_ERROR));
            $existing = SubscriptionPayment::query()->where('merchant_id', $locked->merchant_id)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                abort_unless(hash_equals($existing->request_hash, $requestHash), 409, 'The idempotency key was already used with different payment details.');

                return $existing;
            }
            if (($data['reference'] ?? null) !== null) {
                abort_if(SubscriptionPayment::query()->where('merchant_id', $locked->merchant_id)->where('reference', $data['reference'])->exists(), 409, 'The payment reference was already recorded.');
            }

            $before = $locked->current_period_ends_at;
            $anchor = $before->isFuture() ? $before : CarbonImmutable::now();
            $after = $anchor->addDays($locked->plan->billing_period_days * $data['periods']);
            $payment = SubscriptionPayment::query()->create([
                'merchant_id' => $locked->merchant_id,
                'merchant_subscription_id' => $locked->id,
                'recorded_by_platform_operator_id' => $operator->id,
                'idempotency_key' => $data['idempotency_key'],
                'request_hash' => $requestHash,
                'reference' => $data['reference'] ?? null,
                'amount' => $data['amount'],
                'currency' => 'PKR',
                'paid_on' => $data['paid_on'],
                'period_ends_before' => $before,
                'period_ends_after' => $after,
                'note' => $data['note'] ?? null,
            ]);
            $from = $locked->status;
            $locked->update([
                'status' => SubscriptionStatus::Active,
                'current_period_ends_at' => $after,
                'grace_ends_at' => null,
                'restricted_at' => null,
                'cancelled_at' => null,
                'version' => $locked->version + 1,
            ]);
            SubscriptionLifecycleEvent::query()->create([
                'merchant_id' => $locked->merchant_id,
                'merchant_subscription_id' => $locked->id,
                'actor_platform_operator_id' => $operator->id,
                'from_status' => $from,
                'to_status' => SubscriptionStatus::Active,
                'reason_code' => 'manual_payment',
                'metadata' => ['payment_id' => $payment->public_id],
                'occurred_at' => now(),
            ]);
            PlatformAuditEvent::query()->create([
                'platform_operator_id' => $operator->id,
                'merchant_id' => $locked->merchant_id,
                'event_code' => 'subscription.payment_recorded',
                'outcome' => 'succeeded',
                'aggregate_type' => 'subscription_payment',
                'aggregate_public_id' => $payment->public_id,
                'metadata' => ['periods' => $data['periods']],
                'occurred_at' => now(),
            ]);

            return $payment->load(['subscription.plan']);
        });
    }
}
