<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\FixedRolePermissions;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FixedRolePermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{MembershipRole, Permission, bool}> */
    public static function permissionCases(): array
    {
        return [
            'owner sees payroll' => [MembershipRole::Owner, Permission::PayrollView, true],
            'manager cannot see payroll' => [MembershipRole::Manager, Permission::PayrollView, false],
            'cashier cannot see payroll' => [MembershipRole::Cashier, Permission::PayrollView, false],
            'manager operates inventory' => [MembershipRole::Manager, Permission::InventoryManage, true],
            'manager publishes pricing' => [MembershipRole::Manager, Permission::PricingPublish, true],
            'cashier cannot adjust inventory' => [MembershipRole::Cashier, Permission::InventoryManage, false],
            'cashier can sell' => [MembershipRole::Cashier, Permission::SaleCreate, true],
            'cashier can operate assigned cash session' => [MembershipRole::Cashier, Permission::CashOperate, true],
            'cashier cannot pay out drawer cash' => [MembershipRole::Cashier, Permission::CashManage, false],
            'manager cannot manage credentials' => [MembershipRole::Manager, Permission::IntegrationCredentials, false],
        ];
    }

    #[DataProvider('permissionCases')]
    public function test_fixed_roles_enforce_sensitive_permissions(MembershipRole $role, Permission $permission, bool $expected): void
    {
        $this->assertSame($expected, (new FixedRolePermissions)->allows($role, $permission));
    }

    public function test_permission_api_hides_payroll_from_manager_and_returns_404_cross_tenant(): void
    {
        $manager = User::factory()->create();
        $merchant = Merchant::factory()->create();
        MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);

        $response = $this->actingAs($manager)->getJson("/api/v1/merchants/{$merchant->public_id}/permissions")->assertOk();
        $this->assertNotContains(Permission::PayrollView->value, $response->json('data.permissions'));
        $this->assertContains(Permission::InventoryManage->value, $response->json('data.permissions'));

        $other = Merchant::factory()->create();
        $this->actingAs($manager)->getJson("/api/v1/merchants/{$other->public_id}/permissions")->assertNotFound();
    }

    public function test_platform_operator_identity_does_not_grant_merchant_membership(): void
    {
        $operator = User::factory()->create();
        PlatformOperator::factory()->for($operator)->create();
        Merchant::factory()->create();

        $this->actingAs($operator)->getJson('/api/v1/merchants')->assertOk()->assertJsonCount(0, 'data');
    }
}
