<?php

namespace Tests\Feature;

use App\Domain\Auth\Actions\ConsumeApproval;
use App\Domain\Auth\Actions\IssueApproval;
use App\Domain\Auth\Actions\SwitchDeviceStaff;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Exceptions\InvalidApproval;
use App\Domain\Auth\Exceptions\InvalidPin;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\DeviceEnrollment;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Register;
use App\Models\SecurityAuditEvent;
use App\Models\StaffDeviceSession;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PinSwitchAndApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_device_can_switch_staff_and_revokes_the_previous_staff_session(): void
    {
        $fixture = $this->fixture();

        $first = $this->withHeader('X-Device-Key', 'device-secret')->postJson(
            "/api/v1/devices/{$fixture['device']->public_id}/staff-sessions",
            ['membership_id' => $fixture['cashier']->public_id, 'pin' => '1357'],
        )->assertCreated()->assertJsonStructure(['data' => ['session_id', 'staff_session_token', 'expires_at']]);
        $this->assertStringNotContainsString($fixture['cashier']->pin_hash, $first->getContent());

        $this->withHeader('X-Device-Key', 'device-secret')->postJson(
            "/api/v1/devices/{$fixture['device']->public_id}/staff-sessions",
            ['membership_id' => $fixture['manager']->public_id, 'pin' => '2468'],
        )->assertCreated();

        $sessions = StaffDeviceSession::query()->oldest('id')->get();
        $this->assertNotNull($sessions->first()->revoked_at);
        $this->assertNull($sessions->last()->revoked_at);
        $this->assertSame(2, SecurityAuditEvent::query()->where('event_code', 'staff_pin_switch')->where('outcome', 'succeeded')->count());
    }

    public function test_wrong_pin_cross_store_staff_and_revoked_device_are_rejected(): void
    {
        $fixture = $this->fixture();
        $otherStore = Store::factory()->for($fixture['merchant'])->create();
        $otherStaff = MerchantMembership::factory()->withPin('9999')->for($fixture['merchant'])->create();
        StoreAssignment::query()->create(['merchant_id' => $fixture['merchant']->id, 'merchant_membership_id' => $otherStaff->id, 'store_id' => $otherStore->id]);

        $switch = app(SwitchDeviceStaff::class);
        foreach ([[$fixture['cashier'], '0000'], [$otherStaff, '9999']] as [$membership, $pin]) {
            try {
                $switch->handle($fixture['device'], $membership, 'device-secret', $pin);
                $this->fail('Invalid staff switch unexpectedly succeeded.');
            } catch (InvalidPin) {
                $this->addToAssertionCount(1);
            }
        }

        $fixture['device']->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->expectException(InvalidPin::class);
        $switch->handle($fixture['device'], $fixture['cashier'], 'device-secret', '1357');
    }

    public function test_pin_endpoint_limits_repeated_failures_without_logging_the_pin(): void
    {
        $fixture = $this->fixture();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('X-Device-Key', 'device-secret')->postJson(
                "/api/v1/devices/{$fixture['device']->public_id}/staff-sessions",
                ['membership_id' => $fixture['cashier']->public_id, 'pin' => '0000'],
            )->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');
        }

        $this->withHeader('X-Device-Key', 'device-secret')->postJson(
            "/api/v1/devices/{$fixture['device']->public_id}/staff-sessions",
            ['membership_id' => $fixture['cashier']->public_id, 'pin' => '0000'],
        )->assertTooManyRequests();
        SecurityAuditEvent::query()->get()->each(function (SecurityAuditEvent $event): void {
            $this->assertArrayNotHasKey('pin', $event->metadata);
            $this->assertArrayNotHasKey('device_key', $event->metadata);
        });
    }

    public function test_approval_is_scope_bound_short_lived_and_single_use(): void
    {
        $fixture = $this->fixture();
        $switched = app(SwitchDeviceStaff::class)->handle($fixture['device'], $fixture['cashier'], 'device-secret', '1357');
        $targetId = (string) Str::uuid7();
        $issued = app(IssueApproval::class)->handle(
            $switched['session'], $fixture['manager'], Permission::DrawerNoSale, 'cash_session', $targetId, 'Verified float correction', '2468',
        );

        $this->assertTrue($issued['approval']->expires_at->between(now()->addMinutes(4), now()->addMinutes(6)));
        $consumed = app(ConsumeApproval::class)->handle(
            $issued['token'], $fixture['merchant']->id, $fixture['store']->id, Permission::DrawerNoSale, 'cash_session', $targetId,
        );
        $this->assertNotNull($consumed->consumed_at);

        $this->expectException(InvalidApproval::class);
        app(ConsumeApproval::class)->handle(
            $issued['token'], $fixture['merchant']->id, $fixture['store']->id, Permission::DrawerNoSale, 'cash_session', $targetId,
        );
    }

    public function test_expired_approval_is_rejected(): void
    {
        $fixture = $this->fixture();
        $switched = app(SwitchDeviceStaff::class)->handle($fixture['device'], $fixture['cashier'], 'device-secret', '1357');
        $targetId = (string) Str::uuid7();
        $issued = app(IssueApproval::class)->handle(
            $switched['session'], $fixture['manager'], Permission::DrawerNoSale, 'cash_session', $targetId, 'Verified reason', '2468',
        );
        $this->travel(6)->minutes();

        $this->expectException(InvalidApproval::class);
        app(ConsumeApproval::class)->handle(
            $issued['token'], $fixture['merchant']->id, $fixture['store']->id, Permission::DrawerNoSale, 'cash_session', $targetId,
        );
    }

    public function test_approval_api_returns_the_single_use_token_without_secret_material(): void
    {
        $fixture = $this->fixture();
        $switched = app(SwitchDeviceStaff::class)->handle($fixture['device'], $fixture['cashier'], 'device-secret', '1357');
        $targetId = (string) Str::uuid7();

        $response = $this->withHeaders([
            'X-Staff-Session' => $switched['token'],
        ])->postJson("/api/v1/devices/{$fixture['device']->public_id}/approvals", [
            'approver_membership_id' => $fixture['manager']->public_id,
            'approver_pin' => '2468',
            'permission' => Permission::DrawerNoSale->value,
            'target_type' => 'cash_session',
            'target_id' => $targetId,
            'reason' => 'Manager verified drawer check',
        ])->assertCreated()->assertJsonPath('data.target_id', $targetId)->assertJsonStructure(['approval_token']);

        $this->assertStringNotContainsString('2468', $response->getContent());
        $this->assertStringNotContainsString('pin_hash', $response->getContent());
    }

    public function test_approval_rejects_self_approval_cashier_approval_and_target_substitution(): void
    {
        $fixture = $this->fixture();
        $switched = app(SwitchDeviceStaff::class)->handle($fixture['device'], $fixture['cashier'], 'device-secret', '1357');
        $targetId = (string) Str::uuid7();

        foreach ([$fixture['cashier'], $fixture['owner']] as $approver) {
            if ($approver->id === $fixture['owner']->id) {
                $ownerSession = $switched['session']->replicate();
                $ownerSession->merchant_membership_id = $fixture['owner']->id;
                $requester = $ownerSession;
            } else {
                $requester = $switched['session'];
            }
            try {
                app(IssueApproval::class)->handle($requester, $approver, Permission::PayrollManage, 'payroll', $targetId, 'Attempt', $approver->id === $fixture['cashier']->id ? '1357' : '8642');
                $this->fail('Invalid approval unexpectedly succeeded.');
            } catch (InvalidApproval) {
                $this->addToAssertionCount(1);
            }
        }

        $issued = app(IssueApproval::class)->handle($switched['session'], $fixture['manager'], Permission::DrawerNoSale, 'cash_session', $targetId, 'Valid reason', '2468');
        $this->expectException(InvalidApproval::class);
        app(ConsumeApproval::class)->handle($issued['token'], $fixture['merchant']->id, $fixture['store']->id, Permission::DrawerNoSale, 'cash_session', (string) Str::uuid7());
    }

    /** @return array{merchant: Merchant, store: Store, device: DeviceEnrollment, owner: MerchantMembership, manager: MerchantMembership, cashier: MerchantMembership} */
    private function fixture(): array
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $register = Register::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id]);
        $device = DeviceEnrollment::factory()->create([
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
            'register_id' => $register->id,
            'device_key_hash' => Hash::make('device-secret'),
        ]);
        $memberships = [];
        foreach ([[MembershipRole::Owner, '8642'], [MembershipRole::Manager, '2468'], [MembershipRole::Cashier, '1357']] as [$role, $pin]) {
            $membership = MerchantMembership::factory()->withPin($pin)->for($merchant)->for(User::factory())->create(['role' => $role]);
            StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $store->id]);
            $memberships[$role->value] = $membership;
        }

        return ['merchant' => $merchant, 'store' => $store, 'device' => $device, 'owner' => $memberships['owner'], 'manager' => $memberships['manager'], 'cashier' => $memberships['cashier']];
    }
}
