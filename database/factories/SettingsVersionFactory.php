<?php

namespace Database\Factories;

use App\Domain\Tenancy\Enums\CustomerScope;
use App\Models\Merchant;
use App\Models\SettingsVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettingsVersion>
 */
class SettingsVersionFactory extends Factory
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
            'store_id' => null,
            'scope' => CustomerScope::Merchant,
            'version' => 1,
            'payload' => [],
            'created_by_user_id' => User::factory(),
            'published_at' => now(),
            'effective_at' => now(),
        ];
    }
}
