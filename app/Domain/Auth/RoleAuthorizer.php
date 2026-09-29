<?php

namespace App\Domain\Auth;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Tenancy\TenantAccess;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Store;
use App\Models\User;

final class RoleAuthorizer
{
    public function __construct(
        private readonly TenantAccess $tenantAccess,
        private readonly FixedRolePermissions $permissions,
    ) {}

    public function authorize(User $user, Merchant $merchant, Permission $permission, ?Store $store = null): MerchantMembership
    {
        $membership = $store === null
            ? $this->tenantAccess->membershipOrNotFound($user, $merchant)
            : $this->tenantAccess->assignedStoreOrNotFound($user, $merchant, $store);

        abort_unless($this->permissions->allows($membership->role, $permission), 403, 'This fixed role does not allow the operation.');

        return $membership;
    }
}
