<?php

namespace App\Domain\Inventory\Enums;

enum InventoryMovementKind: string
{
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Return = 'return';
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReturn = 'purchase_return';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Correction = 'correction';
    case BatchAllocation = 'batch_allocation';
    case Stocktake = 'stocktake';
}
