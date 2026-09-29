<?php

namespace App\Domain\Purchasing\Enums;

enum PurchaseOrderState: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
