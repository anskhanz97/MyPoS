<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\SecurityAuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'store_id', 'device_enrollment_id', 'actor_user_id', 'actor_membership_id', 'event_code', 'outcome', 'request_id', 'metadata', 'occurred_at'])]
class SecurityAuditEvent extends Model
{
    /** @use HasFactory<SecurityAuditEventFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['request_id' => BinaryUuid::class, 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
