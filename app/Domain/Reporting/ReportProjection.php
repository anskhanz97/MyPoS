<?php

namespace App\Domain\Reporting;

use App\Jobs\RefreshReportProjection;
use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReportProjection
{
    public const VERSION = 3;

    public const FIELDS = ['document_internal_id', 'document_id', 'document_number', 'business_date', 'occurred_at', 'store_id', 'register_id', 'cash_session_id', 'employee_id', 'customer_id', 'customer_group_id', 'register_group_id', 'product_variant_id', 'product', 'sku', 'unit', 'quantity', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs', 'is_return', 'product_category_id', 'reporting_category_id', 'product_brand_id', 'product_reporting_type_id', 'primary_supplier_id', 'reporting_size', 'is_miscellaneous', 'tax_rate_snapshot', 'tax_classification_snapshot', 'line_snapshot', 'header_snapshot', 'reporting_timezone', 'reporting_offset_minutes'];

    /** @return array{definition_version: int, ledgers: array<string, array{count: int, last_id: int}>} */
    public function watermark(Merchant $merchant): array
    {
        $query = DB::table('sales')->where('merchant_id', $merchant->id)->selectRaw('? ledger, COUNT(*) records, COALESCE(MAX(id), 0) last_id', ['sales']);
        foreach (['sale_lines', 'sale_returns', 'sale_return_lines', 'inventory_cost_entries', 'stock_movements'] as $table) {
            $query->unionAll(DB::table($table)->where('merchant_id', $merchant->id)->selectRaw('? ledger, COUNT(*) records, COALESCE(MAX(id), 0) last_id', [$table]));
        }
        $result = [];
        foreach ($query->get() as $row) {
            $result[$row->ledger] = ['count' => (int) $row->records, 'last_id' => (int) $row->last_id];
        }

        return ['definition_version' => self::VERSION, 'ledgers' => $result];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $access
     * @param  list<literal-string>|null  $fields
     * @param  array{sales: int, returns: int, sales_max: int, returns_max: int, stamp: string}|null  $window
     */
    public function facts(Merchant $merchant, ?Store $store, array $filters, array $access, bool $returns, ?array $fields, ?array $window = null): ?Builder
    {
        if ($fields === null || array_intersect($fields, ['line_id', 'original_line_id']) !== []) {
            return null;
        }
        $window ??= $this->readWindow($merchant);
        if ($window === null) {
            return null;
        }
        $q = DB::table('report_sales_facts as f')->where('f.merchant_id', $merchant->id)->where('f.is_return', $returns)->whereBetween('f.business_date', [$filters['start'], $filters['end']]);
        if ($store !== null) {
            $q->where('f.store_id', $store->id);
        }
        if ($access['actor_id'] !== null) {
            $q->where('f.employee_id', $access['actor_id']);
        }
        foreach (['register_id' => 'register_id', 'employee_id' => 'employee_id', 'session_id' => 'cash_session_id', 'variant_id' => 'product_variant_id'] + array_combine(array_keys(ReportSnapshots::DIMENSIONS), array_keys(ReportSnapshots::DIMENSIONS)) as $filter => $column) {
            if (isset($filters[$filter])) {
                $q->where('f.'.$column, $filters[$filter]);
            }
        }
        if (! empty($filters['closed_only'])) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('cash_sessions as cs')->whereColumn('cs.id', 'f.cash_session_id')->where('cs.status', 'closed'));
        }
        if (! empty($filters['search'])) {
            $q->where(fn ($s) => $s->where('f.product', 'like', '%'.$filters['search'].'%')->orWhere('f.sku', 'like', '%'.$filters['search'].'%')->orWhere('f.document_number', 'like', '%'.$filters['search'].'%'));
        }
        foreach ($fields as $field) {
            $expression = match ($field) {
                'store_name' => '(SELECT name FROM stores WHERE id = f.store_id)',
                'employee_name' => "COALESCE((SELECT name FROM users WHERE id = f.employee_id), 'Unknown staff')",
                'session_name' => '(SELECT number FROM cash_sessions WHERE id = f.cash_session_id)',
                default => 'f.'.$field,
            };
            $q->selectRaw($expression.' '.$field);
        }

        $after = $window[$returns ? 'returns' : 'sales'];
        $last = $window[$returns ? 'returns_max' : 'sales_max'];
        if ($last > $after) {
            $tail = app(ReportQuery::class)->ledgerFacts($merchant, $store, $filters + ['document_min' => $after + 1, 'document_max' => $last], $access, $returns, $fields);
            $q->unionAll($tail);
        }

        return $q;
    }

    /** @return array{sales: int, returns: int, sales_max: int, returns_max: int, stamp: string}|null */
    public function readWindow(Merchant $merchant, bool $queueRefresh = true): ?array
    {
        $state = DB::table('report_projection_states')->where('merchant_id', $merchant->id)->first();
        $previous = $state === null ? null : json_decode($state->watermark, true, flags: JSON_THROW_ON_ERROR);
        $generation = $previous['generation'] ?? 'legacy';
        unset($previous['generation']);
        $current = $this->watermark($merchant);
        if ($queueRefresh && $previous != $current && config('queue.default') !== 'sync' && ! app()->runningUnitTests()) {
            RefreshReportProjection::dispatch($merchant->id)->afterCommit();
        }
        if ($previous === null || ($previous['definition_version'] ?? null) !== self::VERSION || ! isset($previous['ledgers'])) {
            return null;
        }
        $old = $previous['ledgers'];
        $new = $current['ledgers'];
        foreach ($new as $table => $mark) {
            if ($mark['count'] < $old[$table]['count'] || $mark['last_id'] < $old[$table]['last_id']) {
                return null;
            }
        }
        if ($previous != $current) {
            foreach (['sales' => ['sale_lines', 'sale_id'], 'sale_returns' => ['sale_return_lines', 'sale_return_id']] as $table => [$lines, $foreignKey]) {
                $tail = DB::table($table)->where('merchant_id', $merchant->id)->where('id', '>', $old[$table]['last_id'])->count();
                if ($tail !== $new[$table]['count'] - $old[$table]['count']) {
                    return null;
                }
                $tailLines = DB::table($lines)->where('merchant_id', $merchant->id)->where($foreignKey, '>', $old[$table]['last_id'])->count();
                if ($tailLines !== $new[$lines]['count'] - $old[$lines]['count']) {
                    return null;
                }
            }
            $costTail = DB::table('inventory_cost_entries as e')->where('e.merchant_id', $merchant->id)->where('e.id', '>', $old['inventory_cost_entries']['last_id']);
            if ((clone $costTail)->count() !== $new['inventory_cost_entries']['count'] - $old['inventory_cost_entries']['count']) {
                return null;
            }
            $changedHistory = $costTail->join('stock_movements as m', 'm.id', '=', 'e.stock_movement_id')->leftJoin('sale_lines as sl', 'sl.id', '=', 'm.sale_line_id')->leftJoin('sale_return_lines as rl', 'rl.id', '=', 'm.sale_return_line_id')
                ->where(fn ($q) => $q->where('sl.sale_id', '<=', $old['sales']['last_id'])->orWhere('rl.sale_return_id', '<=', $old['sale_returns']['last_id']))->exists();
            if ($changedHistory) {
                return null;
            }
        }

        return ['sales' => $old['sales']['last_id'], 'returns' => $old['sale_returns']['last_id'], 'sales_max' => $new['sales']['last_id'], 'returns_max' => $new['sale_returns']['last_id'], 'stamp' => hash('sha256', $generation.json_encode($current, JSON_THROW_ON_ERROR))];
    }

    public function rebuild(Merchant $merchant): void
    {
        $this->refresh($merchant, true);
    }

    public function refresh(Merchant $merchant, bool $force = false): void
    {
        Cache::lock('report-projection:'.$merchant->id, 900)->block(5, fn () => app(ReportQuery::class)->withDatabaseMemory(function () use ($merchant, $force): void {
            if (DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
            $fullRebuild = DB::transaction(function () use ($merchant, $force): bool {
                $watermark = $this->watermark($merchant);
                $window = $force ? null : $this->readWindow($merchant, false);
                if ($window === null) {
                    DB::table('report_sales_facts')->where('merchant_id', $merchant->id)->delete();
                }
                $filters = ['start' => '1000-01-01', 'end' => '9999-12-31'];
                $access = ['actor_id' => null];
                $query = app(ReportQuery::class);
                $sums = ['quantity', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs'];
                foreach ([false, true] as $returns) {
                    $table = $returns ? 'sale_returns' : 'sales';
                    DB::table($table)->where('merchant_id', $merchant->id)->where('id', '>', $window[$returns ? 'returns' : 'sales'] ?? 0)->where('id', '<=', $watermark['ledgers'][$table]['last_id'])->select('id')->chunkById(5000, function ($headers) use ($query, $merchant, $filters, $access, $returns, $sums): void {
                        $range = $filters + ['document_min' => $headers->first()->id, 'document_max' => $headers->last()->id];
                        $source = $query->ledgerFacts($merchant, null, $range, $access, $returns, self::FIELDS);
                        $rows = DB::query()->fromSub($source, 'source')->selectRaw('? merchant_id', [$merchant->id]);
                        foreach (self::FIELDS as $field) {
                            if (in_array($field, $sums, true)) {
                                $rows->selectRaw('SUM('.$field.') '.$field);
                            } elseif (in_array($field, ['line_snapshot', 'header_snapshot'], true)) {
                                $rows->selectRaw('MIN(CAST('.$field.' AS CHAR)) '.$field);
                            } else {
                                $rows->addSelect($field)->groupBy($field);
                            }
                        }
                        DB::table('report_sales_facts')->insertUsing(['merchant_id', ...self::FIELDS], $rows);
                    });
                }
                $scopes = $window === null ? null : DB::table('report_sales_facts')->where('merchant_id', $merchant->id)->where(fn ($q) => $q->where(fn ($q) => $q->where('is_return', false)->where('document_internal_id', '>', $window['sales']))->orWhere(fn ($q) => $q->where('is_return', true)->where('document_internal_id', '>', $window['returns'])))
                    ->distinct()->get(['store_id', 'business_date'])->map(fn ($row): array => ['store_id' => (int) $row->store_id, 'business_date' => (string) $row->business_date])->values()->all();
                $this->rebuildSummaries($merchant, $scopes === null || count($scopes) > 32 ? null : array_values($scopes));
                DB::table('report_projection_states')->updateOrInsert(['merchant_id' => $merchant->id], ['watermark' => json_encode($watermark + ['generation' => (string) Str::uuid()], JSON_THROW_ON_ERROR), 'built_at' => now()]);

                return $window === null;
            });
            if ($fullRebuild && DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
                DB::statement('ANALYZE TABLE report_sales_facts, report_receipt_facts, report_daily_facts');
            }
        }));
    }

    /** @param list<array{store_id: int, business_date: string}>|null $scopes */
    private function rebuildSummaries(Merchant $merchant, ?array $scopes = null): void
    {
        $metrics = ['revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs'];
        $sum = implode(', ', array_map(fn (string $column): string => 'SUM('.$column.') '.$column, $metrics));
        foreach ($scopes ?? [null] as $scope) {
            $base = DB::table('report_sales_facts')->where('merchant_id', $merchant->id);
            $receiptTarget = DB::table('report_receipt_facts')->where('merchant_id', $merchant->id);
            $dailyTarget = DB::table('report_daily_facts')->where('merchant_id', $merchant->id);
            if ($scope !== null) {
                foreach ($scope as $column => $value) {
                    $base->where($column, $value);
                    $receiptTarget->where($column, $value);
                    $dailyTarget->where($column, $value);
                }
            }
            $receiptTarget->delete();
            $receipts = (clone $base)
                ->select(['merchant_id', 'store_id', 'document_internal_id', 'is_return'])
                ->selectRaw('MIN(document_id) document_id, MIN(document_number) document_number, MIN(business_date) business_date, '.$sum)
                ->groupBy('merchant_id', 'store_id', 'document_internal_id', 'is_return');
            DB::table('report_receipt_facts')->insertUsing(['merchant_id', 'store_id', 'document_internal_id', 'is_return', 'document_id', 'document_number', 'business_date', ...$metrics], $receipts);
            $dailyTarget->delete();
            foreach (['product', 'tax'] as $kind) {
                $daily = (clone $base)->select(['merchant_id', 'store_id', 'business_date', 'is_return'])
                    ->selectRaw('? kind', [$kind])->groupBy('merchant_id', 'store_id', 'business_date', 'is_return');
                $daily->selectRaw($kind === 'product'
                    ? 'product_variant_id, MIN(product) product, MIN(sku) sku, unit, NULL tax_classification_snapshot, NULL tax_rate_snapshot'
                    : 'NULL product_variant_id, NULL product, NULL sku, NULL unit, tax_classification_snapshot, tax_rate_snapshot');
                $kind === 'product' ? $daily->groupBy('product_variant_id', 'unit') : $daily->groupBy('tax_classification_snapshot', 'tax_rate_snapshot');
                $daily->selectRaw('SUM(quantity) quantity, '.$sum.', COUNT(DISTINCT document_internal_id) documents');
                DB::table('report_daily_facts')->insertUsing(['merchant_id', 'store_id', 'business_date', 'is_return', 'kind', 'product_variant_id', 'product', 'sku', 'unit', 'tax_classification_snapshot', 'tax_rate_snapshot', 'quantity', ...$metrics, 'documents'], $daily);
            }
        }
    }

    public function isCurrent(Merchant $merchant): bool
    {
        $state = DB::table('report_projection_states')->where('merchant_id', $merchant->id)->first();

        if ($state === null) {
            return false;
        }
        $stored = json_decode($state->watermark, true, flags: JSON_THROW_ON_ERROR);
        unset($stored['generation']);

        return $stored == $this->watermark($merchant);
    }
}
