<?php

namespace App\Domain\Devices\Actions;

use App\Models\DevicePairingCode;
use App\Models\Register;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssuePairingCode
{
    /** @return array{pairing: DevicePairingCode, code: string} */
    public function handle(User $actor, Register $register, bool $replaceExisting): array
    {
        return DB::transaction(function () use ($actor, $register, $replaceExisting): array {
            $locked = Register::query()->lockForUpdate()->findOrFail($register->id);
            $hasActive = $locked->devices()->where('status', 'active')->whereNull('revoked_at')->exists();
            abort_if($hasActive && ! $replaceExisting, 409, 'The register already has an active device. Choose controlled replacement.');
            DevicePairingCode::query()->where('register_id', $locked->id)->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $code = Str::upper(Str::random(8));
            $pairing = DevicePairingCode::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'register_id' => $locked->id,
                'created_by_user_id' => $actor->id, 'code_hash' => hash('sha256', $code),
                'replace_existing' => $replaceExisting, 'expires_at' => now()->addMinutes(10),
            ]);
            SecurityAuditEvent::query()->create([
                'merchant_id' => $locked->merchant_id,
                'store_id' => $locked->store_id,
                'actor_user_id' => $actor->id,
                'event_code' => 'device_pairing.issued',
                'outcome' => 'succeeded',
                'metadata' => ['register_id' => $locked->public_id, 'pairing_id' => $pairing->public_id, 'replace_existing' => $replaceExisting],
                'occurred_at' => now(),
            ]);

            return ['pairing' => $pairing, 'code' => $code];
        });
    }
}
