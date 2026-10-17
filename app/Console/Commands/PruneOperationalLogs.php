<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOperationalLogs extends Command
{
    protected $signature = 'ops:prune-logs';

    protected $description = 'Prune expired diagnostic logs without touching business or audit evidence';

    public function handle(): int
    {
        foreach (['application_logs', 'api_request_logs', 'integration_logs'] as $table) {
            $cutoff = now('UTC')->subDays(max(1, (int) config('observability.retention_days.'.$table)));
            $total = 0;
            do {
                $ids = DB::table($table)->where('occurred_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
                $total += DB::table($table)->whereIn('id', $ids)->delete();
            } while ($ids->count() === 1000);
            $this->info($table.': '.$total.' expired rows removed.');
        }

        return self::SUCCESS;
    }
}
