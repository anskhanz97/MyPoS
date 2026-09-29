<?php

namespace Tests\Concerns;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductStoreAvailability;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\UnitOfMeasure;
use App\Models\User;

trait BuildsPricingFixture
{
    /** @return array{merchant: Merchant, store: Store, otherStore: Store, owner: User, manager: User, cashier: User, first: ProductVariant, second: ProductVariant} */
    private function pricingFixture(bool $independent = false): array
    {
        $merchant = $independent ? Merchant::factory()->independent()->create() : Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $otherStore = Store::factory()->for($merchant)->create();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id]);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $independent ? $store->id : null, 'name' => 'Tea']);
        $first = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $product->store_id, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'TEA-1']);
        $second = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $product->store_id, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => 'TEA-2', 'is_default' => false]);
        if (! $independent) {
            foreach ([$store, $otherStore] as $availableStore) {
                ProductStoreAvailability::query()->create(['merchant_id' => $merchant->id, 'store_id' => $availableStore->id, 'product_id' => $product->id, 'is_available' => true]);
            }
        }
        $users = [];
        foreach (MembershipRole::cases() as $role) {
            $user = User::factory()->create();
            $membership = MerchantMembership::factory()->for($merchant)->for($user)->create(['role' => $role]);
            foreach ([$store, $otherStore] as $assigned) {
                StoreAssignment::query()->create(['merchant_id' => $merchant->id, 'merchant_membership_id' => $membership->id, 'store_id' => $assigned->id]);
            }
            $users[$role->value] = $user;
        }

        return ['merchant' => $merchant, 'store' => $store, 'otherStore' => $otherStore, 'owner' => $users['owner'], 'manager' => $users['manager'], 'cashier' => $users['cashier'], 'first' => $first, 'second' => $second];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function pricingPayload(array $fixture, array $overrides = []): array
    {
        return array_replace_recursive([
            'scope' => 'merchant', 'expected_version' => 0, 'mode' => 'manual', 'effective_from' => now()->subMinute()->toAtomString(),
            'prices' => [
                ['variant_id' => $fixture['first']->public_id, 'unit_price' => '100.0000', 'tax_rate_percent' => '18.000000', 'tax_inclusive' => false],
                ['variant_id' => $fixture['second']->public_id, 'unit_price' => '50.0000', 'tax_rate_percent' => '0', 'tax_inclusive' => false],
            ], 'rules' => [],
        ], $overrides);
    }

    private function pricingUrl(array $fixture): string
    {
        return "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/pricing";
    }
}
