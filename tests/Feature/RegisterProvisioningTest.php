<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Devices\Enums\DeviceStatus;
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

class RegisterProvisioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_creates_and_lists_a_register_with_explicit_capabilities(): void
    {
        $fixture = $this->fixture();
        $url = $this->registersUrl($fixture['merchant'], $fixture['store']);
        $response = $this->actingAs($fixture['owner'])->postJson($url, [
            'name' => 'Front counter', 'code' => ' counter-01 ',
            'capabilities' => ['scanner' => true, 'printer' => true, 'cash_drawer' => true, 'customer_display' => false],
        ])->assertCreated()->assertJsonPath('data.code', 'COUNTER-01')->assertJsonPath('data.capabilities.customer_display', false)->assertJsonPath('data.fencing_epoch', 1);

        $this->actingAs($fixture['owner'])->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('security_audit_events', ['event_code' => 'register.created', 'outcome' => 'succeeded']);
        $this->assertStringNotContainsString('device_key', $response->getContent());
    }

    public function test_register_codes_are_unique_per_store_and_fixed_roles_cannot_manage_devices(): void
    {
        $fixture = $this->fixture();
        $url = $this->registersUrl($fixture['merchant'], $fixture['store']);
        Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'COUNTER-01']);
        $payload = ['name' => 'Duplicate', 'code' => 'counter-01', 'capabilities' => $this->capabilities()];

        $this->actingAs($fixture['owner'])->postJson($url, $payload)->assertUnprocessable()->assertJsonPath('field_errors.0.pointer', '/code');
        $this->actingAs($fixture['manager'])->postJson($url, [...$payload, 'code' => 'COUNTER-02'])->assertForbidden();
        $this->actingAs($fixture['cashier'])->getJson($url)->assertForbidden();
    }

    public function test_pairing_is_one_time_and_bootstrap_returns_only_scoped_configuration(): void
    {
        $fixture = $this->fixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $pairing = $this->actingAs($fixture['owner'])->postJson($this->pairingUrl($fixture['merchant'], $fixture['store'], $register), [])
            ->assertCreated()->assertJsonStructure(['data' => ['pairing_id', 'pairing_code', 'expires_at']]);
        $code = $pairing->json('data.pairing_code');
        $enrollment = $this->postJson('/api/v1/device-pairings/consume', ['pairing_code' => $code, 'device_name' => 'Counter browser', 'protocol_version' => 1])
            ->assertCreated()->assertJsonMissingPath('data.device.device_key_hash');
        $deviceKey = $enrollment->json('data.device_key');
        $deviceId = $enrollment->json('data.device.id');
        $device = DeviceEnrollment::query()->sole();

        $this->assertNotSame($deviceKey, $device->getRawOriginal('device_key_hash'));
        $this->assertTrue(Hash::check($deviceKey, $device->device_key_hash));
        $this->postJson('/api/v1/device-pairings/consume', ['pairing_code' => $code, 'device_name' => 'Replay', 'protocol_version' => 1])->assertConflict();
        $bootstrap = $this->withHeader('X-Device-Key', $deviceKey)->getJson("/api/v1/devices/{$deviceId}/bootstrap")
            ->assertOk()->assertJsonPath('data.tenant.merchant_id', $fixture['merchant']->public_id)
            ->assertJsonPath('data.tenant.store_id', $fixture['store']->public_id)
            ->assertJsonPath('data.coordination.writer_lock_name', "mypos:{$deviceId}:writer")
            ->assertJsonPath('data.coordination.fencing_epoch', 2)->assertJsonMissingPath('data.device.device_key_hash');
        $this->assertStringNotContainsString($deviceKey, $bootstrap->getContent());
        $this->assertStringNotContainsString($code, SecurityAuditEvent::query()->get()->toJson());
    }

    public function test_cross_merchant_register_pairing_and_revocation_substitution_is_rejected(): void
    {
        $fixture = $this->fixture();
        $other = $this->fixture();
        $otherRegister = Register::factory()->create(['merchant_id' => $other['merchant']->id, 'store_id' => $other['store']->id]);
        $otherDevice = DeviceEnrollment::factory()->create(['merchant_id' => $other['merchant']->id, 'store_id' => $other['store']->id, 'register_id' => $otherRegister->id]);

        $this->actingAs($fixture['owner'])->postJson($this->pairingUrl($fixture['merchant'], $fixture['store'], $otherRegister), [])->assertNotFound();
        $this->actingAs($fixture['owner'])->deleteJson($this->deviceUrl($fixture['merchant'], $fixture['store'], $otherDevice), ['reason' => 'Tenant substitution'])->assertNotFound();
    }

    public function test_controlled_replacement_fences_and_revokes_the_previous_device_and_staff_session(): void
    {
        $fixture = $this->fixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $oldDevice = DeviceEnrollment::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'register_id' => $register->id]);
        $staffSession = StaffDeviceSession::query()->create([
            'merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'device_enrollment_id' => $oldDevice->id,
            'merchant_membership_id' => $fixture['cashierMembership']->id, 'session_token_hash' => hash('sha256', 'staff-token'),
            'started_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($fixture['owner'])->postJson($this->pairingUrl($fixture['merchant'], $fixture['store'], $register), [])->assertConflict();
        $code = $this->actingAs($fixture['owner'])->postJson($this->pairingUrl($fixture['merchant'], $fixture['store'], $register), ['replace_existing' => true])
            ->assertCreated()->json('data.pairing_code');
        $newDevice = $this->postJson('/api/v1/device-pairings/consume', ['pairing_code' => $code, 'device_name' => 'Replacement', 'protocol_version' => 1])
            ->assertCreated()->json('data.device');

        $this->assertSame(DeviceStatus::Replaced, $oldDevice->refresh()->status);
        $this->assertNotNull($staffSession->refresh()->revoked_at);
        $this->assertSame(2, $register->refresh()->fencing_epoch);
        $this->assertSame(2, $newDevice['fencing_epoch']);
        $this->assertDatabaseHas('device_lifecycle_events', ['device_enrollment_id' => $oldDevice->id, 'event_code' => 'devices.enrollment.replaced']);
    }

    public function test_revocation_immediately_denies_bootstrap_and_staff_session_use(): void
    {
        $fixture = $this->fixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $device = DeviceEnrollment::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'register_id' => $register->id, 'device_key_hash' => Hash::make('device-secret')]);
        $session = StaffDeviceSession::query()->create([
            'merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'device_enrollment_id' => $device->id,
            'merchant_membership_id' => $fixture['cashierMembership']->id, 'session_token_hash' => hash('sha256', 'staff-token'),
            'started_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($fixture['owner'])->deleteJson($this->deviceUrl($fixture['merchant'], $fixture['store'], $device), ['reason' => 'Browser retired'])
            ->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->withHeader('X-Device-Key', 'device-secret')->getJson("/api/v1/devices/{$device->public_id}/bootstrap")->assertUnauthorized();
        $this->withHeader('X-Staff-Session', 'staff-token')->postJson("/api/v1/devices/{$device->public_id}/approvals", [
            'approver_membership_id' => $fixture['cashierMembership']->public_id,
            'approver_pin' => '1234',
            'permission' => Permission::SaleCreate->value,
            'target_type' => 'sale',
            'target_id' => (string) Str::uuid7(),
            'reason' => 'Revoked device denial',
        ])->assertUnauthorized();
        $this->assertNotNull($session->refresh()->revoked_at);
        $this->assertDatabaseHas('device_lifecycle_events', ['device_enrollment_id' => $device->id, 'event_code' => 'devices.enrollment.revoked', 'reason' => 'Browser retired']);
    }

    public function test_expired_device_lease_denies_online_bootstrap(): void
    {
        $fixture = $this->fixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $device = DeviceEnrollment::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'register_id' => $register->id, 'device_key_hash' => Hash::make('device-secret'), 'lease_expires_at' => now()->subMinute()]);

        $this->withHeader('X-Device-Key', 'device-secret')->getJson("/api/v1/devices/{$device->public_id}/bootstrap")->assertUnauthorized();
    }

    public function test_public_pairing_attempts_are_rate_limited(): void
    {
        $payload = ['pairing_code' => 'BADCODE1', 'device_name' => 'Unknown browser', 'protocol_version' => 1];
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/device-pairings/consume', $payload)->assertConflict();
        }

        $this->postJson('/api/v1/device-pairings/consume', $payload)->assertTooManyRequests();
    }

    /** @return array{merchant: Merchant, store: Store, owner: User, manager: User, cashier: User, cashierMembership: MerchantMembership} */
    private function fixture(): array
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $users = [];
        $memberships = [];
        foreach (MembershipRole::cases() as $role) {
            $user = User::factory()->create();
            $membership = MerchantMembership::factory()->for($merchant)->for($user)->create(['role' => $role]);
            StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $store->id]);
            $users[$role->value] = $user;
            $memberships[$role->value] = $membership;
        }

        return ['merchant' => $merchant, 'store' => $store, 'owner' => $users['owner'], 'manager' => $users['manager'], 'cashier' => $users['cashier'], 'cashierMembership' => $memberships['cashier']];
    }

    /** @return array{scanner: bool, printer: bool, cash_drawer: bool, customer_display: bool} */
    private function capabilities(): array
    {
        return ['scanner' => true, 'printer' => false, 'cash_drawer' => false, 'customer_display' => false];
    }

    private function registersUrl(Merchant $merchant, Store $store): string
    {
        return "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/registers";
    }

    private function pairingUrl(Merchant $merchant, Store $store, Register $register): string
    {
        return $this->registersUrl($merchant, $store)."/{$register->public_id}/pairing-codes";
    }

    private function deviceUrl(Merchant $merchant, Store $store, DeviceEnrollment $device): string
    {
        return "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/devices/{$device->public_id}";
    }
}
