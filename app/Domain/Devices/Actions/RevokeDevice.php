<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Models\DeviceEnrollment;
use App\Models\DeviceLifecycleEvent;
use App\Models\StaffDeviceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RevokeDevice
{
    public function handle(User $actor, DeviceEnrollment $device, string $reason): DeviceEnrollment
    {
        return DB::transaction(function () use ($actor, $device, $reason): DeviceEnrollment {
            $locked = DeviceEnrollment::query()->lockForUpdate()->findOrFail($device->id);
            abort_if($locked->status === DeviceStatus::Revoked || $locked->status === DeviceStatus::Replaced, 409, 'The device is already inactive.');
            $from = $locked->status;
            $locked->update(['status' => DeviceStatus::Revoked, 'revoked_at' => now(), 'revoked_by_user_id' => $actor->id, 'revoke_reason' => $reason, 'credential_version' => $locked->credential_version + 1]);
            StaffDeviceSession::query()->where('device_enrollment_id', $locked->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            DeviceLifecycleEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'device_enrollment_id' => $locked->id, 'actor_user_id' => $actor->id, 'event_code' => 'devices.enrollment.revoked', 'from_status' => $from->value, 'to_status' => DeviceStatus::Revoked->value, 'reason' => $reason, 'occurred_at' => now()]);

            return $locked->refresh();
        });
    }
}
