<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierChargeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $supplier_id
 * @property int $purchase_receipt_id
 * @property int|null $reverses_entry_id
 * @property string $entry_kind
 * @property string $amount
 * @property string $source_reference
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['merchant_id', 'store_id', 'supplier_id', 'purchase_receipt_id', 'reverses_entry_id', 'entry_kind', 'currency', 'amount', 'source_reference', 'occurred_at'])]
class SupplierChargeEntry extends Model
{
    /** @use HasFactory<SupplierChargeEntryFactory> */
    use HasBinaryPublicId, HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Supplier charge entries are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Supplier charge entries cannot be deleted.'));
    }

    /** @return BelongsTo<PurchaseReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'immutable_datetime'];
    }
}
