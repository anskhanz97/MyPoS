<?php

namespace App\Domain\Inventory\Enums;

enum InventoryDisposition: string
{
    case Sellable = 'sellable';
    case Unavailable = 'unavailable';
    case Quarantine = 'quarantine';
}
