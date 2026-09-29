<?php

namespace App\Domain\Purchasing\Enums;

enum SupplierCreditStatus: string
{
    case Expected = 'expected';
    case Confirmed = 'confirmed';
    case Reversed = 'reversed';
}
