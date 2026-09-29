<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['merchant_id', 'store_id', 'cash_session_id', 'counted_by_user_id', 'idempotency_key', 'payload_hash', 'counted_amount', 'denominations', 'cutoff_at', 'movement_high_watermark', 'pending_server_sale_count', 'pending_offline_sale_count'])]
class CashSessionCount extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cash session counts are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Cash session counts cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['counted_amount' => 'decimal:2', 'denominations' => 'array', 'cutoff_at' => 'immutable_datetime', 'movement_high_watermark' => 'integer'];
    }
}
