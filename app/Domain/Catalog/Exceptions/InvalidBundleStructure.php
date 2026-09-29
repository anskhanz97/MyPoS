<?php

namespace App\Domain\Catalog\Exceptions;

use RuntimeException;

final class InvalidBundleStructure extends RuntimeException
{
    public function __construct(string $message = 'The bundle structure is recursive or invalid.')
    {
        parent::__construct($message);
    }
}
