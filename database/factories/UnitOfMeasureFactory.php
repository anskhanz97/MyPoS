<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['merchant_id' => Merchant::factory(), 'code' => fake()->unique()->lexify('UOM-???'), 'name' => fake()->word(), 'symbol' => fake()->lexify('??'), 'decimal_places' => 0, 'is_active' => true];
    }
}
