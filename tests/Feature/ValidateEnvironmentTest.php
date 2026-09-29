<?php

namespace Tests\Feature;

use Tests\TestCase;

class ValidateEnvironmentTest extends TestCase
{
    public function test_returns_failure_and_names_missing_required_configuration(): void
    {
        config(['app.key' => null]);

        $this->artisan('app:environment-check')
            ->expectsOutputToContain('Missing required environment values: APP_KEY')
            ->assertFailed();
    }
}
