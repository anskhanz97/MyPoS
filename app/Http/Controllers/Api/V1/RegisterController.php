<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegisterRequest;
use App\Http\Resources\RegisterResource;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegisterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::DeviceManage, $store);
        abort_unless($store->merchant_id === $merchant->id, 404);

        return RegisterResource::collection(Register::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->with('devices')->orderBy('name')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRegisterRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::DeviceManage, $store);
        abort_unless($store->merchant_id === $merchant->id, 404);
        $register = Register::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'name' => $request->string('name')->toString(), 'code' => $request->string('code')->toString(), 'status' => 'active', 'capabilities' => $request->array('capabilities')]);
        SecurityAuditEvent::query()->create([
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
            'actor_user_id' => $user->id,
            'event_code' => 'register.created',
            'outcome' => 'succeeded',
            'metadata' => ['register_id' => $register->public_id, 'code' => $register->code],
            'occurred_at' => now(),
        ]);

        return (new RegisterResource($register->refresh()->load('devices')))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer): RegisterResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::DeviceManage, $store);
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);

        return new RegisterResource($register->load('devices'));
    }
}
