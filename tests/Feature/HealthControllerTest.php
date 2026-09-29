<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_public_health_endpoint_returns_request_identity(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('data.service', 'mypos')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure(['data' => ['request_id']]);
    }
}
