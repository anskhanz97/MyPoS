<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Store;
use App\Models\StoreAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreAssignment>
 */
class StoreAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'merchant_membership_id' => fn (array $attributes): int => MerchantMembership::factory()->create([
                'merchant_id' => $attributes['merchant_id'],
            ])->id,
            'store_id' => fn (array $attributes): int => Store::factory()->create([
                'merchant_id' => $attributes['merchant_id'],
            ])->id,
        ];
    }
}
