<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $merchant_id
 * @property int $stock_transfer_receipt_id
 * @property int $stock_transfer_line_id
 * @property int|null $destination_product_batch_id
 * @property string $received_destination_quantity_base
 */
#[Fillable(['merchant_id', 'stock_transfer_receipt_id', 'stock_transfer_line_id', 'destination_product_batch_id', 'destination_cost_entry_id', 'received_destination_quantity_base'])]

class StockTransferReceiptLine extends Model
{
    /** @return BelongsTo<StockTransferReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockTransferReceipt::class, 'stock_transfer_receipt_id');
    }

    /** @return BelongsTo<StockTransferLine, $this> */
    public function transferLine(): BelongsTo
    {
        return $this->belongsTo(StockTransferLine::class, 'stock_transfer_line_id');
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function destinationBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'destination_product_batch_id');
    }

    protected function casts(): array
    {
        return ['received_destination_quantity_base' => 'decimal:6'];
    }
}
