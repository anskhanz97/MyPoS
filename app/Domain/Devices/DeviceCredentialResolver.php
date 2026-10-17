<?php

namespace App\Domain\Devices;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Exceptions\InvalidDeviceCredential;
use App\Models\DeviceEnrollment;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Hash;

final class DeviceCredentialResolver
{
    public function resolve(DeviceEnrollment $device, ?string $key, bool $allowRecovery = false): DeviceEnrollment
    {
        $validKey = is_string($key) && $key !== '' && Hash::check($key, $device->device_key_hash);
        $active = $device->status === DeviceStatus::Active && $device->revoked_at === null && $device->lease_expires_at?->isFuture() === true;
        $recovery = $allowRecovery && $device->status !== DeviceStatus::Pending && $device->device_key_hash !== '';
        if (! $validKey || (! $active && ! $recovery)) {
            throw new InvalidDeviceCredential;
        }

        Context::addHidden('log_scope', ['merchant_id' => $device->merchant_id, 'store_id' => $device->store_id, 'device_id' => $device->public_id, 'caller' => 'device']);

        return $device;
    }
}
