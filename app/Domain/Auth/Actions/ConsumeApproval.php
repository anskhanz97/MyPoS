<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Exceptions\InvalidApproval;
use App\Models\Approval;
use App\Models\SecurityAuditEvent;
use Illuminate\Support\Facades\DB;

final class ConsumeApproval
{
    public function handle(string $token, int $merchantId, int $storeId, Permission $permission, string $targetType, string $targetPublicId): Approval
    {
        return DB::transaction(function () use ($token, $merchantId, $storeId, $permission, $targetType, $targetPublicId): Approval {
            $approval = Approval::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if ($approval === null
                || $approval->merchant_id !== $merchantId
                || $approval->store_id !== $storeId
                || $approval->permission_code !== $permission
                || $approval->target_type !== $targetType
                || $approval->target_public_id !== $targetPublicId
                || $approval->consumed_at !== null
                || $approval->revoked_at !== null
                || $approval->expires_at->isPast()) {
                if ($approval !== null) {
                    SecurityAuditEvent::query()->create([
                        'merchant_id' => $approval->merchant_id,
                        'store_id' => $approval->store_id,
                        'device_enrollment_id' => $approval->device_enrollment_id,
                        'actor_membership_id' => $approval->approver_membership_id,
                        'event_code' => 'approval_consumed',
                        'outcome' => 'failed',
                        'metadata' => ['permission' => $permission->value, 'target_type' => $targetType],
                        'occurred_at' => now(),
                    ]);
                }
                throw new InvalidApproval;
            }

            $approval->update(['consumed_at' => now()]);
            SecurityAuditEvent::query()->create([
                'merchant_id' => $approval->merchant_id,
                'store_id' => $approval->store_id,
                'device_enrollment_id' => $approval->device_enrollment_id,
                'actor_membership_id' => $approval->approver_membership_id,
                'event_code' => 'approval_consumed',
                'outcome' => 'succeeded',
                'metadata' => ['permission' => $permission->value, 'target_type' => $targetType],
                'occurred_at' => now(),
            ]);

            return $approval;
        });
    }
}
