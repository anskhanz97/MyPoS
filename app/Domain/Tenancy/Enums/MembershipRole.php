<?php

namespace App\Domain\Tenancy\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
}
