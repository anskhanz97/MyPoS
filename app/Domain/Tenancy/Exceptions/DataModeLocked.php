<?php

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

class DataModeLocked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The store data mode is locked after merchant activation.');
    }
}
