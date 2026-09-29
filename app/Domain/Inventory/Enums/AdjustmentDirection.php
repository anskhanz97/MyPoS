<?php

namespace App\Domain\Inventory\Enums;

enum AdjustmentDirection: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
    case Both = 'both';
}
