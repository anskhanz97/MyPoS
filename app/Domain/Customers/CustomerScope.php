<?php

namespace App\Domain\Customers;

use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Models\Merchant;
use App\Models\Store;

final class CustomerScope
{
    public function storeId(Merchant $merchant, Store $store): ?int
    {
        abort_unless($store->merchant_id === $merchant->id, 404);

        return $merchant->store_data_mode === StoreDataMode::Independent ? $store->id : null;
    }
}
