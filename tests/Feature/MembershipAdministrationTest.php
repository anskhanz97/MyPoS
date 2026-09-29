<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\DeviceEnrollment;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Register;
use App\Models\StaffDeviceSession;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MembershipAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_creates_fixed_role_staff_without_exposing_pin_or_temporary_password(): void
    {
        Notification::fake();
        [$owner, $merchant, $store] = $this->ownerFixture();

        $response = $this->actingAs($owner)->postJson("/api/v1/merchants/{$merchant->public_id}/memberships", [
            'name' => 'Counter Staff', 'email' => 'cashier@example.test', 'role' => 'cashier', 'pin' => '1357',
            'store_ids' => [$store->public_id],
        ])->assertCreated()->assertJsonPath('data.role', 'cashier')->assertJsonPath('data.stores.0.id', $store->public_id);

        $this->assertStringNotContainsString('1357', $response->getContent());
        $membership = MerchantMembership::query()->where('merchant_id', $merchant->id)->where('role', 'cashier')->sole();
        $this->assertTrue(Hash::check('1357', $membership->pin_hash));
        $this->assertSame(1, $membership->storeAssignments()->count());
        Notification::assertSentTo($membership->user, ResetPassword::class);
    }

    public function test_cross_tenant_store_substitution_creates_no_staff_record(): void
    {
        [$owner, $merchant] = $this->ownerFixture();
        $foreignStore = Store::factory()->create();

        $this->actingAs($owner)->postJson("/api/v1/merchants/{$merchant->public_id}/memberships", [
            'name' => 'Invalid Staff', 'email' => 'invalid@example.test', 'role' => 'cashier', 'pin' => '1357',
            'store_ids' => [$foreignStore->public_id],
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.test']);
    }

    public function test_manager_cannot_use_owner_staff_administration_url(): void
    {
        [, $merchant] = $this->ownerFixture();
        $manager = User::factory()->create();
        MerchantMembership::factory()->for($merchant)->for($manager)->create(['role' => MembershipRole::Manager]);

        $this->actingAs($manager)->getJson("/api/v1/merchants/{$merchant->public_id}/memberships")->assertForbidden();
    }

    public function test_deactivating_membership_revokes_device_sessions_and_preserves_attribution(): void
    {
        [$owner, $merchant, $store] = $this->ownerFixture();
        $cashier = MerchantMembership::factory()->withPin()->for($merchant)->create(['role' => MembershipRole::Cashier]);
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $cashier->id, 'store_id' => $store->id]);
        $register = Register::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id]);
        $device = DeviceEnrollment::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id]);
        $session = StaffDeviceSession::query()->create([
            'merchant_id' => $merchant->id, 'store_id' => $store->id, 'device_enrollment_id' => $device->id,
            'merchant_membership_id' => $cashier->id, 'session_token_hash' => hash('sha256', 'session-token'),
            'started_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($owner)->patchJson("/api/v1/merchants/{$merchant->public_id}/memberships/{$cashier->public_id}", ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->assertNotNull($session->refresh()->revoked_at);
        $this->assertModelExists($cashier->refresh());
        $this->assertNotNull($cashier->revoked_at);
    }

    public function test_membership_update_rejects_an_empty_change_set(): void
    {
        [$owner, $merchant] = $this->ownerFixture();
        $cashier = MerchantMembership::factory()->for($merchant)->create(['role' => MembershipRole::Cashier]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/merchants/{$merchant->public_id}/memberships/{$cashier->public_id}", [])
            ->assertUnprocessable()
            ->assertJsonPath('field_errors.0.pointer', '/membership');
    }

    /** @return array{User, Merchant, Store} */
    private function ownerFixture(): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $membership = MerchantMembership::factory()->owner()->for($merchant)->for($owner)->create();
        StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $store->id]);

        return [$owner, $merchant, $store];
    }
}
