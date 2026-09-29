# T001 source capability register

## Classification

- **Implemented:** substantive source path and persistence/query logic exist. Static evidence only.
- **Partial:** meaningful code exists but an operational state, UI, transaction boundary, or provider-neutral path is absent.
- **Placeholder:** the routed screen explicitly says coming soon or only renders scaffolding.
- **Ingestion-only:** behavior records or reports data produced by a separate till; it does not implement the web command.
- **Excluded:** source feature is outside the agreed product scope.
- **Unverified:** artifacts exist, but safe static inspection cannot establish working behavior.

Recommendations use **adapt**, **replace**, **retire**, **defer**, or **exclude**. Target tickets own the actual contract.

## Capability register

| Capability | Source status | Evidence and finding | Target decision | Owner |
|---|---|---|---|---|
| Account authentication | Implemented | Auth routes/controllers in all APIs; protected route groups | Adapt to target auth and merchant membership | T005, T008 |
| Merchant tenancy | Partial | Retail/hospitality often use account-owner `user_id`; loyalty adds business/external IDs | Replace with canonical merchant boundary | T007 |
| Store selection and authorization | Partial | Retail header/query/body resolver and fallback; hospitality active/fallback store resolver | Replace mutation context; adapt authorized read filters | T007, T010 |
| Fixed roles and approvals | Partial | Security group/permission tables exist; workforce roles UI is placeholder | Replace with fixed target roles and approval events | T008 |
| SaaS plans/platform administration | Implemented | Apps, subscriptions, pricing and admin routes | Adapt only manual subscription scope | T009 |
| Register provisioning | Partial | registration/local setup/device and license artifacts | Replace till sync identity with web register provisioning | T010 |
| Settings | Implemented/partial | setup records and settings APIs; hospitality settings UI is placeholder | Adapt required settings with versioning | T010 |
| Product catalogue | Implemented | products/items/categories/departments/UOM/tax routes and models | Adapt vocabulary into canonical catalog | T011 |
| Variants and multiple barcodes | Partial | product/item/SKU structures and tags exist, but no single clear target model | Replace with explicit product/variant/barcode schema | T011 |
| Pack conversions | Partial | UOM and break-pack fields exist | Adapt concepts; newly define exact arithmetic | T011 |
| Label printing | Implemented/partial | print-label records and portal screens | Adapt after barcode/product contract | T011, T024 |
| Price versions | Implemented/partial | sale and future price header/detail records | Replace with effective-dated versioned prices | T012 |
| Promotions | Implemented | discount, coupon, mix-and-match and BNGF families | Collapse into two agreed target modes | T012 |
| Customer profiles | Implemented | customer CRUD in retail/hospitality | Adapt with target privacy and store-mode rules | T013 |
| Customer debt/on-account sales | Implemented in trade/receivable artifacts | Trade customer/credit journal/acct-recv models | Excluded completely | — |
| Inventory journal | Implemented/partial | retail journal CRUD/bulk and summaries (`StockJournalController:112-179,445-538`) | Replace with immutable domain-posted ledger | T014 |
| Batch and expiry | Unverified/absent as coherent flow | No first-class end-to-end family found | Newly design | T015 |
| Transfers | Partial | transfer header/detail CRUD and status; no verified dispatch/receive posting | Replace with explicit state machine | T016 |
| Stocktake | Partial | header/detail records and CRUD; no verified immutable reconciliation posting | Adapt count fields; replace posting | T016 |
| Supplier management | Implemented | vendor/vendor-part routes and models | Adapt | T017 |
| Purchase orders | Partial | atomic header/details creation; accounting dispatch; no native receiving command found | Adapt order structure; replace lifecycle | T017 |
| Partial receiving | Unverified/absent | “received” fields/integration conditions exist, but no verified receipt aggregate/posting path | Newly design | T018 |
| Moving weighted-average cost | Unverified/absent | product cost helpers exist; no verified receipt-driven valuation ledger | Newly design | T018 |
| Purchase returns | Partial | draft/confirm exists but confirm is QuickBooks service driven | Replace provider-neutral stock/supplier posting | T019 |
| Supplier balances/payments | Unverified/partial | accounting and payable-like artifacts, no canonical operational supplier ledger | Newly design | T019 |
| Cash session/opening float | Partial | cash float and closeout records exist, much data till-produced | Replace with native session state machine | T020 |
| Cash movements and closeout | Ingestion-only/partial | closeout aggregate/table families and reports | Replace with computation from posted target facts | T020 |
| Cashier checkout UI | Absent from audited portals | cloud portals administer/report; desktop till UI is unavailable and out of investigation scope | Newly design Vue POS | T021 |
| Barcode scanning | Absent as web checkout path | catalog barcodes exist; no audited browser scan workflow | Newly design keyboard-wedge flow | T021 |
| Cart pricing/discount authorization | Partial | promotion logic exists outside a target checkout boundary | Replace with deterministic pricing service | T012, T021 |
| Quotes/preorders/layaway | Historical order/layaway artifacts | order tables and closeout columns | Excluded completely | — |
| Atomic sale finalization | Absent | invoice, sale and tender controllers independently create/update records (`InvoiceController:93-234`; `SaleController:83-140`; `TenderController:86-133`) | Newly design one transactionally atomic command | T022 |
| Fully settled split tenders | Partial | multiple tender records can share transaction number; no verified invariant prevents imbalance | Replace with enforced settlement invariant | T022 |
| Cash and manual external tenders | Partial | cash/card/tender types and provider artifacts exist | Adapt cash/manual reference only; no direct processing | T022 |
| Offline checkout | Absent | sync machinery is till/cloud transport, not browser durable offline posting | Newly design PWA cash-only queue and reconciliation | T023 |
| Receipt rendering/printing | Partial | printer mappings/templates and invoice records exist | Adapt layouts; build thermal/PDF/helper paths | T024 |
| Cash drawer | Unverified | port/printer artifacts exist, no safe hardware verification | Newly define helper command and simulated contract | T024 |
| Customer display | Absent as target web feature | no relevant complete browser second-screen workflow found | Newly design optional display | T025 |
| Sale returns/voids | Partial | void records/controllers and integration-specific refund dispatchers | Replace with original-sale return and reversal ledger | T026 |
| Exchanges | Unverified/absent as atomic workflow | no coherent linked return-plus-sale transaction found | Newly design | T026 |
| Loyalty enrolment/member lookup | Implemented | member/public/POS lookup and join paths | Adapt identity; reduce exposed DTO | T027, T028 |
| Loyalty earn/redeem | Implemented | `PointsService` uses transactions, row locks and idempotency (`:725-777`, `:891-952`) | Adapt concurrency/idempotency pattern | T027 |
| Loyalty refund reversal | Implemented/partial | EPOS refund/reverse paths and transaction events | Adapt into sale-return orchestration | T026, T027 |
| Loyalty balance repair | Implemented | `LoyaltyBalanceService` transaction plus `lockForUpdate` (`:54-56`) | Adapt as reconciliation control | T027 |
| Loyalty tiers | Implemented in portal/program configuration | tier defaults and program configuration | Excluded from initial scope | — |
| Loyalty visits | Implemented | visit routes/models/entitlements | Excluded completely | — |
| Payment-card member linking | Implemented | member cards and hashed lookup | Excluded completely | — |
| Public member join | Implemented but contract is unsafe for target | join returns existing/new member state; success pages repeat POST every 10 seconds | Replace with idempotent command plus limited GET | T028 |
| Attendance | Placeholder/partial | model/API artifacts; retail and hospitality UI uses `FeatureComingSoon` | Newly design end-to-end | T029 |
| Rota | Placeholder | both portals use `FeatureComingSoon` | Newly design | T029 |
| Leave | Placeholder | both portals use `FeatureComingSoon` | Newly design | T029 |
| Employee performance | Placeholder | both portals use `FeatureComingSoon` | Excluded unless later separately approved | — |
| Payroll | Placeholder | both portals use `FeatureComingSoon`; no complete payroll lifecycle | Newly design operational payroll only | T030 |
| Expenses | Partial/unverified | closeout/acct records and placeholder petty-cash reports | Newly design operational expenses | T031 |
| Provider settlements | Partial | card/accounting reconciliation code exists but initial tenders are manual | Adapt provider-neutral records only | T031 |
| Sales reporting | Implemented/partial | query-heavy sales/product/refund/average reports | Adapt definitions; rebuild on target facts | T032 |
| Stock reporting | Implemented/partial | stock reports exist; several UI pages are coming soon | Adapt verified measures; build complete UI | T032 |
| Financial/Z reporting | Implemented/ingestion-only | closeout and financial queries often read imported aggregates | Replace with target-derived reports | T020, T032 |
| Workforce reporting | Partial/placeholder | route/query artifacts; source comments and UI lack complete data path | Rebuild after workforce implementation | T029, T030, T032 |
| Imports | Implemented/partial | bulk catalog/vendor and loyalty member import services | Adapt validation/staging ideas | T033 |
| Exports | Implemented/partial | report/export and general-mapping artifacts | Adapt portable target exports | T033 |
| Email receipts | Partial/unverified | notification/mail infrastructure exists across apps, no complete target receipt flow | Newly integrate after receipts | T034 |
| In-app notifications | Implemented/partial | loyalty admin notifications; hospitality screen placeholder | Adapt operational alerts only | T034 |
| SMS/WhatsApp | No approved initial implementation in the source baseline | not material to audited core | Implemented as an optional merchant-controlled extension | T038 |
| Pakistan fiscal adapters | Absent as verified target integration | source deployments and provider artifacts do not prove current legal applicability | Research then adapter ports | T003, T035 |
| Audit log | Partial | record tracker, API logs and application logs | Replace with actor/context/domain audit events | T004, T036 |
| Idempotency | Strong in loyalty, uneven elsewhere | loyalty reference/idempotency helpers; separate retail resource writes lack common key | Adapt as cross-cutting target contract | T004, T022, T023, T027 |
| Jobs/retries | Implemented | many provider/sync jobs | Adapt queue mechanics; retire source synchronization jobs | T005, T036 |
| Observability/recovery | Partial | logs and reconciliation commands exist, fragmented per app | Replace with unified operations controls | T036 |
| Shopify/WooCommerce/eBay/Linnworks | Implemented | connector routes, services, jobs, mappings | Defer outside initial scope | post-MVP |
| Xero/QuickBooks/Sage | Implemented/partial | accounting connector suites and reconciliation | Defer; core must remain provider-neutral | post-MVP |
| Hospitality tables/KDS/waiter | Implemented/partial | table, waiter/KDS, menu/meal paths | Excluded completely | — |
| Recipes/ingredients/preparation/waste | Source relevance intentionally not investigated | user removed this feature from scope | Excluded completely | — |
| Reservations/waitlists/delivery/dispatch | Source has Skedda/delivery artifacts | user removed these features from scope | Excluded completely | — |
| Direct scale integration | No approved target path | source does not change decision | Excluded completely | — |
| Serials, repairs and rentals | Historical source artifacts | work orders, repair/rental fields and reports | Excluded completely | — |
| Till/cloud ingestion | Implemented | large hospitality register-data controller; retail sync/mappers/record tracker | Retire completely; target web POS is the system of record | — |
| Missing C# rule recovery | Not performed | expressly removed from the work | Excluded completely | — |

