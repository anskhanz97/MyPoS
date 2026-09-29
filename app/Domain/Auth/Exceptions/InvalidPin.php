<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

class InvalidPin extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The staff credentials are invalid for this device.');
    }
}
