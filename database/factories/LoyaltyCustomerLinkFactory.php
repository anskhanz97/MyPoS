<?php

namespace Database\Factories;

use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyCustomerLink>
 */
class LoyaltyCustomerLinkFactory extends Factory
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
            'loyalty_member_id' => fn (array $attributes): int => LoyaltyMember::factory()->create([
                'merchant_id' => $attributes['merchant_id'],
            ])->id,
            'operational_customer_id' => fn (array $attributes): int => OperationalCustomer::factory()->create([
                'merchant_id' => $attributes['merchant_id'],
            ])->id,
        ];
    }
}
