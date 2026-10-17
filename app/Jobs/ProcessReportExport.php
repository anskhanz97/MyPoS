<?php

namespace App\Jobs;

use App\Domain\Reporting\ReportExporter;
use App\Models\DataTransferJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $dataTransferJobId)
    {
        $this->onConnection(config('queue.default') === 'sync' ? 'sync' : 'reports');
        $this->onQueue('reports');
    }

    public function handle(ReportExporter $exporter): void
    {
        $job = DataTransferJob::query()->findOrFail($this->dataTransferJobId);
        if ($job->state === 'ready') {
            return;
        }
        $exporter->execute($job);
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('report-export:'.$this->dataTransferJobId))->releaseAfter(30)->expireAfter(960)];
    }

    public function failed(?Throwable $exception): void
    {
        DataTransferJob::query()->whereKey($this->dataTransferJobId)->where('state', '!=', 'ready')->update(['state' => 'failed', 'failure_message' => 'Export worker exhausted its attempts. Request a new export.']);
    }
}
