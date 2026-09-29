<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $register_id
 * @property int $receipt_snapshot_id
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $copy_type
 * @property string $state
 * @property string|null $printer_name
 * @property int $helper_protocol
 * @property array<string,mixed> $render_payload
 * @property array<string,mixed> $signed_authorization
 * @property string|null $reason
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property-read ReceiptSnapshot $receipt
 * @property-read Register $register
 * @property-read Collection<int, PrintAttempt> $attempts
 * @property-read DrawerCommand|null $drawerCommand
 */
#[Fillable(['merchant_id', 'store_id', 'register_id', 'receipt_snapshot_id', 'requested_by_user_id', 'idempotency_key', 'payload_hash', 'copy_type', 'original_guard', 'state', 'printer_name', 'helper_protocol', 'render_payload', 'signed_authorization', 'reason', 'expires_at', 'completed_at'])]
class PrintJob extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<ReceiptSnapshot, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(ReceiptSnapshot::class, 'receipt_snapshot_id');
    }

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /** @return HasMany<PrintAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PrintAttempt::class);
    }

    /** @return HasOne<DrawerCommand, $this> */
    public function drawerCommand(): HasOne
    {
        return $this->hasOne(DrawerCommand::class);
    }

    protected function casts(): array
    {
        return ['helper_protocol' => 'integer', 'render_payload' => 'array', 'signed_authorization' => 'array', 'expires_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
