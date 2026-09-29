<?php

namespace Tests\Feature;

use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantNamespace;
use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\OperationalCustomer;
use App\Models\Store;
use App\Models\StoreAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantConstraintsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_composite_foreign_keys_reject_a_cross_merchant_store_assignment(): void
    {
        $firstMerchant = Merchant::factory()->create();
        $secondMerchant = Merchant::factory()->create();
        $membership = MerchantMembership::factory()->for($firstMerchant)->create();
        $foreignStore = Store::factory()->for($secondMerchant)->create();

        $this->expectException(QueryException::class);

        StoreAssignment::query()->create([
            'merchant_id' => $firstMerchant->id,
            'merchant_membership_id' => $membership->id,
            'store_id' => $foreignStore->id,
        ]);
    }

    public function test_independent_stores_keep_colliding_customer_references_as_distinct_records(): void
    {
        $merchant = Merchant::factory()->independent()->create();
        $firstStore = Store::factory()->for($merchant)->create();
        $secondStore = Store::factory()->for($merchant)->create();

        $first = OperationalCustomer::factory()->for($merchant)->for($firstStore)->create([
            'local_reference' => 'CUST-001',
            'phone' => '+923001234567',
        ]);
        $second = OperationalCustomer::factory()->for($merchant)->for($secondStore)->create([
            'local_reference' => 'CUST-001',
            'phone' => '+923001234567',
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, OperationalCustomer::query()->whereBelongsTo($merchant)->count());
    }

    public function test_shared_and_independent_merchants_can_reuse_the_same_local_identifiers(): void
    {
        $sharedMerchant = Merchant::factory()->create();
        $independentMerchant = Merchant::factory()->independent()->create();
        $independentStore = Store::factory()->for($independentMerchant)->create(['code' => 'MAIN']);

        $sharedCustomer = OperationalCustomer::factory()->for($sharedMerchant)->create([
            'local_reference' => 'CUST-001',
            'phone' => '+923001234567',
        ]);
        $localCustomer = OperationalCustomer::factory()->for($independentMerchant)->for($independentStore)->create([
            'local_reference' => 'CUST-001',
            'phone' => '+923001234567',
        ]);

        $this->assertSame('CUST-001', $sharedCustomer->local_reference);
        $this->assertSame('CUST-001', $localCustomer->local_reference);
        $this->assertNotSame($sharedCustomer->merchant_id, $localCustomer->merchant_id);
    }

    public function test_duplicate_customer_reference_in_the_same_owner_scope_is_rejected(): void
    {
        $merchant = Merchant::factory()->create();
        OperationalCustomer::factory()->for($merchant)->create(['local_reference' => 'CUST-001']);

        $this->expectException(QueryException::class);

        OperationalCustomer::factory()->for($merchant)->create(['local_reference' => 'CUST-001']);
    }

    public function test_one_merchant_loyalty_identity_can_link_distinct_store_customer_histories(): void
    {
        $merchant = Merchant::factory()->independent()->create();
        $firstStore = Store::factory()->for($merchant)->create();
        $secondStore = Store::factory()->for($merchant)->create();
        $firstCustomer = OperationalCustomer::factory()->for($merchant)->for($firstStore)->create([
            'local_reference' => 'LOCAL-77',
        ]);
        $secondCustomer = OperationalCustomer::factory()->for($merchant)->for($secondStore)->create([
            'local_reference' => 'LOCAL-77',
        ]);
        $loyaltyMember = LoyaltyMember::factory()->for($merchant)->create([
            'canonical_phone' => '+923001234567',
        ]);

        foreach ([$firstCustomer, $secondCustomer] as $customer) {
            LoyaltyCustomerLink::query()->create([
                'merchant_id' => $merchant->id,
                'loyalty_member_id' => $loyaltyMember->id,
                'operational_customer_id' => $customer->id,
            ]);
        }

        $this->assertSame(2, $loyaltyMember->customerLinks()->count());
        $this->assertSame(2, OperationalCustomer::query()->whereBelongsTo($merchant)->count());
    }

    public function test_composite_foreign_keys_reject_cross_merchant_loyalty_links(): void
    {
        $memberMerchant = Merchant::factory()->create();
        $customerMerchant = Merchant::factory()->create();
        $loyaltyMember = LoyaltyMember::factory()->for($memberMerchant)->create();
        $customer = OperationalCustomer::factory()->for($customerMerchant)->create();

        $this->expectException(QueryException::class);

        LoyaltyCustomerLink::query()->create([
            'merchant_id' => $memberMerchant->id,
            'loyalty_member_id' => $loyaltyMember->id,
            'operational_customer_id' => $customer->id,
        ]);
    }

    public function test_cache_job_and_object_namespaces_require_both_tenant_identifiers(): void
    {
        $first = new TenantContext(
            '0199aeda-cc75-7cd7-94b9-909dc3f0f01a',
            '0199aeda-dd75-7cd7-94b9-909dc3f0f01b',
        );
        $second = new TenantContext(
            '0199aeda-cc75-7cd7-94b9-909dc3f0f01a',
            '0199aeda-ee75-7cd7-94b9-909dc3f0f01c',
        );
        $namespace = new TenantNamespace;

        $this->assertNotSame(
            $namespace->cacheKey($first, 'catalog', 'latest'),
            $namespace->cacheKey($second, 'catalog', 'latest'),
        );
        $this->assertEquals($first, TenantContext::fromArray($first->jsonSerialize()));
        $this->assertStringContainsString($first->storePublicId, $namespace->objectPath($first, 'receipts', 'R-1.pdf'));
    }
}
