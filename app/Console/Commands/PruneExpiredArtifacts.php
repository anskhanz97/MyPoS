<?php

namespace App\Console\Commands;

use App\Models\DataTransferJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExpiredArtifacts extends Command
{
    protected $signature = 'ops:prune-expired-artifacts {--dry-run}';

    protected $description = 'Remove expired import/export files while retaining job and audit metadata';

    public function handle(): int
    {
        $count = 0;
        DataTransferJob::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where(fn ($query) => $query->whereNotNull('artifact_path')->orWhereNotNull('source_path'))
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use (&$count): void {
                foreach ($jobs as $job) {
                    if (! $this->option('dry-run')) {
                        foreach (array_filter([$job->artifact_path, $job->source_path]) as $path) {
                            Storage::disk($job->disk)->delete((string) $path);
                        }
                        $job->forceFill(['artifact_path' => null, 'source_path' => null])->save();
                    }
                    $count++;
                }
            });

        $this->info("{$count} expired transfer artifact set(s) ".($this->option('dry-run') ? 'identified.' : 'pruned.'));

        return self::SUCCESS;
    }
}
