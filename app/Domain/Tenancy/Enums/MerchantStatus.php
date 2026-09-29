<?php

namespace App\Domain\Tenancy\Enums;

enum MerchantStatus: string
{
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
