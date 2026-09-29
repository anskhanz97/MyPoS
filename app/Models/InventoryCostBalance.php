<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryCostState;
use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\InventoryCostBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $product_variant_id
 * @property string $quantity_base
 * @property string|null $inventory_value
 * @property string|null $average_unit_cost
 * @property InventoryCostState $cost_state
 * @property string $pending_negative_quantity_base
 * @property int|null $last_cost_entry_id
 * @property int $projection_version
 * @property-read ProductVariant $variant
 * @property-read InventoryCostEntry|null $lastEntry
 */
#[Fillable(['merchant_id', 'store_id', 'product_variant_id', 'quantity_base', 'inventory_value', 'average_unit_cost', 'cost_state', 'pending_negative_quantity_base', 'last_cost_entry_id', 'projection_version'])]
class InventoryCostBalance extends Model
{
    /** @use HasFactory<InventoryCostBalanceFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<InventoryCostEntry, $this> */
    public function lastEntry(): BelongsTo
    {
        return $this->belongsTo(InventoryCostEntry::class, 'last_cost_entry_id');
    }

    protected function casts(): array
    {
        return ['quantity_base' => 'decimal:6', 'inventory_value' => 'decimal:6', 'average_unit_cost' => 'decimal:6', 'cost_state' => InventoryCostState::class, 'pending_negative_quantity_base' => 'decimal:6', 'projection_version' => 'integer'];
    }
}
