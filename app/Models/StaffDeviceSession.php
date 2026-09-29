<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\StaffDeviceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['merchant_id', 'store_id', 'device_enrollment_id', 'merchant_membership_id', 'session_token_hash', 'started_at', 'last_seen_at', 'expires_at', 'revoked_at'])]
#[Hidden(['session_token_hash'])]
class StaffDeviceSession extends Model
{
    /** @use HasFactory<StaffDeviceSessionFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
