<?php

namespace App\Domain\Inventory\Enums;

enum InventoryCostState: string
{
    case Pending = 'pending';
    case Provisional = 'provisional';
    case Final = 'final';
    case NotApplicable = 'not_applicable';
}
