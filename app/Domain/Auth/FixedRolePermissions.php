<?php

namespace App\Domain\Auth;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Tenancy\Enums\MembershipRole;

final class FixedRolePermissions
{
    /** @return list<Permission> */
    public function forRole(MembershipRole $role): array
    {
        return match ($role) {
            MembershipRole::Owner => Permission::cases(),
            MembershipRole::Manager => array_map(
                Permission::from(...),
                [
                    'catalog.view', 'catalog.manage', 'pricing.publish', 'customer.manage', 'sale.create', 'sale.view',
                    'return.create', 'discount.override', 'drawer.no_sale', 'cash.operate', 'cash.manage',
                    'cash.variance.approve', 'inventory.view', 'inventory.manage',
                    'inventory.adjust.approve', 'purchasing.manage', 'loyalty.manage',
                    'loyalty.adjust', 'attendance.own', 'attendance.manage', 'payroll.own',
                    'expense.submit', 'expense.approve', 'report.store', 'audit.view', 'approval.issue',
                ],
            ),
            MembershipRole::Cashier => array_map(
                Permission::from(...),
                [
                    'catalog.view', 'customer.manage', 'sale.create', 'sale.view', 'return.create', 'cash.operate',
                    'inventory.view', 'attendance.own', 'payroll.own', 'expense.submit', 'report.store',
                ],
            ),
        };
    }

    public function allows(MembershipRole $role, Permission $permission): bool
    {
        return in_array($permission, $this->forRole($role), true);
    }

    public function canApprove(MembershipRole $role, Permission $permission): bool
    {
        if ($role === MembershipRole::Owner) {
            return true;
        }

        return $role === MembershipRole::Manager && in_array($permission, [
            Permission::DiscountOverride,
            Permission::DrawerNoSale,
            Permission::CashVarianceApprove,
            Permission::InventoryAdjustApprove,
            Permission::ReturnCreate,
            Permission::LoyaltyAdjust,
            Permission::AttendanceManage,
            Permission::ExpenseApprove,
        ], true);
    }
}
