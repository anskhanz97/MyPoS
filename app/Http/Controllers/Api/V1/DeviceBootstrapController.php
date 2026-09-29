<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Devices\DeviceCredentialResolver;
use App\Domain\Devices\SettingsResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceEnrollmentResource;
use App\Http\Resources\RegisterResource;
use App\Models\DeviceEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceBootstrapController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, DeviceEnrollment $device, DeviceCredentialResolver $credentials, SettingsResolver $settings): JsonResponse
    {
        $credentials->resolve($device, $request->header('X-Device-Key'));
        $device->update(['last_seen_at' => now()]);
        $device->load(['merchant', 'store', 'register.devices']);
        $resolved = $settings->current($device->merchant, $device->store);
        $device->update(['settings_version' => max($resolved['merchant_version'], $resolved['store_version'] ?? 0)]);

        return response()->json(['data' => [
            'device' => (new DeviceEnrollmentResource($device->refresh()))->resolve(),
            'register' => (new RegisterResource($device->register))->resolve(),
            'tenant' => ['merchant_id' => $device->merchant->public_id, 'store_id' => $device->store->public_id, 'timezone' => $device->store->timezone],
            'configuration' => $resolved, 'lease' => ['expires_at' => $device->lease_expires_at?->toAtomString(), 'staff_auth_max_age_seconds' => 86400],
            'coordination' => ['writer_lock_name' => "mypos:{$device->public_id}:writer", 'fencing_epoch' => $device->fencing_epoch],
        ]]);
    }
}
