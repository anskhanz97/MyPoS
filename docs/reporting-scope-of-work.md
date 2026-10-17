# MyPOS reporting scope of work

Approved direction: 17 October 2026. This file is the implementation and acceptance checklist; an unchecked gate is not a completion claim.

## Product outcome

Deliver a live reporting workspace, adapting the Retail and Hospitality report layouts, cards, tables, filter panels, dropdowns, pagination and drill-downs. Use MyPOS's authoritative ledgers and theme tokens. No report may fall back to sample figures, silently truncate an export, or treat an unavailable historical fact as zero.

The reference inventory contains 72 Retail report-area views and 68 Hospitality views, with 76 distinct paths. Some paths are aliases, configuration pages, placeholders or incomplete endpoints. The source mapping below accounts for every path. Reference projects remain read-only; no historical XEPOS data migration is included.

## Agreed boundaries

- MyPOS reports first, including the data capture and setup needed to make them meaningful.
- Include real CSV, XLSX and server-generated PDF; use OpenSpout 4.32.0 for streaming XLSX and existing Dompdf for PDF.
- Owner: merchant-wide and sensitive reports. Manager: assigned-store operations. Cashier: their own allowed sales activity. Enforce separate cost/financial/payroll/customer-export permissions on every data path, including legacy exports.
- Retain one Reports navigation entry. The report catalogue belongs inside that workspace.
- Keep application/API/integration diagnostics in their three database tables; do not restore the Logs screen or use diagnostic retention tables as business ledgers.
- Defer table service, covers, cashback, service charges, integrated payment acquisition, statutory accounts/filing, custom report builders, Word export and scheduled report emails.
- Existing tax, tender, printer and security configuration belongs in its owning module, not duplicate report pages.

## Report families

### Sales, products and customers

Sales summary and daily sales; hourly intervals; day/week/month/year grouping; previous-period/year comparison; trends; products/variants; product performance; top and bottom ranking; employees; stores; register groups; cash sessions; customer groups; brands; primary suppliers; size; catalogue/reporting categories; business product types and optional wet/dry classifications; miscellaneous products; extras/options; promotions, coupons, manual and loyalty discount allocation; average transaction size; repeat customers; returns and removed-line audits.

Each report includes only meaningful dimensions. Quantities retain units and weighted precision. Product/variant comparisons never merge independent-store records merely because SKU text matches. Supplier attribution is the product's captured primary supplier, not a claim about the physical procurement provenance of a sold unit.

### Cash, financial and tax

Live session and immutable finalized closeouts; staff/register activity; variance and revision history; tenders and pending refunds; float and paid-in/out/corrections; petty cash; tax rate/classification reconciliation with daily/monthly/quarterly/annual presets; operational gross profit and P&L; recorded cash/settlement flow; bookkeeping details; restricted payroll.

Opening a report does not close a cash session, reset counters, submit tax, verify a bank payment or acquire money. Blind-count expected values remain concealed until the business workflow permits disclosure. Reopened sessions receive a new closeout revision rather than overwriting earlier evidence.

### Stock, purchasing, workforce and loyalty

Current balances and valuation; low/negative/expiry warnings; movement journal and history; stocktake discrepancies; non-selling stock; purchase orders/receipts/returns; supplier charges, confirmed/expected credits, payments and allocations; expenses; settlements; attendance and corrections; worked time and breaks; rota; leave; posted payroll/advances/payouts; merchant-wide loyalty activity and balances.

Current stock is never relabelled as historical stock. Historical quantity reports use posting time and movement IDs; effective timestamps remain visible. Monetary values from stock and payroll must not leak through operational reports or drill-downs.

## Data and calculation contracts

