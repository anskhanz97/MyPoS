<?php

namespace App\Domain\Tenancy\Enums;

enum CustomerScope: string
{
    case Merchant = 'merchant';
    case Store = 'store';
}
