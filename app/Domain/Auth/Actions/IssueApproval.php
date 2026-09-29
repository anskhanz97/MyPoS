<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Exceptions\InvalidApproval;
use App\Domain\Auth\FixedRolePermissions;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Approval;
use App\Models\MerchantMembership;
use App\Models\SecurityAuditEvent;
use App\Models\StaffDeviceSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class IssueApproval
{
    public function __construct(private readonly FixedRolePermissions $permissions) {}

    /** @return array{approval: Approval, token: string} */
    public function handle(StaffDeviceSession $requester, MerchantMembership $approver, Permission $permission, string $targetType, string $targetPublicId, string $reason, string $pin): array
    {
        $valid = $requester->revoked_at === null
            && $requester->expires_at->isFuture()
            && $approver->merchant_id === $requester->merchant_id
            && $approver->id !== $requester->merchant_membership_id
            && $approver->status === MembershipStatus::Active
            && $approver->pin_hash !== null
            && Hash::check($pin, $approver->pin_hash)
            && $this->permissions->canApprove($approver->role, $permission)
            && $approver->storeAssignments()->where('store_id', $requester->store_id)->exists();

        if (! $valid) {
            SecurityAuditEvent::query()->create([
                'merchant_id' => $requester->merchant_id,
                'store_id' => $requester->store_id,
                'device_enrollment_id' => $requester->device_enrollment_id,
                'actor_membership_id' => $approver->merchant_id === $requester->merchant_id ? $approver->id : null,
                'event_code' => 'approval_issued',
                'outcome' => 'failed',
                'metadata' => ['permission' => $permission->value, 'target_type' => $targetType],
                'occurred_at' => now(),
            ]);
            throw new InvalidApproval('The approver credentials or scope do not authorize this action.');
        }

        $token = Str::random(64);
        $approval = Approval::query()->create([
            'merchant_id' => $requester->merchant_id,
            'store_id' => $requester->store_id,
            'device_enrollment_id' => $requester->device_enrollment_id,
            'requester_membership_id' => $requester->merchant_membership_id,
            'approver_membership_id' => $approver->id,
            'permission_code' => $permission,
            'target_type' => $targetType,
            'target_public_id' => $targetPublicId,
            'reason' => $reason,
            'token_hash' => hash('sha256', $token),
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        SecurityAuditEvent::query()->create([
            'merchant_id' => $requester->merchant_id,
            'store_id' => $requester->store_id,
            'device_enrollment_id' => $requester->device_enrollment_id,
            'actor_membership_id' => $approver->id,
            'event_code' => 'approval_issued',
            'outcome' => 'succeeded',
            'metadata' => ['permission' => $permission->value, 'target_type' => $targetType],
            'occurred_at' => now(),
        ]);

        return ['approval' => $approval, 'token' => $token];
    }
}
