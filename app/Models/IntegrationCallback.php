<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_integration_id', 'notification_delivery_id', 'provider_event_id', 'event_type', 'outcome', 'payload_hash', 'occurred_at'])]
class IntegrationCallback extends Model
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
