<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\DeviceEnrollment;
use App\Models\Merchant;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SecurityAuditEvent;
use App\Models\SupportAccessGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportDiagnosticsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Merchant $merchant): JsonResponse
    {
        $operator = $request->user()?->platformOperator;
        abort_unless($operator instanceof PlatformOperator && $operator->is_active, 403);
        $grant = SupportAccessGrant::query()->whereBelongsTo($merchant)->whereBelongsTo($operator, 'operator')
            ->where('scope', 'diagnostics')->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->first();
        abort_unless($grant instanceof SupportAccessGrant, 403, 'An active diagnostics support grant is required.');
        $subscription = $merchant->subscription()->first();
        PlatformAuditEvent::query()->create(['platform_operator_id' => $operator->id, 'merchant_id' => $merchant->id, 'event_code' => 'support.diagnostics_viewed', 'outcome' => 'succeeded', 'aggregate_type' => 'support_access_grant', 'aggregate_public_id' => $grant->public_id, 'reason' => $grant->purpose, 'occurred_at' => now()]);

        return response()->json(['data' => [
            'merchant' => ['id' => $merchant->public_id, 'name' => $merchant->name, 'status' => $merchant->status->value],
            'subscription' => $subscription === null ? null : ['status' => $subscription->status->value, 'current_period_ends_at' => $subscription->current_period_ends_at->toAtomString()],
            'active_devices' => DeviceEnrollment::query()->where('merchant_id', $merchant->id)->where('status', 'active')->count(),
            'failed_security_events_24h' => SecurityAuditEvent::query()->where('merchant_id', $merchant->id)->where('outcome', 'failed')->where('occurred_at', '>=', now()->subDay())->count(),
            'grant_expires_at' => $grant->expires_at->toAtomString(),
        ]]);
    }
}
