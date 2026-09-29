<?php

namespace Database\Factories;

use App\Models\LoyaltyMember;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyMember>
 */
class LoyaltyMemberFactory extends Factory
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
            'name' => fake()->name(),
            'canonical_phone' => '+92'.fake()->unique()->numerify('3#########'),
        ];
    }
}
