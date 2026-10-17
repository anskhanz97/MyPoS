<?php

namespace App\Domain\Reporting;

use App\Domain\Shared\Math\Decimal;
use App\Models\Merchant;
use App\Models\Store;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class ReportQuery
{
    private ?string $ledgerStamp = null;

    private const MONEY_COLUMNS = ['revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'gross_profit', 'amount', 'expected_amount', 'counted_amount', 'variance_amount', 'inventory_value', 'unit_cost', 'value_delta', 'average_unit_cost', 'fee_amount', 'difference_amount', 'settled_amount', 'net_amount', 'paid_amount', 'gross_amount', 'recorded_expenses', 'posted_payroll', 'average_transaction', 'pending_refunds', 'opening_amount', 'deduction_amount', 'advance_amount', 'outstanding_amount', 'operating_profit', 'cash_amount', 'external_recorded_amount', 'supplier_recorded_amount', 'unallocated_payroll', 'statutory_amount'];

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $access
     * @return array<string, mixed>
     */
    public function run(Merchant $merchant, ?Store $store, string $key, array $filters, array $access): array
    {
        return $this->withDatabaseMemory(fn (): array => $this->readSnapshot(fn (): array => $this->execute($merchant, $store, $key, $filters, $access)));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function readSnapshot(callable $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return DB::transaction(fn () => $callback());
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withDatabaseMemory(callable $callback): mixed
    {
        if (DB::getDriverName() !== 'mysql') {
            return $callback();
        }
        $settings = DB::selectOne('SELECT @@session.tmp_table_size temp_size, @@session.max_heap_table_size heap_size, @@session.sort_buffer_size sort_size');
        $bytes = max(16, min(128, (int) config('reporting.query_temp_memory_mb', 128))) * 1048576;
        try {
            DB::statement('SET SESSION tmp_table_size = ?, max_heap_table_size = ?, sort_buffer_size = ?', [$bytes, $bytes, 8388608]);

            return $callback();
        } finally {
            DB::statement('SET SESSION tmp_table_size = ?, max_heap_table_size = ?, sort_buffer_size = ?', [(int) $settings->temp_size, (int) $settings->heap_size, (int) $settings->sort_size]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $access
     * @return array<string, mixed>
     */
    private function execute(Merchant $merchant, ?Store $store, string $key, array $filters, array $access): array
    {
        $report = ReportCatalog::get($key);
        $start = CarbonImmutable::parse($filters['start']);
        $end = CarbonImmutable::parse($filters['end']);
        abort_if($start->diffInDays($end) > 365, 422, 'Report range cannot exceed 366 inclusive days.');
        $query = $this->rows($merchant, $store, $key, $filters, $access);
        $columns = $this->columns($query);
        foreach ($columns as &$column) {
            $column['label'] = match ($column['key']) {
                'label' => match ($report['dimension']) {
                    'product', 'line', 'variant', 'batch' => 'Product', 'receipt' => 'Receipt / document',
                    'employee' => 'Employee', 'store' => 'Store', 'session' => 'Cash session',
                    default => ucfirst(str_replace('_', ' ', $report['dimension'])),
                },
                'cogs' => 'COGS', 'margin_percent' => 'Margin %', 'turnover_percent' => 'Turnover %',
                default => $column['label'],
            };
        }
        unset($column);
        if (! $access['costs']) {
            $columns = array_values(array_filter($columns, fn ($c) => ! in_array($c['key'], ['cogs', 'gross_profit', 'margin_percent', 'inventory_value', 'unit_cost', 'value_delta', 'average_unit_cost'], true)));
        }
        $cacheable = $this->ledgerStamp !== null && in_array($report['type'], ['sales', 'transactions', 'customers'], true) && $key !== 'staff-z-report';
        $summaryKey = 'reports:summary:'.hash('sha256', $merchant->id.$this->ledgerStamp.$query->toSql().serialize([$query->getBindings(), $columns]));
        $totals = $cacheable ? Cache::remember($summaryKey, 300, fn () => $this->totals($query, $columns)) : $this->totals($query, $columns);
        if ($key === 'profit-loss' && $store !== null && Decimal::of($totals['unallocated_payroll'] ?? '0')->isPositive()) {
            $totals['operating_profit'] = null;
        }
        if (in_array($report['dimension'], ['product', 'line', 'tax'], true)) {
            // A receipt can span several groups, so group counts cannot be added.
            unset($totals['transactions'], $totals['returns']);
        }
        $sort = $filters['sort'] ?? ($key === 'top-bottom-sellers' ? 'revenue' : 'label');
        $allowed = array_column($columns, 'key');
        abort_unless(in_array($sort, $allowed, true) || $sort === 'group_key', 422, 'Invalid report sort column.');
        $direction = $filters['direction'] ?? ($key === 'top-bottom-sellers' ? 'desc' : 'asc');
        $this->order($query, $sort, $direction, $totals);
        $page = (int) ($filters['page'] ?? 1);
        $size = (int) ($filters['per_page'] ?? 50);
        $readPage = fn () => (clone $query)->forPage($page, $size)->get()->all();
        $pageKey = 'reports:page:'.hash('sha256', $summaryKey.serialize([$sort, $direction, $page, $size]));
        $cachePage = $cacheable && in_array($report['dimension'], ['receipt', 'product', 'tax', 'day', 'month', 'quarter', 'year', 'period', 'hour'], true);
        $paginator = new LengthAwarePaginator($cachePage ? Cache::remember($pageKey, 300, $readPage) : $readPage(), $totals['rows'], $size, $page);
        $rows = array_map(fn ($row) => $this->present((array) $row, $columns, $totals['revenue'] ?? null), $paginator->items());
        $result = ['report' => $key, 'title' => $report['title'], 'definition_version' => 2, 'scope' => $store === null ? 'merchant' : 'store',
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'store' => $store !== null ? $store->name : 'All stores', 'timezone' => ($store !== null ? $store->timezone : $merchant->timezone), 'basis' => in_array($report['type'], ['stock', 'batch_stock', 'valuation'], true) ? 'current balance; date window applies to activity/expiry only' : 'recorded business date / ledger posting time'],
            'freshness' => ['generated_at' => now()->toAtomString(), 'mode' => $this->ledgerStamp === null ? 'live-ledger' : 'verified-projection-with-live-ledger'], 'definitions' => ReportCatalog::definitions(),
            'columns' => $columns, 'summary' => $totals, 'rows' => $rows, 'pagination' => ['current_page' => $page, 'per_page' => $size, 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
            'limitations' => ['Historical dimensions not captured at posting appear as Not captured.', 'Pending costs are not treated as zero. Different units must not be added together.', 'External tenders and settlements are recorded declarations, not bank verification.']];
        if ($key === 'time-comparisons') {
            $days = (int) $start->diffInDays($end) + 1;
            $previousStart = ($filters['comparison'] ?? 'previous_period') === 'previous_year' ? $start->subYearNoOverflow() : $start->subDays($days);
            $previousEnd = ($filters['comparison'] ?? 'previous_period') === 'previous_year' ? $end->subYearNoOverflow() : $start->subDay();
            $previous = $this->rows($merchant, $store, $key, array_replace($filters, ['start' => $previousStart->toDateString(), 'end' => $previousEnd->toDateString()]), $access);
            $result['comparison'] = ['start' => $previousStart->toDateString(), 'end' => $previousEnd->toDateString(), 'summary' => $this->totals($previous, $columns)];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     */
    public function rows(Merchant $merchant, ?Store $store, string $key, array $f, array $access): Builder
    {
        $this->ledgerStamp = null;
        $report = ReportCatalog::get($key);
        if (in_array($report['type'], ['sales', 'transactions', 'customers', 'profit'], true)) {
            return $this->salesRows($merchant, $store, $key, $f, $access);
        }
        if (in_array($report['type'], ['allocations', 'extras'], true)) {
            return $this->allocationRows($merchant, $store, $key, $f, $access);
        }

        $query = $this->ledgerRows($merchant, $store, $key, $f, $access);
        if (! empty($f['search'])) {
            $query = DB::query()->fromSub($query, 'filtered')->select('*')->where(function ($where) use ($f, $report): void {
                $where->where('label', 'like', '%'.$f['search'].'%');
                if (in_array($report['type'], ['stock', 'batch_stock', 'valuation', 'history', 'movements', 'stocktakes', 'cost_entries'], true)) {
                    $where->orWhere('sku', 'like', '%'.$f['search'].'%');
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     * @param  list<literal-string>|null  $projection
     */
    private function facts(Merchant $merchant, ?Store $store, array $f, array $access, bool $returns, ?array $projection = null): Builder
    {
        return app(ReportProjection::class)->facts($merchant, $store, $f, $access, $returns, $projection)
            ?? $this->ledgerFacts($merchant, $store, $f, $access, $returns, $projection);
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     * @param  list<literal-string>|null  $projection
     */
    public function ledgerFacts(Merchant $merchant, ?Store $store, array $f, array $access, bool $returns, ?array $projection = null): Builder
    {
        $costKey = $returns ? 'sale_return_line_id' : 'sale_line_id';
        $costs = DB::table('inventory_cost_entries as e')->join('stock_movements as m', fn ($join) => $join->on('m.id', '=', 'e.stock_movement_id')->on('m.store_id', '=', 'e.store_id')->on('m.merchant_id', '=', 'e.merchant_id'))
            ->where('e.merchant_id', $merchant->id)->whereNotNull('m.'.$costKey)
            ->selectRaw("m.$costKey line_id, -SUM(e.value_delta) cogs, SUM(CASE WHEN e.value_delta IS NULL OR e.cost_state != 'final' THEN 1 ELSE 0 END) pending_costs")
            ->groupBy('m.'.$costKey);
        $costs->join(($returns ? 'sale_return_lines' : 'sale_lines').' as cost_line', 'cost_line.id', '=', 'm.'.$costKey)
            ->join(($returns ? 'sale_returns' : 'sales').' as cost_header', 'cost_header.id', '=', 'cost_line.'.($returns ? 'sale_return_id' : 'sale_id'))
            ->whereBetween('cost_header.business_date', [$f['start'], $f['end'].' 23:59:59']);
        $this->scope($costs, $merchant, $store, 'cost_header');
        if (isset($f['document_min'], $f['document_max'])) {
            $costs->whereBetween('cost_header.id', [$f['document_min'], $f['document_max']]);
        }
        $q = $returns
            ? DB::table('sale_return_lines as x')->join('sale_returns as d', 'd.id', '=', 'x.sale_return_id')->join('sale_lines as l', 'l.id', '=', 'x.sale_line_id')->join('sales as s', 's.id', '=', 'l.sale_id')
            : DB::table('sale_lines as l')->join('sales as s', 's.id', '=', 'l.sale_id');
        $header = $returns ? 'd' : 's';
        $line = $returns ? 'x' : 'l';
        $sign = $returns ? '-' : '';
        $q->leftJoinSub($costs, 'cost', fn ($j) => $j->on('cost.line_id', '=', $line.'.id'));
        $this->scope($q, $merchant, $store, $header);
        if (isset($f['document_min'], $f['document_max'])) {
            $q->whereBetween($header.'.id', [$f['document_min'], $f['document_max']]);
        }
        $this->scope($q, $merchant, $store, 'l');
        if ($returns) {
            $this->scope($q, $merchant, $store, 'x');
        }
        $q->whereBetween($header.'.business_date', [$f['start'], $f['end'].' 23:59:59']);
        if (! empty($f['closed_only'])) {
            $q->whereExists(fn ($sessions) => $sessions->selectRaw('1')->from('cash_sessions as closed_session')->whereColumn('closed_session.id', $header.'.cash_session_id')->where('closed_session.status', 'closed'));
        }
        if ($access['actor_id'] !== null) {
            $q->where($header.'.created_by_user_id', $access['actor_id']);
        }
        foreach (['register_id' => $header.'.register_id', 'employee_id' => $header.'.created_by_user_id', 'session_id' => $header.'.cash_session_id', 'variant_id' => 'l.product_variant_id'] as $filter => $column) {
            if (isset($f[$filter])) {
                $q->where($column, $f[$filter]);
            }
        }
        foreach (array_keys(ReportSnapshots::DIMENSIONS) as $column) {
            if (isset($f[$column])) {
                $q->where('l.'.$column, $f[$column]);
            }
        }
        if (! empty($f['search'])) {
            $q->where(fn ($where) => $where->where('l.product_name_snapshot', 'like', '%'.$f['search'].'%')->orWhere('l.sku_snapshot', 'like', '%'.$f['search'].'%')->orWhere($header.'.number', 'like', '%'.$f['search'].'%'));
        }
        $pending = $returns ? 'COALESCE(cost.pending_costs, 0)' : 'CASE WHEN cost.cogs IS NULL THEN 1 ELSE COALESCE(cost.pending_costs, 0) END';
        $expressions = [
            'document_internal_id' => "$header.id", 'document_id' => "HEX($header.public_id)", 'document_number' => "$header.number",
            'business_date' => "SUBSTR($header.business_date, 1, 10)", 'occurred_at' => $returns ? 'd.posted_at' : 's.finalized_at',
            'store_id' => "$header.store_id", 'register_id' => "$header.register_id", 'cash_session_id' => "$header.cash_session_id",
            'employee_id' => "$header.created_by_user_id", 'customer_id' => 's.customer_id', 'customer_group_id' => 's.customer_group_id', 'register_group_id' => $returns ? $this->json('d.reporting_snapshot', 'register_group_id', 'NULL') : 's.register_group_id',
            'original_line_id' => 'l.id', 'line_id' => $returns ? 'HEX(x.public_id)' : 'HEX(l.public_id)', 'product_variant_id' => 'l.product_variant_id',
            'product' => 'l.product_name_snapshot', 'sku' => 'l.sku_snapshot', 'unit' => 'l.unit_symbol_snapshot',
            'quantity' => "$sign$line.quantity_uom", 'revenue' => "$sign$line.net", 'tax' => "$sign$line.tax", 'sales_including_tax' => "$sign$line.gross", 'discounts' => "$sign$line.discount",
            'cogs' => 'COALESCE(cost.cogs, 0)', 'pending_costs' => $pending, 'is_return' => $returns ? '1' : '0',
            'store_name' => "(SELECT name FROM stores WHERE id = $header.store_id)", 'register_name' => "(SELECT name FROM registers WHERE id = $header.register_id)",
            'session_name' => "(SELECT number FROM cash_sessions WHERE id = $header.cash_session_id)", 'employee_name' => "COALESCE((SELECT name FROM users WHERE id = $header.created_by_user_id), 'Unknown staff')",
            'product_category_id' => 'l.product_category_id', 'reporting_category_id' => 'l.reporting_category_id', 'product_brand_id' => 'l.product_brand_id',
            'product_reporting_type_id' => 'l.product_reporting_type_id', 'primary_supplier_id' => 'l.primary_supplier_id', 'reporting_size' => 'l.reporting_size', 'is_miscellaneous' => 'l.is_miscellaneous',
            'tax_rate_snapshot' => 'l.tax_rate_snapshot', 'tax_classification_snapshot' => 'l.tax_classification_snapshot',
            'line_snapshot' => 'l.reporting_snapshot', 'header_snapshot' => $returns ? 'd.reporting_snapshot' : 's.reporting_snapshot', 'reporting_timezone' => $returns ? $this->json('d.reporting_snapshot', 'timezone', 'NULL') : 's.reporting_timezone',
            'reporting_offset_minutes' => $this->json($returns ? 'd.reporting_snapshot' : 's.reporting_snapshot', 'utc_offset_minutes', 'NULL'),
        ];
        foreach ($projection ?? array_keys($expressions) as $alias) {
            if (! array_key_exists($alias, $expressions)) {
                throw new \InvalidArgumentException('Invalid reporting fact field.');
            }
            $q->selectRaw($expressions[$alias].' '.$alias);
        }

        return $q;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     */
    private function salesRows(Merchant $merchant, ?Store $store, string $key, array $f, array $access): Builder
    {
        $dimension = ReportCatalog::get($key)['dimension'];
        if ($key === 'staff-z-report') {
            $f['closed_only'] = true;
        }
        $needed = ['document_internal_id', 'is_return', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs'];
        $needed = array_merge($needed, match ($dimension) {
            'product' => ['product_variant_id', 'product', 'sku', 'unit', 'quantity'],
            'line' => ['line_id', 'product', 'sku', 'unit', 'quantity', 'document_id', 'document_number', 'business_date'],
            'receipt' => ['document_id', 'document_number', 'business_date'],
            'employee' => ['employee_id', 'header_snapshot', 'employee_name'],
            'store' => ['store_id', 'header_snapshot', 'store_name'],
            'session' => ['cash_session_id', 'session_name'],
            'register_group' => ['register_group_id', 'header_snapshot'],
            'customer_group' => ['customer_group_id', 'header_snapshot'],
            'brand' => ['product_brand_id', 'line_snapshot'],
            'supplier' => ['primary_supplier_id', 'line_snapshot'],
            'category' => ['product_category_id', 'line_snapshot'],
            'reporting_category' => ['reporting_category_id', 'line_snapshot'],
            'product_type' => ['product_reporting_type_id', 'line_snapshot'],
            'wet_dry' => ['line_snapshot'], 'size' => ['reporting_size'],
            'tax' => ['tax_classification_snapshot', 'tax_rate_snapshot'],
            'hour' => ['occurred_at', 'reporting_offset_minutes'],
            default => ['business_date'],
        });
        if ($key === 'misc-products') {
            $needed[] = 'is_miscellaneous';
        }
        if ($key === 'repeat-customer-rate') {
            $needed = array_merge($needed, ['customer_id', 'business_date']);
        }
        if (! empty($f['detail'])) {
            $needed = array_merge($needed, ['line_id', 'product', 'sku', 'unit', 'quantity', 'document_id', 'document_number', 'business_date']);
        }
        $needed = array_values(array_unique($needed));
        $projectionReader = app(ReportProjection::class);
        $projectable = array_intersect($needed, ['line_id', 'original_line_id']) === [] && ! isset($f['_ledger_bounds']);
        $window = $projectable ? $projectionReader->readWindow($merchant) : null;
        $this->ledgerStamp = $window['stamp'] ?? null;
        $summaryRows = $this->summaryRows($merchant, $store, $key, $f, $access, $window);
        if ($summaryRows !== null) {
            return $summaryRows;
        }
        $salesFacts = $window !== null ? $projectionReader->facts($merchant, $store, $f, $access, false, $needed, $window) : $this->ledgerFacts($merchant, $store, array_replace($f, $f['_ledger_bounds']['sales'] ?? []), $access, false, $needed);
        $returnFacts = $window !== null ? $projectionReader->facts($merchant, $store, $f, $access, true, $needed, $window) : $this->ledgerFacts($merchant, $store, array_replace($f, $f['_ledger_bounds']['returns'] ?? []), $access, true, $needed);
        $facts = (clone $salesFacts)->unionAll(clone $returnFacts);
        $q = DB::query()->fromSub($facts, 'f');
        $expression = match ($dimension) {
            'product' => DB::getDriverName() === 'sqlite' ? "CAST(product_variant_id AS TEXT) || ':' || unit" : "CONCAT(product_variant_id, ':', unit)", 'line' => 'line_id', 'receipt' => 'document_id',
            'employee' => 'CAST(employee_id AS CHAR)', 'store' => 'CAST(store_id AS CHAR)', 'session' => 'CAST(cash_session_id AS CHAR)',
            'register_group' => "COALESCE(CAST(register_group_id AS CHAR), 'unknown')", 'customer_group' => "COALESCE(CAST(customer_group_id AS CHAR), 'unknown')",
            'brand' => "COALESCE(CAST(product_brand_id AS CHAR), 'unknown')", 'supplier' => "COALESCE(CAST(primary_supplier_id AS CHAR), 'unknown')",
            'category' => "COALESCE(CAST(product_category_id AS CHAR), 'unknown')", 'reporting_category' => "COALESCE(CAST(reporting_category_id AS CHAR), 'unknown')",
            'product_type' => "COALESCE(CAST(product_reporting_type_id AS CHAR), 'unknown')", 'wet_dry' => $this->json('line_snapshot', 'wet_dry'),
            'size' => "COALESCE(reporting_size, 'Not captured')", 'tax' => DB::getDriverName() === 'sqlite' ? "COALESCE(tax_classification_snapshot, 'Unclassified') || ' @ ' || COALESCE(CAST(tax_rate_snapshot AS TEXT), 'Not captured')" : "CONCAT(COALESCE(tax_classification_snapshot, 'Unclassified'), ' @ ', COALESCE(CAST(tax_rate_snapshot AS CHAR), 'Not captured'))",
            'month' => 'SUBSTR(business_date, 1, 7)', 'quarter' => DB::getDriverName() === 'sqlite' ? "SUBSTR(business_date, 1, 4) || '-Q' || CAST((CAST(SUBSTR(business_date,6,2) AS INTEGER)+2)/3 AS INTEGER)" : "CONCAT(YEAR(business_date), '-Q', QUARTER(business_date))",
            'year' => 'SUBSTR(business_date, 1, 4)', 'hour' => $this->hour(),
            'period' => $this->period($f['group_by'] ?? 'day'), default => 'business_date',
        };
        $label = match ($dimension) {
            'product', 'line' => 'CONCAT_PLACEHOLDER', 'receipt' => 'document_number', 'employee' => $this->json('header_snapshot', 'employee', 'employee_name'),
            'store' => $this->json('header_snapshot', 'store', 'store_name'), 'session' => 'session_name',
            'register_group' => $this->json('header_snapshot', 'register_group'), 'customer_group' => $this->json('header_snapshot', 'customer_group'),
            'brand' => $this->json('line_snapshot', 'product_brand_id'), 'supplier' => $this->json('line_snapshot', 'primary_supplier_id'),
            'category' => $this->json('line_snapshot', 'product_category_id'), 'reporting_category' => $this->json('line_snapshot', 'reporting_category_id'),
            'product_type' => $this->json('line_snapshot', 'product_reporting_type_id'), default => $expression,
        };
        if ($label === 'CONCAT_PLACEHOLDER') {
            $label = 'product';
        }
        if (in_array($key, ['profit-loss', 'average-size'], true)) {
            $expression = "'overall'";
            $label = "'Selected period'";
        }
        if ($key === 'misc-products') {
            $q->where('is_miscellaneous', true);
        }
        if ($key === 'discount-on-items') {
            $q->where('discounts', '!=', 0);
        }
        if (isset($f['group_key'])) {
            $q->whereRaw("$expression = ?", [$f['group_key']]);
        }
        if (! empty($f['detail'])) {
            $dimension = 'line';
            $expression = 'line_id';
            $label = 'product';
        }
        if ($key === 'repeat-customer-rate') {
            return $q->leftJoin('operational_customers as customer', fn ($join) => $join->on('customer.id', '=', 'f.customer_id')->where('customer.merchant_id', $merchant->id))->whereNotNull('f.customer_id')->where('is_return', 0)->selectRaw("CAST(f.customer_id AS CHAR) group_key, COALESCE(MIN(customer.name), 'Unavailable customer') label, MIN(customer.local_reference) customer_reference, COUNT(DISTINCT document_internal_id) transactions, SUM(revenue) revenue, MIN(business_date) first_purchase, MAX(business_date) last_purchase, CASE WHEN COUNT(DISTINCT document_internal_id) > 1 THEN 1 ELSE 0 END repeat_customer")
                ->groupBy('f.customer_id');
        }
        $selectedKey = $dimension === 'receipt' && $key !== 'average-size' ? 'MIN(document_id)' : $expression;
        $q->selectRaw("$selectedKey group_key, MIN($label) label, COUNT(DISTINCT CASE WHEN is_return = 0 THEN document_internal_id END) transactions,
            COUNT(DISTINCT CASE WHEN is_return = 1 THEN document_internal_id END) returns, COALESCE(SUM(revenue),0) revenue, COALESCE(SUM(tax),0) tax, COALESCE(SUM(sales_including_tax),0) sales_including_tax,
            COALESCE(SUM(discounts),0) discounts, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE COALESCE(SUM(cogs),0) END cogs,
            CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE COALESCE(SUM(revenue),0) - COALESCE(SUM(cogs),0) END gross_profit, COALESCE(SUM(pending_costs),0) pending_costs");
        if (! in_array($key, ['profit-loss', 'average-size'], true)) {
            if ($dimension === 'product') {
                $q->groupBy('product_variant_id');
            } elseif ($dimension === 'receipt') {
                $q->groupBy('document_internal_id');
            } elseif ($dimension === 'tax') {
                $q->groupBy('tax_classification_snapshot', 'tax_rate_snapshot');
            } else {
                $q->groupByRaw($expression);
            }
        }
        if (in_array($dimension, ['product', 'line'], true)) {
            $q->addSelect('unit')->selectRaw('MIN(sku) sku, SUM(quantity) quantity')->groupBy('unit');
        }
        if (in_array($dimension, ['line', 'receipt'], true)) {
            $q->selectRaw('MIN(document_id) document_id, MIN(document_number) document_number, MIN(business_date) business_date');
        }
        $salesAggregate = (clone $q)->setBindings([], 'from')->fromSub(
            $salesFacts, 'f',
        );
        $returnAggregate = (clone $q)->setBindings([], 'from')->fromSub(
            $returnFacts, 'f',
        );
        $combined = $salesAggregate->unionAll($returnAggregate);
        if (in_array($dimension, ['line', 'receipt'], true) && $key !== 'average-size') {
            return DB::query()->fromSub($combined, 'combined')->select('*')->selectRaw('CASE WHEN revenue <> 0 THEN gross_profit * 100.0 / revenue ELSE NULL END margin_percent, NULL turnover_percent');
        }
        $q = DB::query()->fromSub($combined, 'combined')->selectRaw('group_key, MIN(label) label,
            SUM(transactions) transactions, SUM(returns) returns, SUM(revenue) revenue, SUM(tax) tax, SUM(sales_including_tax) sales_including_tax,
            SUM(discounts) discounts, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(cogs) END cogs,
            CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(gross_profit) END gross_profit, SUM(pending_costs) pending_costs')->groupBy('group_key');
        if (in_array($dimension, ['product', 'line'], true)) {
            $q->addSelect('unit')->selectRaw('MIN(sku) sku, SUM(quantity) quantity')->groupBy('unit');
        }
        if (in_array($dimension, ['line', 'receipt'], true)) {
            $q->selectRaw('MIN(document_id) document_id, MIN(document_number) document_number, MIN(business_date) business_date');
        }
        if ($key === 'average-size') {
            $q = DB::query()->fromSub($q, 't')->select('*')->selectRaw('CASE WHEN transactions > 0 THEN sales_including_tax / transactions ELSE NULL END average_transaction');
        }
        if ($key === 'profit-loss') {
            $expenses = $this->scope(DB::table('expenses'), $merchant, $store)->whereBetween('expense_date', [$f['start'], $f['end'].' 23:59:59'])->sum('amount');
            $payroll = DB::table('payroll_items as p')->join('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')->join('payroll_input_snapshots as input', 'input.id', '=', 'p.payroll_input_snapshot_id')->where('p.merchant_id', $merchant->id)->where('p.state', 'posted')->whereBetween('r.period_end', [$f['start'], $f['end'].' 23:59:59']);
            $payrollStore = $this->json('input.payload', 'default_store_id', 'NULL');
            $unallocated = (string) (clone $payroll)->whereRaw($payrollStore.' IS NULL')->sum('p.gross_amount');
            if ($store !== null) {
                $payroll->whereRaw($payrollStore.' = ?', [$store->id]);
            }
            $pay = (string) $payroll->sum('p.gross_amount');
            $q = DB::query()->fromSub($q, 'p')->select('*')->selectRaw('? recorded_expenses, ? posted_payroll, ? unallocated_payroll, CASE WHEN ? = 1 THEN NULL ELSE gross_profit - ? - ? END operating_profit', [(string) $expenses, $pay, $unallocated, $store !== null && Decimal::of($unallocated)->isPositive() ? 1 : 0, (string) $expenses, $pay]);
        }
        if (! in_array($key, ['average-size', 'profit-loss'], true)) {
            $q = DB::query()->fromSub($q, 'metrics')->select('*')->selectRaw('CASE WHEN revenue <> 0 THEN gross_profit * 100.0 / revenue ELSE NULL END margin_percent, NULL turnover_percent');
        }

        return $q;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     * @param  array{sales: int, returns: int, sales_max: int, returns_max: int, stamp: string}|null  $window
     */
    private function summaryRows(Merchant $merchant, ?Store $store, string $key, array $f, array $access, ?array $window): ?Builder
    {
        if ($window === null || $access['actor_id'] !== null) {
            return null;
        }
        if (! in_array($key, ['sales-summary', 'sales-by-product', 'product-performance', 'top-bottom-sellers', 'daily-sales', 'time-period', 'time-comparisons', 'trends', 'tax-report', 'daily-tax', 'monthly-tax', 'quarterly-tax', 'end-of-year-tax', 'tax-filing'], true)) {
            return null;
        }
        foreach (['detail', 'group_key', 'closed_only', 'search', 'variant_id', 'register_id', 'employee_id', 'session_id', ...array_keys(ReportSnapshots::DIMENSIONS)] as $filter) {
            if (! empty($f[$filter])) {
                return null;
            }
        }
        $dimension = ReportCatalog::get($key)['dimension'];
        if (! in_array($dimension, ['product', 'tax', 'receipt', 'day', 'month', 'quarter', 'year', 'period'], true)) {
            return null;
        }
        $q = $this->scope(DB::table(in_array($dimension, ['product', 'tax'], true) ? 'report_daily_facts' : 'report_receipt_facts'), $merchant, $store)->whereBetween('business_date', [$f['start'], $f['end']]);
        if ($dimension === 'receipt') {
            $q->selectRaw('document_id group_key, document_number label, CASE WHEN is_return = 0 THEN 1 ELSE 0 END transactions, CASE WHEN is_return = 1 THEN 1 ELSE 0 END returns, revenue, tax, sales_including_tax, discounts, CASE WHEN pending_costs > 0 THEN NULL ELSE cogs END cogs, CASE WHEN pending_costs > 0 THEN NULL ELSE revenue - cogs END gross_profit, pending_costs, document_id, document_number, business_date');
        } elseif (! in_array($dimension, ['product', 'tax'], true)) {
            $expression = $this->period(match ($dimension) {
                'period' => $f['group_by'] ?? 'day', default => $dimension
            });
            $q->selectRaw($expression.' group_key, MIN('.$expression.') label, SUM(CASE WHEN is_return = 0 THEN 1 ELSE 0 END) transactions, SUM(CASE WHEN is_return = 1 THEN 1 ELSE 0 END) returns, SUM(revenue) revenue, SUM(tax) tax, SUM(sales_including_tax) sales_including_tax, SUM(discounts) discounts, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(cogs) END cogs, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(revenue) - SUM(cogs) END gross_profit, SUM(pending_costs) pending_costs')->groupByRaw($expression);
        } else {
            $expression = $dimension === 'product'
                ? (DB::getDriverName() === 'sqlite' ? "CAST(product_variant_id AS TEXT) || ':' || unit" : "CONCAT(product_variant_id, ':', unit)")
                : (DB::getDriverName() === 'sqlite' ? "COALESCE(tax_classification_snapshot, 'Unclassified') || ' @ ' || COALESCE(CAST(tax_rate_snapshot AS TEXT), 'Not captured')" : "CONCAT(COALESCE(tax_classification_snapshot, 'Unclassified'), ' @ ', COALESCE(CAST(tax_rate_snapshot AS CHAR), 'Not captured'))");
            $q->where('kind', $dimension)->selectRaw($expression.' group_key, '.($dimension === 'product' ? 'MIN(product)' : $expression).' label, SUM(CASE WHEN is_return = 0 THEN documents ELSE 0 END) transactions, SUM(CASE WHEN is_return = 1 THEN documents ELSE 0 END) returns, SUM(revenue) revenue, SUM(tax) tax, SUM(sales_including_tax) sales_including_tax, SUM(discounts) discounts, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(cogs) END cogs, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(revenue) - SUM(cogs) END gross_profit, SUM(pending_costs) pending_costs');
            if ($dimension === 'product') {
                $q->addSelect('unit')->selectRaw('MIN(sku) sku, SUM(quantity) quantity')->groupBy('product_variant_id', 'unit');
            } else {
                $q->groupBy('tax_classification_snapshot', 'tax_rate_snapshot');
            }
        }

        $rows = DB::query()->fromSub($q, 'metrics')->select('*')->selectRaw('CASE WHEN revenue <> 0 THEN gross_profit * 100.0 / revenue ELSE NULL END margin_percent, NULL turnover_percent');
        if ($window['sales'] === $window['sales_max'] && $window['returns'] === $window['returns_max']) {
            return $rows;
        }
        $stamp = $this->ledgerStamp;
        $tail = $this->salesRows($merchant, $store, $key, $f + ['_ledger_bounds' => [
            'sales' => ['document_min' => $window['sales'] + 1, 'document_max' => $window['sales_max']],
            'returns' => ['document_min' => $window['returns'] + 1, 'document_max' => $window['returns_max']],
        ]], $access);
        $this->ledgerStamp = $stamp;
        $combined = $rows->unionAll($tail);
        if ($dimension === 'receipt') {
            return DB::query()->fromSub($combined, 'live_receipts')->select('*');
        }
        $merged = DB::query()->fromSub($combined, 'live_metrics')->select('group_key')->selectRaw('MIN(label) label, SUM(transactions) transactions, SUM(returns) returns, SUM(revenue) revenue, SUM(tax) tax, SUM(sales_including_tax) sales_including_tax, SUM(discounts) discounts, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(cogs) END cogs, CASE WHEN SUM(pending_costs) > 0 THEN NULL ELSE SUM(revenue) - SUM(cogs) END gross_profit, SUM(pending_costs) pending_costs')->groupBy('group_key');
        if ($dimension === 'product') {
            $merged->addSelect('unit')->selectRaw('MIN(sku) sku, SUM(quantity) quantity')->groupBy('unit');
        }

        return DB::query()->fromSub($merged, 'metrics')->select('*')->selectRaw('CASE WHEN revenue <> 0 THEN gross_profit * 100.0 / revenue ELSE NULL END margin_percent, NULL turnover_percent');
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     */
    private function allocationRows(Merchant $merchant, ?Store $store, string $key, array $f, array $access): Builder
    {
        if ($key === 'multiple-choice-products') {
            $needed = ['original_line_id', 'quantity', 'revenue', 'tax', 'is_return', 'document_internal_id'];
            $facts = $this->facts($merchant, $store, $f, $access, false, $needed)->unionAll($this->facts($merchant, $store, $f, $access, true, $needed));
            $q = DB::query()->fromSub($facts, 'f')->join('sale_line_extras as e', 'e.sale_line_id', '=', 'f.original_line_id')->join('sale_lines as l', 'l.id', '=', 'f.original_line_id');

            return $q->selectRaw('CAST(e.extra_option_id AS CHAR) group_key, MIN(e.option_name_snapshot) label, MIN(e.group_name_snapshot) option_group,
                SUM(f.quantity) quantity, SUM(CASE WHEN l.unit_price > 0 THEN f.revenue * e.unit_price_delta / l.unit_price ELSE 0 END) revenue,
                SUM(CASE WHEN l.unit_price > 0 THEN f.tax * e.unit_price_delta / l.unit_price ELSE 0 END) tax,
                COUNT(DISTINCT CASE WHEN f.is_return = 0 THEN f.document_internal_id END) transactions')->groupBy('e.extra_option_id');
        }
        $q = $this->scope(DB::table('sale_discount_allocations as a'), $merchant, $store, 'a')->join('sale_lines as l', 'l.id', '=', 'a.sale_line_id')->join('sales as s', 's.id', '=', 'l.sale_id')
            ->whereBetween('a.business_date', [$f['start'], $f['end'].' 23:59:59']);
        if (isset($f['register_id'])) {
            $q->where('s.register_id', $f['register_id']);
        }
        if (isset($f['employee_id'])) {
            $q->where('s.created_by_user_id', $f['employee_id']);
        }
        $group = $key === 'promotions' ? 'COALESCE(a.rule_id, a.source)' : 'a.source';
        if ($key === 'promotions') {
            $q->whereNotNull('a.rule_id');
        }

        return $q->selectRaw("$group group_key, MIN(COALESCE(a.rule_name, a.source)) label, SUM(a.amount) discounts, COUNT(DISTINCT s.id) transactions, COUNT(*) allocations")->groupByRaw($group);
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, mixed>  $access
     */
    private function ledgerRows(Merchant $merchant, ?Store $store, string $key, array $f, array $access): Builder
    {
        $type = ReportCatalog::get($key)['type'];
        $start = CarbonImmutable::parse($f['start'], ($store !== null ? $store->timezone : $merchant->timezone))->startOfDay()->utc();
        $end = CarbonImmutable::parse($f['end'], ($store !== null ? $store->timezone : $merchant->timezone))->endOfDay()->utc();
        if ($type === 'tenders') {
            $sales = $this->scope(DB::table('sale_tenders as t')->join('sales as s', 's.id', '=', 't.sale_id'), $merchant, $store, 's')->whereBetween('s.business_date', [$f['start'], $f['end'].' 23:59:59']);
            $returns = $this->scope(DB::table('sale_return_tenders as t')->join('sale_returns as s', 's.id', '=', 't.sale_return_id'), $merchant, $store, 's')->whereBetween('s.business_date', [$f['start'], $f['end'].' 23:59:59']);
            foreach ([$sales, $returns] as $query) {
                if ($access['actor_id'] !== null) {
                    $query->where('s.created_by_user_id', $access['actor_id']);
                }
                foreach (['register_id', 'employee_id'] as $filter) {
                    if (isset($f[$filter])) {
                        $query->where('s.'.($filter === 'employee_id' ? 'created_by_user_id' : $filter), $f[$filter]);
                    }
                }
            }
            $sales->selectRaw('t.type group_key, t.type label, SUM(t.amount) amount, 0 pending_refunds')->groupBy('t.type');
            $returns->selectRaw("t.type group_key, t.type label, -SUM(CASE WHEN t.state = 'refunded' THEN t.amount ELSE 0 END) amount, SUM(CASE WHEN t.state != 'refunded' THEN t.amount ELSE 0 END) pending_refunds")->groupBy('t.type');
            $change = $this->scope(DB::table('sales as s'), $merchant, $store, 's')->whereBetween('business_date', [$f['start'], $f['end'].' 23:59:59']);
            if ($access['actor_id'] !== null) {
                $change->where('created_by_user_id', $access['actor_id']);
            }
            if (isset($f['register_id'])) {
                $change->where('register_id', $f['register_id']);
            }
            if (isset($f['employee_id'])) {
                $change->where('created_by_user_id', $f['employee_id']);
            }
            $change->selectRaw("'cash' group_key, 'cash' label, -COALESCE(SUM(cash_change),0) amount, 0 pending_refunds");

            return DB::query()->fromSub($sales->unionAll($returns)->unionAll($change), 't')->selectRaw('group_key, MIN(label) label, SUM(amount) amount, SUM(pending_refunds) pending_refunds')->groupBy('group_key');
        }
        if ($type === 'batch_stock') {
            return $this->scope(DB::table('batch_stock_balances as b'), $merchant, $store, 'b')->join('product_batches as batch', 'batch.id', '=', 'b.product_batch_id')->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')->join('inventory_locations as loc', 'loc.id', '=', 'b.inventory_location_id')->join('stores as st', 'st.id', '=', 'b.store_id')
                ->selectRaw('CAST(b.id AS CHAR) group_key, p.name label, v.sku, uom.symbol unit, st.name store, loc.name inventory_location, loc.disposition, batch.code batch, batch.expiry_date, batch.received_on, b.quantity_base quantity');
        }
        if ($type === 'stock') {
            $q = $this->scope(DB::table('stock_balances as b'), $merchant, $store, 'b')->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')->join('inventory_locations as loc', 'loc.id', '=', 'b.inventory_location_id')
                ->leftJoin('inventory_reorder_policies as policy', fn ($j) => $j->on('policy.store_id', '=', 'b.store_id')->on('policy.product_variant_id', '=', 'b.product_variant_id'));
            if (isset($f['variant_id'])) {
                $q->where('v.id', $f['variant_id']);
            }
            if (isset($f['inventory_location_id'])) {
                $q->where('loc.id', $f['inventory_location_id']);
            }
            if ($key === 'stock-warnings') {
                $q->where(fn ($w) => $w->where('b.quantity_base', '<', 0)->orWhereRaw('(SELECT SUM(reorder.quantity_base) FROM stock_balances reorder JOIN inventory_locations rl ON rl.id = reorder.inventory_location_id WHERE reorder.store_id = b.store_id AND reorder.product_variant_id = b.product_variant_id AND rl.disposition = ?) <= policy.minimum_quantity', ['sellable'])->orWhereExists(fn ($batch) => $batch->selectRaw('1')->from('batch_stock_balances as bs')->join('product_batches as pb', 'pb.id', '=', 'bs.product_batch_id')->whereColumn('bs.product_variant_id', 'b.product_variant_id')->whereColumn('bs.inventory_location_id', 'b.inventory_location_id')->where('bs.quantity_base', '>', 0)->where('pb.expiry_date', '<=', $f['end'])));
            }
            if ($key === 'non-selling-stock') {
                $q->where('b.quantity_base', '>', 0)->whereNotExists(fn ($s) => $s->selectRaw('1')->from('sale_lines as sl')->join('sales as s', 's.id', '=', 'sl.sale_id')->where('sl.merchant_id', $merchant->id)->whereColumn('sl.store_id', 'b.store_id')->whereColumn('sl.product_variant_id', 'b.product_variant_id')->whereColumn('s.store_id', 'b.store_id')->whereBetween('s.business_date', [$f['start'], $f['end'].' 23:59:59']));
            }

            $window = app(ReportProjection::class)->readWindow($merchant);
            $lastSale = '(SELECT MAX(s.business_date) FROM sale_lines sl JOIN sales s ON s.id = sl.sale_id WHERE sl.merchant_id = ? AND sl.store_id = b.store_id AND sl.product_variant_id = b.product_variant_id)';
            $lastBindings = [$merchant->id];
            if ($window !== null) {
                $lastSale = "(SELECT MAX(f.business_date) FROM report_daily_facts f WHERE f.merchant_id = ? AND f.kind = 'product' AND f.is_return = 0 AND f.store_id = b.store_id AND f.product_variant_id = b.product_variant_id)";
                if ($window['sales_max'] > $window['sales']) {
                    $tail = '(SELECT MAX(s.business_date) FROM sale_lines sl JOIN sales s ON s.id = sl.sale_id WHERE sl.merchant_id = ? AND sl.sale_id > ? AND sl.sale_id <= ? AND sl.store_id = b.store_id AND sl.product_variant_id = b.product_variant_id)';
                    $maximum = DB::getDriverName() === 'sqlite' ? 'MAX' : 'GREATEST';
                    $lastSale = "NULLIF($maximum(COALESCE($lastSale, '1000-01-01'), COALESCE($tail, '1000-01-01')), '1000-01-01')";
                    $lastBindings = [...$lastBindings, $merchant->id, $window['sales'], $window['sales_max']];
                }
            }

            return $q->selectRaw('CAST(b.id AS CHAR) group_key, p.name label, v.sku, uom.symbol unit, b.store_id, loc.name inventory_location, loc.disposition, b.quantity_base quantity, policy.minimum_quantity, policy.target_quantity,
                (SELECT MIN(pb.expiry_date) FROM batch_stock_balances bs JOIN product_batches pb ON pb.id = bs.product_batch_id WHERE bs.product_variant_id = b.product_variant_id AND bs.inventory_location_id = b.inventory_location_id AND bs.quantity_base > 0) earliest_expiry,
                '.$lastSale.' last_sale', $lastBindings);
        }
        if ($type === 'valuation') {
            return $this->scope(DB::table('inventory_cost_balances as b'), $merchant, $store, 'b')->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')
                ->selectRaw('CAST(b.id AS CHAR) group_key, p.name label, v.sku, uom.symbol unit, b.store_id, b.quantity_base quantity, b.average_unit_cost, b.inventory_value, b.cost_state');
        }
        if ($type === 'cost_entries') {
            return $this->scope(DB::table('inventory_cost_entries as e'), $merchant, $store, 'e')->join('product_variants as v', 'v.id', '=', 'e.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')
                ->whereBetween('e.applied_at', [$start, $end])->selectRaw('CAST(e.id AS CHAR) group_key, p.name label, v.sku, uom.symbol unit, e.entry_kind, e.cost_state, e.quantity_delta quantity, e.unit_cost, e.value_delta, e.cost_adjustment_value amount, e.resolved_negative_quantity, e.source_type, e.source_reference, e.effective_at, e.applied_at posted_at');
        }
        if ($type === 'supplier_credits') {
            return $this->scope(DB::table('supplier_credits as e'), $merchant, $store, 'e')->join('suppliers as s', 's.id', '=', 'e.supplier_id')->whereBetween('e.occurred_at', [$start, $end])->selectRaw('CAST(e.id AS CHAR) group_key, s.name label, e.status, e.entry_kind, e.amount, e.supplier_reference reference, e.occurred_at');
        }
        if ($type === 'supplier_allocations') {
            return $this->scope(DB::table('supplier_payment_allocations as e'), $merchant, $store, 'e')->join('suppliers as s', 's.id', '=', 'e.supplier_id')->join('supplier_payments as p', 'p.id', '=', 'e.supplier_payment_id')->whereBetween('e.created_at', [$start, $end])->selectRaw('CAST(e.id AS CHAR) group_key, s.name label, p.number payment, e.supplier_charge_entry_id charge_reference, e.entry_kind, e.amount, e.created_at posted_at');
        }
        if ($type === 'history') {
            $q = $this->scope(DB::table('stock_movements as m'), $merchant, $store, 'm')->join('product_variants as v', 'v.id', '=', 'm.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')->join('inventory_locations as loc', 'loc.id', '=', 'm.inventory_location_id');
            foreach (['variant_id' => 'm.product_variant_id', 'inventory_location_id' => 'm.inventory_location_id'] as $filter => $column) {
                if (isset($f[$filter])) {
                    $q->where($column, $f[$filter]);
                }
            }
            $identity = DB::getDriverName() === 'sqlite' ? "m.product_variant_id || ':' || m.store_id || ':' || m.inventory_location_id" : "CONCAT(m.product_variant_id, ':', m.store_id, ':', m.inventory_location_id)";

            return $q->where('m.created_at', '<=', $end)->selectRaw($identity.' group_key, MIN(p.name) label, MIN(v.sku) sku, MIN(uom.symbol) unit, m.store_id, loc.name inventory_location,
                    SUM(CASE WHEN m.created_at < ? THEN m.quantity_base ELSE 0 END) opening_quantity, SUM(CASE WHEN m.created_at >= ? THEN m.quantity_base ELSE 0 END) movement_quantity, SUM(m.quantity_base) closing_quantity', [$start, $start])
                ->groupBy('m.product_variant_id', 'm.store_id', 'm.inventory_location_id', 'loc.name');
        }
        if ($type === 'movements') {
            $q = $this->scope(DB::table('stock_movements as m'), $merchant, $store, 'm')->join('product_variants as v', 'v.id', '=', 'm.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')->join('inventory_locations as loc', 'loc.id', '=', 'm.inventory_location_id')
                ->whereBetween('m.created_at', [$start, $end]);
            if (isset($f['variant_id'])) {
                $q->where('m.product_variant_id', $f['variant_id']);
            }
            if (isset($f['inventory_location_id'])) {
                $q->where('loc.id', $f['inventory_location_id']);
            }

            return $q->selectRaw('CAST(m.id AS CHAR) group_key, p.name label, v.sku, uom.symbol unit, loc.name inventory_location, m.movement_kind, m.quantity_base quantity, m.unit_cost, m.cost_state, m.reason_text reason, m.source_type, m.source_reference, m.occurred_at effective_at, m.created_at posted_at');
        }
        if ($type === 'stocktakes') {
            return $this->scope(DB::table('stocktake_lines as l'), $merchant, $store, 'l')->join('stocktakes as d', 'd.id', '=', 'l.stocktake_id')->join('product_variants as v', 'v.id', '=', 'l.product_variant_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('unit_of_measures as uom', 'uom.id', '=', 'v.base_unit_id')
                ->whereBetween('d.opened_at', [$start, $end])->selectRaw('CAST(l.id AS CHAR) group_key, p.name label, d.number document_number, d.state, v.sku, uom.symbol unit, l.baseline_quantity_base baseline_quantity, l.expected_at_count_quantity_base expected_quantity, l.counted_quantity_base counted_quantity, l.discrepancy_quantity_base discrepancy_quantity, l.counted_at');
        }
        if ($type === 'sessions') {
            $q = $this->scope(DB::table('cash_sessions as c'), $merchant, $store, 'c')->join('registers as r', 'r.id', '=', 'c.register_id')->whereBetween('c.business_date', [$f['start'], $f['end'].' 23:59:59']);
            if (isset($f['register_id'])) {
                $q->where('r.id', $f['register_id']);
            }

            return $q->selectRaw("CAST(c.id AS CHAR) group_key, c.number label, r.name register_name, c.business_date, c.status, c.opening_amount, CASE WHEN c.status IN ('closed', 'variance_review') THEN c.expected_amount ELSE NULL END expected_amount,
                c.counted_amount, CASE WHEN c.status IN ('closed', 'variance_review') THEN c.variance_amount ELSE NULL END variance_amount, c.pending_offline_sale_count, c.pending_server_sale_count, c.opened_at, c.closed_at");
        }
        if ($type === 'closeouts') {
            $q = $this->scope(DB::table('cash_closeout_snapshots as c'), $merchant, $store, 'c')->join('cash_sessions as s', 's.id', '=', 'c.cash_session_id')->whereBetween('s.business_date', [$f['start'], $f['end'].' 23:59:59']);
            if (isset($f['register_id'])) {
                $q->where('s.register_id', $f['register_id']);
            }
            if ($key === 'z-report') {
                $q->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('cash_closeout_snapshots as newer')->whereColumn('newer.cash_session_id', 'c.cash_session_id')->whereColumn('newer.revision', '>', 'c.revision'));
            }
            $revenue = $this->json('c.payload', 'sales.revenue', '0').' - '.$this->json('c.payload', 'returns.revenue', '0');
            $tax = $this->json('c.payload', 'sales.tax', '0').' - '.$this->json('c.payload', 'returns.tax', '0');

            return $q->selectRaw('CAST(c.id AS CHAR) group_key, s.number label, c.revision, s.business_date, '.$revenue.' revenue, '.$tax.' tax, '.$this->json('c.payload', 'expected_cash', 'NULL').' expected_amount, '.$this->json('c.payload', 'counted_cash', 'NULL').' counted_amount, '.$this->json('c.payload', 'variance', 'NULL').' variance_amount, c.movement_high_watermark, c.content_hash, c.payload, c.occurred_at');
        }
        if ($type === 'cashflow') {
            $cash = $this->scope(DB::table('cash_movements as c'), $merchant, $store, 'c')->whereBetween('c.occurred_at', [$start, $end])->selectRaw("HEX(c.public_id) group_key, c.kind label, 'drawer ledger' basis, c.signed_amount cash_amount, 0 external_recorded_amount, 0 supplier_recorded_amount, 0 settled_amount, c.reason, c.occurred_at");
            $external = $this->scope(DB::table('expenses as e'), $merchant, $store, 'e')->where('e.payment_source', 'external_recorded')->whereBetween('e.expense_date', [$f['start'], $f['end'].' 23:59:59'])->selectRaw("HEX(e.public_id) group_key, e.payee label, 'external expense declaration' basis, 0 cash_amount, -e.amount external_recorded_amount, 0 supplier_recorded_amount, 0 settled_amount, e.reason, e.expense_date occurred_at");
            $suppliers = $this->scope(DB::table('supplier_payments as p'), $merchant, $store, 'p')->whereBetween('p.paid_at', [$start, $end])->selectRaw("HEX(p.public_id) group_key, p.number label, 'supplier payment declaration' basis, 0 cash_amount, 0 external_recorded_amount, -p.amount supplier_recorded_amount, 0 settled_amount, p.reference reason, p.paid_at occurred_at");
            $settlements = $this->scope(DB::table('settlement_batches as b'), $merchant, $store, 'b')->join('settlement_lines as l', 'l.settlement_batch_id', '=', 'b.id')->whereBetween('b.period_end', [$f['start'], $f['end'].' 23:59:59'])->selectRaw("HEX(l.public_id) group_key, b.provider label, 'settlement evidence; not additional sales' basis, 0 cash_amount, 0 external_recorded_amount, 0 supplier_recorded_amount, l.settled_amount, l.exception_note reason, b.period_end occurred_at");

            return DB::query()->fromSub($cash->unionAll($external)->unionAll($suppliers)->unionAll($settlements), 'flow')->select('*');
        }
        if ($type === 'cash') {
            $q = $this->scope(DB::table('cash_movements as c'), $merchant, $store, 'c')->join('cash_sessions as s', 's.id', '=', 'c.cash_session_id')->whereBetween('c.occurred_at', [$start, $end]);
            if ($key === 'float-adjustments') {
                $q->whereIn('c.kind', ['opening_float', 'paid_in', 'paid_out', 'correction']);
            }
            if ($key === 'petty-cash') {
                $q->whereIn('c.kind', ['paid_in', 'paid_out', 'expense']);
            }
            if ($key === 'no-sales') {
                $q->where('c.kind', 'no_sale_open');
            }

            return $q->selectRaw('CAST(c.id AS CHAR) group_key, c.kind label, s.number cash_session, c.signed_amount amount, c.reason, c.source_type, c.occurred_at');
        }
        if ($type === 'voids') {
            return $this->scope(DB::table('cart_audit_events'), $merchant, $store)->whereBetween('occurred_at', [$start, $end])->selectRaw('CAST(id AS CHAR) group_key, subject label, event, quantity_delta quantity, reason, actor_user_id, occurred_at');
        }
        if ($type === 'returns') {
            return $this->scope(DB::table('sale_returns as r'), $merchant, $store, 'r')->join('sales as s', 's.id', '=', 'r.sale_id')->whereBetween('r.business_date', [$f['start'], $f['end'].' 23:59:59'])
                ->selectRaw('CAST(r.id AS CHAR) group_key, r.number label, HEX(r.public_id) document_id, s.number original_sale, r.state, r.reason, r.late_exception, r.business_date, r.net_total revenue, r.tax_total tax, r.total amount');
        }
        if ($type === 'supplier') {
            $charges = $this->scope(DB::table('supplier_charge_entries as e'), $merchant, $store, 'e')->whereBetween('occurred_at', [$start, $end])->selectRaw("e.id source_id, e.supplier_id, 'charge' label, e.amount amount, e.occurred_at");
            $credits = $this->scope(DB::table('supplier_credits as e'), $merchant, $store, 'e')->whereBetween('occurred_at', [$start, $end])->where('status', 'confirmed')->selectRaw("e.id source_id, e.supplier_id, 'confirmed_credit' label, -e.amount amount, e.occurred_at");
            $payments = $this->scope(DB::table('supplier_payments as e'), $merchant, $store, 'e')->whereBetween('paid_at', [$start, $end])->selectRaw("e.id source_id, e.supplier_id, 'payment' label, -e.amount amount, e.paid_at occurred_at");

            return DB::query()->fromSub($charges->unionAll($credits)->unionAll($payments), 'e')->join('suppliers as s', 's.id', '=', 'e.supplier_id')->selectRaw('e.*, s.name supplier, '.(DB::getDriverName() === 'sqlite' ? "e.label || ':' || e.source_id" : "CONCAT(e.label, ':', e.source_id)").' group_key');
        }
        if ($type === 'settlements') {
            return $this->scope(DB::table('settlement_batches as b'), $merchant, $store, 'b')->join('settlement_lines as l', 'l.settlement_batch_id', '=', 'b.id')->whereBetween('b.period_end', [$f['start'], $f['end'].' 23:59:59'])
                ->selectRaw('CAST(l.id AS CHAR) group_key, b.provider label, b.statement_reference, b.provenance, l.expected_amount, l.settled_amount, l.fee_amount, l.difference_amount, l.state, l.exception_note');
        }
        if ($type === 'payroll') {
            $q = DB::table('payroll_items as p')->join('payroll_runs as r', 'r.id', '=', 'p.payroll_run_id')->join('staff_profiles as sp', 'sp.id', '=', 'p.staff_profile_id')->join('payroll_input_snapshots as input', 'input.id', '=', 'p.payroll_input_snapshot_id')->where('p.merchant_id', $merchant->id)->whereBetween('r.period_end', [$f['start'], $f['end'].' 23:59:59']);
            if ($store !== null) {
                $q->whereRaw($this->json('input.payload', 'default_store_id', 'NULL').' = ?', [$store->id]);
            }

            return $q->selectRaw('CAST(p.id AS CHAR) group_key, '.$this->json('input.payload', 'staff_name', 'sp.display_name').' label, sp.employee_number, CASE WHEN '.$this->json('input.payload', 'default_store_id', 'NULL')." IS NULL THEN 'Not captured' ELSE 'Captured default store' END store_attribution, r.period_start, r.period_end, p.state, p.gross_amount, p.deduction_amount, p.statutory_amount, p.advance_amount, p.net_amount, p.paid_amount, p.net_amount - p.paid_amount outstanding_amount");
        }
        if ($type === 'loyalty') {
            return DB::table('loyalty_point_entries')->where('merchant_id', $merchant->id)->when($store !== null, fn ($q) => $q->where('store_id', $store->id))->whereBetween('occurred_at', [$start, $end])->selectRaw('CAST(id AS CHAR) group_key, kind label, loyalty_member_id, store_id origin_store, points, reason, expires_at, occurred_at');
        }
        if (in_array($type, ['attendance_events', 'attendance_shifts', 'rota_shifts', 'leave_requests'], true)) {
            $q = DB::table($type.' as w')->join('staff_profiles as sp', 'sp.id', '=', 'w.staff_profile_id')->where('w.merchant_id', $merchant->id);
            if ($store !== null) {
                $q->where(in_array($type, ['leave_requests'], true) ? 'sp.default_store_id' : 'w.store_id', $store->id);
            }
            if ($type === 'leave_requests') {
                return $q->where('w.starts_on', '<=', $f['end'])->where('w.ends_on', '>=', $f['start'])->selectRaw('CAST(w.id AS CHAR) group_key, sp.display_name label, w.state, w.starts_on, w.ends_on, w.days, w.decision_reason');
            }
            $date = match ($type) {
                'attendance_events' => 'occurred_at', 'attendance_shifts' => 'clocked_in_at', default => 'starts_at'
            };
            $q->whereBetween('w.'.$date, [$start, $end]);
            $fields = match ($type) {
                'attendance_events' => 'w.event_type, w.reason, w.occurred_at', 'attendance_shifts' => 'w.state, w.clocked_in_at, w.clocked_out_at, w.break_seconds', default => 'w.state, w.starts_at, w.ends_at, w.notes'
            };

            $q->selectRaw('CAST(w.id AS CHAR) group_key, sp.display_name label, '.$fields);
            if ($type === 'attendance_shifts') {
                $seconds = DB::getDriverName() === 'sqlite' ? "(strftime('%s', w.clocked_out_at) - strftime('%s', w.clocked_in_at))" : 'TIMESTAMPDIFF(SECOND, w.clocked_in_at, w.clocked_out_at)';
                $q->selectRaw("CASE WHEN w.clocked_out_at IS NULL THEN NULL WHEN $seconds < w.break_seconds THEN 0 ELSE ($seconds - w.break_seconds) / 3600.0 END worked_hours");
            }

            return $q;
        }
        $config = match ($type) {
            'purchase_orders' => ['purchase_orders', 'order_date', 'number, state, order_date, expected_date'],
            'purchase_receipts' => ['purchase_receipts', 'received_at', "number, status, CASE WHEN status = 'reversed' THEN 0 ELSE supplier_charge_total END amount, CASE WHEN status = 'reversed' THEN 0 ELSE recoverable_tax_total END tax, received_at"],
            'purchase_returns' => ['purchase_returns', 'returned_at', "number, status, credit_status, CASE WHEN status = 'reversed' THEN 0 ELSE supplier_credit_total END amount, returned_at"],
            'supplier_payments' => ['supplier_payments', 'paid_at', 'number, payment_method, amount, reference, paid_at'],
            'expenses' => ['expenses', 'expense_date', 'payee, state, amount, payment_source, reason, expense_date'],
            default => abort(404, 'Report implementation not found.'),
        };
        [$table, $date, $fields] = $config;
        $q = $this->scope(DB::table($table), $merchant, $store)->whereBetween($date, in_array($date, ['order_date', 'expense_date'], true) ? [$f['start'], $f['end'].' 23:59:59'] : [$start, $end]);

        return $q->selectRaw('CAST(id AS CHAR) group_key, '.($type === 'expenses' ? 'payee' : 'number').' label, '.$fields);
    }

    /** @param literal-string $column
     * @param  literal-string  $key
     * @param  literal-string|null  $fallback
     * @return literal-string
     */
    private function json(string $column, string $key, ?string $fallback = null): string
    {
        $extract = "json_extract($column, '$.$key')";
        if (DB::getDriverName() !== 'sqlite') {
            $extract = "CASE WHEN JSON_TYPE($extract) = 'NULL' THEN NULL ELSE json_unquote($extract) END";
        }

        return "COALESCE($extract, ".($fallback ?? "'Not captured'").')';
    }

    /** @return literal-string */
    private function hour(): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "CASE WHEN reporting_offset_minutes IS NULL THEN 'Not captured' ELSE strftime('%H:00', datetime(occurred_at, CAST(reporting_offset_minutes AS TEXT) || ' minutes')) END";
        }

        return "CASE WHEN reporting_offset_minutes IS NULL THEN 'Not captured' ELSE DATE_FORMAT(TIMESTAMPADD(MINUTE, reporting_offset_minutes, occurred_at), '%H:00') END";
    }

    /** @return literal-string */
    private function period(string $period): string
    {
        return match ($period) {
            'month' => 'SUBSTR(business_date, 1, 7)', 'year' => 'SUBSTR(business_date, 1, 4)',
            'quarter' => DB::getDriverName() === 'sqlite' ? "SUBSTR(business_date, 1, 4) || '-Q' || CAST((CAST(SUBSTR(business_date,6,2) AS INTEGER)+2)/3 AS INTEGER)" : "CONCAT(YEAR(business_date), '-Q', QUARTER(business_date))",
            'week' => DB::getDriverName() === 'sqlite' ? "strftime('%Y-W%W', business_date)" : "DATE_FORMAT(business_date, '%x-W%v')",
            default => 'business_date',
        };
    }

    private function scope(Builder $q, Merchant $merchant, ?Store $store, string $prefix = ''): Builder
    {
        $prefix = $prefix === '' ? '' : $prefix.'.';

        return $q->where($prefix.'merchant_id', $merchant->id)->when($store !== null, fn (Builder $q) => $q->where($prefix.'store_id', $store->id));
    }

    /**
     * @return list<array{key: string, label: string, type: string}>
     */
    public function columns(Builder $query): array
    {
        $connection = $query->getConnection();
        $statement = DB::getPdo()->prepare((clone $query)->limit(0)->toSql());
        $statement->execute($connection->prepareBindings((clone $query)->limit(0)->getBindings()));
        $keys = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $metadata = $statement->getColumnMeta($i);
            if ($metadata === false) {
                throw new \RuntimeException('Report column metadata is unavailable.');
            }
            $keys[] = $metadata['name'];
        }

        return array_map(function (string $key): array {
            $type = match (true) {
                in_array($key, self::MONEY_COLUMNS, true) => 'money',
                in_array($key, ['transactions', 'returns', 'pending_costs', 'repeat_customer', 'allocations', 'revision', 'movement_high_watermark', 'pending_offline_sale_count', 'pending_server_sale_count'], true) => 'integer',
                str_ends_with($key, '_percent') => 'percentage',
                preg_match('/quantity|hours|points/', $key) === 1 => 'decimal',
                default => 'text',
            };

            return ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)), 'type' => $type];
        }, $keys);
    }

    /**
     * @param  list<array<string, string>>  $columns
     * @return array<string, mixed>
     */
    private function totals(Builder $query, array $columns): array
    {
        $keys = array_column($columns, 'key');
        $expressions = ['COUNT(*) AS `rows`'];
        $money = [];
        foreach (self::MONEY_COLUMNS as $key) {
            if (in_array($key, $keys, true) && ! in_array($key, ['unit_cost', 'average_unit_cost', 'average_transaction'], true)) {
                $money[] = $key;
                $expressions[] = 'SUM('.$key.') '.$key;
                if (in_array($key, ['expected_amount', 'counted_amount', 'variance_amount'], true)) {
                    $expressions[] = 'SUM(CASE WHEN '.$key.' IS NULL THEN 1 ELSE 0 END) hidden_'.$key;
                }
            }
        }
        foreach (['transactions', 'returns', 'pending_costs', 'repeat_customer'] as $key) {
            if (in_array($key, $keys, true)) {
                $expressions[] = 'SUM('.$key.') '.$key;
            }
        }
        $summary = (array) DB::query()->fromSub(clone $query, 'totals')->selectRaw(implode(', ', $expressions))->first();
        foreach ($money as $key) {
            $summary[$key] = ($summary['hidden_'.$key] ?? 0) > 0 ? null : Decimal::fixed(Decimal::of((string) ($summary[$key] ?? '0')), 2);
            unset($summary['hidden_'.$key]);
        }
        foreach (['rows', 'transactions', 'returns', 'pending_costs', 'repeat_customer'] as $key) {
            if (array_key_exists($key, $summary)) {
                $summary[$key] = (int) ($summary[$key] ?? 0);
            }
        }
        if (($summary['pending_costs'] ?? 0) > 0) {
            foreach (['cogs', 'gross_profit', 'operating_profit'] as $key) {
                if (array_key_exists($key, $summary)) {
                    $summary[$key] = null;
                }
            }
        }
        if (isset($summary['revenue'], $summary['gross_profit']) && ! Decimal::of($summary['revenue'])->isZero()) {
            $summary['margin_percent'] = (string) Decimal::of($summary['gross_profit'])->multipliedBy(100)->dividedBy($summary['revenue'], 2, RoundingMode::HalfUp);
        }
        if (in_array('average_transaction', $keys, true)) {
            $summary['average_transaction'] = ($summary['transactions'] ?? 0) > 0 ? (string) Decimal::of($summary['sales_including_tax'])->dividedBy((string) $summary['transactions'], 2, RoundingMode::HalfUp) : null;
        }
        if (in_array('repeat_customer', $keys, true)) {
            $summary['repeat_customer_rate'] = $summary['rows'] > 0 ? (string) Decimal::of((string) $summary['repeat_customer'])->multipliedBy(100)->dividedBy((string) $summary['rows'], 2, RoundingMode::HalfUp) : null;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, string>>  $columns
     * @return array<string, mixed>
     */
    public function present(array $row, array $columns, ?string $totalRevenue = null): array
    {
        if (array_key_exists('turnover_percent', $row)) {
            $row['turnover_percent'] = $totalRevenue !== null && ! Decimal::of($totalRevenue)->isZero()
                ? (string) Decimal::of((string) $row['revenue'])->multipliedBy(100)->dividedBy($totalRevenue, 2, RoundingMode::HalfUp) : null;
        }
        $result = [];
        foreach ($columns as $column) {
            $key = $column['key'];
            $value = $row[$key] ?? null;
            if (is_string($value) && in_array($key, ['business_date', 'period_start', 'period_end', 'starts_on', 'ends_on', 'order_date', 'expected_date', 'received_on', 'expiry_date', 'earliest_expiry', 'last_sale'], true)) {
                $value = substr($value, 0, 10);
            }
            if ($value !== null && in_array($column['type'], ['money', 'percentage'], true)) {
                $value = Decimal::fixed(Decimal::of((string) $value), 2);
            }
            if (is_string($value) && strlen($value) === 16 && str_ends_with($key, '_id')) {
                $value = Uuid::fromBytes($value)->toString();
            }
            if (is_string($value) && strlen($value) === 32 && ctype_xdigit($value) && in_array($key, ['document_id', 'line_id', 'group_key'], true)) {
                $value = Uuid::fromString($value)->toString();
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @param  array<string, mixed>  $summary
     */
    public function order(Builder $query, string $column, string $direction, array $summary): Builder
    {
        if ($column === 'turnover_percent') {
            $column = 'revenue';
            if (isset($summary['revenue']) && Decimal::of($summary['revenue'])->isNegative()) {
                $direction = $direction === 'asc' ? 'desc' : 'asc';
            }
        }

        return $query->orderBy($column, $direction)->orderBy('group_key');
    }
}
