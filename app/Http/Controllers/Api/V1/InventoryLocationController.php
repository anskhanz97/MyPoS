<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\Enums\InventoryDisposition;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryLocationRequest;
use App\Http\Resources\InventoryLocationResource;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryLocationController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $validated = $request->validate(['include_archived' => ['nullable', 'boolean']]);
        $locations = InventoryLocation::query()
            ->where('merchant_id', $merchant->id)
            ->where('store_id', $store->id)
            ->when(! ($validated['include_archived'] ?? false), fn ($query) => $query->whereNull('archived_at'))
            ->orderByDesc('is_default')->orderBy('name')->limit(250)->get();

        return response()->json(['data' => InventoryLocationResource::collection($locations)->resolve($request)]);
    }

    public function store(StoreInventoryLocationRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        abort_unless($store->merchant_id === $merchant->id, 404);
        $data = $request->validated();
        $location = DB::transaction(function () use ($user, $merchant, $store, $data): InventoryLocation {
            $code = mb_strtoupper((string) $data['code']);
            abort_if(InventoryLocation::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('code', $code)->exists(), 422, 'An inventory location code already exists in this store.');
            $makeDefault = (bool) ($data['is_default'] ?? false) || ! InventoryLocation::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->whereNull('archived_at')->exists();
            if ($makeDefault) {
                InventoryLocation::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('is_default', true)->update(['is_default' => false]);
            }
            $location = InventoryLocation::query()->create([
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
                'code' => $code,
                'name' => trim((string) $data['name']),
                'disposition' => InventoryDisposition::from((string) $data['disposition']),
                'is_default' => $makeDefault,
            ]);
            SecurityAuditEvent::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id,
                'event_code' => 'inventory.location.created', 'outcome' => 'succeeded',
                'metadata' => ['location_id' => $location->public_id, 'disposition' => $location->disposition->value, 'is_default' => $location->is_default], 'occurred_at' => now(),
            ]);

            return $location;
        });

        return response()->json(['data' => (new InventoryLocationResource($location))->resolve($request)], 201);
    }
}
