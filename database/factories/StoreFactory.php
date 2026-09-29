<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
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
            'name' => fake()->company().' Store',
            'code' => fake()->unique()->bothify('STORE-###??'),
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ];
    }
}
