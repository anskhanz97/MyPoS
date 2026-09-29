<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $supplier_id
 * @property int|null $reverses_payment_id
 * @property string $number
 * @property string $entry_kind
 * @property string $payload_hash
 * @property string $amount
 * @property string $payment_method
 * @property string $reference
 * @property string|null $notes
 * @property CarbonImmutable $paid_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, SupplierPaymentAllocation> $allocations
 */
#[Fillable(['merchant_id', 'store_id', 'supplier_id', 'recorded_by_user_id', 'reverses_payment_id', 'number', 'entry_kind', 'idempotency_key', 'payload_hash', 'currency', 'amount', 'payment_method', 'reference', 'notes', 'paid_at'])]
class SupplierPayment extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Supplier payments are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Supplier payments cannot be deleted.'));
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<SupplierPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'immutable_datetime'];
    }
}
