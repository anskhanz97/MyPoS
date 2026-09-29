<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\OperationalCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalCustomer>
 */
class OperationalCustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+92'.fake()->numerify('3#########');

        return [
            'merchant_id' => Merchant::factory(),
            'store_id' => null,
            'local_reference' => fake()->unique()->bothify('CUS-#####'),
            'name' => fake()->name(),
            'phone' => $phone,
            'normalized_phone' => $phone,
            'email_receipt_preference' => false,
            'marketing_email_consent' => false,
            'version' => 1,
        ];
    }
}
