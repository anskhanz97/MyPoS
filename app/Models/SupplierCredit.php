<?php

namespace App\Models;

use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $supplier_id
 * @property int $purchase_return_id
 * @property string $entry_kind
 * @property SupplierCreditStatus $status
 * @property string $amount
 * @property string|null $supplier_reference
 * @property CarbonImmutable $occurred_at
 * @property-read PurchaseReturn $purchaseReturn
 */
#[Fillable(['merchant_id', 'store_id', 'supplier_id', 'purchase_return_id', 'reverses_credit_id', 'entry_kind', 'status', 'currency', 'amount', 'supplier_reference', 'occurred_at'])]
class SupplierCredit extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('Supplier credits cannot be deleted.'));
    }

    /** @return BelongsTo<PurchaseReturn, $this> */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    protected function casts(): array
    {
        return ['status' => SupplierCreditStatus::class, 'amount' => 'decimal:2', 'occurred_at' => 'immutable_datetime'];
    }
}