## Detailed operational traces

### Purchase ordering and receiving

`PoHeaderController::store` wraps header and nested detail creation in a database transaction (`api-retail-project/app/Http/Controllers/API/PoHeaderController.php:65-120`). The same controller contains bulk paths and till field mapping. It can compute remaining return quantities from confirmed return lines (`:261-277`). No clear receipt aggregate, partial receipt command, inventory posting, cost-layer update and supplier-balance posting were found as one operation. The target may adapt supplier/PO field vocabulary, but T018 must create receiving and moving-average costing from first principles.

### Stock movement

`StockJournalController::store` and `bulkStore` use transactions and record tracking, while update and delete are also exposed (`api-retail-project/app/Http/Controllers/API/StockJournalController.php:112-179,214-312,445-538`). Summary logic groups `IN`, `OUT`, `ADJ` and `TRF` quantities (`:395-419`). A financial/inventory ledger that can be edited or deleted is incompatible with the target invariant. T014 will post immutable movements and use compensating entries.

Transfer creation is simple header CRUD (`TransferHeaderController.php:53-74`) with a short status field; stocktake stores header records in transactions (`StockTakeHeaderController.php:39-62`). Neither proves an atomic, balanced source/destination posting. T016 owns those state machines.

### Sale and invoice writing

Retail invoice, sale and tender resources create their own models. A caller can write them independently, and later update tender amount or tender type. This does not guarantee that lines, tax, inventory, tenders, receipt number, loyalty and audit data commit together. T022 must implement a single sale-posting application command with idempotency, authorization, register-session validation and balance checks inside one database transaction.

