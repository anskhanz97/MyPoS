<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Domain\Tenancy\Exceptions\DataModeLocked;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MerchantTenancyApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_confirm_onboarding_and_the_selected_mode_is_locked(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->postJson('/api/v1/merchants', [
            'name' => 'Northstar Retail',
            'slug' => ' Northstar-Retail ',
            'store_data_mode' => StoreDataMode::Independent->value,
            'timezone' => 'Asia/Karachi',
            'store_name' => 'Lahore Flagship',
            'store_code' => ' lhr-01 ',
            'review_confirmed' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.slug', 'northstar-retail')
            ->assertJsonPath('data.store_data_mode', 'independent')
            ->assertJsonPath('data.stores.0.code', 'LHR-01');

        $merchant = Merchant::query()->sole();
        $this->assertNotNull($merchant->store_data_mode_locked_at);
        $this->assertSame(StoreDataMode::Independent, $merchant->store_data_mode);
        $this->assertSame(1, $merchant->memberships()->count());
        $this->assertSame(1, StoreAssignment::query()->count());
        $this->assertDatabaseHas('settings_versions', [
            'merchant_id' => $merchant->id,
            'version' => 1,
            'scope' => 'merchant',
        ]);
    }

    public function test_onboarding_without_the_review_confirmation_returns_422_problem_details(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson('/api/v1/merchants', [
            'name' => 'Unreviewed Merchant',
            'slug' => 'unreviewed-merchant',
            'store_data_mode' => 'shared',
            'timezone' => 'Asia/Karachi',
            'store_name' => 'Main Store',
            'store_code' => 'MAIN',
        ]);

        $response
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('field_errors.0.pointer', '/review_confirmed');
        $this->assertDatabaseCount('merchants', 0);
    }

    public function test_merchant_list_is_derived_from_the_authenticated_users_memberships(): void
    {
        $user = User::factory()->create();
        $visible = Merchant::factory()->create(['name' => 'Visible Merchant']);
        $hidden = Merchant::factory()->create(['name' => 'Hidden Merchant']);
        MerchantMembership::factory()->for($visible)->for($user)->create();
        MerchantMembership::factory()->for($hidden)->inactive()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/merchants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->public_id);
    }

    public function test_cross_tenant_store_list_returns_404_without_confirming_the_merchant_exists(): void
    {
        $intruder = User::factory()->create();
        $merchant = Merchant::factory()->create();

        $this->actingAs($intruder)
            ->getJson("/api/v1/merchants/{$merchant->public_id}/stores")
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_manager_store_list_contains_only_explicit_assignments(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->create();
        $assigned = Store::factory()->for($merchant)->create(['name' => 'Assigned']);
        Store::factory()->for($merchant)->create(['name' => 'Unassigned']);
        $membership = MerchantMembership::factory()->for($merchant)->for($user)->create();
        StoreAssignment::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_membership_id' => $membership->id,
            'store_id' => $assigned->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/merchants/{$merchant->public_id}/stores")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->public_id);
    }

    public function test_locked_data_mode_change_returns_409_problem_details(): void
    {
        [$owner, $merchant] = $this->ownerAndMerchant(StoreDataMode::Shared);

        $this->actingAs($owner)
            ->putJson("/api/v1/merchants/{$merchant->public_id}/store-data-mode", [
                'store_data_mode' => 'independent',
            ])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'invalid_state_transition');

        $this->assertSame(StoreDataMode::Shared, $merchant->refresh()->store_data_mode);
    }

    public function test_model_layer_also_rejects_bypassing_the_locked_data_mode_api(): void
    {
        [, $merchant] = $this->ownerAndMerchant(StoreDataMode::Shared);
        $merchant->store_data_mode = StoreDataMode::Independent;

        $this->expectException(DataModeLocked::class);

        $merchant->save();
    }

    public function test_store_context_is_server_validated_and_cross_tenant_substitution_returns_404(): void
    {
        [$owner, $merchant] = $this->ownerAndMerchant();
        $store = Store::factory()->for($merchant)->create();
        $otherStore = Store::factory()->create();

        $this->actingAs($owner)
            ->postJson("/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/context")
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->public_id)
            ->assertJsonPath('data.store_id', $store->public_id);

        $this->actingAs($owner)
            ->postJson("/api/v1/merchants/{$merchant->public_id}/stores/{$otherStore->public_id}/context")
            ->assertNotFound();
    }

    /** @return array{User, Merchant} */
    private function ownerAndMerchant(StoreDataMode $mode = StoreDataMode::Shared): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->create(['store_data_mode' => $mode]);
        MerchantMembership::factory()->owner()->for($merchant)->for($owner)->create([
            'status' => MembershipStatus::Active,
            'role' => MembershipRole::Owner,
        ]);

        return [$owner, $merchant];
    }
}
