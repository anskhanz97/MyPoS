<?php

namespace App\Console\Commands;

use App\Domain\Reporting\ReportSnapshots;
use App\Models\SaleLine;
use App\Models\SaleReturnLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class BackfillReportSnapshots extends Command
{
    protected $signature = 'reports:backfill {--dry-run}';

    protected $description = 'Mark uncaptured historical dimensions honestly and link proven ledger references, without changing transaction amounts';

    public function handle(): int
    {
        $count = 0;
        foreach (['sales', 'sale_lines', 'sale_returns'] as $table) {
            DB::table($table)->whereNull('reporting_snapshot')->orderBy('id')->chunkById(500, function ($rows) use ($table, &$count): void {
                foreach ($rows as $row) {
                    if (! $this->option('dry-run')) {
                        DB::table($table)->where('id', $row->id)->whereNull('reporting_snapshot')->update(['reporting_snapshot' => json_encode(['version' => 1, 'provenance' => 'not_captured'], JSON_THROW_ON_ERROR)]);
                    }
                    $count++;
                }
            });
        }
        DB::table('stock_movements')->whereIn('source_type', ['sale', 'sale_return'])->whereNull('sale_line_id')->whereNull('sale_return_line_id')->orderBy('id')->chunkById(500, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                $reference = substr((string) $row->source_line_reference, 0, 36);
                if (! Uuid::isValid($reference)) {
                    continue;
                }
                $table = $row->source_type === 'sale' ? 'sale_lines' : 'sale_return_lines';
                $line = DB::table($table)->where('merchant_id', $row->merchant_id)->where('store_id', $row->store_id)->where('public_id', Uuid::fromString($reference)->getBytes())->first();
                if ($line !== null) {
                    if (! $this->option('dry-run')) {
                        DB::table('stock_movements')->where('id', $row->id)->update([$row->source_type === 'sale' ? 'sale_line_id' : 'sale_return_line_id' => $line->id]);
                    }
                    $count++;
                }
            }
        });
        foreach ([SaleLine::class, SaleReturnLine::class] as $model) {
            $model::query()->where('discount', '!=', 0)->orderBy('id')->chunkById(250, function ($lines) use (&$count): void {
                foreach ($lines as $line) {
                    if (! $this->option('dry-run')) {
                        $snapshots = app(ReportSnapshots::class);
                        $line instanceof SaleLine ? $snapshots->captureDiscounts($line) : $snapshots->captureReturnDiscounts($line);
                    }
                    $count++;
                }
            });
        }
        if (! $this->option('dry-run') && $count > 0) {
            DB::table('report_projection_states')->delete();
        }
        $this->info($count.' historical records '.($this->option('dry-run') ? 'identified.' : 'processed. No financial amounts changed.'));

        return self::SUCCESS;
    }
}
