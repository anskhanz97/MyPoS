<?php

namespace App\Domain\Inventory\Enums;

enum StockTransferState: string
{
    case Draft = 'draft';
    case Dispatched = 'dispatched';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
