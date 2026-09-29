<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Customers\CustomerScope;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerGroupRequest;
use App\Models\CustomerGroup;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerGroupController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CustomerScope $scope): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        $validated = $request->validate(['include_archived' => ['nullable', 'boolean']]);
        $groups = CustomerGroup::query()
            ->where('merchant_id', $merchant->id)
            ->where('store_id', $scope->storeId($merchant, $store))
            ->when(! ($validated['include_archived'] ?? false), fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('name')
            ->limit(250)
            ->get()
            ->map(fn (CustomerGroup $group): array => [
                'id' => $group->public_id,
                'scope' => $group->store_id === null ? 'merchant' : 'store',
                'code' => $group->code,
                'name' => $group->name,
                'archived_at' => $group->archived_at?->toAtomString(),
            ]);

        return response()->json(['data' => $groups]);
    }

    public function store(StoreCustomerGroupRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CustomerScope $scope): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        abort_if($membership->role === MembershipRole::Cashier, 403, 'Cashiers cannot manage customer groups.');
        $data = $request->validated();
        $scopeStoreId = $scope->storeId($merchant, $store);
        $code = mb_strtoupper((string) $data['code']);

        $group = DB::transaction(function () use ($user, $merchant, $store, $scopeStoreId, $code, $data): CustomerGroup {
            abort_if(CustomerGroup::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->where('code', $code)->exists(), 422, 'A customer group code already exists in this scope.');
            $group = CustomerGroup::query()->create([
                'merchant_id' => $merchant->id,
                'store_id' => $scopeStoreId,
                'code' => $code,
                'name' => trim((string) $data['name']),
            ]);
            SecurityAuditEvent::query()->create([
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
                'actor_user_id' => $user->id,
                'event_code' => 'customer.group.created',
                'outcome' => 'succeeded',
                'metadata' => ['customer_group_id' => $group->public_id, 'scope' => $scopeStoreId === null ? 'merchant' : 'store'],
                'occurred_at' => now(),
            ]);

            return $group;
        });

        return response()->json(['data' => [
            'id' => $group->public_id,
            'scope' => $group->store_id === null ? 'merchant' : 'store',
            'code' => $group->code,
            'name' => $group->name,
            'archived_at' => null,
        ]], 201);
    }
}