- Add tenant-owned product categories, reporting categories, brands, business product types, register groups, store/variant reorder policies, durable cart audit events and immutable closeout snapshots.
- Add indexed reporting dimensions and immutable labels/provenance to sale lines; capture store timezone/UTC offset, customer group and register group on sale headers.
- Normalize recorded sale/return discount allocations for portable MySQL/MariaDB/SQLite reporting.
- Link sale/return stock-cost effects explicitly, including bundle components and stock-consuming extras.
- Add setup forms, archive/rename controls, SKU-based metadata CSV validation/commit and metadata export. Metadata CSV cells double a literal leading apostrophe and prefix spreadsheet-formula text with one apostrophe; the import boundary decodes this once. Keep the export prefixes when editing a MyPOS CSV. Report CSV keeps legitimate negative numbers numeric, and XLSX writes untrusted text as explicit string cells. Validation must not mutate product settings. Revalidate at commit; retain idempotency and tenant/store isolation.
- Backfill only proven ledger links and historical evidence. Unknown classifications remain `Not captured`. Never assign today's brand/category/supplier to old sales as an established historical fact.
- Capture semantic cart quantity reductions/removals transactionally. Delete-and-recreate internals, unchanged quantities, retries and rolled-back edits must not produce false successful voids.
- Revenue excludes tax and is after discounts/returns. Preserve an explicitly separate sales-including-tax metric.
- Returns reduce revenue on their own business date even when an external refund remains pending. Tender reports separate completed payments and pending entitlements.
- Net COGS uses signed sale and restock effects; discarded goods do not reverse stock cost. Missing/provisional cost is visible and never silently treated as zero. Later cost adjustments must remain separately identifiable.
- Gross profit = revenue excluding tax - net COGS. Aggregate margin uses aggregate amounts; undefined denominators are unavailable.
- Subtract cash change once. Split tenders, extras and multiple child rows must not multiply revenue.
- Transfers are neither sales nor supplier purchases. Supplier and payroll payments are settlement evidence, not additional expenses. Merchant-wide loyalty never multiplies by store count.
- Preserve decimal arithmetic. Do not add incompatible stock units into one universal quantity.
- Business-date reports retain recorded session business dates. Hourly buckets use the captured local offset, including daylight-saving offsets, without depending on installed MySQL timezone tables.

## API, screen and export contracts

Versioned merchant routes under `/api/v1/merchants/{merchant}/reporting`: catalogue; fixed report results and source rows; export request/list/download; personal saved-filter CRUD; reporting setup and product/variant/register assignments. Preserve existing seven report keys with compatibility restrictions and corrected metric definitions.

Results include definition version 2, scope, period/basis/timezone, freshness, summary, typed columns, paginated rows and limitations. Default to 30 days, 50 rows/page, maximum 200; maximum 366 inclusive days per period. Sort identifiers are allowlisted and ordering has a stable tie-breaker. UUID lookups must resolve inside the requested merchant/store.

Use searchable report navigation, responsive filter grids, readable summary cards, sortable numeric tables, visible totals, clear empty/error/loading states, source-line drill-down, personal filters and report URLs that preserve the selection. Adapt source patterns to MyPOS instead of importing legacy global styles or a second component framework. Test keyboard access and both themes.

Exports use the complete filtered dataset, not the visible page. Recheck initiating user, current role and store assignment at request, worker execution and download. Keep artifacts private for seven days; existing `ops:prune-expired-artifacts` removes expired files while retaining job metadata.

- CSV/XLSX: up to one million detail rows; reject larger selections clearly. Split XLSX detail sheets at 100,000 rows.
- XLSX: information/definitions/summary sheet and actual detail worksheets. Preserve identifiers and large decimals; use numeric money cells where Excel can represent their precision safely.
- PDF: server-generated A4, title/scope/filter/definition/freshness information, summary and at most 2,000 detail rows. Larger requests use a summary PDF or narrower filters.
- Prevent spreadsheet formula execution. Preserve Unicode and negative amounts.
- Use one consistent read snapshot for totals and exported rows. Stream large datasets rather than accumulating a complete workbook in memory.
- Dedicated `reports` queue connection: retry_after 960 seconds, worker/job timeout 900 seconds, three attempts. Start with `php artisan queue:work reports --queue=reports --tries=3 --timeout=900`.
- Log export start/completion/failure and queue/build identity through existing backend diagnostics. Do not include credentials or report payloads in diagnostic logs.

## Delivery and acceptance gates

- [x] Read source report views/routes/controllers and inventory both reference projects.
- [x] Confirm report scope, export formats, restricted roles and source-led frontend direction with the user.
- [x] Save local MyPOS SQL backup before migrations: `C:/Temp/mypos-reporting-backup-20261017.sql`.
- [x] Apply reporting foundation migration on local MariaDB; recover the initially overlong generated index name without dropping business data.
- [x] Verify all catalogue reports against empty ledgers and populated sales/returns on isolated MySQL 8.
- [x] Verify signed sale/partial-return reconciliation, historical brand preservation, safe cart reductions and real CSV/XLSX/PDF generation.
- [x] Complete and verify all remaining calculation/data capture requirements in this scope.
- [x] Verify source-related setup/import/export, permission revocation, closed-session revision and incomplete-cost cases.
- [x] Verify browser interactions, populated states, both themes, mobile layout and export downloads.
- [x] Benchmark one million sale lines and detailed 100,000-row CSV/XLSX exports; record actual timings/memory/query plans.
- [x] Finish static analysis, formatting, relevant regressions, API contracts and local rollout checks.

## Reconciliation and test requirements

