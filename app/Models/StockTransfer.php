<?php

namespace App\Models;

use App\Domain\Inventory\Enums\StockTransferState;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $source_store_id
 * @property int $destination_store_id
 * @property int $source_inventory_location_id
 * @property int $destination_inventory_location_id
 * @property string $number
 * @property StockTransferState $state
 * @property int $version
 * @property string|null $notes
 * @property string|null $dispatch_key
 * @property string|null $dispatch_hash
 * @property CarbonImmutable|null $dispatched_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $cancelled_at
 * @property-read Store $sourceStore
 * @property-read Store $destinationStore
 * @property-read InventoryLocation $sourceLocation
 * @property-read InventoryLocation $destinationLocation
 * @property-read Collection<int, StockTransferLine> $lines
 */
#[Fillable(['merchant_id', 'source_store_id', 'destination_store_id', 'source_inventory_location_id', 'destination_inventory_location_id', 'created_by_user_id', 'number', 'state', 'version', 'notes', 'dispatch_key', 'dispatch_hash', 'dispatched_at', 'received_at', 'cancelled_at'])]

class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Store, $this> */
    public function sourceStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'source_store_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_inventory_location_id');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_inventory_location_id');
    }

    /** @return HasMany<StockTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /** @return HasMany<StockTransferReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(StockTransferReceipt::class);
    }

    protected function casts(): array
    {
        return ['state' => StockTransferState::class, 'version' => 'integer', 'dispatched_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
