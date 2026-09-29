<?php

namespace App\Domain\Fiscal;

final readonly class FiscalTransportResult
{
    public function __construct(public string $state, public ?string $authorityReference, public ?string $datedAt, public ?string $qrValue, public ?int $httpStatus, public ?string $authorityCode, public string $safeMessage, public ?string $responseHash) {}
}
