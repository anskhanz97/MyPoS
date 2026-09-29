<?php

namespace App\Domain\Tenancy\Enums;

enum StoreDataMode: string
{
    case Shared = 'shared';
    case Independent = 'independent';
}
