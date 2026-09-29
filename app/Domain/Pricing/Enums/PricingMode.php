<?php

namespace App\Domain\Pricing\Enums;

enum PricingMode: string
{
    case Manual = 'manual';
    case Advanced = 'advanced';
}
