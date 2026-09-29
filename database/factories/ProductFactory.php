<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['merchant_id' => Merchant::factory(), 'store_id' => null, 'type' => 'standard', 'name' => fake()->words(3, true), 'description' => fake()->sentence(), 'batch_tracking_enabled' => false, 'structure_version' => 1];
    }
}
