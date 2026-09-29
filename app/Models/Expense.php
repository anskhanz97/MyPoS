<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property string $public_id @property int $merchant_id @property int $store_id @property string $payload_hash @property string $amount @property string $payment_source @property CarbonImmutable $expense_date @property string $payee @property string|null $reference @property string $reason */
#[Fillable(['merchant_id', 'store_id', 'expense_category_id', 'cash_session_id', 'correction_of_id', 'recorded_by_user_id', 'idempotency_key', 'payload_hash', 'state', 'expense_date', 'payee', 'amount', 'payment_source', 'reference', 'reason'])] class Expense extends Model
{
    use HasBinaryPublicId;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['expense_date' => 'immutable_date', 'amount' => 'decimal:2', 'created_at' => 'immutable_datetime'];
    }
}
