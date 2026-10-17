<?php

namespace App\Domain\Reporting;

use App\Models\AttendanceShift;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\InventoryCostEntry;
use App\Models\LeaveRequest;
use App\Models\LoyaltyPointEntry;
use App\Models\Merchant;
use App\Models\PayrollItem;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\SupplierChargeEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class StandardReportService
{
    public function __construct(private readonly ReportQuery $workspace) {}

    public const KEYS = ['sales', 'inventory', 'purchasing', 'expenses', 'loyalty', 'workforce', 'cash'];

    /** @return array<string,mixed> */
    public function run(Merchant $merchant, ?int $storeId, string $key, CarbonImmutable $start, CarbonImmutable $end): array
    {
        abort_unless(in_array($key, self::KEYS, true), 404);
        abort_if($start->diffInDays($end) > 366, 422, 'Report range cannot exceed 366 days.');
        $data = match ($key) {
            'sales' => $this->sales($merchant->id, $storeId, $start, $end),'inventory' => $this->inventory($merchant, $storeId, $start, $end),'purchasing' => $this->purchasing($merchant->id, $storeId, $start, $end),'expenses' => $this->expenses($merchant->id, $storeId, $start, $end),'loyalty' => $this->loyalty($merchant, $start, $end),'workforce' => $this->workforce($merchant->id, $storeId, $start, $end),'cash' => $this->cash($merchant->id, $storeId, $start, $end)
        };

        return ['report' => $key, 'scope' => $storeId === null ? 'merchant' : 'store', 'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'timezone' => $merchant->timezone], 'freshness' => ['generated_at' => now()->toAtomString(), 'mode' => 'live-ledger'], 'definitions' => $this->definitions($key), 'data' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    private function sales(int $merchantId, ?int $storeId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sales = $this->scoped(Sale::query(), $merchantId, $storeId)->whereBetween('business_date', [$start, $end]);
        $returns = $this->scoped(SaleReturn::query(), $merchantId, $storeId)->whereBetween('business_date', [$start, $end]);
        $gross = $this->sum($sales, 'total');
        $refunds = $this->sum($returns, 'total');
        $metrics = $this->workspace->run(Merchant::query()->findOrFail($merchantId), $storeId === null ? null : Store::query()->findOrFail($storeId), 'sales-summary', ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'per_page' => 1], ['actor_id' => null, 'costs' => true])['summary'];

        return ['transactions' => (clone $sales)->count(), 'gross_sales' => $gross, 'discounts' => $this->sum(clone $sales, 'discount_total'), 'net_sales' => $this->sum(clone $sales, 'net_total'), 'tax' => $this->sum(clone $sales, 'tax_total'), 'refunds' => $refunds, 'sales_after_refunds' => $this->money(BigDecimal::of($gross)->minus($refunds)), 'cost_of_goods' => $metrics['cogs'], 'gross_margin' => $metrics['gross_profit'], 'drilldown' => (clone $sales)->latest('finalized_at')->limit(100)->get(['public_id', 'number', 'business_date', 'total'])->map(fn (Sale $s) => ['type' => 'sale', 'id' => $s->public_id, 'number' => $s->number, 'date' => $s->business_date->toDateString(), 'amount' => $s->total])->all(), 'limitations' => ['returns are recognized on return business date', 'provisional cost entries remain visible in valuation reports']];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventory(Merchant $merchant, ?int $storeId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $batches = ProductBatch::query()->where('merchant_id', $merchant->id)->when($storeId !== null, fn (Builder $q) => $q->where('origin_store_id', $storeId));

        return ['quantity_on_hand' => (string) $this->scoped(StockBalance::query(), $merchant->id, $storeId)->sum('quantity_base'), 'expiring_batches' => $batches->whereBetween('expiry_date', [$start, $end])->count(), 'cost_adjustments' => $this->sum($this->scoped(InventoryCostEntry::query(), $merchant->id, $storeId)->whereBetween('effective_at', [$start->startOfDay(), $end->endOfDay()]), 'cost_adjustment_value'), 'negative_balances' => $this->scoped(StockBalance::query(), $merchant->id, $storeId)->where('quantity_base', '<', 0)->count()];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasing(int $merchantId, ?int $storeId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $q = $this->scoped(SupplierChargeEntry::query(), $merchantId, $storeId)->whereBetween('occurred_at', [$start->startOfDay(), $end->endOfDay()]);

        return ['supplier_charges' => $this->sum($q, 'amount'), 'entries' => (clone $q)->count()];
    }

    /**
     * @return array<string, mixed>
     */
    private function expenses(int $merchantId, ?int $storeId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $q = $this->scoped(Expense::query(), $merchantId, $storeId)->whereBetween('expense_date', [$start, $end]);

        return ['net_expenses' => $this->sum($q, 'amount'), 'records' => (clone $q)->count(), 'note' => 'Supplier and payroll payments are excluded to prevent double counting.'];
    }

    /** @return array<string,mixed> */
    private function loyalty(Merchant $m, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $q = LoyaltyPointEntry::query()->where('merchant_id', $m->id)->whereBetween('occurred_at', [$start->startOfDay(), $end->endOfDay()]);

        return ['points_net' => (string) $q->sum('points'), 'entries' => (clone $q)->count(), 'scope_note' => 'Merchant-wide; never multiplied by store count.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function workforce(int $merchantId, ?int $storeId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $attendance = $this->scoped(AttendanceShift::query(), $merchantId, $storeId)->whereBetween('clocked_in_at', [$start->startOfDay(), $end->endOfDay()]);
        $pay = PayrollItem::query()
            ->where('merchant_id', $merchantId)
            ->when($storeId !== null, fn (Builder $query) => $query->whereHas('staffProfile', fn (Builder $staff) => $staff->where('default_store_id', $storeId)))
            ->whereHas('run', fn (Builder $query) => $query->whereBetween('period_end', [$start, $end]))
            ->where('state', 'posted');
        $leave = LeaveRequest::query()
            ->where('merchant_id', $merchantId)
            ->when($storeId !== null, fn (Builder $query) => $query->whereHas('staffProfile', fn (Builder $staff) => $staff->where('default_store_id', $storeId)))
            ->where('state', 'approved')
            ->where('starts_on', '<=', $end)
            ->where('ends_on', '>=', $start);

        return ['attendance_shifts' => (clone $attendance)->count(), 'needs_review' => (clone $attendance)->where('state', 'needs_review')->count(), 'approved_leave' => $leave->count(), 'posted_net_payroll' => $this->sum($pay, 'net_amount'), 'privacy' => 'Payroll totals require payroll.manage at the API boundary.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function cash(int $merchantId, ?int $storeId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $q = $this->scoped(CashMovement::query(), $merchantId, $storeId)->whereBetween('occurred_at', [$start->startOfDay(), $end->endOfDay()]);

        return ['net_cash' => $this->sum($q, 'signed_amount'), 'movements' => (clone $q)->count(), 'by_kind' => (clone $q)->selectRaw('kind, SUM(signed_amount) amount')->groupBy('kind')->pluck('amount', 'kind')->all()];
    }

    /** @return array<string,string> */
    private function definitions(string $key): array
    {
        return ['gross_sales' => 'Posted sale total including tax before refunds.', 'net_sales' => 'Posted sale value excluding tax after line/cart discounts.', 'refunds' => 'Returns recognized on their own business date.', 'gross_margin' => 'Revenue excluding tax less returns excluding tax and net recorded sale/restock costs.', 'report_note' => 'Fixed '.$key.' definition; source documents remain authoritative.'];
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $q
     */
    private function sum(Builder $q, string $column): string
    {
        return $this->money(BigDecimal::of((string) $q->sum($column)));
    }

    private function money(BigDecimal $n): string
    {
        return (string) $n->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scoped(Builder $query, int $merchantId, ?int $storeId): Builder
    {
        return $query->where('merchant_id', $merchantId)->when($storeId !== null, fn (Builder $builder) => $builder->where('store_id', $storeId));
    }
}
