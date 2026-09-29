<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Tenancy\Enums\CustomerScope;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\SettingsVersion;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PublishSettings
{
    /** @param array<string, mixed> $payload */
    public function handle(User $actor, Merchant $merchant, ?Store $store, int $expectedVersion, array $payload, ?string $effectiveAt): SettingsVersion
    {
        return DB::transaction(function () use ($actor, $merchant, $store, $expectedVersion, $payload, $effectiveAt): SettingsVersion {
            if ($store !== null) {
                abort_unless($store->merchant_id === $merchant->id, 404);
            }
            $query = SettingsVersion::query()->where('merchant_id', $merchant->id)->where('store_id', $store?->id)->lockForUpdate();
            $current = $query->orderByDesc('version')->first();
            $currentVersion = $current === null ? 0 : $current->version;
            abort_unless($currentVersion === $expectedVersion, 409, 'The settings version changed. Reload before publishing.');
            $settings = SettingsVersion::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store?->id,
                'scope' => $store === null ? CustomerScope::Merchant : CustomerScope::Store,
                'version' => $currentVersion + 1, 'payload' => $payload, 'created_by_user_id' => $actor->id,
                'published_at' => now(), 'effective_at' => $effectiveAt ?? now(),
            ]);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store?->id, 'actor_user_id' => $actor->id, 'event_code' => 'settings.version.published', 'outcome' => 'succeeded', 'metadata' => ['version' => $settings->version, 'scope' => $settings->scope->value, 'changed_keys' => array_keys($payload)], 'occurred_at' => now()]);

            return $settings;
        });
    }
}
