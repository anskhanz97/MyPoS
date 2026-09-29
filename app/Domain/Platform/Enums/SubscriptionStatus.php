<?php

namespace App\Domain\Platform\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Restricted = 'restricted';
    case Cancelled = 'cancelled';
}
