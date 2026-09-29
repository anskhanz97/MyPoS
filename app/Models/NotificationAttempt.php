<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['notification_delivery_id', 'attempt_number', 'outcome', 'provider_message_id', 'failure_code', 'safe_message', 'attempted_at'])] class NotificationAttempt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'attempted_at' => 'immutable_datetime'];
    }
}
