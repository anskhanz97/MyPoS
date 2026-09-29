<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Exceptions\InvalidPin;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\DeviceEnrollment;
use App\Models\MerchantMembership;
use App\Models\SecurityAuditEvent;
use App\Models\StaffDeviceSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class SwitchDeviceStaff
{
    /** @return array{session: StaffDeviceSession, token: string} */
    public function handle(DeviceEnrollment $device, MerchantMembership $membership, string $deviceKey, string $pin): array
    {
        $validDevice = $device->status === DeviceStatus::Active
            && $device->revoked_at === null
            && $device->lease_expires_at?->isFuture() === true
            && Hash::check($deviceKey, $device->device_key_hash);
        $validMembership = $membership->merchant_id === $device->merchant_id
            && $membership->status === MembershipStatus::Active
            && $membership->revoked_at === null
            && $membership->pin_hash !== null
            && Hash::check($pin, $membership->pin_hash)
            && ($membership->role === MembershipRole::Owner
                || $membership->storeAssignments()->where('store_id', $device->store_id)->exists());

        if (! $validDevice || ! $validMembership) {
            SecurityAuditEvent::query()->create([
                'merchant_id' => $device->merchant_id,
                'store_id' => $device->store_id,
                'device_enrollment_id' => $device->id,
                'actor_membership_id' => $membership->merchant_id === $device->merchant_id ? $membership->id : null,
                'event_code' => 'staff_pin_switch',
                'outcome' => 'failed',
                'metadata' => ['reason' => 'invalid_credentials_or_scope'],
                'occurred_at' => now(),
            ]);

            throw new InvalidPin;
        }

        return DB::transaction(function () use ($device, $membership): array {
            StaffDeviceSession::query()
                ->where('device_enrollment_id', $device->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $token = Str::random(64);
            $session = StaffDeviceSession::query()->create([
                'merchant_id' => $device->merchant_id,
                'store_id' => $device->store_id,
                'device_enrollment_id' => $device->id,
                'merchant_membership_id' => $membership->id,
                'session_token_hash' => hash('sha256', $token),
                'started_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addHours(12),
            ]);

            $device->update(['last_seen_at' => now()]);
            SecurityAuditEvent::query()->create([
                'merchant_id' => $device->merchant_id,
                'store_id' => $device->store_id,
                'device_enrollment_id' => $device->id,
                'actor_membership_id' => $membership->id,
                'event_code' => 'staff_pin_switch',
                'outcome' => 'succeeded',
                'metadata' => ['role' => $membership->role->value],
                'occurred_at' => now(),
            ]);

            return ['session' => $session, 'token' => $token];
        });
    }
}
