<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['merchant_id', 'store_id', 'cash_session_id', 'reviewed_by_user_id', 'idempotency_key', 'payload_hash', 'decision', 'reason', 'expected_amount', 'counted_amount', 'variance_amount', 'reviewed_at'])]
class CashVarianceReview extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cash variance reviews are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Cash variance reviews cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'counted_amount' => 'decimal:2', 'variance_amount' => 'decimal:2', 'reviewed_at' => 'immutable_datetime'];
    }
}
