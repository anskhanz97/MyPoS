<?php

namespace App\Domain\Checkout\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Converted = 'converted';
    case Abandoned = 'abandoned';
}