### Refunds

Retail has `VoidInvController` transactions and connector-specific refund dispatch. Purchase return confirmation is explicitly delegated to `QuickBooksPurchaseReturnService`. Loyalty has refund/reverse paths. These fragments inform field mapping and reversal events, but no one source workflow is the target contract. T026 coordinates sale return, tender reversal record, inventory movement and T027 loyalty reversal by original sale/line.

### Loyalty

The most reusable implementation pattern is the loyalty transaction service: it acquires a member row lock, creates a ledger transaction under a database transaction and applies an idempotency key. `LoyaltyBalanceService` also locks and repairs drift. The target should keep an append-only loyalty ledger and cached balance, but remove cross-database tenant resolution, visits, tiers, payment-card linking and source-system synchronization.

### Reporting

The source report catalogue is broad, but it mixes direct transactional queries, imported closeout aggregates, aliases, raw SQL and placeholder screens. The target report definitions must name authoritative tables, date/time basis, filters, tax treatment, return sign and store-mode visibility. A source chart or route name is never sufficient evidence for a metric formula.

## Unresolved facts that remain explicit

- Runtime correctness and deployed schema versions were not tested.
- Provider calls, hardware behavior and source observer ordering remain unverified.
- No source path proves batch/expiry, web offline checkout, atomic settlement, moving-average receiving or exchanges to the target standard.
- No effort will be spent recovering behavior that exists only in unavailable desktop C# code.

These are design inputs for later tickets, not blockers to T001 completion.
