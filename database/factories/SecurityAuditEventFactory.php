<?php

namespace Database\Factories;

use App\Models\SecurityAuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityAuditEvent>
 */
class SecurityAuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_code' => 'test_event',
            'outcome' => 'succeeded',
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
