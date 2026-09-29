<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
class RegisterFactory extends Factory
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
            'store_id' => fn (array $attributes): int => Store::factory()->create([
                'merchant_id' => $attributes['merchant_id'],
            ])->id,
            'name' => fake()->company().' Register',
            'code' => fake()->unique()->bothify('REG-###??'),
            'status' => 'active',
            'capabilities' => ['scanner' => true, 'printer' => false, 'cash_drawer' => false, 'customer_display' => false],
        ];
    }
}
