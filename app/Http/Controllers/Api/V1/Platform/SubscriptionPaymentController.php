<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domain\Platform\Actions\RecordSubscriptionPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordSubscriptionPaymentRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\MerchantSubscription;
use App\Models\PlatformOperator;
use Illuminate\Http\JsonResponse;

class SubscriptionPaymentController extends Controller
{
    public function store(RecordSubscriptionPaymentRequest $request, MerchantSubscription $subscription, RecordSubscriptionPayment $record): JsonResponse
    {
        $operator = $request->user()?->platformOperator;
        abort_unless($operator instanceof PlatformOperator && $operator->is_active, 403);
        $payment = $record->handle($operator, $subscription, [
            'idempotency_key' => $request->string('idempotency_key')->toString(), 'reference' => $request->input('reference'),
            'amount' => $request->string('amount')->toString(), 'paid_on' => $request->string('paid_on')->toString(),
            'periods' => $request->integer('periods'), 'note' => $request->input('note'),
        ]);

        return response()->json(['data' => [
            'payment_id' => $payment->public_id, 'reference' => $payment->reference, 'amount' => $payment->amount,
            'currency' => $payment->currency, 'paid_on' => $payment->paid_on->toDateString(),
            'period_ends_after' => $payment->period_ends_after->toAtomString(),
            'subscription' => (new SubscriptionResource($payment->subscription->load(['merchant', 'plan.entitlements'])))->resolve(),
        ]], 201);
    }
}
