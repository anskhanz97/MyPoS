<?php

namespace App\Domain\Printing;

use App\Domain\Offline\CanonicalPayload;
use App\Models\OutboxEvent;
use App\Models\PrintAttempt;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReportPrintAttempt
{
    public function __construct(private readonly CanonicalPayload $canonical) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{attempt: PrintAttempt, job: PrintJob, replayed: bool}
     */
    public function handle(PrintJob $job, array $data): array
    {
        $hash = $this->canonical->hash($data);

        return DB::transaction(function () use ($job, $data, $hash): array {
            $locked = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
            $existing = PrintAttempt::query()->where('helper_event_id', $data['helper_event_id'])->lockForUpdate()->first();
            if ($existing instanceof PrintAttempt) {
                if ($existing->print_job_id !== $locked->id || ! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The helper event identity was reused with different data.');
                }

                return ['attempt' => $existing, 'job' => $locked, 'replayed' => true];
            }
            $attemptNumber = PrintAttempt::query()->where('print_job_id', $locked->id)->where('attempt_number', $data['attempt_number'])->lockForUpdate()->first();
            if ($attemptNumber instanceof PrintAttempt) {
                throw new ConflictHttpException('The print attempt number was reused with a different helper event.');
            }
            if ($locked->state === 'printed_reported') {
                throw new ConflictHttpException('The print job already has a final reported print outcome.');
            }
            $nextAttempt = ((int) PrintAttempt::query()->where('print_job_id', $locked->id)->max('attempt_number')) + 1;
            if ((int) $data['attempt_number'] !== $nextAttempt) {
                throw new ConflictHttpException('The print attempt number is out of sequence.');
            }
            abort_if($locked->expires_at->isPast() && $data['outcome'] === 'accepted_by_helper', 409, 'The print authorization expired before helper acceptance.');
            $attempt = PrintAttempt::query()->create(['print_job_id' => $locked->id, 'helper_event_id' => $data['helper_event_id'], 'payload_hash' => $hash, 'attempt_number' => $data['attempt_number'], 'outcome' => $data['outcome'], 'helper_id' => $data['helper_id'], 'helper_version' => $data['helper_version'], 'printer_name' => $data['printer_name'], 'error_code' => $data['error_code'] ?? null, 'safe_message' => $data['safe_message'] ?? null, 'reported_at' => $data['reported_at'], 'created_at' => now()]);
            $state = match ($data['outcome']) {
                'accepted_by_helper' => 'accepted_by_helper', 'printed_reported' => 'printed_reported', 'failed' => 'failed', default => 'outcome_unknown'
            };
            $locked->forceFill(['state' => $state, 'printer_name' => $data['printer_name'], 'completed_at' => $data['outcome'] === 'printed_reported' ? now() : null])->save();
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'printing.job.outcome_recorded', 'aggregate_type' => 'print_job', 'aggregate_id' => $locked->public_id, 'payload' => ['print_job_id' => $locked->public_id, 'attempt_id' => $attempt->public_id, 'outcome' => $data['outcome'], 'error_code' => $data['error_code'] ?? null], 'occurred_at' => now(), 'available_at' => now()]);

            return ['attempt' => $attempt, 'job' => $locked, 'replayed' => false];
        }, attempts: 3);
    }
}
