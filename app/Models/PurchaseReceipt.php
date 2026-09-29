<?php

namespace App\Models;

use App\Domain\Purchasing\Enums\PurchaseReceiptStatus;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $purchase_order_id
 * @property int $inventory_location_id
 * @property PurchaseReceiptStatus $status
 * @property string $number
 * @property string $payload_hash
 * @property string|null $supplier_reference
 * @property string|null $notes
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable $posted_at
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property string $merchandise_total
 * @property string $landed_cost_total
 * @property string $recoverable_tax_total
 * @property string $supplier_charge_total
 * @property string $valuation_timing
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read InventoryLocation $location
 * @property-read Collection<int, PurchaseReceiptLine> $lines
 */
#[Fillable(['merchant_id', 'store_id', 'purchase_order_id', 'inventory_location_id', 'received_by_user_id', 'number', 'status', 'idempotency_key', 'payload_hash', 'expected_order_version', 'supplier_reference', 'notes', 'received_at', 'posted_at', 'reversed_at', 'reversed_by_user_id', 'reversal_reason', 'merchandise_total', 'landed_cost_total', 'recoverable_tax_total', 'supplier_charge_total', 'valuation_timing'])]
class PurchaseReceipt extends Model
{
    /** @use HasFactory<PurchaseReceiptFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** @return HasMany<PurchaseReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class);
    }

    protected function casts(): array
    {
        return ['status' => PurchaseReceiptStatus::class, 'received_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime', 'merchandise_total' => 'decimal:6', 'landed_cost_total' => 'decimal:6', 'recoverable_tax_total' => 'decimal:6', 'supplier_charge_total' => 'decimal:6'];
    }
}
