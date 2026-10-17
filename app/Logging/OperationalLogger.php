<?php

namespace App\Logging;

use App\Models\Merchant;
use App\Models\MerchantIntegration;
use App\Models\Store;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalLogger
{
    private bool $writing = false;

    public function __construct(private readonly BuildIdentity $build) {}

    /** @return array<string, mixed> */
    public function scope(): array
    {
        $scope = (array) Context::getHidden('log_scope', []);
        if (app()->bound('request')) {
            $request = request();
            $merchant = $request->route('merchant');
            $store = $request->route('store');
            $integration = $request->route('merchantIntegration');
            if ($integration instanceof MerchantIntegration) {
                $scope['merchant_id'] = $integration->merchant_id;
            }
            if ($merchant instanceof Merchant && $request->user() !== null) {
                $scope['merchant_id'] = $merchant->id;
                $scope['store_id'] = $store instanceof Store && $store->merchant_id === $merchant->id ? $store->id : null;
            }
            if ($request->user() !== null) {
                $scope['actor_user_id'] = $request->user()->getAuthIdentifier();
            }
        }
        $scope['request_id'] = Context::get('request_id');

        return $scope;
    }

    /** @param array<string, mixed> $attributes */
    public function application(string $event, string $message, array $attributes = []): void
    {
        $this->write('application_logs', $event, $message, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function integration(string $provider, string $event, string $message, array $attributes = []): void
    {
        $this->write('integration_logs', $event, $message, [...$attributes, 'provider' => $provider]);
    }

    /** @param array<string, mixed> $attributes */
    public function api(array $attributes): void
    {
        $this->write('api_request_logs', 'http.completed', $attributes['method'].' '.$attributes['route'], $attributes);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function safeContext(array $values): array
    {
        $allowed = ['stage', 'reason_code', 'attempt', 'job_uuid', 'job_class', 'queue', 'connection', 'worker', 'worker_build', 'dispatch_build', 'duration_ms', 'http_status', 'exception_class', 'exception_file', 'exception_line', 'mode', 'provider_message_id', 'delivery_id', 'document_id', 'failure_code', 'state', 'changed_fields', 'client_ip', 'device_id', 'caller'];
        $safe = array_intersect_key($values, array_flip($allowed));
        foreach ($safe as $key => $value) {
            $safe[$key] = is_scalar($value) || $value === null ? mb_substr($this->safeText((string) $value), 0, 500) : '[OMITTED]';
        }

        return $safe;
    }

    public function safeText(string $message): string
    {
        return preg_replace([
            '/\\bBearer\\s+\\S+/i',
            '/\\b[\\w.+-]+@[\\w.-]+\\.[a-z]{2,}\\b/i',
            '/(?<![\\w-])\\+?(?:\\d[ -]?){10,19}(?![\\w-])/',
            '/\\b(password|pin|token|secret|api[_-]?key|authorization)\\s*[:=]\\s*[^\\s,;]+/i',
            '/(https?:\\/\\/[^\\s?]+)\\?[^\\s]+/i',
        ], ['[REDACTED]', '[REDACTED_EMAIL]', '[REDACTED_NUMBER]', '$1=[REDACTED]', '$1?[REDACTED]'], $message) ?? '[UNAVAILABLE]';
    }

    /** @param array<string, mixed> $attributes */
    private function write(string $table, string $event, string $message, array $attributes): void
    {
        if ($this->writing || ! config('observability.enabled')) {
            return;
        }
        $this->writing = true;
        $row = [];
        try {
            $row = array_intersect_key([...$this->scope(), ...$attributes], array_flip(['merchant_id', 'store_id', 'actor_user_id', 'request_id', 'operation_id', 'level', 'status', 'subject']));
            $row += ['level' => 'info', 'status' => 'recorded'];
            $row += ['event' => mb_substr($event, 0, 100), 'message' => mb_substr($this->safeText($message), 0, 500), 'build_version' => $this->build->version, 'context' => json_encode($this->safeContext((array) ($attributes['context'] ?? [])), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'occurred_at' => now('UTC')->format('Y-m-d H:i:s.u')];
            foreach ($table === 'integration_logs' ? ['provider'] : ($table === 'api_request_logs' ? ['method', 'route', 'http_status', 'duration_ms'] : []) as $key) {
                $row[$key] = $attributes[$key];
            }
            DB::table($table)->insert($row);
        } catch (Throwable $exception) {
            try {
                Log::channel('single')->warning('Database logging unavailable; diagnostic event retained in this file.', ['event' => $event, 'exception_class' => $exception::class, 'diagnostic' => $row]);
            } catch (Throwable) {
                error_log('MyPOS database and file logging unavailable.');
            }
        } finally {
            $this->writing = false;
        }
    }
}
