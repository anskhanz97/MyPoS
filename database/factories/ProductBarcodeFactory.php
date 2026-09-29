<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBarcode>
 */
class ProductBarcodeFactory extends Factory
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
            'product_variant_id' => fn (array $attributes): int => ProductVariant::factory()->create(['merchant_id' => $attributes['merchant_id'], 'store_id' => $attributes['store_id']])->id,
            'code' => fake()->unique()->numerify('#############'), 'kind' => 'unit', 'is_primary' => true,
        ];
    }
}
