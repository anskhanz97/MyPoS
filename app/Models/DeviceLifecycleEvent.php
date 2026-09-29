<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\DeviceLifecycleEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'store_id', 'device_enrollment_id', 'actor_user_id', 'event_code', 'from_status', 'to_status', 'reason', 'metadata', 'occurred_at'])]
class DeviceLifecycleEvent extends Model
{
    /** @use HasFactory<DeviceLifecycleEventFactory> */
    use HasBinaryPublicId, HasFactory;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
