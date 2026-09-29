<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $loyalty_member_id @property string $payload_hash */
#[Fillable(['merchant_id', 'loyalty_program_id', 'loyalty_member_id', 'attributed_store_id', 'idempotency_key', 'payload_hash', 'marketing_consent', 'joined_at'])]
class LoyaltyPublicEnrollment extends Model
{
    protected function casts(): array
    {
        return ['marketing_consent' => 'boolean', 'joined_at' => 'immutable_datetime'];
    }
}