Use deterministic multi-merchant and shared/independent-store fixtures with same-text SKUs, multiple staff/registers, taxes, weighted units, bundles/extras, discounts/coupons/loyalty, split tenders/change, partial/full/damaged returns and pending refunds. Include receipts, credits, payments/reversals, negative/provisional costs and resolution, backdated entries, live-trading stocktakes/transfers, expiry, attendance/leave/payroll, overnight sessions and offline replay.

Check hand-calculated totals across summary, detail, source rows and each export. Test foreign merchants/stores, cashier substitution, absent sensitive fields, customer/payroll restrictions, revoked permissions, saved-filter ownership, malicious sort input and formulas, historical renaming/archival, retries, rollback and no-op cart replacement.

Test on MySQL 8 and local MariaDB 10.4; use SQLite for fast tests but do not replace database-specific coverage with it. Target common 30-day pages at p95 <=2 seconds and annual summaries <=5 seconds on a documented baseline. Target 100,000-row exports <=5 minutes and <=256 MB memory. Add indexes from measured plans; introduce rebuildable projections only if measured performance needs them. Keep heavy aggregations off checkout write paths.

Final acceptance requires every included catalogue item to be real, authorized, reconciled and exportable. A menu label, placeholder endpoint or successful empty response alone is not completion.

## Source inventory and mapping

`R` = portal-retail-xepos/src/views/reports; `H` = xepos-hq-hospitality/resources/js/views/reports. Each row below is a unique view path relative to these roots. `API call present` is static evidence, not a claim that the source endpoint works correctly.

