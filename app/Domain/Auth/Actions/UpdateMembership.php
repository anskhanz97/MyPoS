<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\StaffDeviceSession;
use App\Models\Store;
use App\Models\StoreAssignment;
use Illuminate\Support\Facades\DB;

final class UpdateMembership
{
    /** @param array{role?: string, status?: string, pin?: string, store_ids?: list<string>} $data */
    public function handle(Merchant $merchant, MerchantMembership $membership, array $data): MerchantMembership
    {
        abort_unless($membership->merchant_id === $merchant->id && $membership->role !== MembershipRole::Owner, 404);

        return DB::transaction(function () use ($merchant, $membership, $data): MerchantMembership {
            $changes = [];
            if (isset($data['role'])) {
                $changes['role'] = MembershipRole::from($data['role']);
            }
            if (isset($data['status'])) {
                $changes['status'] = MembershipStatus::from($data['status']);
                $changes['revoked_at'] = $data['status'] === MembershipStatus::Inactive->value ? now() : null;
            }
            if (isset($data['pin'])) {
                $changes['pin_hash'] = $data['pin'];
                $changes['pin_changed_at'] = now();
            }
            $membership->update($changes);

            if (isset($data['store_ids'])) {
                StoreAssignment::query()->where('merchant_membership_id', $membership->id)->delete();
                foreach ($data['store_ids'] as $publicId) {
                    $store = (new Store)->resolveRouteBinding($publicId);
                    abort_unless($store instanceof Store && $store->merchant_id === $merchant->id, 404);
                    StoreAssignment::query()->create([
                        'merchant_id' => $merchant->id,
                        'merchant_membership_id' => $membership->id,
                        'store_id' => $store->id,
                    ]);
                }
            }

            StaffDeviceSession::query()->where('merchant_membership_id', $membership->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $membership->load(['user', 'storeAssignments.store']);
        });
    }
}
