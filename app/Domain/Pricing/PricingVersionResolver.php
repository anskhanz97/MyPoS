<?php

namespace App\Domain\Pricing;

use App\Models\Merchant;
use App\Models\PriceBookVersion;
use App\Models\Store;
use Carbon\CarbonInterface;

final class PricingVersionResolver
{
    /** @return array{merchant: PriceBookVersion|null, store: PriceBookVersion|null, version: string} */
    public function current(Merchant $merchant, Store $store, ?CarbonInterface $at = null): array
    {
        abort_unless($store->merchant_id === $merchant->id, 404);
        $at ??= now();
        $base = PriceBookVersion::query()->where('merchant_id', $merchant->id)->whereNull('store_id')->where('effective_from', '<=', $at)->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))->orderByDesc('version')->first();
        $override = PriceBookVersion::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('effective_from', '<=', $at)->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))->orderByDesc('version')->first();
        $base?->load(['prices.variant.product', 'rules']);
        $override?->load(['prices.variant.product', 'rules']);
        abort_if($base === null && $override === null, 409, 'No published pricing version is effective for this store.');

        return ['merchant' => $base, 'store' => $override, 'version' => 'm'.($base instanceof PriceBookVersion ? $base->version : 0).'-s'.($override instanceof PriceBookVersion ? $override->version : 0)];
    }
}
