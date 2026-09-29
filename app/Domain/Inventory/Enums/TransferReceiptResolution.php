<?php

namespace App\Domain\Inventory\Enums;

enum TransferReceiptResolution: string
{
    case Partial = 'partial';
    case Complete = 'complete';
    case ShortClose = 'short_close';
    case ExcessAccept = 'excess_accept';
}
