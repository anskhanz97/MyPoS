<?php

use App\Domain\Loyalty\LoyaltyService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('loyalty:expire', function (LoyaltyService $loyalty): void {
    $this->info($loyalty->expire().' expired loyalty earn entries processed.');
})->purpose('Post idempotent loyalty point expiry entries');

Artisan::command('loyalty:reconcile {--repair}', function (LoyaltyService $loyalty): void {
    $result = $loyalty->reconcile((bool) $this->option('repair'));
    $this->info("Checked {$result['checked']} balances; {$result['mismatched']} mismatched; {$result['repaired']} repaired.");
})->purpose('Compare loyalty balance projections with the append-only ledger');

Schedule::command('loyalty:expire')->hourly()->withoutOverlapping();
Schedule::command('loyalty:reconcile')->daily()->withoutOverlapping();
Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('ops:prune-expired-artifacts')->dailyAt('02:20')->withoutOverlapping();
