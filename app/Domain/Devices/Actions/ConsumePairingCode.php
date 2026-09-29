<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Models\DeviceEnrollment;
use App\Models\DeviceLifecycleEvent;
use App\Models\DevicePairingCode;
use App\Models\Register;
use App\Models\StaffDeviceSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ConsumePairingCode
{
    /** @return array{device: DeviceEnrollment, device_key: string} */
    public function handle(string $code, string $name, int $protocolVersion): array
    {
        return DB::transaction(function () use ($code, $name, $protocolVersion): array {
            $pairing = DevicePairingCode::query()->where('code_hash', hash('sha256', Str::upper($code)))->lockForUpdate()->first();
            abort_unless($pairing !== null && $pairing->consumed_at === null && $pairing->revoked_at === null && $pairing->expires_at->isFuture(), 409, 'The pairing code is invalid or expired.');
            abort_unless($protocolVersion === 1, 409, 'The device protocol version is unsupported.');
            $register = Register::query()->lockForUpdate()->findOrFail($pairing->register_id);
            $active = DeviceEnrollment::query()->where('register_id', $register->id)->where('status', DeviceStatus::Active)->lockForUpdate()->get();
            abort_if($active->isNotEmpty() && ! $pairing->replace_existing, 409, 'The register already has an active device.');
            if ($active->isNotEmpty()) {
                $ids = $active->pluck('id');
                foreach ($active as $replacedDevice) {
                    DeviceLifecycleEvent::query()->create([
                        'merchant_id' => $replacedDevice->merchant_id,
                        'store_id' => $replacedDevice->store_id,
                        'device_enrollment_id' => $replacedDevice->id,
                        'actor_user_id' => $pairing->created_by_user_id,
                        'event_code' => 'devices.enrollment.replaced',
                        'from_status' => DeviceStatus::Active->value,
                        'to_status' => DeviceStatus::Replaced->value,
                        'reason' => 'controlled_replacement',
                        'metadata' => ['replacement_register_id' => $register->id],
                        'occurred_at' => now(),
                    ]);
                }
                DeviceEnrollment::query()->whereIn('id', $ids)->update(['status' => DeviceStatus::Replaced, 'revoked_at' => now(), 'revoke_reason' => 'controlled_replacement']);
                StaffDeviceSession::query()->whereIn('device_enrollment_id', $ids)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            $register->increment('fencing_epoch');
            $key = Str::random(64);
            $device = DeviceEnrollment::query()->create([
                'merchant_id' => $pairing->merchant_id, 'store_id' => $pairing->store_id, 'register_id' => $register->id,
                'name' => $name, 'device_key_hash' => Hash::make($key), 'credential_version' => 1,
                'fencing_epoch' => $register->fencing_epoch, 'settings_version' => 1, 'protocol_min' => 1, 'protocol_max' => 1,
                'status' => DeviceStatus::Active, 'enrolled_at' => now(), 'last_seen_at' => now(), 'lease_expires_at' => now()->addHours(72),
            ]);
            $pairing->update(['consumed_at' => now()]);
            DeviceLifecycleEvent::query()->create(['merchant_id' => $device->merchant_id, 'store_id' => $device->store_id, 'device_enrollment_id' => $device->id, 'actor_user_id' => $pairing->created_by_user_id, 'event_code' => 'devices.enrollment.activated', 'to_status' => DeviceStatus::Active->value, 'metadata' => ['protocol_version' => $protocolVersion, 'fencing_epoch' => $device->fencing_epoch], 'occurred_at' => now()]);

            return ['device' => $device, 'device_key' => $key];
        });
    }
}
