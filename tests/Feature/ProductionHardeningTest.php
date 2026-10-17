<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveLogs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    public function test_public_health_has_defensive_headers_and_no_sensitive_diagnostics(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Content-Security-Policy')
            ->assertJsonMissing(['database', 'cache', 'scheduler']);
    }

    public function test_local_app_allows_the_configured_vite_server_in_its_content_security_policy(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('app.vite_dev_server_url', 'http://127.0.0.1:5173');

        $response = $this->get('/');
        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($policy);
        $this->assertStringContainsString("script-src 'self' http://127.0.0.1:5173", $policy);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' http://127.0.0.1:5173", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function test_readiness_is_secret_protected_and_fails_closed_on_stale_scheduler(): void
    {
        config()->set('operations.health_key', 'health-test-key');
        $this->getJson('/api/v1/health/ready')->assertNotFound();
        $this->withHeader('X-Health-Key', 'wrong')->getJson('/api/v1/health/ready')->assertNotFound();

        Cache::forget('ops:scheduler-heartbeat');
        $this->withHeader('X-Health-Key', 'health-test-key')->getJson('/api/v1/health/ready')
            ->assertServiceUnavailable()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.status', 'not_ready')
            ->assertJsonPath('data.checks.scheduler', 'stale');

        $this->artisan('ops:heartbeat')->assertSuccessful();
        $this->withHeader('X-Health-Key', 'health-test-key')->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.private_storage', 'ok');
    }

    public function test_log_context_redaction_is_recursive_and_masks_card_like_numbers(): void
    {
        $redacted = (new RedactSensitiveLogs)->redact([
            'password' => 'secret',
            'nested' => ['bearer_token' => 'abc', 'note' => 'card 4111 1111 1111 1111'],
            'safe' => 'kept',
        ]);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['nested']['bearer_token']);
        $this->assertSame('card [REDACTED_CARD]', $redacted['nested']['note']);
        $this->assertSame('kept', $redacted['safe']);
    }

    public function test_configured_logging_channel_applies_redaction_without_falling_back_to_emergency_logging(): void
    {
        config(['logging.channels.redaction_probe' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'tap' => [RedactSensitiveLogs::class]]]);
        $logger = Log::channel('redaction_probe');
        $logger->warning('Redaction probe', ['password' => 'sensitive-value', 'safe' => 'kept']);
        $monolog = $logger->getLogger();
        $this->assertInstanceOf(Logger::class, $monolog);
        $handler = $monolog->getHandlers()[0];
        $this->assertInstanceOf(TestHandler::class, $handler);
        $record = $handler->getRecords()[0];
        $this->assertSame('[REDACTED]', $record->context['password']);
        $this->assertSame('kept', $record->context['safe']);
    }
}
