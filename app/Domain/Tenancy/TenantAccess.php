<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Store;
use App\Models\User;

final class TenantAccess
{
    public function membershipOrNotFound(User $user, Merchant $merchant): MerchantMembership
    {
        return MerchantMembership::query()
            ->whereBelongsTo($merchant)
            ->whereBelongsTo($user)
            ->where('status', MembershipStatus::Active)
            ->firstOrFail();
    }

    public function ownerOrForbidden(User $user, Merchant $merchant): MerchantMembership
    {
        $membership = $this->membershipOrNotFound($user, $merchant);

        abort_unless($membership->role === MembershipRole::Owner, 403, 'Owner access is required.');

        return $membership;
    }

    public function assignedStoreOrNotFound(User $user, Merchant $merchant, Store $store): MerchantMembership
    {
        abort_unless($store->merchant_id === $merchant->id, 404);

        $membership = $this->membershipOrNotFound($user, $merchant);

        abort_unless(
            $membership->role === MembershipRole::Owner
                || $membership->storeAssignments()->whereBelongsTo($store)->exists(),
            404,
        );

        return $membership;
    }
}
