<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'user_id', 'in_app_enabled', 'operational_email_enabled'])] class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return ['in_app_enabled' => 'boolean', 'operational_email_enabled' => 'boolean'];
    }
}
