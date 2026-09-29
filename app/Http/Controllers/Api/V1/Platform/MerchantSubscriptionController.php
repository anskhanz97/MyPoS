<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domain\Platform\Actions\TransitionSubscription;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignSubscriptionRequest;
use App\Http\Requests\TransitionSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\Plan;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SubscriptionLifecycleEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantSubscriptionController extends Controller
{
    public function store(AssignSubscriptionRequest $request, Merchant $merchant): JsonResponse
    {
        abort_if($merchant->subscription()->exists(), 409, 'The merchant already has a subscription.');
        $plan = (new Plan)->resolveRouteBinding($request->string('plan_id')->toString());
        abort_unless($plan instanceof Plan && $plan->is_active, 404);
        $operator = $this->operator($request);
        $starts = CarbonImmutable::parse($request->string('starts_at')->toString());
        $subscription = DB::transaction(function () use ($merchant, $plan, $operator, $starts, $request): MerchantSubscription {
            $status = SubscriptionStatus::from($request->string('status')->toString());
            $subscription = MerchantSubscription::query()->create([
                'merchant_id' => $merchant->id, 'plan_id' => $plan->id, 'status' => $status,
                'starts_at' => $starts, 'trial_ends_at' => $request->filled('trial_ends_at') ? $request->date('trial_ends_at') : null,
                'current_period_ends_at' => $starts->addDays($plan->billing_period_days),
            ]);
            SubscriptionLifecycleEvent::query()->create(['merchant_id' => $merchant->id, 'merchant_subscription_id' => $subscription->id, 'actor_platform_operator_id' => $operator->id, 'to_status' => $status, 'reason_code' => 'assigned', 'occurred_at' => now()]);
            PlatformAuditEvent::query()->create(['platform_operator_id' => $operator->id, 'merchant_id' => $merchant->id, 'event_code' => 'subscription.assigned', 'outcome' => 'succeeded', 'aggregate_type' => 'merchant_subscription', 'aggregate_public_id' => $subscription->public_id, 'occurred_at' => now()]);

            return $subscription;
        });

        return (new SubscriptionResource($subscription->load(['merchant', 'plan.entitlements'])))->response()->setStatusCode(201);
    }

    public function update(TransitionSubscriptionRequest $request, MerchantSubscription $subscription, TransitionSubscription $transition): SubscriptionResource
    {
        return new SubscriptionResource($transition->handle($this->operator($request), $subscription, SubscriptionStatus::from($request->string('status')->toString()), $request->string('reason')->toString()));
    }

    private function operator(Request $request): PlatformOperator
    {
        $operator = $request->user()?->platformOperator;
        abort_unless($operator instanceof PlatformOperator && $operator->is_active, 403);

        return $operator;
    }
}
