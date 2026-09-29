<?php

namespace Tests\Feature;

use Tests\TestCase;

class BootstrapControllerTest extends TestCase
{
    public function test_returns_401_problem_when_bootstrap_request_is_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/bootstrap');

        $response
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('retryable', false)
            ->assertJsonStructure(['type', 'title', 'status', 'code', 'request_id', 'retryable']);
    }
}
