<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['in_app_alert_id', 'user_id', 'read_at'])] class InAppAlertRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
