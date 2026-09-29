<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\BatchSelectionSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $inventory_location_id
 * @property int $product_variant_id
 * @property int|null $actor_user_id
 * @property string $strategy
 * @property string $requested_quantity_base
 * @property CarbonImmutable $business_date
 * @property string $content_hash
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['merchant_id', 'store_id', 'inventory_location_id', 'product_variant_id', 'actor_user_id', 'strategy', 'requested_quantity_base', 'business_date', 'content_hash', 'expires_at'])]

class BatchSelectionSnapshot extends Model
{
    /** @use HasFactory<BatchSelectionSnapshotFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return HasMany<BatchSelectionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BatchSelectionLine::class)->orderBy('sequence');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    protected function casts(): array
    {
        return ['requested_quantity_base' => 'decimal:6', 'business_date' => 'immutable_date', 'expires_at' => 'immutable_datetime'];
    }
}
