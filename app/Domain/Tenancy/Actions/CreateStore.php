<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Store;
use App\Models\StoreAssignment;
use Illuminate\Support\Facades\DB;

final class CreateStore
{
    /** @param array{name: string, code: string, timezone: string} $data */
    public function handle(Merchant $merchant, MerchantMembership $creator, array $data): Store
    {
        return DB::transaction(function () use ($merchant, $creator, $data): Store {
            $store = Store::query()->create([
                'merchant_id' => $merchant->id,
                'name' => $data['name'],
                'code' => $data['code'],
                'timezone' => $data['timezone'],
                'is_active' => true,
            ]);

            if ($creator->role === MembershipRole::Owner) {
                StoreAssignment::query()->create([
                    'merchant_id' => $merchant->id,
                    'merchant_membership_id' => $creator->id,
                    'store_id' => $store->id,
                ]);
            }

            return $store;
        });
    }
}
