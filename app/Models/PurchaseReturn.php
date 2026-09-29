<?php

namespace App\Models;

use App\Domain\Purchasing\Enums\PurchaseReturnStatus;
use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $supplier_id
 * @property int $purchase_receipt_id
 * @property int $inventory_location_id
 * @property string $number
 * @property PurchaseReturnStatus $status
 * @property SupplierCreditStatus $credit_status
 * @property string $supplier_credit_total
 * @property string $reason
 * @property string|null $supplier_credit_reference
 * @property string $payload_hash
 * @property CarbonImmutable $returned_at
 * @property CarbonImmutable $posted_at
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property-read PurchaseReceipt $receipt
 * @property-read InventoryLocation $location
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseReturnLine> $lines
 * @property-read SupplierCredit|null $credit
 */
#[Fillable(['merchant_id', 'store_id', 'supplier_id', 'purchase_receipt_id', 'inventory_location_id', 'posted_by_user_id', 'number', 'status', 'idempotency_key', 'payload_hash', 'reason', 'supplier_credit_reference', 'credit_status', 'supplier_credit_total', 'returned_at', 'posted_at', 'reversed_at', 'reversed_by_user_id', 'reversal_reason'])]
class PurchaseReturn extends Model
{
    use HasBinaryPublicId;

    /** @return BelongsTo<PurchaseReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class);
    }

    /** @return HasOne<SupplierCredit, $this> */
    public function credit(): HasOne
    {
        return $this->hasOne(SupplierCredit::class);
    }

    protected function casts(): array
    {
        return ['status' => PurchaseReturnStatus::class, 'credit_status' => SupplierCreditStatus::class, 'supplier_credit_total' => 'decimal:2', 'returned_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }
}
