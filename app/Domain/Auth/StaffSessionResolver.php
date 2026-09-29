<?php

namespace App\Domain\Auth;

use App\Domain\Auth\Exceptions\InvalidPin;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Models\DeviceEnrollment;
use App\Models\StaffDeviceSession;

final class StaffSessionResolver
{
    public function resolve(DeviceEnrollment $device, ?string $token): StaffDeviceSession
    {
        if ($token === null || $token === '' || $device->status !== DeviceStatus::Active || $device->revoked_at !== null || $device->lease_expires_at?->isFuture() !== true) {
            throw new InvalidPin;
        }

        $session = StaffDeviceSession::query()
            ->where('device_enrollment_id', $device->id)
            ->where('session_token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($session === null) {
            throw new InvalidPin;
        }

        $session->update(['last_seen_at' => now()]);

        return $session;
    }
}
