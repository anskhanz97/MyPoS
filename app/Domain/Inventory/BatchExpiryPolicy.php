<?php

namespace App\Domain\Inventory;

use App\Models\ProductBatch;
use App\Models\Store;
use Carbon\CarbonImmutable;

final class BatchExpiryPolicy
{
    public function businessDate(Store $store, ?CarbonImmutable $instant = null): CarbonImmutable
    {
        $instant ??= CarbonImmutable::now('UTC');

        return $instant->setTimezone($store->timezone)->startOfDay();
    }

    public function isExpired(ProductBatch $batch, Store $store, ?CarbonImmutable $instant = null): bool
    {
        return $this->expiryDate($batch, $store)->lessThan($this->businessDate($store, $instant));
    }

    public function daysUntilExpiry(ProductBatch $batch, Store $store, ?CarbonImmutable $instant = null): int
    {
        return (int) $this->businessDate($store, $instant)->diffInDays($this->expiryDate($batch, $store), false);
    }

    private function expiryDate(ProductBatch $batch, Store $store): CarbonImmutable
    {
        return CarbonImmutable::parse($batch->expiry_date->toDateString(), $store->timezone)->startOfDay();
    }
}
