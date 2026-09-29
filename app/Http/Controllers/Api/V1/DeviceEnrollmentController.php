<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Devices\Actions\RevokeDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\RevokeDeviceRequest;
use App\Http\Resources\DeviceEnrollmentResource;
use App\Models\DeviceEnrollment;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;

class DeviceEnrollmentController extends Controller
{
    public function destroy(RevokeDeviceRequest $request, Merchant $merchant, Store $store, DeviceEnrollment $device, RoleAuthorizer $authorizer, RevokeDevice $revoke): DeviceEnrollmentResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::DeviceManage, $store);
        abort_unless($device->merchant_id === $merchant->id && $device->store_id === $store->id, 404);

        return new DeviceEnrollmentResource($revoke->handle($user, $device, $request->string('reason')->toString()));
    }
}
