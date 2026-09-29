<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'operational_customer_id', 'recorded_by_user_id', 'normalized_phone', 'channel', 'purpose', 'state', 'source', 'evidence_reference', 'recorded_at'])]
class NotificationConsent extends Model
{
    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime'];
    }
}
