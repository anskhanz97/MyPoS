<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\ScaleBarcodeFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScaleBarcodeFormat>
 */
class ScaleBarcodeFormatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['merchant_id' => Merchant::factory(), 'store_id' => null, 'name' => 'EAN-13 weight label', 'prefix' => '21', 'total_length' => 13, 'item_start' => 2, 'item_length' => 5, 'value_start' => 7, 'value_length' => 5, 'value_type' => 'quantity', 'value_decimal_places' => 3, 'checksum' => 'ean13', 'is_active' => true];
    }
}
