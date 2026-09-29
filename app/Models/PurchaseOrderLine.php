<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $public_id
 * @property int $merchant_id
 * @property int $store_id
 * @property int $purchase_order_id
 * @property int $product_variant_id
 * @property int $purchase_unit_id
 * @property string $product_name_snapshot
 * @property string|null $sku_snapshot
 * @property string $unit_symbol_snapshot
 * @property string $ordered_quantity_uom
 * @property string $factor_to_base
 * @property string $ordered_quantity_base
 * @property string $received_quantity_base
 * @property string $returned_quantity_base
 * @property string $cancelled_quantity_base
 * @property string $expected_unit_cost
 * @property string|null $tax_reference
 * @property-read ProductVariant $variant
 * @property-read UnitOfMeasure $purchaseUnit
 */
#[Fillable(['merchant_id', 'store_id', 'purchase_order_id', 'product_variant_id', 'purchase_unit_id', 'product_name_snapshot', 'sku_snapshot', 'unit_symbol_snapshot', 'ordered_quantity_uom', 'factor_to_base', 'ordered_quantity_base', 'received_quantity_base', 'returned_quantity_base', 'cancelled_quantity_base', 'expected_unit_cost', 'tax_reference'])]
class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasBinaryPublicId, HasFactory;

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_unit_id');
    }

    protected function casts(): array
    {
        return [
            'ordered_quantity_uom' => 'decimal:6', 'factor_to_base' => 'decimal:6', 'ordered_quantity_base' => 'decimal:6',
            'received_quantity_base' => 'decimal:6', 'returned_quantity_base' => 'decimal:6', 'cancelled_quantity_base' => 'decimal:6', 'expected_unit_cost' => 'decimal:6',
        ];
    }
}
