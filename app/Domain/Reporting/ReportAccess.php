<?php

namespace App\Domain\Reporting;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\FixedRolePermissions;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;

final class ReportAccess
{
    public function __construct(private readonly RoleAuthorizer $authorizer, private readonly FixedRolePermissions $permissions) {}

    /**
     * @return array{role: string, actor_id: int|null, costs: bool, customer_export: bool}
     */
    public function authorize(User $user, Merchant $merchant, ?Store $store, string $key, bool $export = false): array
    {
        $membership = $this->authorizer->authorize($user, $merchant, $store === null ? Permission::ReportConsolidated : Permission::ReportStore, $store);
        $report = ReportCatalog::get($key);
        $owner = $membership->role === MembershipRole::Owner;
        $costs = $this->permissions->allows($membership->role, Permission::ReportCosts);
        if ($report['sensitive']) {
            $this->authorizer->authorize($user, $merchant, $key === 'payroll-detail' ? Permission::PayrollManage : Permission::ReportFinancial, $store);
        }
        if ($report['group'] === 'Audit') {
            $this->authorizer->authorize($user, $merchant, Permission::AuditView, $store);
        }
        if (in_array($report['type'], ['attendance_events', 'attendance_shifts', 'rota_shifts', 'leave_requests'], true)) {
            $this->authorizer->authorize($user, $merchant, Permission::AttendanceManage, $store);
        }
        if ($membership->role === MembershipRole::Cashier) {
            abort_unless(in_array($key, ['sales-summary', 'daily-sales', 'sales-by-product', 'employees', 'shift-based', 'average-size', 'payment-method'], true), 403, 'Cashiers can view their own sales activity only.');
            abort_if($export, 403, 'Report exports require a manager or owner.');
        }
        if ($export && in_array($key, ['repeat-customer-rate', 'bookkeeping'], true)) {
            $this->authorizer->authorize($user, $merchant, Permission::CustomerExport, $store);
        }

        return ['role' => $membership->role->value, 'actor_id' => $membership->role === MembershipRole::Cashier ? $user->id : null, 'costs' => $costs, 'customer_export' => $owner];
    }
}
