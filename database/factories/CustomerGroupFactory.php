<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(), 'store_id' => null, 'code' => fake()->unique()->bothify('GROUP-###'), 'name' => fake()->words(2, true),
        ];
    }
}
