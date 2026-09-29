<?php

namespace App\Domain\Devices\Enums;

enum DeviceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case LeaseExpired = 'lease_expired';
    case Revoked = 'revoked';
    case Replaced = 'replaced';
}
