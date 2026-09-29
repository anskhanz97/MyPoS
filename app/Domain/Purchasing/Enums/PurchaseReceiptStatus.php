<?php

namespace App\Domain\Purchasing\Enums;

enum PurchaseReceiptStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
}
