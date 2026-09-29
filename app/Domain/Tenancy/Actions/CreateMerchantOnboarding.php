<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\CustomerScope;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\MerchantStatus;
use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\SettingsVersion;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateMerchantOnboarding
{
    /**
     * @param  array{name: string, slug: string, store_data_mode: string, timezone: string, store_name: string, store_code: string}  $data
     */
    public function handle(User $owner, array $data): Merchant
    {
        return DB::transaction(function () use ($owner, $data): Merchant {
            $merchant = Merchant::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'store_data_mode' => StoreDataMode::from($data['store_data_mode']),
                'status' => MerchantStatus::Active,
                'timezone' => $data['timezone'],
                'currency' => 'PKR',
                'store_data_mode_locked_at' => now(),
            ]);

            $store = Store::query()->create([
                'merchant_id' => $merchant->id,
                'name' => $data['store_name'],
                'code' => $data['store_code'],
                'timezone' => $data['timezone'],
                'is_active' => true,
            ]);

            $membership = MerchantMembership::query()->create([
                'merchant_id' => $merchant->id,
                'user_id' => $owner->id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);

            StoreAssignment::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_membership_id' => $membership->id,
                'store_id' => $store->id,
            ]);

            SettingsVersion::query()->create([
                'merchant_id' => $merchant->id,
                'store_id' => null,
                'scope' => CustomerScope::Merchant,
                'version' => 1,
                'payload' => ['store_data_mode' => $merchant->store_data_mode->value],
                'created_by_user_id' => $owner->id,
                'published_at' => now(),
                'effective_at' => now(),
            ]);

            return $merchant->load('stores');
        });
    }
}
