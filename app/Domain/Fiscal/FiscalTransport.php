<?php

namespace App\Domain\Fiscal;

use App\Models\FiscalConfiguration;

interface FiscalTransport
{
    /** @param array<string,mixed> $payload */
    public function submit(FiscalConfiguration $configuration, array $payload): FiscalTransportResult;
}
