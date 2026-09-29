<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Domain\Auth\Enums\Permission;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Permission $permission_code
 * @property string $target_public_id
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['merchant_id', 'store_id', 'device_enrollment_id', 'requester_membership_id', 'approver_membership_id', 'permission_code', 'target_type', 'target_public_id', 'reason', 'token_hash', 'issued_at', 'expires_at', 'consumed_at', 'revoked_at'])]
#[Hidden(['token_hash'])]
class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return [
            'permission_code' => Permission::class,
            'target_public_id' => BinaryUuid::class,
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
