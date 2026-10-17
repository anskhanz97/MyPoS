<?php

namespace App\Logging;

use App\Models\FiscalDocument;
use App\Models\NotificationDelivery;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class TrackRuntime
{
    /** @var array<string, int|float> */
    private array $started = [];

    private int $lastHeartbeat = 0;

    public function __construct(private readonly OperationalLogger $logs, private readonly BuildIdentity $build) {}

    public function register(): void
    {
        NotificationDelivery::saved(function (NotificationDelivery $delivery): void {
            if (! $delivery->isDirty('state')) {
                return;
            }
            $state = $delivery->state;
            $this->logs->integration($delivery->logContext()['provider'], 'delivery.'.$state, match ($state) {
                'queued' => 'Receipt delivery requested.', 'sending' => 'Sending receipt to the provider.',
                'accepted', 'sent' => str_starts_with((string) $delivery->provider_message_id, 'sandbox-') ? 'Receipt simulated in sandbox; no external message was sent.' : 'Provider accepted the receipt. Delivery is not yet confirmed.',
                'delivered' => 'Receipt delivery confirmed.', 'read' => 'Receipt read confirmed.',
                'cancelled' => 'Receipt delivery skipped.', 'failed' => 'Receipt delivery failed.', default => 'Receipt delivery state changed.',
            }, [...$delivery->logContext(), 'status' => $state === 'cancelled' ? 'skipped' : $state, 'level' => $state === 'failed' ? 'error' : 'info', 'context' => ['state' => $state, 'failure_code' => $delivery->failure_code, 'reason_code' => $delivery->failure_code, 'attempt' => $delivery->attempt_count, 'provider_message_id' => $delivery->provider_message_id]]);
        });
        FiscalDocument::saved(function (FiscalDocument $document): void {
            if ($document->isDirty('state')) {
                $this->logs->integration('fbr', 'fiscal.'.$document->state, 'Fiscal document '.$document->state.'.', [...$document->logContext(), 'status' => $document->state, 'level' => in_array($document->state, ['unknown', 'rejected'], true) ? 'warning' : 'info', 'context' => ['state' => $document->state, 'reason_code' => $document->failure_code]]);
            }
        });
        Event::listen(RequestHandled::class, $this->request(...));
        Queue::createPayloadUsing(fn (): array => ['mypos_log' => [...$this->logs->scope(), 'dispatch_build' => $this->build->version]]);
        Event::listen(JobQueued::class, function (JobQueued $event): void {
            $payload = $event->payload();
            $attributes = [
                ...($payload['mypos_log'] ?? []), 'operation_id' => $payload['uuid'], 'status' => 'queued',
                'context' => ['job_uuid' => $payload['uuid'], 'job_class' => $payload['displayName'] ?? 'Job', 'queue' => $event->queue ?? 'default', 'connection' => $event->connectionName, 'dispatch_build' => $this->build->version],
            ];
            $this->logs->application('queue.queued', 'Job queued: '.class_basename($payload['displayName'] ?? 'Job'), $attributes);
            $scope = $payload['mypos_log'] ?? [];
            if (isset($scope['provider'])) {
                $this->logs->integration($scope['provider'], 'queue.queued', 'Operation queued for a worker.', [...$attributes, 'operation_id' => $scope['operation_id']]);
            }
        });
        Event::listen(JobProcessing::class, fn (JobProcessing $event) => $this->job($event, 'started'));
        Event::listen(JobProcessed::class, fn (JobProcessed $event) => $this->job($event, 'completed'));
        Event::listen(JobExceptionOccurred::class, fn (JobExceptionOccurred $event) => $this->job($event, 'exception'));
        Event::listen(JobFailed::class, fn (JobFailed $event) => $this->job($event, 'failed'));
        Event::listen(JobTimedOut::class, fn (JobTimedOut $event) => $this->job($event, 'timed_out'));
        Event::listen(JobReleasedAfterException::class, fn (JobReleasedAfterException $event) => $this->job($event, 'released'));
        Event::listen(Looping::class, function (Looping $event): void {
            if (time() - $this->lastHeartbeat < 30) {
                return;
            }
            $this->lastHeartbeat = time();
            $this->logs->application('worker.heartbeat', 'Queue worker is checking for work.', [
                'merchant_id' => null, 'store_id' => null, 'actor_user_id' => null, 'request_id' => null, 'status' => 'running',
                'context' => ['worker' => gethostname().':'.getmypid(), 'connection' => $event->connectionName, 'queue' => $event->queue, 'worker_build' => $this->build->version],
            ]);
        });
        Event::listen(WorkerStopping::class, function (WorkerStopping $event): void {
            $this->logs->application('worker.stopped', 'Queue worker stopped.', [
                'merchant_id' => null, 'store_id' => null, 'actor_user_id' => null, 'request_id' => null, 'status' => 'stopped',
                'context' => ['worker' => gethostname().':'.getmypid(), 'connection' => $event->connectionName, 'queue' => $event->queue, 'worker_build' => $this->build->version],
            ]);
        });
    }

    public function request(RequestHandled $event): void
    {
        $request = $event->request;
        if (is_string($request->attributes->get('request_id'))) {
            $event->response->headers->set('X-Request-Id', $request->attributes->get('request_id'));
        }
        if (! $request->is('api/*')) {
            return;
        }
        $status = $event->response->getStatusCode();
        $route = $request->route()?->uri() ?? 'unmatched';
        $scope = $this->logs->scope();
        $this->logs->api([
            'method' => $request->method(), 'route' => mb_substr($route, 0, 255),
            'http_status' => $status, 'duration_ms' => max(0, (int) round((microtime(true) - (float) $request->attributes->get('log_started_at', microtime(true))) * 1000)),
            'status' => $status >= 400 ? 'failed' : 'succeeded', 'level' => $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'),
            'context' => ['client_ip' => $request->ip(), 'device_id' => $scope['device_id'] ?? null, 'caller' => $scope['caller'] ?? ($request->user() !== null ? 'account' : ($request->route('merchantIntegration') !== null ? 'provider_callback' : 'unauthenticated'))],
        ]);
    }

    private function job(JobProcessing|JobProcessed|JobExceptionOccurred|JobFailed|JobTimedOut|JobReleasedAfterException $event, string $stage): void
    {
        $payload = $event->job->payload();
        $id = $payload['uuid'];
        $scope = $payload['mypos_log'] ?? [];
        if ($stage === 'started') {
            $this->started[$id] = hrtime(true);
            Context::addHidden('log_scope', $scope);
        }
        $context = [
            'job_uuid' => $id, 'job_class' => $event->job->resolveName(), 'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName, 'worker' => gethostname().':'.getmypid(),
            'worker_build' => $this->build->version, 'dispatch_build' => $scope['dispatch_build'] ?? 'unknown',
            'attempt' => $event->job->attempts(),
        ];
        if (isset($this->started[$id])) {
            $context['duration_ms'] = (int) ((hrtime(true) - $this->started[$id]) / 1_000_000);
        }
        if ($event instanceof JobExceptionOccurred || $event instanceof JobFailed) {
            $context['exception_class'] = $event->exception::class;
        }
        $status = $stage === 'completed' && $event->job->isReleased() ? 'released' : $stage;
        $this->logs->application('queue.'.$stage, 'Job '.$status.': '.class_basename($event->job->resolveName()), [
            ...$scope, 'operation_id' => $id, 'status' => $status,
            'level' => in_array($stage, ['failed', 'exception', 'timed_out'], true) ? 'error' : 'info', 'context' => $context,
        ]);
        if (isset($scope['provider'])) {
            $this->logs->integration($scope['provider'], 'queue.'.$stage, $status === 'completed' ? 'Queue job finished. Provider outcome is recorded separately.' : 'Worker '.$status.' the operation.', [...$scope, 'status' => $status === 'completed' ? 'processed' : $status, 'level' => in_array($stage, ['failed', 'exception', 'timed_out'], true) ? 'error' : 'info', 'context' => $context]);
        }
        if ($stage !== 'started') {
            unset($this->started[$id]);
            Context::forgetHidden('log_scope');
        }
    }
}
