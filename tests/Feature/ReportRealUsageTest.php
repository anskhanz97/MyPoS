<?php

namespace Tests\Feature;

use App\Domain\Reporting\ReportProjection;
use App\Domain\Shared\DocumentNumber;
use App\Models\CashSession;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\OperationalCustomer;
use App\Models\ProductVariant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsReportingFixture;
use Tests\TestCase;
use Throwable;

#[Group('performance')]
class ReportRealUsageTest extends TestCase
{
    use BuildsReportingFixture;

    public function test_diverse_ledgers_reconcile_under_concurrent_report_requests(): void
    {
        if (getenv('RUN_REAL_REPORT_BENCHMARK') !== '1') {
            $this->markTestSkipped('Opt in on the disposable MySQL reporting database.');
        }
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mypos_test', DB::connection()->getDatabaseName());
        $this->assertSame('3307', (string) DB::connection()->getConfig('port'));
        $lines = (int) (getenv('REAL_REPORT_LINES') ?: 100000);
        $this->assertContains($lines, [100000, 1000000]);
        $path = base_path('output/reporting-real-usage-'.$lines.'.json');
        $progress = ['state' => 'seeding', 'lines' => $lines];
        file_put_contents($path, json_encode($progress, JSON_PRETTY_PRINT));
        $merchant = Merchant::query()->where('slug', 'report-real-usage-'.$lines)->first();
        if ($merchant === null) {
            $context = $this->soldFixture();
            $merchant = $context['f']['merchant'];
            $merchant->update(['slug' => 'report-real-usage-'.$lines]);
            $this->seedDiverseLedger($context, $lines, $path);
        } elseif (DB::table('sale_lines')->where('merchant_id', $merchant->id)->count() < $lines + 1 || ! DB::table('sale_returns')->where('merchant_id', $merchant->id)->exists() || DB::table('stock_balances')->where('merchant_id', $merchant->id)->count() < 640) {
            $firstSale = DB::table('sales')->find(DB::table('sales')->where('merchant_id', $merchant->id)->min('id'));
            $firstLine = DB::table('sale_lines')->where('sale_id', $firstSale->id)->first();
            $context = ['f' => ['merchant' => $merchant, 'store' => Store::query()->findOrFail($firstSale->store_id), 'first' => ProductVariant::query()->findOrFail($firstLine->product_variant_id)]];
            foreach (['owner', 'manager', 'cashier'] as $role) {
                $context['f'][$role] = User::query()->findOrFail(DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('role', $role)->value('user_id'));
            }
            $context['location'] = InventoryLocation::query()->where('store_id', $firstSale->store_id)->firstOrFail();
            $this->seedDiverseLedger($context, $lines, $path);
        }
        $this->assertSame($lines, DB::table('sale_lines as l')->join('sales as s', 's.id', '=', 'l.sale_id')->where('l.merchant_id', $merchant->id)->where('s.number', 'like', 'SALE-LOAD-%')->count());
        $this->assertSame(intdiv($lines, 10), DB::table('sales')->where('merchant_id', $merchant->id)->where('number', 'like', 'SALE-LOAD-%')->count());
        $this->assertSame(0, DB::table('sale_lines as l')->leftJoin('sales as s', fn ($join) => $join->on('s.id', '=', 'l.sale_id')->on('s.store_id', '=', 'l.store_id')->on('s.merchant_id', '=', 'l.merchant_id'))->where('l.merchant_id', $merchant->id)->whereNull('s.id')->count());
        $this->assertSame(0, DB::table('inventory_cost_entries as e')->leftJoin('stock_movements as m', fn ($join) => $join->on('m.id', '=', 'e.stock_movement_id')->on('m.store_id', '=', 'e.store_id')->on('m.merchant_id', '=', 'e.merchant_id'))->where('e.merchant_id', $merchant->id)->whereNotNull('e.stock_movement_id')->whereNull('m.id')->count());
        $this->assertSame(1, (int) DB::selectOne('SELECT @@foreign_key_checks checks_enabled')->checks_enabled);
        $this->assertSame(1, (int) DB::selectOne('SELECT @@unique_checks checks_enabled')->checks_enabled);
        $stores = Store::query()->where('merchant_id', $merchant->id)->where('code', 'like', 'LOAD-%')->orderBy('code')->get();
        $this->assertCount(8, $stores);
        $this->assertSame(365, DB::table('sales')->where('merchant_id', $merchant->id)->distinct()->count('business_date'));
        $this->assertSame(80, DB::table('sale_lines')->where('merchant_id', $merchant->id)->distinct()->count('product_variant_id'));
        if (getenv('REAL_REPORT_SEED_ONLY') === '1') {
            file_put_contents($path, json_encode(['state' => 'seeded', 'lines' => $lines, 'merchant_id' => $merchant->id], JSON_PRETTY_PRINT));

            return;
        }
        $started = microtime(true);
        $projection = app(ReportProjection::class);
        if (! $projection->isCurrent($merchant)) {
            $projection->refresh($merchant);
        }
        $progress['projection_seconds'] = microtime(true) - $started;
        $progress['state'] = 'checking_cold_requests';
        file_put_contents($path, json_encode($progress, JSON_PRETTY_PRINT));
        $ownerId = (int) DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('role', 'owner')->value('user_id');
        $start = DB::table('sales')->where('merchant_id', $merchant->id)->min('business_date');
        $end = DB::table('sales')->where('merchant_id', $merchant->id)->max('business_date');
        $cases = [];
        $expectations = [];
        foreach ([null, $stores[0], $stores[4]] as $store) {
            foreach (['sales-by-product', 'sales-summary', 'tax-report', 'stock-levels', 'daily-sales', 'monthly-tax'] as $key) {
                foreach ([29, 364] as $days) {
                    $from = CarbonImmutable::parse($end)->subDays($days)->toDateString();
                    $filters = ['start' => $from, 'end' => substr($end, 0, 10), 'per_page' => 25];
                    if ($store !== null) {
                        $filters['store_id'] = $store->public_id;
                    }
                    $scopeKey = ($store?->id ?? 0).':'.$from;
                    $expectations[$scopeKey] ??= $this->expectedLedgerSummary($merchant->id, $store?->id, $from, substr($end, 0, 10));
                    $expected = $key === 'stock-levels' ? [] : $expectations[$scopeKey];
                    $stockRows = $key === 'stock-levels' ? DB::table('stock_balances')->where('merchant_id', $merchant->id)->when($store !== null, fn ($q) => $q->where('store_id', $store->id))->count() : null;
                    $cases[] = ['key' => $key, 'scope' => $store?->code ?? 'merchant', 'days' => $days + 1, 'url' => '/api/v1/merchants/'.$merchant->public_id.'/reporting/reports/'.$key.'?'.http_build_query($filters), 'expected' => $expected, 'expected_stock_rows' => $stockRows];
                }
            }
        }
        $database = config('database.connections.mysql');
        $cold = [];
        foreach ($cases as $case) {
            $cold[] = self::requestReport($database, $ownerId, $case, 'cold-'.Str::uuid());
        }
        $progress['cold'] = $cold;
        $firstSale = Sale::query()->findOrFail(DB::table('sales')->where('merchant_id', $merchant->id)->min('id'));
        $store = Store::query()->findOrFail($firstSale->store_id);
        $register = Register::query()->findOrFail($firstSale->register_id);
        $firstLine = SaleLine::query()->where('sale_id', $firstSale->id)->firstOrFail();
        $context = [
            'f' => ['owner' => User::query()->findOrFail($ownerId), 'cashier' => User::query()->findOrFail($firstSale->created_by_user_id)],
            'base' => '/api/v1/merchants/'.$merchant->public_id.'/stores/'.$store->public_id.'/registers/'.$register->public_id,
            'location' => InventoryLocation::query()->where('store_id', $store->id)->where('disposition', 'sellable')->firstOrFail(),
            'session' => ['id' => CashSession::query()->findOrFail($firstSale->cash_session_id)->public_id],
        ];
        $liveSale = $this->postReportingSale($context, [['variant_id' => ProductVariant::query()->findOrFail($firstLine->product_variant_id)->public_id, 'quantity' => '1.000000']]);
        $progress['live_sale'] = $liveSale['number'];
        $this->assertNotNull($projection->readWindow($merchant));
        foreach ($cases as &$case) {
            if ($case['scope'] === 'merchant' || $case['scope'] === $store->code) {
                foreach ($case['expected'] as $metric => &$amount) {
                    $delta = match ($metric) {
                        'revenue' => $liveSale['totals']['net'], 'tax' => $liveSale['totals']['tax'],
                        'sales_including_tax' => $liveSale['totals']['gross'], 'discounts' => $liveSale['totals']['discount'],
                        'cogs' => '40.00', 'gross_profit' => (string) BigDecimal::of($liveSale['totals']['net'])->minus('40.00'),
                    };
                    $amount = (string) BigDecimal::of($amount)->plus($delta);
                }
                unset($amount);
            }
        }
        unset($case);
        $progress['state'] = 'concurrent_requests';
        file_put_contents($path, json_encode($progress, JSON_PRETTY_PRINT));
        $users = [$ownerId];
        for ($i = 0; $i < 4; $i++) {
            $user = User::factory()->create();
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => 'owner']);
            $users[] = $user->id;
        }
        $tasks = [];
        $merchantId = (int) $merchant->id;
        $prefix = 'report-load-'.Str::uuid();
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT');
        for ($worker = 0; $worker < 5; $worker++) {
            $userId = $users[$worker];
            $workerCases = array_map(fn (int $iteration): array => $cases[($worker * 7 + $iteration) % count($cases)], range(0, 7));
            $tasks[] = static function () use ($database, $userId, $workerCases, $prefix, $worker, $merchantId, $systemRoot): array {
                $measurements = [];
                try {
                    if (is_string($systemRoot)) {
                        putenv('SystemRoot='.$systemRoot);
                    }
                    foreach ($workerCases as $iteration => $case) {
                        $refreshSeconds = null;
                        if ($worker === 0 && $iteration === 3) {
                            $refreshStarted = microtime(true);
                            app(ReportProjection::class)->refresh(Merchant::query()->findOrFail($merchantId));
                            $refreshSeconds = microtime(true) - $refreshStarted;
                        }
                        $measurement = self::requestReport($database, $userId, $case, $prefix.'-'.$worker.'-'.$iteration);
                        if ($refreshSeconds !== null) {
                            $measurement['projection_refresh_seconds'] = $refreshSeconds;
                        }
                        $measurement['allocated_number'] = app(DocumentNumber::class)->next($merchantId, 'LOAD', '2026-10-17');
                        $measurements[] = $measurement;
                    }
                } catch (Throwable $exception) {
                    $measurements[] = ['key' => 'worker-error', 'scope' => (string) $worker, 'days' => 30, 'status' => 0, 'seconds' => 0, 'reconciled' => false, 'error' => $exception->getMessage()];
                }

                return $measurements;
            };
        }
        $parallel = array_merge(...Concurrency::run($tasks, timeout: 180));
        $progress['concurrent'] = $parallel;
        $progress['concurrent_users'] = 5;
        $progress['cases'] = count($cases);
        $progress['fixture'] = ['stores' => 8, 'products' => 80, 'business_dates' => 365, 'customers' => 500, 'receipt_lines' => 10, 'actual_sale_lines' => DB::table('sale_lines')->where('merchant_id', $merchant->id)->count(), 'tax_rates' => [0, 5, 18], 'discounts' => true, 'split_tenders' => true, 'known_line_costs' => true, 'source' => 'Synthetic SQL read fixture plus the original sale and API live-sale checks; not a checkout-throughput test'];
        $passed = true;
        foreach ([...$cold, ...$parallel] as $measurement) {
            if ($measurement['status'] !== 200 || ! $measurement['reconciled'] || $measurement['seconds'] > ($measurement['days'] > 30 ? 5 : 2)) {
                $passed = false;
            }
        }
        $progress['state'] = $passed ? 'passed' : 'failed';
        $progress['notes'] = 'Cold means a new result-cache namespace per request, not an operating-system/disk cache reset. Five distinct owner accounts use independent PHP HTTP-kernel workers simultaneously; network and process startup are excluded. Financial totals are checked against independent signed sale/return header sums on every request. The SQL fixture exercises report reads and recorded sale costs, not checkout posting throughput or full inventory-ledger audit sequencing.';
        $progress['baseline'] = ['php' => PHP_VERSION, 'mysql' => DB::selectOne('SELECT VERSION() version')->version, 'innodb_buffer_pool_mb' => (int) DB::selectOne('SELECT @@innodb_buffer_pool_size bytes')->bytes / 1048576, 'php_peak_mb' => memory_get_peak_usage(true) / 1048576, 'database_directory' => DB::selectOne('SELECT @@datadir path')->path, 'source_sha256' => hash('sha256', file_get_contents(app_path('Domain/Reporting/ReportQuery.php')).file_get_contents(app_path('Domain/Reporting/ReportProjection.php')).file_get_contents(app_path('Domain/Reporting/ReportExporter.php')))];
        file_put_contents($path, json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->assertCount(40, $parallel, json_encode($parallel));
        $this->assertCount(40, array_unique(array_column($parallel, 'allocated_number')));
        foreach ([...$cold, ...$parallel] as $measurement) {
            $this->assertSame(200, $measurement['status']);
            $this->assertTrue($measurement['reconciled'], json_encode($measurement));
            $this->assertLessThanOrEqual($measurement['days'] > 30 ? 5 : 2, $measurement['seconds'], json_encode($measurement));
        }
    }

    /** @param array<string, mixed> $database
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    private static function requestReport(array $database, int $userId, array $case, string $prefix): array
    {
        config(['database.connections.mysql' => $database, 'database.default' => 'mysql', 'cache.default' => 'database', 'cache.prefix' => $prefix, 'queue.default' => 'sync', 'observability.enabled' => true]);
        DB::purge('mysql');
        Cache::purge('database');
        Auth::guard()->setUser(User::query()->findOrFail($userId));
        $request = Request::create($case['url'], 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $kernel = app(Kernel::class);
        $started = microtime(true);
        $response = $kernel->handle($request);
        $seconds = microtime(true) - $started;
        $data = json_decode($response->getContent(), true);
        $kernel->terminate($request, $response);
        $actual = $data['data']['summary'] ?? [];
        $reconciled = true;
        foreach ($case['expected'] as $metric => $expected) {
            if (! isset($actual[$metric]) || BigDecimal::of($actual[$metric])->compareTo($expected) !== 0) {
                $reconciled = false;
            }
        }
        if ($case['expected_stock_rows'] !== null) {
            $rows = $data['data']['rows'] ?? [];
            $quantities = DB::table('stock_balances')->whereIn('id', array_column($rows, 'group_key'))->pluck('quantity_base', 'id');
            $reconciled = ($data['data']['pagination']['total'] ?? null) === $case['expected_stock_rows'];
            foreach ($rows as $row) {
                if (! isset($quantities[$row['group_key']]) || BigDecimal::of($row['quantity'])->compareTo($quantities[$row['group_key']]) !== 0) {
                    $reconciled = false;
                }
            }
        }

        return ['key' => $case['key'], 'scope' => $case['scope'], 'days' => $case['days'], 'seconds' => $seconds, 'status' => $response->getStatusCode(), 'reconciled' => $reconciled, 'actual' => array_intersect_key($actual, $case['expected']), 'expected' => $case['expected']];
    }

    /** @return array<string, string> */
    private function expectedLedgerSummary(int $merchantId, ?int $storeId, string $start, string $end): array
    {
        $totals = array_fill_keys(['revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs'], '0');
        foreach (['sales' => ['sale_lines', 'sale_id'], 'sale_returns' => ['sale_return_lines', 'sale_return_id']] as $table => [$lines, $foreignKey]) {
            $headers = DB::table($table)->where('merchant_id', $merchantId)->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))->whereBetween('business_date', [$start, $end]);
            $amounts = (clone $headers)->selectRaw('SUM(net_total) revenue, SUM(tax_total) tax, SUM(total) sales_including_tax, SUM(discount_total) discounts')->first();
            $quantity = DB::table($lines.' as l')->joinSub((clone $headers)->select('id'), 'h', 'h.id', '=', 'l.'.$foreignKey)->sum('l.quantity_base');
            $values = (array) $amounts;
            $values['cogs'] = (string) BigDecimal::of((string) $quantity)->multipliedBy('40');
            foreach ($totals as $metric => &$amount) {
                $amount = (string) ($table === 'sales' ? BigDecimal::of($amount)->plus((string) ($values[$metric] ?? '0')) : BigDecimal::of($amount)->minus((string) ($values[$metric] ?? '0')));
            }
            unset($amount);
        }
        $totals['gross_profit'] = (string) BigDecimal::of($totals['revenue'])->minus($totals['cogs']);

        return $totals;
    }

    /** @param array<string, mixed> $context */
    private function seedDiverseLedger(array $context, int $lines, string $path): void
    {
        $merchant = $context['f']['merchant'];
        $mid = (int) $merchant->id;
        $templateSale = (array) DB::table('sales')->find(DB::table('sales')->where('merchant_id', $mid)->min('id'));
        $templateLine = (array) DB::table('sale_lines')->where('sale_id', $templateSale['id'])->orderBy('sequence')->first();
        $templateMovement = (array) DB::table('stock_movements')->where('sale_line_id', $templateLine['id'])->first();
        $templateCost = (array) DB::table('inventory_cost_entries')->where('merchant_id', $mid)->where('store_id', $templateSale['store_id'])->where('stock_movement_id', $templateMovement['id'])->first();
        $templateBalance = (array) DB::table('inventory_cost_balances')->where('id', $templateCost['inventory_cost_balance_id'])->first();
        $templateSession = (array) DB::table('cash_sessions')->where('id', $templateSale['cash_session_id'])->first();
        $templateTender = (array) DB::table('sale_tenders')->where('sale_id', $templateSale['id'])->first();
        $templateStockBalance = (array) DB::table('stock_balances')->where('merchant_id', $mid)->first();
        $templateCart = (array) DB::table('carts')->where('id', $templateSale['cart_id'])->first();
        $templateQuote = (array) DB::table('cart_quotes')->where('id', $templateSale['cart_quote_id'])->first();
        $date = substr($templateSale['business_date'], 0, 10);
        DB::statement('CREATE TEMPORARY TABLE report_load_numbers (n INT PRIMARY KEY)');
        DB::statement('SET SESSION cte_max_recursion_depth=6000');
        DB::statement('INSERT INTO report_load_numbers WITH RECURSIVE n AS (SELECT 0 n UNION ALL SELECT n+1 FROM n WHERE n<4999) SELECT n FROM n');
        DB::statement('SET SESSION cte_max_recursion_depth=1000');
        DB::statement('CREATE TEMPORARY TABLE report_load_stores (idx INT PRIMARY KEY, store_id BIGINT, register_id BIGINT, location_id BIGINT, cart_id BIGINT, quote_id BIGINT, name VARCHAR(255))');
        DB::statement('CREATE TEMPORARY TABLE report_load_variants (idx INT PRIMARY KEY, variant_id BIGINT, name VARCHAR(255), sku VARCHAR(120))');
        for ($i = 0; $i < 8; $i++) {
            $store = Store::query()->where('merchant_id', $mid)->where('code', 'LOAD-'.($i + 1))->first() ?? ($i === 0 ? $context['f']['store'] : Store::factory()->create(['merchant_id' => $mid, 'timezone' => 'Asia/Karachi']));
            $store->update(['code' => 'LOAD-'.($i + 1)]);
            $register = Register::query()->where('store_id', $store->id)->first() ?? Register::factory()->create(['merchant_id' => $mid, 'store_id' => $store->id]);
            $location = InventoryLocation::query()->where('store_id', $store->id)->first() ?? InventoryLocation::factory()->create(['merchant_id' => $mid, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main stock', 'disposition' => 'sellable']);
            $cartId = DB::table('carts')->where('store_id', $store->id)->value('id');
            if ($cartId === null) {
                $cart = $templateCart;
                unset($cart['id']);
                $cartId = DB::table('carts')->insertGetId(array_replace($cart, ['public_id' => Str::uuid()->getBytes(), 'store_id' => $store->id, 'register_id' => $register->id, 'inventory_location_id' => $location->id, 'reference' => 'LOAD-CART-'.$i]));
            }
            $quoteId = DB::table('cart_quotes')->where('cart_id', $cartId)->value('id');
            if ($quoteId === null) {
                $quote = $templateQuote;
                unset($quote['id']);
                $quoteId = DB::table('cart_quotes')->insertGetId(array_replace($quote, ['public_id' => Str::uuid()->getBytes(), 'store_id' => $store->id, 'cart_id' => $cartId]));
            }
            DB::table('report_load_stores')->insert(['idx' => $i, 'store_id' => $store->id, 'register_id' => $register->id, 'location_id' => $location->id, 'cart_id' => $cartId, 'quote_id' => $quoteId, 'name' => $store->name]);
        }
        for ($i = 0; $i < 80; $i++) {
            $variant = ProductVariant::query()->where('merchant_id', $mid)->where('sku', 'LOAD-SKU-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT))->first() ?? ($i === 0 ? $context['f']['first'] : ProductVariant::factory()->create(['merchant_id' => $mid, 'base_unit_id' => $context['f']['first']->base_unit_id]));
            $variant->update(['sku' => 'LOAD-SKU-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            DB::table('report_load_variants')->insert(['idx' => $i, 'variant_id' => $variant->id, 'name' => 'Load product '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'sku' => 'LOAD-SKU-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }
        $existingCustomers = DB::table('operational_customers')->where('merchant_id', $mid)->count();
        if ($existingCustomers < 500) {
            OperationalCustomer::factory()->count(500 - $existingCustomers)->create(['merchant_id' => $mid]);
        }
        $customerIds = DB::table('operational_customers')->where('merchant_id', $mid)->orderBy('id')->pluck('id')->all();
        $customers = 'ELT(MOD(k,500)+1,'.implode(',', $customerIds).')';
        $actors = implode(',', [$context['f']['owner']->id, $context['f']['manager']->id, $context['f']['cashier']->id]);
        $days = "DATE_SUB('$date', INTERVAL FLOOR(k/8) DAY)";
        for ($offset = DB::table('cash_sessions')->where('merchant_id', $mid)->where('number', 'like', 'LOAD-CS-%')->count(); $offset < 2920; $offset += 5000) {
            $from = 'FROM (SELECT n+'.(int) $offset.' k FROM report_load_numbers WHERE n+'.(int) $offset.'<2920) n JOIN report_load_stores st ON st.idx=MOD(k,8)';
            $this->cloneRows('cash_sessions', $templateSession, ['store_id' => 'st.store_id', 'register_id' => 'st.register_id', 'number' => "CONCAT('LOAD-CS-',k)", 'status' => "'closed'", 'active_guard' => 'NULL', 'open_idempotency_key' => 'UUID()', 'business_date' => $days, 'opened_at' => "CONCAT($days, ' 03:00:00')", 'closed_at' => "CONCAT($days, ' 18:00:00')"], $from);
        }
        DB::statement('CREATE TEMPORARY TABLE report_load_sessions AS SELECT CAST(SUBSTRING(number,9) AS UNSIGNED) idx, id FROM cash_sessions WHERE merchant_id='.$mid." AND number LIKE 'LOAD-CS-%'");
        DB::statement('ALTER TABLE report_load_sessions ADD PRIMARY KEY (idx)');
        $balanceBase = $templateBalance;
        unset($balanceBase['id']);
        $this->cloneRows('inventory_cost_balances', $balanceBase, ['store_id' => 'st.store_id', 'product_variant_id' => 'v.variant_id', 'last_cost_entry_id' => 'NULL', 'quantity_base' => '100000', 'inventory_value' => '4000000', 'average_unit_cost' => '40'], 'FROM report_load_stores st CROSS JOIN report_load_variants v WHERE NOT EXISTS (SELECT 1 FROM inventory_cost_balances b WHERE b.merchant_id='.$mid.' AND b.store_id=st.store_id AND b.product_variant_id=v.variant_id)');
        DB::statement('SET SESSION foreign_key_checks=0, unique_checks=0');
        try {
            for ($offset = intdiv(DB::table('sale_lines')->where('merchant_id', $mid)->count() - 1, 10); $offset < intdiv($lines, 10); $offset += 5000) {
                DB::transaction(function () use ($offset, $lines, $mid, $templateSale, $templateLine, $templateMovement, $templateCost, $templateTender, $customers, $actors, $date): void {
                    $batchSize = min(5000, intdiv($lines, 10) - $offset);
                    $from = 'FROM (SELECT n+'.(int) $offset.' k FROM report_load_numbers WHERE n<'.$batchSize.') n JOIN report_load_stores st ON st.idx=MOD(FLOOR(k/365),8) JOIN report_load_sessions cs ON cs.idx=MOD(k,365)*8+st.idx';
                    $day = "DATE_SUB('$date', INTERVAL MOD(k,365) DAY)";
                    $this->cloneRows('sales', $templateSale, ['cart_id' => 'st.cart_id', 'cart_quote_id' => 'st.quote_id', 'store_id' => 'st.store_id', 'register_id' => 'st.register_id', 'cash_session_id' => 'cs.id', 'number' => "CONCAT('SALE-LOAD-',LPAD(k,8,'0'))", 'client_transaction_id' => 'UUID()', 'idempotency_key' => 'UUID()', 'customer_id' => 'CASE WHEN MOD(k,4)=0 THEN NULL ELSE '.$customers.' END', 'created_by_user_id' => 'ELT(MOD(k,3)+1,'.$actors.')', 'business_date' => $day, 'finalized_at' => "CONCAT($day,' 12:00:00')", 'cash_change' => '0', 'reporting_snapshot' => "JSON_OBJECT('version',1,'provenance','captured','store',st.name,'employee','Load staff','timezone','Asia/Karachi','utc_offset_minutes',300)"], $from);
                    $saleRange = 's.merchant_id='.$mid.' AND s.number BETWEEN '.DB::getPdo()->quote('SALE-LOAD-'.str_pad((string) $offset, 8, '0', STR_PAD_LEFT)).' AND '.DB::getPdo()->quote('SALE-LOAD-'.str_pad((string) ($offset + $batchSize - 1), 8, '0', STR_PAD_LEFT));
                    $quantity = '(MOD(n.n,3)+1)*0.25';
                    $price = '(50+v.idx)';
                    $discount = 'CASE WHEN MOD(s.id,5)=0 THEN 1 ELSE 0 END';
                    $net = "ROUND($quantity*$price,2)-$discount";
                    $rate = 'ELT(MOD(v.idx,3)+1,0,5,18)';
                    $tax = "ROUND(($net)*($rate)/100,2)";
                    $this->cloneRows('sale_lines', $templateLine, ['store_id' => 's.store_id', 'sale_id' => 's.id', 'sequence' => 'n.n+1', 'product_variant_id' => 'v.variant_id', 'product_name_snapshot' => 'v.name', 'sku_snapshot' => 'v.sku', 'quantity_uom' => $quantity, 'quantity_base' => $quantity, 'unit_price' => $price, 'pre_discount' => "ROUND($quantity*$price,2)", 'discount' => $discount, 'net' => $net, 'tax' => $tax, 'gross' => "($net)+$tax", 'tax_rate_snapshot' => $rate, 'tax_classification_snapshot' => "CONCAT('VAT ',$rate,'%')"], 'FROM sales s JOIN report_load_numbers n ON n.n<10 JOIN report_load_variants v ON v.idx=MOD(s.id*7+n.n*3,80) WHERE '.$saleRange);
                    DB::statement('UPDATE sales s JOIN (SELECT sale_id,SUM(pre_discount) subtotal,SUM(discount) discount_total,SUM(net) net_total,SUM(tax) tax_total,SUM(gross) total FROM sale_lines l JOIN sales s ON s.id=l.sale_id WHERE '.$saleRange.' GROUP BY sale_id) x ON x.sale_id=s.id SET s.subtotal=x.subtotal,s.discount_total=x.discount_total,s.net_total=x.net_total,s.tax_total=x.tax_total,s.total=x.total');
                    $this->cloneRows('stock_movements', $templateMovement, ['store_id' => 'l.store_id', 'inventory_location_id' => 'st.location_id', 'product_variant_id' => 'l.product_variant_id', 'sale_line_id' => 'l.id', 'source_reference' => 's.number', 'source_line_reference' => 'HEX(l.public_id)', 'source_key' => "SHA2(CONCAT('load-sale-',l.id),256)", 'quantity_base' => '-l.quantity_base', 'occurred_at' => 's.finalized_at'], 'FROM sale_lines l JOIN sales s ON s.id=l.sale_id JOIN report_load_stores st ON st.store_id=l.store_id WHERE '.$saleRange);
                    $this->cloneRows('inventory_cost_entries', $templateCost, ['store_id' => 'l.store_id', 'product_variant_id' => 'l.product_variant_id', 'inventory_cost_balance_id' => 'b.id', 'stock_movement_id' => 'm.id', 'quantity_delta' => '-l.quantity_base', 'unit_cost' => '40', 'value_delta' => '-l.quantity_base*40', 'source_reference' => 's.number', 'source_line_reference' => 'HEX(l.public_id)', 'source_key' => "SHA2(CONCAT('load-cost-',l.id),256)", 'effective_at' => 's.finalized_at', 'applied_at' => 's.finalized_at'], 'FROM sale_lines l JOIN sales s ON s.id=l.sale_id JOIN stock_movements m ON m.sale_line_id=l.id JOIN inventory_cost_balances b ON b.merchant_id=l.merchant_id AND b.store_id=l.store_id AND b.product_variant_id=l.product_variant_id WHERE '.$saleRange);
                    foreach (['cash', 'external_card'] as $type) {
                        $this->cloneRows('sale_tenders', $templateTender, ['sale_id' => 's.id', 'store_id' => 's.store_id', 'client_tender_id' => 'UUID()', 'type' => DB::getPdo()->quote($type), 'amount' => $type === 'cash' ? 'ROUND(s.total*0.4,2)' : 's.total-ROUND(s.total*0.4,2)'], 'FROM sales s WHERE '.$saleRange);
                    }
                });
                file_put_contents($path, json_encode(['state' => 'seeding', 'lines' => $lines, 'seeded_lines' => min($lines, ($offset + 5000) * 10)], JSON_PRETTY_PRINT));
            }
        } finally {
            DB::statement('SET SESSION foreign_key_checks=1, unique_checks=1');
        }
        DB::statement('UPDATE inventory_cost_balances b LEFT JOIN (SELECT store_id,product_variant_id,SUM(quantity_base) quantity FROM sale_lines WHERE merchant_id='.$mid.' GROUP BY store_id,product_variant_id) l ON l.store_id=b.store_id AND l.product_variant_id=b.product_variant_id SET b.quantity_base=100000-COALESCE(l.quantity,0),b.inventory_value=(100000-COALESCE(l.quantity,0))*40 WHERE b.merchant_id='.$mid);
        $this->cloneRows('stock_balances', $templateStockBalance, ['store_id' => 'b.store_id', 'inventory_location_id' => 'st.location_id', 'product_variant_id' => 'b.product_variant_id', 'quantity_base' => 'b.quantity_base', 'last_stock_movement_id' => 'NULL'], 'FROM inventory_cost_balances b JOIN report_load_stores st ON st.store_id=b.store_id WHERE b.merchant_id='.$mid.' AND NOT EXISTS (SELECT 1 FROM stock_balances sb WHERE sb.merchant_id=b.merchant_id AND sb.store_id=b.store_id AND sb.product_variant_id=b.product_variant_id)');
        DB::statement('UPDATE stock_balances sb JOIN inventory_cost_balances b ON b.store_id=sb.store_id AND b.product_variant_id=sb.product_variant_id SET sb.quantity_base=b.quantity_base WHERE sb.merchant_id='.$mid);
        DB::statement('ANALYZE TABLE sales,sale_lines,stock_movements,inventory_cost_entries');
        if (! DB::table('sale_returns')->where('merchant_id', $mid)->exists()) {
            $this->withoutExceptionHandling();
            $base = '/api/v1/merchants/'.$merchant->public_id.'/stores/'.$context['f']['store']->public_id.'/registers/'.Register::query()->findOrFail($templateSale['register_id'])->public_id;
            $sale = Sale::query()->findOrFail($templateSale['id']);
            $session = CashSession::query()->findOrFail($templateSale['cash_session_id']);
            $line = SaleLine::query()->findOrFail($templateLine['id']);
            $this->actingAs($context['f']['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales/'.$sale->public_id.'/returns', [
                'register_session_id' => $session->public_id, 'reason' => 'Read benchmark partial return',
                'lines' => [['sale_line_id' => $line->public_id, 'quantity' => '0.500000', 'disposition' => 'restock']],
                'refund_tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'state' => 'refunded', 'amount' => '59.00', 'confirmation_source' => 'not_applicable']],
            ])->assertCreated();
        }
    }

    /** @param array<string, mixed> $template
     * @param  array<string, string>  $expressions
     */
    private function cloneRows(string $table, array $template, array $expressions, string $from): void
    {
        unset($template['id']);
        $select = $bindings = [];
        foreach ($template as $column => $value) {
            if ($column === 'public_id') {
                $select[] = "UNHEX(REPLACE(UUID(),'-',''))";
            } elseif (isset($expressions[$column])) {
                $select[] = $expressions[$column];
            } else {
                $select[] = '?';
                $bindings[] = $value;
            }
        }
        DB::insert('INSERT INTO `'.$table.'` (`'.implode('`,`', array_keys($template)).'`) SELECT '.implode(',', $select).' '.$from, $bindings);
    }
}