| Source path | Projects | Disposition | Evidence |
|---|---|---|---|
| child/accounting/bookkeeping/index.vue | H,R | bookkeeping | placeholder/demo content present |
| child/accounting/cash-flow-statement/index.vue | H,R | cash-flow-statement | API call present; placeholder/demo content present |
| child/accounting/daily-tax/index.vue | H,R | daily-tax | placeholder/demo content present |
| child/accounting/end-of-year-tax/index.vue | H,R | end-of-year-tax | placeholder/demo content present |
| child/accounting/gross-profit/index.vue | H,R | gross-profit | placeholder/demo content present |
| child/accounting/monthly-tax/index.vue | H,R | monthly-tax | placeholder/demo content present |
| child/accounting/payroll/index.vue | H,R | payroll | placeholder/demo content present |
| child/accounting/profit-loss/index.vue | H,R | profit-loss | API call present; placeholder/demo content present |
| child/accounting/quarterly-tax/index.vue | H,R | quarterly-tax | placeholder/demo content present |
| child/accounting/tax-filing/index.vue | H,R | tax-filing | API call present; placeholder/demo content present |
| child/auditing/discounts/index.vue | H,R | discounts | placeholder/demo content present |
| child/auditing/no-sales/index.vue | H,R | no-sales | placeholder/demo content present |
| child/auditing/refunds/index.vue | H,R | refunds | placeholder/demo content present |
| child/auditing/void-lines/index.vue | H,R | void-lines | placeholder/demo content present |
| child/banking/cashback/index.vue | H,R | Deferred business workflow | placeholder/demo content present |
| child/banking/end-of-day/index.vue | H,R | z-report | placeholder/demo content present |
| child/banking/float-adjustments/index.vue | H,R | float-adjustments | placeholder/demo content present |
| child/banking/integrated-card-types/index.vue | H,R | Deferred business workflow | placeholder/demo content present |
| child/banking/petty-cash/index.vue | H,R | petty-cash | placeholder/demo content present |
| child/banking/service-charges/index.vue | H,R | Deferred business workflow | placeholder/demo content present |
| child/banking/tenders/index.vue | H,R | tender-breakdown | placeholder/demo content present |
| child/financial-report/eod-variance/history/index.vue | R | eod-variance-history | API call present |
| child/financial-report/eod-variance/index.vue | R | eod-variance | API call present |
| child/financial-report/staff-z-report/index.vue | H | staff-z-report | API call present |
| child/financial-report/tax-report/index.vue | H,R | tax-report | API call present |
| child/financial-report/tender-breakdown/index.vue | H,R | tender-breakdown | API call present |
| child/financial-report/terminal-z-report/index.vue | H | terminal-z-report | API call present |
| child/financial-report/z-report/index.vue | H,R | z-report | API call present |
| child/meal-periods/index.vue | H,R | Owning-module configuration; no duplicate report | API call present |
| child/printerType/index.vue | H,R | Owning-module configuration; no duplicate report | API call present |
| child/product/performance/index.vue | H,R | product-performance | API call present |
| child/product/top-bottom-sellers/index.vue | R | top-bottom-sellers | API call present |
| child/sales-report/brands/index.vue | H,R | brands | API call present; placeholder/demo content present |
| child/sales-report/covers/index.vue | H,R | Deferred business workflow | placeholder/demo content present |
| child/sales-report/customer-types/index.vue | H,R | customer-types | API call present; placeholder/demo content present |
| child/sales-report/daily-sales/index.vue | R | daily-sales | API call present |
| child/sales-report/device-groups/index.vue | H | device-groups | API call present |
| child/sales-report/discount-on-items/index.vue | R | discount-on-items | API call present |
| child/sales-report/discount-usage/index.vue | H,R | discount-usage | API call present |
| child/sales-report/employees/index.vue | H,R | employees | API call present |
| child/sales-report/locations/index.vue | H,R | locations | API call present |
| child/sales-report/misc-products/index.vue | H,R | misc-products | API call present |
| child/sales-report/multiple-choice-products/index.vue | H,R | multiple-choice-products | placeholder/demo content present |
| child/sales-report/payment-method/index.vue | H,R | payment-method | API call present |
| child/sales-report/promotions/index.vue | H,R | promotions | API call present; placeholder/demo content present |
| child/sales-report/reporting-categories/index.vue | H,R | reporting-categories | API call present; placeholder/demo content present |
| child/sales-report/sales-by-product-type/index.vue | H | sales-by-product-type | API call present |
| child/sales-report/sales-by-product/index.vue | H,R | sales-by-product | API call present |
| child/sales-report/sales-summary/index.vue | H,R | sales-summary | API call present |
| child/sales-report/shift-based/index.vue | H,R | shift-based | API call present |
| child/sales-report/size/index.vue | H,R | size | API call present; placeholder/demo content present |
| child/sales-report/suppliers/index.vue | H,R | suppliers | API call present; placeholder/demo content present |
| child/sales-report/table/index.vue | H,R | Deferred business workflow | placeholder/demo content present |
| child/sales-report/till-categories/index.vue | H,R | till-categories | placeholder/demo content present |
| child/sales-report/time-comparisons/index.vue | H,R | time-comparisons | API call present |
| child/sales-report/time-intervals/index.vue | H,R | time-intervals | API call present |
| child/sales-report/time-period/index.vue | H,R | time-period | API call present |
| child/sales-report/trends/index.vue | H,R | trends | API call present |
| child/sales-report/wet-and-dry/index.vue | H,R | wet-and-dry | API call present |
| child/security-permission/index.vue | H,R | Owning-module configuration; no duplicate report | API call present |
| child/security/index.vue | H,R | Owning-module configuration; no duplicate report | API call present |
| child/stock/non-selling-stock/index.vue | H,R | non-selling-stock | placeholder/demo content present |
| child/stock/stock-changes/index.vue | H,R | stock-changes | placeholder/demo content present |
| child/stock/stock-discrepancies/index.vue | H,R | stock-discrepancies | placeholder/demo content present |
| child/stock/stock-history/index.vue | H,R | stock-history | placeholder/demo content present |
| child/stock/stock-journal/index.vue | R | stock-journal | API call present |
| child/stock/stock-levels/index.vue | H,R | stock-levels | API call present; placeholder/demo content present |
| child/stock/stock-warnings/index.vue | H,R | stock-warnings | API call present; placeholder/demo content present |
| child/tax/index.vue | H,R | Owning-module configuration; no duplicate report | API call present |
| child/tender-types/index.vue | H,R | Owning-module configuration; no duplicate report | API call present |
| child/transaction-history/average-size/index.vue | H,R | average-size | API call present |
| child/transaction-history/refund-void/index.vue | H,R | refund-void | API call present |
| child/transaction-history/repeat-customer-rate/index.vue | H,R | repeat-customer-rate | API call present |
| child/workforce/attendance-logs/index.vue | H,R | attendance-logs | API call present |
| child/workforce/clock-in-out/index.vue | R | clock-in-out | API call present |
| child/workforce/employee-sales/index.vue | R | employees | API call present |

## Initial implementation evidence (before the follow-up below)

The workspace now has 77 fixed report definitions. The local MariaDB foundations and measured store/date index are applied. The dedicated reports worker completed a local CSV export. The main MyPOS database remains free of synthetic sales; populated browser evidence uses the separate `mypos_test` acceptance merchant.

Implemented behavior includes source-adapted report navigation, date/store/staff/register/classification filters, complete-filter totals, pagination, source-line pagination, personal filters, shareable selections, real CSV/XLSX/PDF jobs, private expiring downloads, metadata setup and validate/commit CSV import/export. Backend business ledgers remain the source; diagnostic logging stays in the database without a Logs screen.

Financial checks include sale/partial-return revenue, tax and cost reconciliation; cent-accurate discount reversals; unknown costs remaining unavailable; expense corrections; settlement fees and unmatched evidence; payroll paid/outstanding amounts; payroll captured store/name surviving a later staff transfer; and unknown historical payroll attribution preventing a fabricated store profit. Z reports read frozen closeout totals, while revision history retains earlier payloads and hashes.

