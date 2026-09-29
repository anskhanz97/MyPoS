<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryDisposition;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property string $code
 * @property string $name
 * @property InventoryDisposition $disposition
 * @property bool $is_default
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['merchant_id', 'store_id', 'code', 'name', 'disposition', 'is_default', 'archived_at'])]
class InventoryLocation extends Model
{
    /** @use HasFactory<InventoryLocationFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function casts(): array
    {
        return ['disposition' => InventoryDisposition::class, 'is_default' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }
}
