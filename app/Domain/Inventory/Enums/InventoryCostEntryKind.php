<?php

namespace App\Domain\Inventory\Enums;

enum InventoryCostEntryKind: string
{
    case PurchaseReceipt = 'purchase_receipt';
    case ReceiptReversal = 'receipt_reversal';
    case PurchaseReturn = 'purchase_return';
    case PurchaseReturnReversal = 'purchase_return_reversal';
    case SaleIssue = 'sale_issue';
    case SaleReturn = 'sale_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
}