The populated browser acceptance test passed filters, pagination, source records, saved filters, CSV download, product/gross-profit/P&L reconciliation, both themes and a 390px mobile viewport. Evidence screenshots are under `output/mypos-reports-live-*.png`. The main local preview is `output/mypos-reports-local.png`.

### Reconciliation acceptance

The focused database suite passes on both MySQL 8.4.11 and local MariaDB 10.4.32: **73 tests, 1,619 assertions per engine**. Each engine uses an empty, schema-only validation database; the main application's business data and the retained benchmark corpus are not reset. Results are recorded in `output/reporting-mysql-regression.xml` and `output/reporting-mariadb-regression.xml`.

| Contract | Verified evidence |
|---|---|
| Sales, returns and units | Mixed 0%/18% tax, cart discounts and a partial return reconcile to revenue 299.33, tax 44.94 and gross 344.27. Carton/base quantities remain separate; their posted monetary amounts reconcile. |
| Bundles, extras and costs | Weighted bundle components and multiple extras reconcile to net revenue 320.00 and COGS 105.00 after a partial return, without multiplying revenue through child joins. Unknown/provisional costs remain unavailable; discarded goods retain their cost. |
| Tenders and retries | Split cash/card tenders subtract change once; pending refunds reduce revenue separately from completed payments; offline replay creates one sale and one loyalty earn. |
| Historical attribution | Captured classifications survive renames. Refunds retain their own staff/register/timezone evidence, including DST and overnight hours. Separate locations with the same name do not merge. |
| Purchasing and inventory | Transfers do not create revenue. Live-trading stocktake discrepancies preserve intervening sales. Negative stock cost resolution, backdated posting, expiry/quarantine, receipt/return reversals, supplier credit confirmation and payment/allocation reversals reconcile. Reversed purchase documents retain their history with zero active amount. |
| Cash, workforce and payroll | Blind-count totals remain hidden until variance review; closeout revisions are immutable. Breaks, overnight worked time, corrections, rota and leave reconcile. Repeated payroll freezing reuses the original immutable input; payouts/advances do not become duplicate expenses. |
| Permissions and artifacts | Tenant/store boundaries, cashier scope, sensitive columns, saved-filter ownership, permission revocation at export/download, formula-safe CSV/XLSX and real CSV/XLSX/PDF contents are covered. |
| Projection correctness | Results match raw ledgers before/after rebuilding. New sales/returns remain visible in the live tail; late-committing lower IDs and changes to old costs trigger ledger fallback. Backfills and forced rebuilds invalidate cached results even when source row counts are unchanged. |

Coverage lives in `ReportWorkspaceTest`, `SaleFinalizationTest`, `SaleReturnTest`, `OfflineReconciliationTest`, `PurchaseReceivingAndValuationTest`, `StockTransferAndStocktakeTest`, `CashSessionTest`, `WorkforceTest`, `OperationalFinanceTest`, `PayrollTest`, and the reporting/pricing/data-transfer regressions. Functional browser acceptance covers every catalogue endpoint, reconciliation across product/profit/P&L, pagination, source lines, saved filters, downloadable CSV, both themes and mobile overflow.

### Performance implementation and operations

Measured plans exposed excessive materialization, text grouping and repeated aggregation. Queries now select only required fields, use numeric document/product keys, avoid redundant outer grouping, and use measured covering indexes. `report_sales_facts` and `report_projection_states` are rebuildable read projections; business ledgers remain authoritative. Projection grouping preserves receipt/product/unit/tax/dimension boundaries and signed cost effects.

The scheduler dispatches stale merchant projections every minute to the existing `reports` queue. Run Laravel's scheduler and the dedicated worker in deployment. Report requests can also enqueue a unique refresh; they do not synchronously rebuild a million-line corpus. Matching projections are combined with a verified live tail. Unsafe or missing projections fall back to the ledgers rather than silently returning stale figures.

```powershell
C:\php83\php.exe artisan reports:backfill --dry-run --no-interaction
C:\php83\php.exe artisan reports:backfill --no-interaction
C:\php83\php.exe artisan reports:refresh --force --no-interaction
C:\php83\php.exe artisan queue:work reports --queue=reports --tries=3 --timeout=900 --no-interaction
```

`reports:refresh` optionally accepts a merchant UUID and `--queue`; without `--force`, it rebuilds only stale merchants. Run backfill before rebuilding historical data. It links only proven references and marks missing history as uncaptured. A projection rebuild is atomic, serialized per merchant, updates optimizer statistics and uses a read isolation level that avoids holding source-row shared locks during aggregation. The benchmark verifies an independent source update can complete during the rebuild.

