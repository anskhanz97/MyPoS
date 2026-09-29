<?php

namespace App\Domain\Inventory\Enums;

enum StocktakeState: string
{
    case Counting = 'counting';
    case Review = 'review';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
