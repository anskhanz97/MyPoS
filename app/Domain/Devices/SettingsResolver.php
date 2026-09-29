<?php

namespace App\Domain\Devices;

use App\Models\Merchant;
use App\Models\SettingsVersion;
use App\Models\Store;

final class SettingsResolver
{
    /** @return array{version: string, merchant_version: int, store_version: int|null, payload: array<string, mixed>} */
    public function current(Merchant $merchant, Store $store): array
    {
        $now = now();
        $merchantSettings = SettingsVersion::query()->where('merchant_id', $merchant->id)->whereNull('store_id')->where('effective_at', '<=', $now)->orderByDesc('version')->first();
        $storeSettings = SettingsVersion::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('effective_at', '<=', $now)->orderByDesc('version')->first();
        $merchantPayload = is_array($merchantSettings?->payload) ? $merchantSettings->payload : [];
        $storePayload = is_array($storeSettings?->payload) ? $storeSettings->payload : [];
        $merged = array_replace_recursive($merchantPayload, $storePayload);
        $merchantVersion = $merchantSettings === null ? 0 : $merchantSettings->version;
        $storeVersion = $storeSettings?->version;

        return ['version' => "m{$merchantVersion}-s".($storeVersion ?? 0), 'merchant_version' => $merchantVersion, 'store_version' => $storeVersion, 'payload' => $merged];
    }
}
