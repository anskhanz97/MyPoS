<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(), 'store_id' => null,
            'product_id' => fn (array $attributes): int => Product::factory()->create(['merchant_id' => $attributes['merchant_id'], 'store_id' => $attributes['store_id']])->id,
            'base_unit_id' => fn (array $attributes): int => UnitOfMeasure::factory()->create(['merchant_id' => $attributes['merchant_id']])->id,
            'sku' => fake()->unique()->bothify('SKU-#####'), 'name' => fake()->words(2, true), 'attributes' => [], 'is_weighted' => false, 'is_default' => true,
        ];
    }
}
