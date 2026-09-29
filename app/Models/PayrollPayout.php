<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property string $payload_hash */
#[Fillable(['merchant_id', 'payroll_item_id', 'cash_session_id', 'recorded_by_user_id', 'idempotency_key', 'payload_hash', 'amount', 'payment_method', 'reference', 'paid_at'])]
class PayrollPayout extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'immutable_datetime'];
    }
}
