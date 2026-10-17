<?php

namespace App\Jobs;

use App\Domain\Reporting\ReportProjection;
use App\Models\Merchant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshReportProjection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 3;

    public int $uniqueFor = 960;

    public bool $force = false;

    public function __construct(public readonly int $merchantId, bool $force = false)
    {
        $this->force = $force;
        $this->onConnection('reports');
        $this->onQueue('reports');
    }

    public function uniqueId(): string
    {
        return $this->merchantId.($this->force ? ':full' : '');
    }

    public function handle(ReportProjection $projection): void
    {
        $projection->refresh(Merchant::query()->findOrFail($this->merchantId), $this->force);
    }
}
