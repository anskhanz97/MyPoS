<?php

namespace Tests\Feature;

use App\Domain\Reporting\ReportExporter;
use App\Domain\Reporting\ReportProjection;
use App\Domain\Reporting\ReportQuery;
use App\Models\DataTransferJob;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsReportingFixture;
use Tests\TestCase;

#[Group('performance')]
class ReportPerformanceTest extends TestCase
{
    use BuildsReportingFixture;

    public function test_large_ledger_queries_and_streaming_exports(): void
    {
        if (getenv('RUN_REPORT_BENCHMARK') !== '1') {
            $this->markTestSkipped('Set RUN_REPORT_BENCHMARK=1 on a disposable test database.');
        }
        $count = (int) (getenv('REPORT_BENCHMARK_LINES') ?: 1000000);
        $this->assertSame('mypos_test', DB::connection()->getDatabaseName());
        $this->assertSame('3307', (string) DB::connection()->getConfig('port'));
        $this->assertTrue(app()->environment('testing'));
        $queryOnly = getenv('REPORT_BENCHMARK_QUERY_ONLY') === '1';
        $profile = [];
        if (getenv('REPORT_PROFILE_QUERIES') === '1') {
            DB::listen(function (QueryExecuted $event) use (&$profile): void {
                if ($event->time > 10) {
                    $profile[] = ['milliseconds' => $event->time, 'sql' => $event->sql, 'bindings' => $event->bindings];
                    file_put_contents(base_path('output/reporting-cold-query-profile.json'), json_encode($profile, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                }
            });
        }
        $evidence = base_path('output/reporting-'.($queryOnly ? 'query-' : '').'benchmark-'.$count.'.json');
        $progress = ['lines' => $count, 'state' => 'seeding', 'started_at' => now()->toAtomString(), 'seeded_lines' => 0];
        file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        Storage::fake('report_benchmark');
        $merchant = Merchant::query()->where('slug', 'reporting-benchmark-'.$count)->first();
        if ($merchant === null) {
            $c = $this->soldFixture();
            $c['f']['merchant']->update(['slug' => 'reporting-benchmark-'.$count]);
        } else {
            $sale = DB::table('sales')->find(DB::table('sales')->where('merchant_id', $merchant->id)->min('id'));
            $this->assertNotNull($sale);
            $ownerId = DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('role', 'owner')->value('user_id');
            $c = ['f' => ['merchant' => $merchant, 'store' => Store::query()->findOrFail($sale->store_id), 'owner' => User::query()->findOrFail($ownerId)]];
        }
        $originalSale = (array) DB::table('sales')->find(DB::table('sales')->where('merchant_id', $c['f']['merchant']->id)->min('id'));
        $originalSaleId = (int) $originalSale['id'];
        $originalLine = (array) DB::table('sale_lines')->where('sale_id', $originalSaleId)->orderBy('sequence')->first();
        $this->assertGreaterThan(0, $count);
        $this->assertSame(0, $count % 10);
        $this->assertLessThanOrEqual(1000000, $count);
        $date = substr($originalSale['business_date'], 0, 10);
        $seeded = DB::table('sale_lines')->where('merchant_id', $c['f']['merchant']->id)->count() - 1;
        if ($seeded < $count) {
            unset($originalSale['id'], $originalLine['id']);
            $prefix = 'B-'.Str::lower(Str::random(8)).'-';
            $select = $bindings = [];
            foreach ($originalSale as $column => $value) {
                $select[] = match ($column) {
                    'public_id' => "UNHEX(REPLACE(UUID(), '-', ''))",
                    'number' => 'CONCAT(?, a.n * 10000 + b.n * 100 + c.n)',
                    'client_transaction_id', 'idempotency_key' => 'UUID()',
                    default => '?',
                };
                if ($column === 'number') {
                    $bindings[] = $prefix;
                } elseif (! in_array($column, ['public_id', 'client_transaction_id', 'idempotency_key'], true)) {
                    $bindings[] = $value;
                }
            }
            $saleSql = 'INSERT INTO sales (`'.implode('`, `', array_keys($originalSale)).'`) WITH RECURSIVE numbers AS (SELECT 0 n UNION ALL SELECT n + 1 FROM numbers WHERE n < 99) SELECT '.implode(', ', $select).' FROM numbers a CROSS JOIN numbers b CROSS JOIN numbers c LIMIT ?';
            $saleBindings = $bindings;
            $originalLine = array_replace($originalLine, ['quantity_uom' => '0.200000', 'quantity_base' => '0.200000', 'pre_discount' => '20.00', 'net' => '20.00', 'tax' => '3.60', 'gross' => '23.60']);
            $select = $bindings = [];
            foreach ($originalLine as $column => $value) {
                $select[] = match ($column) {
                    'public_id' => "UNHEX(REPLACE(UUID(), '-', ''))",
                    'sale_id' => 's.id', 'sequence' => 'n.n + 1', default => '?',
                };
                if (! in_array($column, ['public_id', 'sale_id', 'sequence'], true)) {
                    $bindings[] = $value;
                }
            }
            $lineSql = 'INSERT INTO sale_lines (`'.implode('`, `', array_keys($originalLine)).'`) WITH RECURSIVE numbers AS (SELECT 0 n UNION ALL SELECT n + 1 FROM numbers WHERE n < 9) SELECT '.implode(', ', $select).' FROM sales s CROSS JOIN numbers n WHERE s.merchant_id = ? AND s.number LIKE ?';
            for ($offset = intdiv($seeded, 10); $offset < intdiv($count, 10); $offset += 1000) {
                $batchPrefix = $prefix.$offset.'-';
                $batchBindings = $saleBindings;
                $batchBindings[array_search($prefix, $saleBindings, true)] = $batchPrefix;
                $batchBindings[] = min(1000, intdiv($count, 10) - $offset);
                DB::transaction(function () use ($saleSql, $batchBindings, $lineSql, $bindings, $c, $batchPrefix): void {
                    DB::insert($saleSql, $batchBindings);
                    DB::insert($lineSql, [...$bindings, $c['f']['merchant']->id, $batchPrefix.'%']);
                });
                $progress['seeded_lines'] = min($count, ($offset + 1000) * 10);
                file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            }
        }
        $this->assertSame($count + 1, DB::table('sale_lines')->where('merchant_id', $c['f']['merchant']->id)->count());
        DB::statement('ANALYZE TABLE sales, sale_lines');
        $progress['seeded_lines'] = $count;
        file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $filters = ['start' => $date, 'end' => $date, 'page' => 1, 'per_page' => 50];
        $access = ['role' => 'owner', 'actor_id' => null, 'costs' => true, 'customer_export' => true];
        $started = microtime(true);
        $progress['state'] = 'projection_build';
        file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $projection = app(ReportProjection::class);
        $verifyConcurrency = getenv('VERIFY_REPORT_REBUILD_CONCURRENCY') === '1';
        $concurrentWriteChecked = false;
        if ($verifyConcurrency) {
            DB::listen(function (QueryExecuted $event) use (&$concurrentWriteChecked, $originalSaleId): void {
                if ($concurrentWriteChecked || ! str_starts_with($event->sql, 'insert into `report_sales_facts`')) {
                    return;
                }
                $concurrentWriteChecked = true;
                $config = DB::connection()->getConfig();
                $pdo = new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
                $pdo->beginTransaction();
                try {
                    $statement = $pdo->prepare('UPDATE sales SET updated_at = updated_at WHERE id = ?');
                    $statement->execute([$originalSaleId]);
                } finally {
                    $pdo->rollBack();
                }
            });
        }
        if ($verifyConcurrency || ! $projection->isCurrent($c['f']['merchant'])) {
            $projection->rebuild($c['f']['merchant']);
        }
        if ($verifyConcurrency) {
            $this->assertTrue($concurrentWriteChecked);
        }
        $projectionSeconds = microtime(true) - $started;
        $progress['projection_build_seconds'] = $projectionSeconds;
        $progress['state'] = 'query';
        file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $query = app(ReportQuery::class)->rows($c['f']['merchant'], $c['f']['store'], 'sales-by-product', $filters, $access);
        $sessionBefore = (array) DB::selectOne('SELECT @@session.tmp_table_size temp_size, @@session.max_heap_table_size heap_size, @@session.sort_buffer_size sort_size');
        $plan = DB::select('EXPLAIN FORMAT=JSON '.$query->toSql(), $query->getBindings());
        file_put_contents(base_path('output/reporting-query-plan-'.$count.'.json'), json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $started = microtime(true);
        $report = app(ReportQuery::class)->run($c['f']['merchant'], $c['f']['store'], 'sales-by-product', $filters, $access);
        $querySeconds = microtime(true) - $started;
        $progress['state'] = 'exporting';
        $progress['query_seconds'] = $querySeconds;
        file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->assertSame(1, $report['pagination']['total']);
        $this->assertSame((string) ($count * 20 + 200).'.00', $report['summary']['revenue']);
        $this->assertSame($sessionBefore, (array) DB::selectOne('SELECT @@session.tmp_table_size temp_size, @@session.max_heap_table_size heap_size, @@session.sort_buffer_size sort_size'));
        try {
            app(ReportQuery::class)->run($c['f']['merchant'], $c['f']['store'], 'invalid-report', $filters, $access);
            $this->fail('An unknown report must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame($sessionBefore, (array) DB::selectOne('SELECT @@session.tmp_table_size temp_size, @@session.max_heap_table_size heap_size, @@session.sort_buffer_size sort_size'));
        $latencies = [];
        $sampleCount = (int) (getenv('REPORT_BENCHMARK_SAMPLES') ?: 20);
        foreach (['common' => ['sales-by-product', 29], 'receipts' => ['sales-summary', 29], 'tax' => ['tax-report', 29], 'stock' => ['stock-levels', 29], 'annual' => ['sales-by-product', 365]] as $period => [$reportKey, $days]) {
            $periodFilters = array_replace($filters, ['start' => CarbonImmutable::parse($date)->subDays($days)->toDateString()]);
            $caseQuery = app(ReportQuery::class)->rows($c['f']['merchant'], $c['f']['store'], $reportKey, $periodFilters, $access);
            $casePlan = DB::select('EXPLAIN FORMAT=JSON '.$caseQuery->toSql(), $caseQuery->getBindings());
            file_put_contents(base_path('output/reporting-query-plan-'.$reportKey.'-'.$count.'.json'), json_encode($casePlan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $samples = [];
            for ($sample = 0; $sample < $sampleCount; $sample++) {
                if (getenv('REPORT_BENCHMARK_COLD') === '1') {
                    $this->assertSame('array', config('cache.default'));
                    Cache::store('array')->flush();
                }
                $started = microtime(true);
                $this->actingAs($c['f']['owner'])->getJson('/api/v1/merchants/'.$c['f']['merchant']->public_id.'/reporting/reports/'.$reportKey.'?'.http_build_query($periodFilters + ['store_id' => $c['f']['store']->public_id]))->assertOk();
                $samples[] = microtime(true) - $started;
            }
            $firstSeconds = $samples[0];
            sort($samples);
            $latencies[$period] = ['first_seconds' => $firstSeconds, 'p95_seconds' => $samples[(int) ceil($sampleCount * 0.95) - 1], 'samples' => $samples];
            $progress['latencies'] = $latencies;
            file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        if ($queryOnly) {
            $gates = [];
            foreach ($latencies as $case => $measurement) {
                $gates[$case] = $measurement['p95_seconds'] <= ($case === 'annual' ? 5 : 2);
            }
            $progress['state'] = in_array(false, $gates, true) ? 'failed' : 'passed';
            $progress['gates'] = $gates;
            $progress['baseline'] = ['samples' => $sampleCount, 'result_cache_cold_every_request' => getenv('REPORT_BENCHMARK_COLD') === '1', 'innodb_buffer_pool_mb' => (int) DB::selectOne('SELECT @@innodb_buffer_pool_size bytes')->bytes / 1048576, 'source_sha256' => hash('sha256', file_get_contents(app_path('Domain/Reporting/ReportQuery.php')).file_get_contents(app_path('Domain/Reporting/ReportProjection.php')).file_get_contents(app_path('Domain/Reporting/ReportExporter.php')))];
            file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->assertLessThanOrEqual(2, $latencies['common']['p95_seconds']);
            foreach (['receipts', 'tax', 'stock'] as $case) {
                $this->assertLessThanOrEqual(2, $latencies[$case]['p95_seconds'], $case);
            }
            $this->assertLessThanOrEqual(5, $latencies['annual']['p95_seconds']);

            return;
        }
        $exportSeconds = [];
        foreach (['csv', 'xlsx'] as $format) {
            $job = DataTransferJob::query()->create(['merchant_id' => $c['f']['merchant']->id, 'store_id' => $c['f']['store']->id, 'actor_user_id' => $c['f']['owner']->id, 'direction' => 'export', 'resource' => 'report_workspace', 'state' => 'queued', 'disk' => 'report_benchmark', 'options' => ['report_key' => 'bookkeeping', 'format' => $format, 'filters' => $filters]]);
            $started = microtime(true);
            if ($count >= 1000000) {
                $job->options = ['report_key' => 'sales-by-product', 'format' => $format, 'filters' => $filters];
                $job->save();
            }
            app(ReportExporter::class)->execute($job);
            $exportSeconds[$format] = microtime(true) - $started;
            $progress['export_seconds'] = $exportSeconds;
            file_put_contents($evidence, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->assertSame('ready', $job->refresh()->state);
            $this->assertTrue(Storage::disk('report_benchmark')->exists($job->artifact_path));
            $path = Storage::disk('report_benchmark')->path($job->artifact_path);
            $this->assertSame($job->artifact_hash, hash_file('sha256', $path));
            $actualRows = 0;
            if ($format === 'csv') {
                $file = fopen($path, 'rb');
                fgetcsv($file, escape: '');
                while (fgetcsv($file, escape: '') !== false) {
                    $actualRows++;
                }
                fclose($file);
            } else {
                $reader = new Reader;
                $reader->open($path);
                foreach ($reader->getSheetIterator() as $sheet) {
                    if (str_starts_with($sheet->getName(), 'Detail ')) {
                        $header = true;
                        foreach ($sheet->getRowIterator() as $row) {
                            if (! $header) {
                                $actualRows++;
                            }
                            $header = false;
                        }
                    }
                }
                $reader->close();
            }
            $this->assertSame($job->total_rows, $actualRows);
        }
        $result = ['state' => 'completed', 'lines' => $count, 'projection_build_seconds' => $projectionSeconds, 'query_seconds' => $querySeconds, 'latencies' => $latencies, 'export_seconds' => $exportSeconds, 'peak_memory_mb' => memory_get_peak_usage(true) / 1048576, 'database' => DB::selectOne('SELECT VERSION() version')->version, 'note' => 'Synthetic read benchmark; all rows in one day/variant, 20 samples per range. Projection build is measured separately. Clones do not represent inventory posting throughput.'];
        $gates = ['php_memory' => $result['peak_memory_mb'] < 256, 'exports' => max($exportSeconds) < 300];
        foreach ($latencies as $period => $measurement) {
            $gates[$period] = $measurement['p95_seconds'] <= ($period === 'annual' ? 5 : 2);
        }
        $result['gates'] = $gates;
        $result['state'] = in_array(false, $gates, true) ? 'failed' : 'passed';
        $result['baseline'] = ['php' => PHP_VERSION, 'laravel' => Application::VERSION,
            'innodb_buffer_pool_mb' => (int) DB::selectOne('SELECT @@innodb_buffer_pool_size bytes')->bytes / 1048576,
            'report_temp_limit_mb' => (int) config('reporting.query_temp_memory_mb'), 'php_memory_limit' => ini_get('memory_limit'),
            'transport' => 'Laravel HTTP kernel; network excluded', 'cache' => 'Ledger-watermark/generation keyed summaries and pages; first response included',
            'actual_sale_lines' => $count + 1, 'projection_rows' => DB::table('report_sales_facts')->where('merchant_id', $c['f']['merchant']->id)->count(),
            'concurrent_source_write_verified' => $concurrentWriteChecked, 'source_sha256' => hash('sha256', file_get_contents(app_path('Domain/Reporting/ReportQuery.php')).file_get_contents(app_path('Domain/Reporting/ReportProjection.php')).file_get_contents(app_path('Domain/Reporting/ReportExporter.php')))];
        file_put_contents(base_path('output/reporting-benchmark-'.$count.'.json'), json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->assertLessThan(256, $result['peak_memory_mb']);
        $this->assertLessThanOrEqual(2, $latencies['common']['p95_seconds']);
        foreach (['receipts', 'tax', 'stock'] as $case) {
            $this->assertLessThanOrEqual(2, $latencies[$case]['p95_seconds'], $case);
        }
        $this->assertLessThanOrEqual(5, $latencies['annual']['p95_seconds']);
        foreach ($exportSeconds as $seconds) {
            $this->assertLessThan(300, $seconds);
        }
    }
}
