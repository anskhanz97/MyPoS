<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return ['merchant_id' => Merchant::factory(), 'code' => fake()->unique()->bothify('SUP-###'), 'name' => fake()->company(), 'email' => fake()->companyEmail()];
    }
}
