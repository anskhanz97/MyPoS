<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $purchase_receipt_line_id
 * @property int|null $product_batch_id
 * @property int $product_variant_id
 * @property int $inventory_cost_entry_id
 * @property string $returned_quantity_uom
 * @property string $returned_quantity_base
 * @property string $supplier_credit_amount
 * @property string|null $valuation_unit_cost
 * @property string $product_name_snapshot
 * @property string|null $sku_snapshot
 * @property string $unit_symbol_snapshot
 * @property-read PurchaseReceiptLine $receiptLine
 * @property-read InventoryCostEntry $costEntry
 * @property-read StockMovement $stockMovement
 * @property-read ProductBatch|null $batch
 */
#[Fillable(['merchant_id', 'store_id', 'purchase_return_id', 'purchase_receipt_line_id', 'product_variant_id', 'product_batch_id', 'stock_movement_id', 'inventory_cost_entry_id', 'product_name_snapshot', 'sku_snapshot', 'unit_symbol_snapshot', 'returned_quantity_uom', 'factor_to_base', 'returned_quantity_base', 'source_credit_unit_base', 'supplier_credit_amount', 'valuation_unit_cost'])]
class PurchaseReturnLine extends Model
{
    use HasBinaryPublicId;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Purchase return lines are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Purchase return lines cannot be deleted.'));
    }

    /** @return BelongsTo<PurchaseReceiptLine, $this> */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptLine::class, 'purchase_receipt_line_id');
    }

    /** @return BelongsTo<PurchaseReturn, $this> */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /** @return BelongsTo<InventoryCostEntry, $this> */
    public function costEntry(): BelongsTo
    {
        return $this->belongsTo(InventoryCostEntry::class, 'inventory_cost_entry_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    protected function casts(): array
    {
        return ['returned_quantity_uom' => 'decimal:6', 'factor_to_base' => 'decimal:6', 'returned_quantity_base' => 'decimal:6', 'source_credit_unit_base' => 'decimal:6', 'supplier_credit_amount' => 'decimal:2', 'valuation_unit_cost' => 'decimal:6'];
    }
}
