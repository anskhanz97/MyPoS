<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryCostEntryKind;
use App\Domain\Inventory\Enums\InventoryCostState;
use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryCostEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $product_variant_id
 * @property int $inventory_cost_balance_id
 * @property int|null $stock_movement_id
 * @property InventoryCostEntryKind $entry_kind
 * @property string $quantity_delta
 * @property string|null $unit_cost
 * @property string|null $value_delta
 * @property string $previous_quantity
 * @property string|null $previous_value
 * @property string|null $previous_average
 * @property string $resulting_quantity
 * @property string|null $resulting_value
 * @property string|null $resulting_average
 * @property InventoryCostState $cost_state
 * @property string $resolved_negative_quantity
 * @property string $cost_adjustment_value
 * @property string $valuation_timing
 * @property string $source_reference
 * @property string $source_key
 * @property string $payload_hash
 * @property CarbonImmutable $effective_at
 * @property CarbonImmutable $applied_at
 */
#[Fillable(['merchant_id', 'store_id', 'product_variant_id', 'inventory_cost_balance_id', 'stock_movement_id', 'adjusts_cost_entry_id', 'entry_kind', 'quantity_delta', 'unit_cost', 'value_delta', 'previous_quantity', 'previous_value', 'previous_average', 'resulting_quantity', 'resulting_value', 'resulting_average', 'cost_state', 'resolved_negative_quantity', 'cost_adjustment_value', 'valuation_timing', 'source_type', 'source_reference', 'source_line_reference', 'source_key', 'payload_hash', 'effective_at', 'applied_at'])]
class InventoryCostEntry extends Model
{
    /** @use HasFactory<InventoryCostEntryFactory> */
    use HasBinaryPublicId, HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Inventory cost entries are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Inventory cost entries cannot be deleted.'));
    }

    /** @return BelongsTo<InventoryCostBalance, $this> */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(InventoryCostBalance::class, 'inventory_cost_balance_id');
    }

    protected function casts(): array
    {
        return [
            'entry_kind' => InventoryCostEntryKind::class, 'cost_state' => InventoryCostState::class,
            'quantity_delta' => 'decimal:6', 'unit_cost' => 'decimal:6', 'value_delta' => 'decimal:6',
            'previous_quantity' => 'decimal:6', 'previous_value' => 'decimal:6', 'previous_average' => 'decimal:6',
            'resulting_quantity' => 'decimal:6', 'resulting_value' => 'decimal:6', 'resulting_average' => 'decimal:6',
            'resolved_negative_quantity' => 'decimal:6', 'cost_adjustment_value' => 'decimal:6',
            'effective_at' => 'immutable_datetime', 'applied_at' => 'immutable_datetime',
        ];
    }
}
