<?php

namespace App\Models;

use App\Domain\Purchasing\Enums\PurchaseOrderState;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseOrderFactory;
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
 * @property int $supplier_id
 * @property string $number
 * @property PurchaseOrderState $state
 * @property int $version
 * @property CarbonImmutable $order_date
 * @property CarbonImmutable|null $expected_date
 * @property string|null $source_reference
 * @property string|null $notes
 * @property string|null $issue_key
 * @property string|null $issue_hash
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read Store $store
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseOrderLine> $lines
 * @property-read Collection<int, PurchaseOrderHistory> $history
 */
#[Fillable(['merchant_id', 'store_id', 'supplier_id', 'created_by_user_id', 'number', 'state', 'version', 'order_date', 'expected_date', 'source_reference', 'notes', 'issue_key', 'issue_hash', 'issued_at', 'cancelled_at', 'cancellation_reason'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** @return HasMany<PurchaseOrderHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(PurchaseOrderHistory::class)->orderBy('version');
    }

    protected function casts(): array
    {
        return ['state' => PurchaseOrderState::class, 'version' => 'integer', 'order_date' => 'immutable_date', 'expected_date' => 'immutable_date', 'issued_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
