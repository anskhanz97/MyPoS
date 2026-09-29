<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\Enums\AdjustmentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryAdjustmentReasonRequest;
use App\Models\InventoryAdjustmentReason;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAdjustmentReasonController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $reasons = InventoryAdjustmentReason::query()->where('merchant_id', $merchant->id)->where('is_active', true)->orderBy('name')->limit(250)->get()->map(fn (InventoryAdjustmentReason $reason): array => $this->serialize($reason));

        return response()->json(['data' => $reasons]);
    }

    public function store(StoreInventoryAdjustmentReasonRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $data = $request->validated();
        $code = mb_strtoupper((string) $data['code']);
        abort_if(InventoryAdjustmentReason::query()->where('merchant_id', $merchant->id)->where('code', $code)->exists(), 422, 'An adjustment reason code already exists for this merchant.');
        $reason = InventoryAdjustmentReason::query()->create([
            'merchant_id' => $merchant->id,
            'code' => $code,
            'name' => trim((string) $data['name']),
            'direction' => AdjustmentDirection::from((string) $data['direction']),
            'requires_approval' => (bool) ($data['requires_approval'] ?? false),
            'is_active' => true,
        ]);
        SecurityAuditEvent::query()->create([
            'merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id,
            'event_code' => 'inventory.adjustment_reason.created', 'outcome' => 'succeeded',
            'metadata' => ['reason_id' => $reason->public_id, 'direction' => $reason->direction->value, 'requires_approval' => $reason->requires_approval], 'occurred_at' => now(),
        ]);

        return response()->json(['data' => $this->serialize($reason)], 201);
    }

    /** @return array{id: string, code: string, name: string, direction: string, requires_approval: bool} */
    private function serialize(InventoryAdjustmentReason $reason): array
    {
        return ['id' => $reason->public_id, 'code' => $reason->code, 'name' => $reason->name, 'direction' => $reason->direction->value, 'requires_approval' => $reason->requires_approval];
    }
}