Selected immutable report summaries/pages are cached for five minutes with authorization-dependent SQL, filters, ledger watermarks and projection generation in the key. Current access is still checked on every request. New ledger entries invalidate the key immediately; forced rebuilds also change it. The cache is an optimization, not a freshness substitute.

MySQL/MariaDB report queries temporarily allow up to 128 MB for database temporary tables and 8 MB for sorting, restoring the previous session settings on success or failure. `REPORT_QUERY_TEMP_MEMORY_MB` can lower the temporary-table allowance (16–128 MB). These are per-connection database limits; they are separate from PHP memory and must be budgeted for concurrent report traffic.

### Earlier performance evidence (superseded workload)

Run `ReportPerformanceTest` serially on the disposable MySQL 8 database with `RUN_REPORT_BENCHMARK=1`, `REPORT_BENCHMARK_LINES=100000` or `1000000`, `REPORT_BENCHMARK_SAMPLES=20` and `VERIFY_REPORT_REBUILD_CONCURRENCY=1`. It refuses other environment/database/port combinations. The corpus is committed in bounded batches and retained for repeat runs, avoiding a giant transaction and rollback. Do not run schema-refresh tests against this corpus concurrently.

The baseline is PHP 8.3.31, Laravel 13.33.0, MySQL 8.4.11, a 512 MB InnoDB buffer pool and a 256 MB PHP memory limit on this Windows development machine (Intel Core i5-6200U 2.30 GHz, two physical cores/four logical processors, 7.7 GiB usable physical RAM). Main MariaDB server settings are unchanged. Each case measures 20 actual Laravel HTTP-kernel requests, including its first response; network transport is excluded. p95 uses the nineteenth sorted sample. Cached repeats and first responses are recorded separately.

The synthetic corpus contains 100,001 or 1,000,001 sale lines including the original fixture, 10 cloned lines per receipt, one product and one business day. The million-line projection contains 100,001 rows. This verifies a defined read/export workload, not arbitrary product/date cardinality, sustained concurrent traffic or checkout/stock-posting throughput. Unknown cloned costs remain unknown. Broader production capacity needs production-shaped data and load tests.

The detailed 100,001-row export run passed: CSV **60.64 seconds**, XLSX **109.36 seconds**, **48 MB peak PHP memory**. Actual file row counts and hashes are checked; XLSX is split into detail sheets at 100,000 rows. The million-line run exports a product summary, not a million-row detail file: its 1,000,001 detail rows exceed the explicit one-million-row export cap.

The earlier million-line run passed, with 136 assertions, 276.31 seconds overall, 48 MB peak PHP memory and 153.92 seconds for a projection rebuild. Product-summary CSV/XLSX took 2.82/2.50 seconds. These historical benchmark files use query/projection/exporter source hash `3acda731729757c715a7fb11bb4658bfcdd90cb714dba7ad59320e51fe3e004d`; they do not validate the subsequent summary/live-tail implementation or diverse concurrent workload.

| HTTP-kernel report case | 100k p95 | Million p95 | Million first request | p95 target |
|---|---:|---:|---:|---:|
| 30-day product | 0.144s | 1.030s | 2.703s | <=2s |
| 30-day receipts | 0.210s | 0.748s | 9.090s | <=2s |
| 30-day tax | 0.166s | 0.714s | 6.169s | <=2s |
| 30-day stock | 0.212s | 1.779s | 8.907s | <=2s |
| Annual product summary | 0.126s | 0.973s | 2.210s | <=5s |

**First loads are slower than repeat requests.** The initial direct one-day query took 10.10 seconds. The accepted p95 target passes on this baseline; this does not establish a <=2-second cold-load guarantee. No concurrent-load or production-shaped multi-product/multi-day throughput claim is made.

Historical results, gates, first-response timings, projection rebuild duration, memory, concurrency check, runtime configuration and source hash are in `output/reporting-benchmark-100000.json` and `output/reporting-benchmark-1000000.json`. Per-report EXPLAIN plans are `output/reporting-query-plan-<report>-<size>.json`. The earlier interrupted InnoDB rollback has completed.

Verification uses `C:/php83/php.exe`; the ordinary `php` command here points to PHP 8.2. The final SQLite in-memory run also passes **73 tests and 1,619 assertions**, with PDO SQLite and sodium enabled (`output/reporting-sqlite-regression.xml`). PHPStan level 7 passes with a 512 MB analysis limit, and Pint passes. Frontend type checking, ESLint and production bundling pass. The main MyPOS database remains free of synthetic sales; populated screenshots use the separate acceptance merchant.

