<?php

namespace App\Domain\Catalog\Enums;

enum BarcodeKind: string
{
    case Unit = 'unit';
    case ScaleReference = 'scale_reference';
}
