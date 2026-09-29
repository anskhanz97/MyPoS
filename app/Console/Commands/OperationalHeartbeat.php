<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OperationalHeartbeat extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Publish the scheduler heartbeat used by the readiness probe';

    public function handle(): int
    {
        Cache::put('ops:scheduler-heartbeat', now()->timestamp, now()->addMinutes(10));
        $this->info('Operational scheduler heartbeat published.');

        return self::SUCCESS;
    }
}
