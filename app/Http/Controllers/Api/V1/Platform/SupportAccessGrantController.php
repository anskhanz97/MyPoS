<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportAccessGrantRequest;
use App\Http\Resources\SupportAccessGrantResource;
use App\Models\Merchant;
use App\Models\PlatformAuditEvent;
use App\Models\PlatformOperator;
use App\Models\SupportAccessGrant;
use Illuminate\Http\JsonResponse;

class SupportAccessGrantController extends Controller
{
    public function store(StoreSupportAccessGrantRequest $request): JsonResponse
    {
        $operator = $request->user()?->platformOperator;
        abort_unless($operator instanceof PlatformOperator && $operator->is_active, 403);
        $merchant = (new Merchant)->resolveRouteBinding($request->string('merchant_id')->toString());
        abort_unless($merchant instanceof Merchant, 404);
        $grant = SupportAccessGrant::query()->create([
            'merchant_id' => $merchant->id, 'platform_operator_id' => $operator->id,
            'scope' => $request->string('scope')->toString(), 'purpose' => $request->string('purpose')->toString(),
            'expires_at' => $request->date('expires_at'),
        ]);
        PlatformAuditEvent::query()->create(['platform_operator_id' => $operator->id, 'merchant_id' => $merchant->id, 'event_code' => 'support.access_granted', 'outcome' => 'succeeded', 'aggregate_type' => 'support_access_grant', 'aggregate_public_id' => $grant->public_id, 'reason' => $grant->purpose, 'metadata' => ['scope' => $grant->scope, 'expires_at' => $grant->expires_at->toAtomString()], 'occurred_at' => now()]);

        return (new SupportAccessGrantResource($grant->load(['merchant', 'operator'])))->response()->setStatusCode(201);
    }
}
