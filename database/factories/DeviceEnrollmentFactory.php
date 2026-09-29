<?php

namespace Database\Factories;

use App\Models\DeviceEnrollment;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<DeviceEnrollment>
 */
class DeviceEnrollmentFactory extends Factory
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
            'register_id' => fn (array $attributes): int => Register::factory()->create([
                'merchant_id' => $attributes['merchant_id'],
                'store_id' => $attributes['store_id'],
            ])->id,
            'name' => fake()->word().' till',
            'device_key_hash' => Hash::make('test-device-key'),
            'status' => 'active',
            'enrolled_at' => now(),
            'lease_expires_at' => now()->addHours(72),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => 'revoked', 'revoked_at' => now()]);
    }
}
