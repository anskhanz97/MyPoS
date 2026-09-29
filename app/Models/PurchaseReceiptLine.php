<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\PurchaseReceiptLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $purchase_receipt_id
 * @property int $purchase_order_id
 * @property int $purchase_order_line_id
 * @property int $product_variant_id
 * @property int $purchase_unit_id
 * @property int|null $product_batch_id
 * @property int $inventory_cost_entry_id
 * @property string $product_name_snapshot
 * @property string|null $sku_snapshot
 * @property string $unit_symbol_snapshot
 * @property string $received_quantity_uom
 * @property string $factor_to_base
 * @property string $received_quantity_base
 * @property string $unit_cost_uom
 * @property string $unit_cost_base
 * @property string $landed_cost_total
 * @property string $recoverable_tax_total
 * @property string $inventory_unit_cost_base
 * @property string $inventory_value
 * @property-read PurchaseOrderLine $purchaseOrderLine
 * @property-read ProductBatch|null $batch
 * @property-read StockMovement $stockMovement
 * @property-read InventoryCostEntry $costEntry
 */
#[Fillable(['merchant_id', 'store_id', 'purchase_receipt_id', 'purchase_order_id', 'purchase_order_line_id', 'product_variant_id', 'purchase_unit_id', 'product_batch_id', 'stock_movement_id', 'inventory_cost_entry_id', 'product_name_snapshot', 'sku_snapshot', 'unit_symbol_snapshot', 'received_quantity_uom', 'factor_to_base', 'received_quantity_base', 'unit_cost_uom', 'unit_cost_base', 'landed_cost_total', 'recoverable_tax_total', 'inventory_unit_cost_base', 'inventory_value'])]
class PurchaseReceiptLine extends Model
{
    /** @use HasFactory<PurchaseReceiptLineFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<PurchaseReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<InventoryCostEntry, $this> */
    public function costEntry(): BelongsTo
    {
        return $this->belongsTo(InventoryCostEntry::class, 'inventory_cost_entry_id');
    }

    protected function casts(): array
    {
        return ['received_quantity_uom' => 'decimal:6', 'factor_to_base' => 'decimal:6', 'received_quantity_base' => 'decimal:6', 'unit_cost_uom' => 'decimal:6', 'unit_cost_base' => 'decimal:6', 'landed_cost_total' => 'decimal:6', 'recoverable_tax_total' => 'decimal:6', 'inventory_unit_cost_base' => 'decimal:6', 'inventory_value' => 'decimal:6'];
    }
}
