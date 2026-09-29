<?php

namespace App\Models;

use App\Domain\Inventory\Enums\TransferReceiptResolution;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\StockTransferReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $stock_transfer_id
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property TransferReceiptResolution $resolution
 * @property string|null $reason
 * @property CarbonImmutable $received_at
 */
#[Fillable(['merchant_id', 'stock_transfer_id', 'received_by_user_id', 'idempotency_key', 'payload_hash', 'resolution', 'reason', 'received_at'])]

class StockTransferReceipt extends Model
{
    /** @use HasFactory<StockTransferReceiptFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<StockTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    /** @return HasMany<StockTransferReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferReceiptLine::class);
    }

    protected function casts(): array
    {
        return ['resolution' => TransferReceiptResolution::class, 'received_at' => 'immutable_datetime'];
    }
}
