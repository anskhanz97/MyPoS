<?php

namespace App\Domain\Catalog\Enums;

enum ProductType: string
{
    case Standard = 'standard';
    case Weighted = 'weighted';
    case Bundle = 'bundle';
}
