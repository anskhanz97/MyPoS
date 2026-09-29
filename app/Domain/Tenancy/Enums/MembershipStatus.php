<?php

namespace App\Domain\Tenancy\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
