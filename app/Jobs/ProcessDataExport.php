<?php

namespace App\Jobs;

use App\Domain\DataTransfer\ExportService;
use App\Models\DataTransferJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $dataTransferJobId) {}

    public function handle(ExportService $service): void
    {
        $job = DataTransferJob::query()->findOrFail($this->dataTransferJobId);
        if (! in_array($job->state, ['queued', 'failed'], true)) {
            return;
        }
        $service->execute($job);
    }
}
