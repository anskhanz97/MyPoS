<?php

namespace App\Domain\Reporting;

final class ReportCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $groups = [
            'Sales' => [
                'sales-summary' => ['Sales summary', 'sales', 'receipt'], 'daily-sales' => ['Daily sales', 'sales', 'day'],
                'time-intervals' => ['Time intervals', 'sales', 'hour'], 'time-period' => ['Sales by period', 'sales', 'period'],
                'time-comparisons' => ['Period comparisons', 'sales', 'day'], 'trends' => ['Sales trends', 'sales', 'day'],
                'sales-by-product' => ['Sales by product', 'sales', 'product'], 'employees' => ['Employee sales', 'sales', 'employee'],
                'locations' => ['Sales by store', 'sales', 'store'], 'device-groups' => ['Register groups', 'sales', 'register_group'],
                'shift-based' => ['Sales by cash session', 'sales', 'session'], 'customer-types' => ['Customer groups', 'sales', 'customer_group'],
                'brands' => ['Sales by brand', 'sales', 'brand'], 'suppliers' => ['Sales by primary supplier', 'sales', 'supplier'],
                'size' => ['Sales by size', 'sales', 'size'], 'till-categories' => ['Catalogue categories', 'sales', 'category'],
                'reporting-categories' => ['Reporting categories', 'sales', 'reporting_category'],
                'sales-by-product-type' => ['Business product types', 'sales', 'product_type'], 'wet-and-dry' => ['Wet and dry', 'sales', 'wet_dry'],
                'misc-products' => ['Miscellaneous products', 'sales', 'product'], 'discount-on-items' => ['Discounted items', 'sales', 'line'],
                'promotions' => ['Promotion performance', 'allocations', 'promotion'], 'discount-usage' => ['Discount usage', 'allocations', 'source'],
                'multiple-choice-products' => ['Extras and options', 'extras', 'option'], 'payment-method' => ['Payment methods', 'tenders', 'type'],
            ],
            'Products & customers' => [
                'product-performance' => ['Product performance', 'sales', 'product'], 'top-bottom-sellers' => ['Top and bottom sellers', 'sales', 'product'],
                'average-size' => ['Average transaction size', 'transactions', 'receipt'], 'repeat-customer-rate' => ['Repeat customers', 'customers', 'customer'],
                'refund-void' => ['Returns and refunds', 'returns', 'receipt'],
            ],
            'Cash & financial' => [
                'z-report' => ['End of day / Z report', 'closeouts', 'session'], 'staff-z-report' => ['Staff closeout activity', 'sales', 'employee'],
                'terminal-z-report' => ['Register closeout activity', 'sessions', 'register'], 'eod-variance' => ['Cash variance', 'sessions', 'session'],
                'eod-variance-history' => ['Closeout revision history', 'closeouts', 'session'],
                'tender-breakdown' => ['Tender breakdown', 'tenders', 'type'], 'float-adjustments' => ['Float and adjustments', 'cash', 'kind'],
                'petty-cash' => ['Petty cash', 'cash', 'kind'], 'tax-report' => ['Tax reconciliation', 'sales', 'tax'],
                'daily-tax' => ['Daily tax', 'sales', 'day'], 'monthly-tax' => ['Monthly tax', 'sales', 'month'],
                'quarterly-tax' => ['Quarterly tax', 'sales', 'quarter'], 'end-of-year-tax' => ['Annual tax', 'sales', 'year'],
                'tax-filing' => ['Tax reconciliation worksheet', 'sales', 'tax'], 'gross-profit' => ['Gross profit', 'sales', 'product'],
                'profit-loss' => ['Operational profit and loss', 'profit', 'day'], 'cash-flow-statement' => ['Recorded operational cash flow', 'cashflow', 'kind'],
                'bookkeeping' => ['Bookkeeping detail', 'sales', 'line'],
            ],
            'Stock' => [
                'stock-levels' => ['Current stock levels', 'stock', 'variant'], 'batch-stock' => ['Batch stock and expiry', 'batch_stock', 'batch'], 'stock-warnings' => ['Stock and expiry warnings', 'stock', 'variant'],
                'stock-journal' => ['Stock movement journal', 'movements', 'movement'], 'stock-history' => ['Historical stock balances', 'history', 'variant'],
                'stock-changes' => ['Stock changes', 'movements', 'movement'], 'stock-discrepancies' => ['Stocktake discrepancies', 'stocktakes', 'variant'],
                'non-selling-stock' => ['Non-selling stock', 'stock', 'variant'], 'stock-valuation' => ['Stock valuation', 'valuation', 'variant'],
                'cost-adjustments' => ['Cost adjustments and pending valuations', 'cost_entries', 'entry'],
            ],
            'Purchasing & expenses' => [
                'purchase-orders' => ['Purchase orders', 'purchase_orders', 'document'], 'purchase-receipts' => ['Goods receipts', 'purchase_receipts', 'document'],
                'purchase-returns' => ['Purchase returns', 'purchase_returns', 'document'], 'supplier-statement' => ['Supplier ledger', 'supplier', 'document'],
                'supplier-payments' => ['Supplier payments', 'supplier_payments', 'document'], 'expenses-detail' => ['Business expenses', 'expenses', 'document'],
                'settlements' => ['External settlement reconciliation', 'settlements', 'document'],
                'supplier-credits' => ['Expected and confirmed supplier credits', 'supplier_credits', 'document'],
                'supplier-allocations' => ['Supplier payment allocations and reversals', 'supplier_allocations', 'document'],
            ],
            'Workforce & loyalty' => [
                'attendance-logs' => ['Attendance events', 'attendance_events', 'event'], 'clock-in-out' => ['Clock in and out', 'attendance_shifts', 'shift'],
                'rota' => ['Staff rota', 'rota_shifts', 'shift'], 'leave' => ['Leave requests', 'leave_requests', 'request'],
                'payroll-detail' => ['Payroll and payouts', 'payroll', 'employee'], 'loyalty-activity' => ['Loyalty point activity', 'loyalty', 'member'],
            ],
            'Audit' => [
                'void-lines' => ['Removed cart lines', 'voids', 'line'], 'refunds' => ['Return audit', 'returns', 'receipt'],
                'discounts' => ['Discount audit', 'allocations', 'source'], 'no-sales' => ['No-sale drawer openings', 'cash', 'kind'],
            ],
        ];
        $reports = [];
        foreach ($groups as $group => $items) {
            foreach ($items as $key => [$title, $type, $dimension]) {
                $reports[$key] = ['key' => $key, 'title' => $title, 'group' => $group, 'type' => $type, 'dimension' => $dimension,
                    'sensitive' => in_array($key, ['gross-profit', 'profit-loss', 'bookkeeping', 'stock-valuation', 'cost-adjustments', 'cash-flow-statement', 'payroll-detail'], true),
                    'exports' => ['csv', 'xlsx', 'pdf'], 'definition_version' => 2];
            }
        }

        return $reports;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? abort(404, 'Unknown report.');
    }

    /**
     * @return list<string>
     */
    public static function definitions(): array
    {
        return [
            'Revenue excludes tax and is after discounts and returns on their own business dates.',
            'Sales including tax = revenue excluding tax + recorded tax. Cash change is deducted once from tender receipts.',
            'COGS uses signed sale and restock cost entries, including bundle components and stock-consuming extras. Missing costs are unavailable, never zero.',
            'Gross profit = revenue excluding tax - net recorded COGS. Margin = gross profit / revenue; zero denominators are unavailable.',
            'Returns awaiting an external refund reduce revenue; completed refund payments are reported separately.',
            'Primary supplier means captured product attribution, not procurement provenance of an individual sold unit.',
            'Historical classifications use transaction snapshots. Not captured means historical evidence was unavailable.',
            'Stock history uses ledger posting time. Effective movement times remain visible in the journal.',
            'Reversed purchase documents remain listed with zero active amount; supplier credits and payments retain their signed reversal entries.',
            'Operational profit, cash flow, and tax worksheets are recorded operational evidence, not statutory accounts or tax filings.',
            'Diagnostic logs are not business ledgers. Archived products and classifications remain in historical totals.',
            'Top and bottom sellers rank products with trading activity. Non-selling stock lists current positive stock without a sale in the selected period.',
            'Store payroll attribution uses the frozen payroll input. Older unallocated payroll remains in consolidated totals and makes store operating profit unavailable.',
        ];
    }
}
