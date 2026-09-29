<?php

namespace App\Models;

use App\Domain\Inventory\Enums\StocktakeState;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\StocktakeFactory;
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
 * @property int $inventory_location_id
 * @property string $number
 * @property StocktakeState $state
 * @property int $version
 * @property int $baseline_movement_id
 * @property string|null $post_key
 * @property string|null $post_hash
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $posted_at
 * @property-read InventoryLocation $location
 * @property-read Collection<int, StocktakeLine> $lines
 */
#[Fillable(['merchant_id', 'store_id', 'inventory_location_id', 'opened_by_user_id', 'number', 'state', 'version', 'baseline_movement_id', 'post_key', 'post_hash', 'opened_at', 'reviewed_at', 'posted_at', 'cancelled_at', 'notes'])]

class Stocktake extends Model
{
    /** @use HasFactory<StocktakeFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** @return HasMany<StocktakeLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class);
    }

    protected function casts(): array
    {
        return ['state' => StocktakeState::class, 'version' => 'integer', 'baseline_movement_id' => 'integer', 'opened_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
