<?php

namespace App\Domain\Purchasing\Enums;

enum PurchaseReturnStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
}
