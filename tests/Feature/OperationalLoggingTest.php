<?php

namespace Tests\Feature;

use App\Domain\Integrations\IntegrationManager;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Jobs\SendNotificationMessage;
use App\Logging\OperationalLogger;
use App\Models\Merchant;
use App\Models\MerchantIntegration;
use App\Models\MerchantMembership;
use App\Models\NotificationDelivery;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalLoggingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_api_history_captures_errors_without_storing_urls_payloads_or_credentials(): void
    {
        $merchant = Merchant::factory()->create();
        $this->actingAs($this->member($merchant));
        Route::get('api/log-test-error', function (): never {
            throw new \RuntimeException('password=do-not-store owner@example.test');
        });
        $response = $this->getJson('/api/log-test-error?token=secret-query')->assertStatus(500);
        $request = DB::table('api_request_logs')->where('route', 'api/log-test-error')->first();
        $this->assertNotNull($request);
        $this->assertSame(500, (int) $request->http_status);
        $this->assertSame('failed', $request->status);
        $this->assertNotNull($request->request_id);
        $response->assertHeader('X-Request-Id', $request->request_id);
        $this->assertStringNotContainsString('secret-query', json_encode($request, JSON_THROW_ON_ERROR));
        $exception = DB::table('application_logs')->where('event', 'application.exception')->first();
        $this->assertNotNull($exception);
        $this->assertStringNotContainsString('do-not-store', json_encode($exception, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('owner@example.test', json_encode($exception, JSON_THROW_ON_ERROR));
    }

    public function test_general_logging_keeps_only_safe_bounded_metadata(): void
    {
        $merchant = Merchant::factory()->create();
        Context::addHidden('log_scope', ['merchant_id' => $merchant->id]);
        Log::channel('database')->warning('Provider timed out.', ['reason_code' => 'timeout', 'payload' => ['password' => 'secret-value'], 'authorization' => 'Bearer secret-value', 'recipient' => 'private@example.test']);
        $row = DB::table('application_logs')->where('merchant_id', $merchant->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(['reason_code' => 'timeout'], json_decode($row->context, true));
        $this->assertStringNotContainsString('secret-value', json_encode($row, JSON_THROW_ON_ERROR));
        $this->assertSame('warning', $row->level);
        Context::forgetHidden('log_scope');
    }

    public function test_queue_history_preserves_failures_attempts_and_dispatch_and_worker_builds(): void
    {
        $merchant = Merchant::factory()->create();
        $id = (string) Str::uuid();
        $scope = ['merchant_id' => $merchant->id, 'provider' => 'twilio_sms', 'operation_id' => 'notification:test', 'dispatch_build' => 'previous-build'];
        $payload = json_encode(['uuid' => $id, 'job' => 'App\\Jobs\\ExampleJob', 'displayName' => 'App\\Jobs\\ExampleJob', 'mypos_log' => $scope], JSON_THROW_ON_ERROR);
        $job = new SyncJob($this->app, $payload, 'sync', 'default');
        Event::dispatch(new JobQueued('sync', 'default', 1, 'ExampleJob', $payload, null));
        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobExceptionOccurred('sync', $job, new \RuntimeException('secret')));
        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobProcessed('sync', $job));
        $rows = DB::table('application_logs')->where('operation_id', $id)->orderBy('id')->get();
        $this->assertSame(['queued', 'started', 'exception', 'started', 'completed'], $rows->pluck('status')->all());
        $context = json_decode($rows->last()->context, true);
        $this->assertSame('previous-build', $context['dispatch_build']);
        $this->assertArrayHasKey('worker_build', $context);
        $this->assertSame(5, DB::table('integration_logs')->where('operation_id', 'notification:test')->count());
        $this->assertNull(Context::getHidden('log_scope'));
    }

    public function test_delivery_decisions_and_callbacks_share_a_readable_timeline_without_recipient_data(): void
    {
        $merchant = Merchant::factory()->create();
        $integration = MerchantIntegration::factory()->create(['merchant_id' => $merchant->id]);
        $delivery = NotificationDelivery::query()->create(['merchant_id' => $merchant->id, 'merchant_integration_id' => $integration->id, 'channel' => 'sms', 'template_key' => 'receipt', 'source_type' => 'receipt_snapshot', 'source_reference' => (string) Str::uuid(), 'recipient' => '+923001234567', 'deduplication_key' => hash('sha256', 'synthetic-receipt'), 'state' => 'queued']);
        $delivery->update(['state' => 'cancelled', 'failure_code' => 'consent_missing']);
        $delivery->save();
        $rows = DB::table('integration_logs')->where('operation_id', 'notification:'.$delivery->public_id)->orderBy('id')->get();
        $this->assertSame(['queued', 'skipped'], $rows->pluck('status')->all());
        $this->assertSame('consent_missing', json_decode($rows->last()->context, true)['reason_code']);
        $this->assertStringNotContainsString('+923001234567', $rows->toJson());
    }

    public function test_pruning_removes_only_expired_diagnostic_rows(): void
    {
        $this->freezeTime();
        $audit = SecurityAuditEvent::factory()->create(['occurred_at' => now()->subDays(100)]);
        $logs = app(OperationalLogger::class);
        $logs->application('old.event', 'Old diagnostic.');
        DB::table('application_logs')->update(['occurred_at' => now()->subDays(31)]);
        $logs->application('new.event', 'Recent diagnostic.');
        $this->artisan('ops:prune-logs')->assertSuccessful();
        $this->assertSame(['new.event'], DB::table('application_logs')->pluck('event')->all());
        $this->assertNotNull($audit->fresh());
    }

    public function test_a_real_database_queue_job_keeps_tenant_context_and_records_why_delivery_was_skipped(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
        $merchant = Merchant::factory()->create();
        $integration = MerchantIntegration::factory()->create(['merchant_id' => $merchant->id, 'state' => 'disabled']);
        $delivery = NotificationDelivery::query()->create(['merchant_id' => $merchant->id, 'merchant_integration_id' => $integration->id, 'channel' => 'sms', 'template_key' => 'receipt', 'source_type' => 'receipt_snapshot', 'source_reference' => (string) Str::uuid(), 'recipient' => '+923001234567', 'deduplication_key' => hash('sha256', 'queue-test'), 'state' => 'queued']);
        Context::scope(fn () => Bus::dispatch(new SendNotificationMessage($delivery->id)), hidden: ['log_scope' => $delivery->logContext()]);
        $payload = json_decode(DB::table('jobs')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($merchant->id, $payload['mypos_log']['merchant_id']);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertSame('cancelled', $delivery->refresh()->state);
        $this->assertSame(['queued', 'started', 'completed'], DB::table('application_logs')->where('operation_id', $payload['uuid'])->orderBy('id')->pluck('status')->all());
        $this->assertDatabaseHas('integration_logs', ['merchant_id' => $merchant->id, 'operation_id' => 'notification:'.$delivery->public_id, 'status' => 'skipped']);
    }

    public function test_connection_checks_link_the_start_and_result_without_storing_credentials(): void
    {
        $integration = MerchantIntegration::factory()->create();
        app(IntegrationManager::class)->test($integration);
        $rows = DB::table('integration_logs')->where('subject', 'integration:'.$integration->public_id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows->first()->operation_id);
        $this->assertSame($rows->first()->operation_id, $rows->last()->operation_id);
    }

    public function test_connection_failures_leave_a_readable_failed_outcome(): void
    {
        $integration = MerchantIntegration::factory()->create(['mode' => 'live', 'config' => ['from_number' => '+15005550006'], 'credentials' => ['account_sid' => 'ACsynthetic', 'auth_token' => 'private-test-token']]);
        Http::fake(['https://api.twilio.com/2010-04-01/Accounts/ACsynthetic.json' => Http::failedConnection()]);
        try {
            app(IntegrationManager::class)->test($integration);
            $this->fail('An unreachable provider should preserve the connection exception.');
        } catch (ConnectionException) {
            $this->assertSame('failed', $integration->refresh()->last_test_state);
        }
        $rows = DB::table('integration_logs')->where('subject', 'integration:'.$integration->public_id)->orderBy('id')->get();
        $this->assertSame(['started', 'failed'], $rows->pluck('status')->all());
        $this->assertStringNotContainsString('private-test-token', $rows->toJson());
        Http::assertSentCount(1);
    }

    public function test_logging_failure_does_not_break_the_business_caller(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.unavailable_log_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('unavailable_log_test');
        try {
            app(OperationalLogger::class)->application('safe.failure', 'An event without a log table.');
            $this->assertTrue(true);
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function member(Merchant $merchant, MembershipRole $role = MembershipRole::Owner): User
    {
        $user = User::factory()->create();
        MerchantMembership::factory()->for($merchant)->for($user)->create(['role' => $role]);

        return $user;
    }
}
