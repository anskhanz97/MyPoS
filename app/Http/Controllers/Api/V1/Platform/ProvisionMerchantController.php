<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Tenancy\Actions\CreateMerchantOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionMerchantRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\MerchantSubscription;
use App\Models\Plan;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SubscriptionLifecycleEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ProvisionMerchantController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ProvisionMerchantRequest $request, CreateMerchantOnboarding $create): JsonResponse
    {
        $operator = $request->user()?->platformOperator;
        abort_unless($operator instanceof PlatformOperator && $operator->is_active, 403);
        $plan = (new Plan)->resolveRouteBinding($request->string('plan_id')->toString());
        abort_unless($plan instanceof Plan && $plan->is_active, 404);
        $email = $request->string('owner_email')->toString();
        $owner = User::query()->where('email', $email)->first();
        $createdOwner = $owner === null;
        $owner ??= User::query()->create(['name' => $request->string('owner_name')->toString(), 'email' => $email, 'password' => Str::random(64), 'is_active' => true]);
        $starts = CarbonImmutable::parse($request->string('starts_at')->toString());
        $subscription = DB::transaction(function () use ($request, $create, $owner, $plan, $operator, $starts): MerchantSubscription {
            $merchant = $create->handle($owner, ['name' => $request->string('name')->toString(), 'slug' => $request->string('slug')->toString(), 'store_data_mode' => $request->string('store_data_mode')->toString(), 'timezone' => $request->string('timezone')->toString(), 'store_name' => $request->string('store_name')->toString(), 'store_code' => $request->string('store_code')->toString()]);
            $status = SubscriptionStatus::from($request->string('subscription_status')->toString());
            $subscription = MerchantSubscription::query()->create(['merchant_id' => $merchant->id, 'plan_id' => $plan->id, 'status' => $status, 'starts_at' => $starts, 'trial_ends_at' => $request->filled('trial_ends_at') ? $request->date('trial_ends_at') : null, 'current_period_ends_at' => $starts->addDays($plan->billing_period_days)]);
            SubscriptionLifecycleEvent::query()->create(['merchant_id' => $merchant->id, 'merchant_subscription_id' => $subscription->id, 'actor_platform_operator_id' => $operator->id, 'to_status' => $status, 'reason_code' => 'merchant_provisioned', 'occurred_at' => now()]);
            PlatformAuditEvent::query()->create(['platform_operator_id' => $operator->id, 'merchant_id' => $merchant->id, 'event_code' => 'merchant.provisioned', 'outcome' => 'succeeded', 'aggregate_type' => 'merchant_subscription', 'aggregate_public_id' => $subscription->public_id, 'metadata' => ['owner_user_id' => $owner->id], 'occurred_at' => now()]);

            return $subscription;
        });
        if ($createdOwner) {
            Password::sendResetLink(['email' => $email]);
        }

        return (new SubscriptionResource($subscription->load(['merchant', 'plan.entitlements'])))->response()->setStatusCode(201);
    }
}
