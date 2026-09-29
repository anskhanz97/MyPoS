<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\BatchBalanceRebuilder;
use App\Domain\Inventory\InventoryBalanceRebuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\RebuildInventoryBalancesRequest;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryReconciliationController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, InventoryBalanceRebuilder $rebuilder, BatchBalanceRebuilder $batchRebuilder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        abort_unless($store->merchant_id === $merchant->id, 404);
        $discrepancies = $rebuilder->inspect($store);
        $batchDiscrepancies = $batchRebuilder->inspect($store);

        return response()->json(['data' => ['status' => $discrepancies === [] && $batchDiscrepancies === [] ? 'in_sync' : 'discrepancy', 'checked_at' => now()->toAtomString(), 'discrepancies' => $discrepancies, 'batch_discrepancies' => $batchDiscrepancies]]);
    }

    public function store(RebuildInventoryBalancesRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, InventoryBalanceRebuilder $rebuilder, BatchBalanceRebuilder $batchRebuilder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        abort_unless($store->merchant_id === $merchant->id, 404);
        $result = $rebuilder->rebuild($store);
        $result += $batchRebuilder->rebuild($store);
        SecurityAuditEvent::query()->create([
            'merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id,
            'event_code' => 'inventory.balance.rebuilt', 'outcome' => 'succeeded',
            'metadata' => $result + ['reason' => $request->string('reason')->toString()], 'occurred_at' => now(),
        ]);

        return response()->json(['data' => $result + ['status' => 'in_sync', 'rebuilt_at' => now()->toAtomString()]]);
    }
}
