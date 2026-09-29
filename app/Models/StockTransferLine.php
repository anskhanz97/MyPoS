<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\StockTransferLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $stock_transfer_id
 * @property int|null $inventory_variant_mapping_id
 * @property int $source_product_variant_id
 * @property int $destination_product_variant_id
 * @property int|null $source_product_batch_id
 * @property string $factor_to_destination_base
 * @property string $requested_source_quantity_base
 * @property string $dispatched_source_quantity_base
 * @property string $expected_destination_quantity_base
 * @property string $received_destination_quantity_base
 * @property string $short_destination_quantity_base
 * @property string $excess_destination_quantity_base
 * @property string|null $source_unit_cost
 * @property string|null $destination_unit_cost
 * @property int|null $source_cost_entry_id
 * @property-read ProductVariant $sourceVariant
 * @property-read ProductVariant $destinationVariant
 * @property-read ProductBatch|null $sourceBatch
 */
#[Fillable(['merchant_id', 'stock_transfer_id', 'inventory_variant_mapping_id', 'source_product_variant_id', 'destination_product_variant_id', 'source_product_batch_id', 'factor_to_destination_base', 'requested_source_quantity_base', 'dispatched_source_quantity_base', 'expected_destination_quantity_base', 'received_destination_quantity_base', 'short_destination_quantity_base', 'excess_destination_quantity_base', 'source_unit_cost', 'destination_unit_cost', 'source_cost_entry_id'])]

class StockTransferLine extends Model
{
    /** @use HasFactory<StockTransferLineFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<StockTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    /** @return BelongsTo<InventoryVariantMapping, $this> */
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(InventoryVariantMapping::class, 'inventory_variant_mapping_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function sourceVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'source_product_variant_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function destinationVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'destination_product_variant_id');
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'source_product_batch_id');
    }

    protected function casts(): array
    {
        return ['factor_to_destination_base' => 'decimal:6', 'requested_source_quantity_base' => 'decimal:6', 'dispatched_source_quantity_base' => 'decimal:6', 'expected_destination_quantity_base' => 'decimal:6', 'received_destination_quantity_base' => 'decimal:6', 'short_destination_quantity_base' => 'decimal:6', 'excess_destination_quantity_base' => 'decimal:6', 'source_unit_cost' => 'decimal:6', 'destination_unit_cost' => 'decimal:6'];
    }
}
