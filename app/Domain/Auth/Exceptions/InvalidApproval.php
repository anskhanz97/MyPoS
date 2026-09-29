<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

class InvalidApproval extends RuntimeException
{
    public function __construct(string $message = 'The approval is invalid, expired or already used.')
    {
        parent::__construct($message);
    }
}
