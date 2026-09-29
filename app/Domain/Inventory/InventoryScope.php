<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\CatalogScope;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;

final class InventoryScope
{
    public function __construct(private readonly CatalogScope $catalogScope) {}

    public function assertLocation(Merchant $merchant, Store $store, InventoryLocation $location, bool $allowArchived = false): void
    {
        abort_unless($store->merchant_id === $merchant->id
            && $location->merchant_id === $merchant->id
            && $location->store_id === $store->id
            && ($allowArchived || $location->archived_at === null), 404);
    }

    public function assertVariant(Merchant $merchant, Store $store, ProductVariant $variant, bool $allowArchived = false): void
    {
        $variant->loadMissing('product');
        $scopeStoreId = $this->catalogScope->storeId($merchant, $store);
        abort_unless($variant->merchant_id === $merchant->id
            && $variant->store_id === $scopeStoreId
            && $variant->product->store_id === $scopeStoreId
            && ($allowArchived || ($variant->archived_at === null && $variant->product->archived_at === null)), 404);
    }
}