Local rollout: all reporting migrations through `2026_10_17_020156` are applied on XAMPP MariaDB. Historical backfill dry-run found zero records requiring work. The Demo merchant projection rebuilt successfully. The dedicated worker was restarted on build `source-ba48fc3407e59da7`; a CSV requested through the local Reports screen completed with matching start/completion build identity in `application_logs`.

Final browser acceptance passed in **1.8 minutes** on the production bundle against the isolated populated merchant: all 77 catalogue endpoints, revenue/profit reconciliation, filters, pagination, source lines, saved selections, real CSV download, light/dark themes and 390px mobile overflow. The test restores the development hot-file after completion. Evidence: `output/reporting-browser-final.txt` and `output/mypos-reports-live-{light,dark,mobile}.png`. The expected initial unauthorized request and the blocked development-only inline diagnostic script do not affect report functionality; there were no application page errors. All agreed delivery gates above are complete. Workflows explicitly deferred in the agreed boundaries remain outside this scope.

## Cold-query, concurrency and shared-control follow-up

The earlier single-product benchmark is historical evidence. This follow-up adds separate receipt and daily product/tax projections, an incremental live tail, consistent report/export read snapshots, shared dropdowns, contextual empty states and readable document references. Final measurements must identify their workload and result-cache behavior; a passing cached p95 is not a cold-load claim.

### Behavior delivered

- All application select controls, Export and the account menu use shared dropdown components and theme tokens. Opening one closes the previous menu. Outside click, Escape, arrows, Home/End, type-ahead, focus restoration, disabled options and required fields are supported. Popups inside dialogs remain interactive.
- Empty tables use the shared icon/title/help state. Report empties sit outside the horizontal table scroller; source-line empties use a document icon too.
- New sale, return, cash session, cart, exchange, purchasing, supplier payment, transfer and stocktake numbers follow `<module>-<YYYYMMDD>-<daily sequence>`, for example `SALE-20261017-00001`. Allocation is tenant/day/module scoped, transactional and locked against concurrent requests. Posted documents keep their existing references. Report download names include the report and selected dates.
- Receipt/product/tax/date reports read prepared summaries when their filters allow it. More detailed filters retain the authoritative ledger/fact path. Current-stock last-sale dates use daily facts plus the live tail.
- Refreshes append new facts and replace only affected store/business-date summaries (up to 32 scopes, otherwise all summaries). Full rebuilds remain available. Late-committing IDs and additional costs against old documents trigger the safe full rebuild path. Repeated refreshes do not duplicate totals.
- Each report and export reads a consistent snapshot across its watermark, totals and rows while a refresh commits. All six ledger marks are captured in one SQL statement, preventing mixed header/line counts when a till posts during a freshness check. Summary/projection/state replacements are atomic. Routine traffic queues refresh work rather than rebuilding the history within a request.

### Acceptance gates

- [x] Wider SQLite regression: 83 tests, 1,812 assertions (`output/reporting-current-sqlite.xml`).
- [x] Latest MariaDB report regression: 24 tests, 891 assertions (`output/reporting-current-mariadb.xml`).
- [x] Latest MySQL report regression: 24 tests, 891 assertions (`output/reporting-current-mysql.xml`, `output/reporting-marker-mysql.txt`).
- [x] PHPStan level 7, 512 MB analysis limit (`output/reporting-phpstan-current-final4.json`).
- [x] Pint; frontend type checking, ESLint, 41 unit tests and production bundling.
- [x] Populated browser/export acceptance on the final bundle (all 77 endpoints, fresh CSV, filters, drill-downs, saved selections, themes and mobile), plus all 37 app-wide browser scenarios.
- [x] Million-line result-cache-cold HTTP-kernel benchmark, 20 samples per case (`output/reporting-query-benchmark-1000000.json`, 224 assertions).
- [x] Diverse 100,000-line reconciliation and five-user concurrent benchmark, including a newly posted API sale and an incremental refresh (`output/reporting-real-usage-100000.json`, 324 assertions).

The diverse acceptance fixture contains eight stores, 80 products, 365 business dates, 500 customers, multiple tax rates, discounts, split tenders, explicit costs and a real partial return. Its concurrent readers use five separate owner accounts and independent PHP workers. Revenue, tax, discounts, sales including tax, COGS and gross profit are compared with independent signed header sums and the fixture's known unit costs. These are synthetic read workloads, not checkout throughput or a complete inventory audit.

Additional million-line diverse fixture expansion is optional and is retained at a committed checkpoint; it is not included in acceptance claims. No synthetic sales are inserted in the main MyPOS database.

