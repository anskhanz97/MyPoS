<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'loyalty_program_id', 'action', 'outcome', 'phone_hash', 'ip_hash', 'user_agent_family', 'occurred_at'])]
class PublicLoyaltyAccessEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
