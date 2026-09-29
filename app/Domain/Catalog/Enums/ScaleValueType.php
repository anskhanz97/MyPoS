<?php

namespace App\Domain\Catalog\Enums;

enum ScaleValueType: string
{
    case Quantity = 'quantity';
    case Price = 'price';
}
