<?php

namespace App\Domain\Tenancy;

use JsonSerializable;

final readonly class TenantContext implements JsonSerializable
{
    public function __construct(
        public string $merchantPublicId,
        public string $storePublicId,
    ) {}

    /** @return array{merchant_id: string, store_id: string} */
    public function jsonSerialize(): array
    {
        return [
            'merchant_id' => $this->merchantPublicId,
            'store_id' => $this->storePublicId,
        ];
    }

    /** @param array{merchant_id: string, store_id: string} $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload['merchant_id'], $payload['store_id']);
    }
}
