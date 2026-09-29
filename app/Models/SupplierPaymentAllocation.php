<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $supplier_charge_entry_id
 * @property string $entry_kind
 * @property string $amount
 * @property-read SupplierChargeEntry $charge
 */
#[Fillable(['merchant_id', 'store_id', 'supplier_id', 'supplier_payment_id', 'supplier_charge_entry_id', 'reverses_allocation_id', 'entry_kind', 'amount'])]
class SupplierPaymentAllocation extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Supplier payment allocations are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Supplier payment allocations cannot be deleted.'));
    }

    /** @return BelongsTo<SupplierChargeEntry, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(SupplierChargeEntry::class, 'supplier_charge_entry_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