Final measurements use the isolated MySQL instance on port 3307 with the retained SSD dataset at `C:/Temp/mypos-logging-mysql/data`, a 1 GB buffer pool, 1 GB redo capacity and binary logging disabled. The D: mechanical-disk copy retains the optional 480,000-line expansion checkpoint. The C: copy was retained after automatic approval review blocked its earlier removal; it is now the active test dataset. The temporary 2 GB loading allowance on D: was restored before measurements, and that server shut down cleanly. Main XAMPP MariaDB settings and business data are unchanged. Cold means an empty report-result cache, not an operating-system or disk cache reset. HTTP-kernel timings exclude network transport and worker startup.

### Final measured results

| Million-line report case | First request | Cold p95 (20 samples) | p95 target |
|---|---:|---:|---:|
| Product, 30 days | 0.988s | 0.917s | 2s |
| Receipts, 30 days | 1.131s | 1.519s | 2s |
| Tax, 30 days | 0.529s | 0.720s | 2s |
| Current stock | 0.402s | 0.825s | 2s |
| Annual product summary | 0.397s | 0.908s | 5s |

Every sample clears the report-result cache. The separate direct query took **0.549s**. First responses in this run were under 1.2 seconds; this does not reset the operating-system/disk cache or guarantee that time on other hardware. Evidence: `output/reporting-million-cold-delivery.txt` and `output/reporting-query-benchmark-1000000.json`.

The diverse fixture passed all **36 cold cases** and **40 requests across five simultaneous users**. Cold requests: observed p95 **0.688s**, maximum **1.830s**. Concurrent requests: observed p95 **0.760s**, maximum **3.085s** (a year-long report, within its five-second target). All requests returned 200 and reconciled; stock row counts and displayed quantities matched balances. All 40 concurrent document allocations were unique. The successful run used **52 MB peak parent PHP memory**. The dataset has 100,000 synthetic lines plus the original sale and six API live-sale checks (100,007 sale lines). This is a short five-user test, not a sustained production-capacity or network-throughput guarantee. Evidence: `output/reporting-real-100k-delivery-final.txt` and `output/reporting-real-usage-100000.json`.

Both final benchmark artifacts use the same query/projection/exporter SHA-256: `90f9ebd53ba91eb621094773d654c2f48dd4e21a98d0fda35f09b08c498b67fe`.

Preparing the diverse projection required a full rebuild taking **262.49s**. The final run's setup refresh took **0.502s**; its refresh during concurrent reads took **0.384s**. A first report immediately after the earlier full rebuild took 13.37s during cleanup; that failed diagnostic is not substituted for isolated read measurements. Schedule full historical rebuilds separately from peak trading. Requests continue to use the verified summaries/live tail or safe ledger fallback.

The Windows process-worker harness restores inherited `SystemRoot` before connecting to MySQL, resets its database connection to the captured test configuration, sends each worker only its eight assigned cases to stay below the Windows environment-size limit, and records child failures as structured results. Run the opt-in benchmark with explicit test DB environment variables (`mysql`, `127.0.0.1`, port `3307`, database/user `mypos_test`, the local test password), `APP_ENV=testing`, `RUN_REAL_REPORT_BENCHMARK=1`, `REAL_REPORT_LINES=100000`. Do not edit its PHP file during a run: Laravel serializes task closures using source locations.

Load diagnostics also exposed a pre-existing file-logging tap type mismatch. `RedactSensitiveLogs` now accepts Laravel's logger wrapper. The configured-channel regression verifies sensitive context is redacted without emergency fallback; nine operational logging tests and both focused redaction tests pass (`output/reporting-logging-regression.txt`, `output/reporting-redaction-regression.txt`).

The final report browser test passes on the production bundle in 2.0 minutes (`output/reporting-browser-current-final.txt`), including a newly requested 31-row CSV with a dated filename. All 37 app-wide browser scenarios pass (`output/reporting-shared-controls-browser-final.txt`). Visual evidence is `output/mypos-dropdown-{light,dark,mobile}.png`, `output/mypos-report-empty.png` and `output/mypos-reports-live-{light,dark,mobile}.png`. The development hot-file is restored after testing. Expected initial authentication and blocked development diagnostic-script messages do not represent application page errors.
Local rollout is complete: reporting migrations through `2026_10_17_034000` are applied on XAMPP MariaDB; the final migration check reports nothing pending. The Demo merchant projection rebuilt, and the dedicated reports worker restarted on build `source-89471bc26827c18d`. A new CSV requested from the live main screen completed successfully with matching `report.export.started` and `report.export.completed` build/operation records (`export:01a14878-5b53-7117-8902-85622ee2fa2e`). The main database still contains zero sales. The temporary test worker has been stopped. Final local UI evidence is `output/mypos-report-final-main.png`; populated report evidence uses the isolated acceptance merchant.
