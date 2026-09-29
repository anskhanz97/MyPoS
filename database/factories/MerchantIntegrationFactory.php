<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantIntegration>
 */
class MerchantIntegrationFactory extends Factory
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
            'provider' => 'twilio_sms',
            'category' => 'communication',
            'mode' => 'sandbox',
            'state' => 'draft',
            'config' => ['daily_limit' => 100],
            'credentials' => ['account_sid' => 'AC'.fake()->sha1(), 'auth_token' => fake()->sha256()],
        ];
    }
}
