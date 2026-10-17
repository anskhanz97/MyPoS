<?php

namespace App\Console\Commands;

use App\Domain\Reporting\ReportProjection;
use App\Jobs\RefreshReportProjection;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;

class RefreshReportProjections extends Command
{
    protected $signature = 'reports:refresh {merchant? : Merchant UUID; omit for all merchants} {--force : Rebuild even if the ledger watermark matches} {--queue : Dispatch the rebuild to the reports queue}';

    protected $description = 'Rebuild stale reporting projections from the authoritative ledgers';

    public function handle(ReportProjection $projection): int
    {
        $query = Merchant::query();
        if ($this->argument('merchant') !== null) {
            if (! Uuid::isValid($this->argument('merchant'))) {
                $this->error('Supply a valid merchant UUID.');

                return self::FAILURE;
            }
            $query->where('public_id', Uuid::fromString($this->argument('merchant'))->getBytes());
            if (! $query->exists()) {
                $this->error('Merchant not found.');

                return self::FAILURE;
            }
        }
        $query->eachById(function (Merchant $merchant) use ($projection): void {
            if ($this->option('force') || ! $projection->isCurrent($merchant)) {
                if ($this->option('queue')) {
                    RefreshReportProjection::dispatch($merchant->id, (bool) $this->option('force'));
                } else {
                    $projection->refresh($merchant, (bool) $this->option('force'));
                }
                $this->info(($this->option('queue') ? 'Queued ' : 'Rebuilt ').$merchant->name);
            }
        });

        return self::SUCCESS;
    }
}
