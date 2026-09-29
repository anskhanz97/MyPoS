<?php

namespace App\Domain\Devices\Exceptions;

use RuntimeException;

final class InvalidDeviceCredential extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The device credential is invalid, expired or revoked.');
    }
}
