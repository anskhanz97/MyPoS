<?php

namespace Database\Factories;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<MerchantMembership>
 */
class MerchantMembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => MembershipRole::Manager,
            'status' => MembershipStatus::Active,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => MembershipRole::Owner]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => MembershipStatus::Inactive]);
    }

    public function withPin(string $pin = '2468'): static
    {
        return $this->state(fn (): array => [
            'pin_hash' => Hash::make($pin),
            'pin_changed_at' => now(),
        ]);
    }
}
