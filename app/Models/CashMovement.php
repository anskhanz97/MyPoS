<?php

namespace App\Models;

use App\Casts\BinaryUuid;
use App\Domain\Cash\Enums\CashMovementKind;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property string $public_id
 * @property CashMovementKind $kind
 * @property string $signed_amount
 * @property string $reason
 * @property string|null $source_type
 * @property string|null $source_public_id
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['merchant_id', 'store_id', 'cash_session_id', 'recorded_by_user_id', 'kind', 'signed_amount', 'reason', 'idempotency_key', 'payload_hash', 'source_type', 'source_public_id', 'source_key', 'occurred_at'])]
class CashMovement extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cash movements are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Cash movements cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['kind' => CashMovementKind::class, 'signed_amount' => 'decimal:2', 'source_public_id' => BinaryUuid::class, 'occurred_at' => 'immutable_datetime'];
    }
}
