<?php

namespace Tests\Feature;

use App\Domain\Customers\CustomerSnapshotBuilder;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\LoyaltyMember;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\OperationalCustomer;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shared_customers_are_available_in_each_assigned_store_and_phone_is_normalized(): void
    {
        $fixture = $this->fixture();
        $created = $this->actingAs($fixture['manager'])->postJson($this->customersUrl($fixture, $fixture['store']), [
            'local_reference' => 'CUS-SHARED', 'name' => 'Ayesha Khan', 'phone' => '0300 1234567',
            'email' => 'AYESHA@EXAMPLE.COM', 'email_receipt_preference' => true,
        ])->assertCreated()->assertJsonPath('data.scope', 'merchant')->assertJsonPath('data.normalized_phone', '+923001234567');

        $this->actingAs($fixture['manager'])->getJson($this->customersUrl($fixture, $fixture['otherStore']).'?q=03001234567')
            ->assertOk()->assertJsonPath('data.0.id', $created->json('data.id'));
        $this->assertSame('ayesha@example.com', OperationalCustomer::query()->sole()->normalized_email);
        $this->actingAs($fixture['manager'])->postJson($this->customersUrl($fixture, $fixture['store']), ['name' => 'Bad number', 'phone' => '03001234567 ext 2'])
            ->assertUnprocessable()->assertJsonPath('field_errors.0.pointer', '/phone');
        $this->actingAs($fixture['manager'])->postJson($this->customersUrl($fixture, $fixture['store']), ['name' => 'Unallocated mobile range', 'phone' => '03701234567'])
            ->assertUnprocessable()->assertJsonPath('field_errors.0.pointer', '/phone');
    }

    public function test_independent_stores_and_merchants_may_reuse_contact_details_without_exposure(): void
    {
        $fixture = $this->fixture(independent: true);
        $payload = ['local_reference' => 'LOCAL-1', 'name' => 'Same person', 'phone' => '+92 300 7654321'];
        $first = $this->actingAs($fixture['owner'])->postJson($this->customersUrl($fixture, $fixture['store']), $payload)->assertCreated()->assertJsonPath('data.scope', 'store');
        $second = $this->actingAs($fixture['owner'])->postJson($this->customersUrl($fixture, $fixture['otherStore']), $payload)->assertCreated();
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->actingAs($fixture['owner'])->getJson($this->customersUrl($fixture, $fixture['otherStore']).'/'.$first->json('data.id'))->assertNotFound();

        $other = $this->fixture();
        $this->actingAs($other['owner'])->postJson($this->customersUrl($other, $other['store']), $payload)->assertCreated();
        $this->assertSame(3, OperationalCustomer::query()->where('normalized_phone', '+923007654321')->count());
        $this->actingAs($other['owner'])->getJson($this->customersUrl($other, $other['store']).'?q=LOCAL-1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_groups_duplicates_consent_and_cashier_minimal_fields_are_enforced(): void
    {
        $fixture = $this->fixture();
        $group = $this->actingAs($fixture['manager'])->postJson($this->groupsUrl($fixture), ['code' => 'vip', 'name' => 'VIP customers'])
            ->assertCreated()->assertJsonPath('data.code', 'VIP');
        $this->actingAs($fixture['cashier'])->postJson($this->groupsUrl($fixture), ['code' => 'NO', 'name' => 'Denied'])->assertForbidden();

        $this->actingAs($fixture['manager'])->postJson($this->customersUrl($fixture, $fixture['store']), [
            'name' => 'Consent customer', 'phone' => '042 12345678', 'email' => 'consent@example.com',
            'customer_group_id' => $group->json('data.id'), 'email_receipt_preference' => false,
            'marketing_email_consent' => true, 'marketing_consent_source' => 'paper-form',
        ])->assertCreated()->assertJsonPath('data.marketing_email_consent', true)->assertJsonPath('data.email_receipt_preference', false);
        $this->actingAs($fixture['manager'])->postJson($this->customersUrl($fixture, $fixture['store']), [
            'name' => 'Missing consent source', 'email' => 'missing@example.com', 'marketing_email_consent' => true,
        ])->assertUnprocessable();

        $this->actingAs($fixture['cashier'])->getJson($this->customersUrl($fixture, $fixture['store']))
            ->assertOk()->assertJsonMissingPath('data.0.address_line')->assertJsonMissingPath('data.0.marketing_email_consent');
        $this->actingAs($fixture['cashier'])->postJson($this->customersUrl($fixture, $fixture['store']), ['name' => 'At checkout', 'phone' => '0301-2223344', 'address_line' => 'Hidden field'])
            ->assertForbidden();
        $this->actingAs($fixture['cashier'])->postJson($this->customersUrl($fixture, $fixture['store']), ['name' => 'At checkout', 'phone' => '0301-2223344'])
            ->assertCreated()->assertJsonMissingPath('data.marketing_email_consent');
        $this->actingAs($fixture['manager'])->getJson($this->customersUrl($fixture, $fixture['store']).'/duplicates?phone=03012223344')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_archive_blocks_new_sale_association_and_does_not_mutate_captured_snapshot(): void
    {
        $fixture = $this->fixture();
        $created = $this->actingAs($fixture['owner'])->postJson($this->customersUrl($fixture, $fixture['store']), [
            'local_reference' => 'HISTORY-1', 'name' => 'Original name', 'phone' => '0302 5556677',
        ])->assertCreated();
        $customer = OperationalCustomer::query()->sole();
        $snapshot = app(CustomerSnapshotBuilder::class)->build($customer);
        $url = $this->customersUrl($fixture, $fixture['store']).'/'.$created->json('data.id');

        $this->actingAs($fixture['cashier'])->getJson($url.'/sale-association')->assertOk()->assertJsonPath('data.name', 'Original name');
        $this->actingAs($fixture['owner'])->patchJson($url, ['expected_version' => 1, 'name' => 'Renamed', 'archived' => true])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->actingAs($fixture['cashier'])->getJson($url.'/sale-association')->assertNotFound();
        $this->assertSame('Original name', $snapshot['name']);
        $this->assertSame('+923025556677', $snapshot['phone']);
        $this->assertNotNull(OperationalCustomer::query()->sole()->archived_at);
    }

    public function test_loyalty_identity_is_merchant_wide_but_full_store_profiles_remain_isolated(): void
    {
        $fixture = $this->fixture(independent: true);
        $phone = '+923033334444';
        $member = LoyaltyMember::factory()->for($fixture['merchant'])->create(['canonical_phone' => $phone]);
        $customers = [];
        foreach ([$fixture['store'], $fixture['otherStore']] as $index => $store) {
            $created = $this->actingAs($fixture['owner'])->postJson($this->customersUrl($fixture, $store), [
                'local_reference' => 'LOYALTY-'.$index, 'name' => 'Store profile '.$index, 'phone' => $phone,
            ])->assertCreated();
            $this->actingAs($fixture['owner'])->putJson($this->customersUrl($fixture, $store).'/'.$created->json('data.id').'/loyalty-link', [
                'loyalty_member_id' => $member->public_id, 'expected_version' => 1,
            ])->assertOk()->assertJsonPath('data.loyalty_member_id', $member->public_id)->assertJsonPath('data.version', 2);
            $customers[] = $created->json('data.id');
        }
        $this->actingAs($fixture['owner'])->getJson($this->customersUrl($fixture, $fixture['store']).'/'.$customers[1])->assertNotFound();
        $this->actingAs($fixture['owner'])->putJson($this->customersUrl($fixture, $fixture['store']).'/'.$customers[0].'/loyalty-link', [
            'loyalty_member_id' => null, 'expected_version' => 1,
        ])->assertConflict();

        $wrong = LoyaltyMember::factory()->for($fixture['merchant'])->create(['canonical_phone' => '+923044445555']);
        $this->actingAs($fixture['owner'])->putJson($this->customersUrl($fixture, $fixture['store']).'/'.$customers[0].'/loyalty-link', [
            'loyalty_member_id' => $wrong->public_id, 'expected_version' => 2,
        ])->assertUnprocessable();
    }

    /** @return array{merchant: Merchant, store: Store, otherStore: Store, owner: User, manager: User, cashier: User} */
    private function fixture(bool $independent = false): array
    {
        $merchant = $independent ? Merchant::factory()->independent()->create() : Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $otherStore = Store::factory()->for($merchant)->create();
        $users = [];
        foreach (MembershipRole::cases() as $role) {
            $user = User::factory()->create();
            $membership = MerchantMembership::factory()->for($merchant)->for($user)->create(['role' => $role]);
            foreach ([$store, $otherStore] as $assignedStore) {
                StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $assignedStore->id]);
            }
            $users[$role->value] = $user;
        }

        return ['merchant' => $merchant, 'store' => $store, 'otherStore' => $otherStore, 'owner' => $users['owner'], 'manager' => $users['manager'], 'cashier' => $users['cashier']];
    }

    /** @param array{merchant: Merchant, store: Store} $fixture */
    private function groupsUrl(array $fixture): string
    {
        return $this->customersUrl($fixture, $fixture['store'], suffix: '/customer-groups');
    }

    /** @param array{merchant: Merchant} $fixture */
    private function customersUrl(array $fixture, Store $store, string $suffix = '/customers'): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$store->public_id}{$suffix}";
    }
}
