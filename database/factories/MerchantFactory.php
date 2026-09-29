<?php

namespace Database\Factories;

use App\Domain\Tenancy\Enums\MerchantStatus;
use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'store_data_mode' => StoreDataMode::Shared,
            'status' => MerchantStatus::Active,
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'store_data_mode_locked_at' => now(),
        ];
    }

    public function independent(): static
    {
        return $this->state(fn (): array => ['store_data_mode' => StoreDataMode::Independent]);
    }

    public function onboarding(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantStatus::Onboarding,
            'store_data_mode_locked_at' => null,
        ]);
    }
}
