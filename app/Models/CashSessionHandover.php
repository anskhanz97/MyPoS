<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['merchant_id', 'store_id', 'cash_session_id', 'from_user_id', 'to_user_id', 'recorded_by_user_id', 'idempotency_key', 'payload_hash', 'note', 'occurred_at'])]
class CashSessionHandover extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cash session handovers are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Cash session handovers cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
