<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property string $public_id @property int $merchant_id @property int $store_id @property int $staff_profile_id @property int $attendance_shift_id @property string $payload_hash @property string $event_type @property \Carbon\CarbonImmutable $occurred_at */
#[Fillable(['merchant_id', 'store_id', 'staff_profile_id', 'attendance_shift_id', 'actor_user_id', 'idempotency_key', 'payload_hash', 'event_type', 'occurred_at', 'reason', 'before_values', 'after_values'])]
class AttendanceEvent extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'before_values' => 'array', 'after_values' => 'array'];
    }
}
