<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Devices\Actions\ConsumePairingCode;
use App\Domain\Devices\Actions\IssuePairingCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumeDevicePairingRequest;
use App\Http\Requests\CreateDevicePairingRequest;
use App\Http\Resources\DeviceEnrollmentResource;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DevicePairingController extends Controller
{
    public function store(CreateDevicePairingRequest $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer, IssuePairingCode $issue): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::DeviceManage, $store);
        abort_unless($register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
        $result = $issue->handle($user, $register, $request->boolean('replace_existing'));

        return response()->json(['data' => ['pairing_id' => $result['pairing']->public_id, 'pairing_code' => $result['code'], 'expires_at' => $result['pairing']->expires_at->toAtomString()]], 201);
    }

    public function consume(ConsumeDevicePairingRequest $request, ConsumePairingCode $consume): JsonResponse
    {
        $result = $consume->handle($request->string('pairing_code')->toString(), $request->string('device_name')->toString(), $request->integer('protocol_version'));

        return response()->json(['data' => ['device' => (new DeviceEnrollmentResource($result['device']))->resolve(), 'device_key' => $result['device_key']]], 201);
    }
}
