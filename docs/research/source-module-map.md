# T001 source module map

This document groups every discovered application route family and relevant model family by business meaning. The exhaustive, statement-level inventory is [source-inventory-and-fingerprint-before.json](evidence/source-inventory-and-fingerprint-before.json); each entry there retains its repository-relative file and line. This map is the review layer that prevents route aliases and similarly named tables from being mistaken for equivalent behavior.

## Retail API

Primary route files: `routes/api.php`, `routes/web.php`, `routes/console.php`, and `routes/channels.php`. The API file combines current portal endpoints, legacy aliases, till synchronization, reports, integrations, and platform administration.

| Route/model family | Source surface | What exists | Target disposition / owner |
|---|---|---|---|
| Authentication, profile, password and user administration | Auth/profile/user controllers; `User` | Sanctum login and protected account routes | Adapt to target sessions, merchant scoping and fixed roles — T005, T007, T008 |
| Platform administration, applications, subscriptions and marketplace | Platform admin, apps, subscriptions, usage fees | SaaS catalogue, tenant subscriptions and status | Adapt only manual plans/admin scope — T009 |
| Stores, setup, registration, local setup and data routing | Store/setup/registration/data-sync routes; `Store`, `Setup`, `LocalSetup`, `Registration`, `DataSyncRouting` | Store records plus till/cloud addressing and source flags | Replace sync addressing with native merchant/store/register contracts — T007, T010 |
| Catalog | products, items, departments, categories, brands, classes, groups, UOM, labels, tax | Broad retail master data with aliases and legacy fields | Adapt concepts; redesign canonical product/variant/barcode/pack/tax schema — T011 |
| Prices and promotions | sale/future prices, discounts, coupons, mix-and-match, buy-N-get-free, fees | Multiple promotion record families and date/time restrictions | Adapt business cases into two frozen target promotion modes — T012 |
| Customers and notes | customers, notes, appointments | Customer profiles and auxiliary records | Adapt customer profile only; appointments are excluded — T013 |
| Employees, groups, permissions, shifts, holidays and attendance | employee/security/shift/holiday/attendance routes | Staff and permission records; workforce UI is mostly placeholder | Replace with fixed roles, attendance/rota/leave and payroll contracts — T008, T029, T030 |
| Vendors and vendor parts | vendors, vend-parts | Supplier and supplier-product records | Adapt — T017 |
| Purchase orders | `PoHeader`, `PoDetail` and controllers | Header/detail CRUD and integration dispatch | Adapt records; replace lifecycle and receiving — T017, T018 |
| Purchase returns | `PurchaseReturn`, `PurchaseReturnLine` | Draft/update/confirm tied to QuickBooks behavior | Replace with provider-independent stock and supplier-ledger workflow — T019 |
| Stock journal | `StockJournal` | CRUD/bulk CRUD and summaries; `TranType` values include IN/OUT/ADJ/TRF | Adapt as evidence; replace with immutable target movement ledger — T014 |
| Transfers | `TransferHeader`, `TransferDetail` | Header/detail CRUD, status field, restore/bulk endpoints | Replace with explicit dispatch/receive state machine — T016 |
| Stocktakes | `StockTakeHeader`, `StockTakeDetail` | CRUD, count fields and record tracking | Adapt fields; replace posting/reconciliation logic — T016 |
| Batches and expiry | no coherent operational route/model family | No verified first-class lifecycle | Newly design — T015 |
| Invoices, items, sales, tenders, tender types and transactions | separate resources: `Invoice`, `Item`, `Sale`, `Tender`, `TenderType`, `Transaction` | Independently writable transaction-shaped records | Replace with one atomic checkout command and derived read models — T021, T022 |
| Cash float, closeout and dashboard | cash float and closeout families | Float records and till-imported closeout aggregates | Adapt concepts; replace with native sessions and computed closeout — T020 |
| Voids and returns | `VoidInv`, store credit, integration refund dispatch | Void records and provider-specific refund paths | Replace with original-sale return/exchange state machine — T026 |
| Receipts, printers and card authorization | printers/types/mappings, print labels, card auth/trans | Configuration and imported/payment artifacts | Adapt printer concepts; manual external tenders only — T024 |
| Loyalty bridge | setup/status/enrolment and sync jobs/services | Entitlement and cross-service synchronization | Replace bridge with in-application loyalty module — T027, T028 |
| Reports | dashboard, sales, product, refund/void, transaction average, stock, accounting, financial/Z, workforce | Many query-backed reports plus aliases and some placeholders | Adapt report definitions only after target ledgers exist — T032 |
| Imports/exports | bulk import services, central export/general mappings, record tracker | Import utilities and legacy sync/export queues | Adapt validation ideas; retire till synchronization — T033 |
| External commerce | Shopify, WooCommerce, eBay, Linnworks | Connections, jobs, webhooks and order/refund sync | Deferred/out of current build; retain extension boundary — post-MVP |
| Accounting integrations | Xero and QuickBooks connections/mappings/logs | Contact, product, invoice, payment, PO and reconciliation sync | Out of initial scope; do not couple core posting to providers |
| Trade accounts and receivables | trade customers/invoices/tenders/credit journal, `AcctRecv` | Customer credit/accounting records | Excluded: no customer debt or on-account sales |
| Orders, layaway, appointments | order headers/details, appointment records | Historical/advance workflow artifacts | Excluded: no preorder, layaway or appointment module |
| Repairs, rentals and work orders | work orders and invoice fields/report columns | Historical specialty-retail artifacts | Excluded |
| Till/cloud synchronization | bulk sync, record tracker, cloud/till field mappers, terminal/source flags | Bidirectional ingestion and deletion orchestration | Retire in target; browser POS writes canonical commands directly |

### Retail portal screens

The 267 route paths and 391 view/page components are captured in the raw inventory. They cover administrator/setup, catalog, promotions, suppliers and purchasing, stock, customers, employees, reports, integrations and subscription areas. They are useful as navigation coverage, not proof of behavior.

Concrete placeholders include:

- Attendance, rota, leave, payroll, performance and employee roles use `FeatureComingSoon` in `src/views/employee/child/*/index.vue`.
- Stock changes, discrepancies and non-selling-stock report pages state “UI coming soon.”
- Banking report pages for float adjustments, petty cash, end of day, integrated card types, cashback and service charges are placeholder text.
- Support and general integrations pages show “Coming Soon”; non-Shopify integration tabs also do so.

These features are classified placeholder even when backend tables or route names exist.

## Hospitality API and integrated Vue portal

The 550 backend route statements and 176 frontend route paths are fully captured in the evidence manifest.

| Route/model family | What exists | Target disposition / owner |
|---|---|---|
| Authentication, users, sessions, subscriptions, marketplace and licensing | Cloud account and device/app licensing | Adapt only tenant/staff/manual-plan needs — T007–T010 |
| Stores and setup | store/setup/local configuration | Adapt store settings concepts — T007, T010 |
| Catalog, UOM, taxes and images | products/items/types/groups/tags/allergens/UOM/tax | Adapt general catalog/UOM/tax ideas; allergens are outside target need — T011 |
| Modifiers, meal deals, meals, tables, waiter/KDS | product modifiers, meal deal records, table groups/details, waiter/KDS licensing | Excluded in full: no restaurant production, table, kitchen or waiter workflows |
| Recipes, ingredients and preparation | no target analysis required | Explicitly excluded by the user; no target ticket |
| Reservations, queues, delivery and dispatch | Skedda bookings, delivery info/charges, order types | Explicitly excluded by the user; no target ticket |
| Customers and loyalty bridge | customer records, observers/jobs, loyalty API logs | Customer ideas adapt to T013; bridge retired in favor of integrated loyalty |
| Promotions and price groups | discounts, coupons, mix-and-match, BNGF, custom price groups, schedules | Adapt applicable retail concepts — T012 |
| Employees, security, shifts, holidays and attendance | model/API coverage; frontend workforce screens largely placeholder | Replace with target fixed roles/workforce modules — T008, T029, T030 |
| Invoices, tenders, transaction text and card transactions | cloud records populated by till payloads | Ingestion-only for checkout semantics; replace — T021, T022, T026 |
| Stock journals | cloud stock records and reports | Ingestion-only/partial; target immutable ledger — T014 |
| Closeout | many closeout and central-export aggregate tables | Mostly imported reporting data; target sessions compute from canonical sales — T020, T032 |
| Till/cloud ingestion | `RegisterTillDataOnCloudController`, send/edit/scheduler endpoints | Large explicit mapping layer for till payloads; retire |
| Reporting and dashboards | transaction, stock, accounting, financial and dashboard queries | Adapt report catalogue after target facts are defined — T032 |
| Printers and receipt templates | printer configuration/templates | Adapt layout/configuration concepts — T024 |
| Xero, QuickBooks, Sage, Shopify and webhooks | provider-specific integrations | Outside initial target; preserve future ports/adapters only |
| Xonder Pay and direct card artifacts | payment provider routes/settings | Excluded from initial manual-external-tender model |
| Tips, auto-tip and service charges | settings and closeout fields | Hospitality-specific and excluded |

Hospitality placeholders mirror the retail portal for attendance, rota, leave, payroll, performance and roles. Settings, notifications, support, integrations and several stock/banking reports also contain explicit coming-soon content. Dynamic pricing edit has an unimplemented TODO. These are not complete capabilities.

## Loyalty API

| Route/model family | What exists | Target disposition / owner |
|---|---|---|
| Authentication and business context | token auth; `EnsureBusinessContext`; `TenantContext` | Replace cross-database/source-platform identity with canonical `merchant_id` — T007, T008 |
| Programs and store assignment | `LoyaltyProgram`, pivot/service, CRUD | Simplify to one merchant-wide program contract unless later requirements demand more — T027 |
| Members and cards | members, QR codes, payment/member cards, import and lookup | Adapt member identity and limited lookup; exclude payment-card linking — T027, T028 |
| Points transactions | earn, redeem, refund/reverse, adjustments, expiration | Adapt transaction ledger, row locks and idempotency — T027 |
| Rewards, offers, rules and campaigns | configurable reward/rule/offer records and campaign service | Adapt only frozen reward/promotional scope — T027 |
| Products and external-source sync | loyalty products, hospitality/retail product services | Retire cross-database sync; use canonical product IDs — T027 |
| Public join | public identifier lookup, join/store list | Replace response and polling contract — T028 |
| POS/EPOS API | API-key lookup/earn/redeem and provider parity logging | Replace network bridge with internal application service invoked by checkout — T022, T027 |
| Visits and entitlements | visits, visit entitlements and reports | Excluded |
| Dashboard and reports | member/program/store/transaction reports | Adapt relevant loyalty reports — T027, T032 |
| Notifications/settings | admin notifications and business/user settings | Adapt only in-app operational alerts and target settings — T010, T034 |
| Internal synchronization and repair commands | source user/store/customer/product sync and repair utilities | Retire; use target migrations/imports when needed — T033 |

## Loyalty portal

The React portal has screens for login, dashboard, programs, members/member detail, stores, rewards, campaigns, visits, reports, settings, public join, join success and welcome back. React implementation is reference-only because the target frontend is Vue.

The public join path requires redesign. `Join.tsx:105` posts to `/public/join`; the result selects welcome-back or join-success (`Join.tsx:117-119`). `useJoinPolling.ts:5-23` then repeats the same POST every ten seconds to refresh member data. The target must use an idempotent join command followed by a limited read endpoint, with explicit DTO fields and rate limiting — T028.

Visits and tier defaults visible in the portal are excluded from the target loyalty scope.

## Cross-project aliases and collision rules

- `user_id` frequently means account owner or tenant in retail/hospitality; it must not become the target staff user foreign key by copying names.
- `store_id` may be a cloud primary key, a till identifier, a string, an integer, or an external-source ID. The target uses one canonical store key and explicit import/external mappings.
- `Invoice`, `Sale`, `Transaction`, `Tender`, `Trans`, and closeout records overlap but do not form one proven transaction boundary. Target sales are posted once and read through derived projections.
- Singular, plural, PascalCase and legacy table names coexist. Names are not normalized evidence of equivalence.
- Central-export and record-tracker tables describe synchronization, not operational source-of-truth entities.
- Hospitality and retail versions of similarly named records can have different columns and semantics. Each target aggregate gets one contract in T004.

## Screen-to-persistence traces

| User surface / trigger | Request/controller | Logic/persistence | Side effects | Finding |
|---|---|---|---|---|
| Retail portal purchase order | PO API resource → `PoHeaderController::store` | DB transaction creates `PoHeader` and nested details | record tracker and accounting dispatch hooks | Useful header/detail evidence; no verified receiving/valuation command |
| Retail stock journal form/import | `StockJournalController::store`/`bulkStore` | transaction creates journal rows | record tracker | Direct journal CRUD permits mutable entries; replace with domain-posted immutable movements |
| Retail invoice/sale/tender resources | separate controllers | independent `Invoice::create`, `Sale::create`, `Tender::create` | observers/integration dispatch vary by record | No atomic checkout boundary; replace |
| Retail purchase return | `PurchaseReturnController` | draft rows; confirm delegates to `QuickBooksPurchaseReturnService` | QuickBooks-oriented posting | Provider-specific partial implementation; replace |
| Hospitality till callback | `RegisterTillDataOnCloudController` | maps payload families in transactions | loyalty/integration/report data follows imported records | Ingestion-only; retire |
| Loyalty public join | React `Join` → `JoinController::join` | finds or creates member and returns data | success route polls same command | Implemented but unsafe contract shape; replace |
| Loyalty earn/redeem | POS/EPOS controllers → `PointsService` | DB transaction, member row lock, ledger insert and balance update | logs/campaign evaluation | Strong concurrency/idempotency pattern to adapt |
| Reports | Vue report route → report controllers/services/raw queries | queries source/imported tables | exports where present | Definitions are reference material; target reports must derive from target ledgers |

## Coverage statement

Every discovered backend route statement, frontend route path, model, controller, request, middleware, migration, observer, service, job, command, screen and test is present in the raw inventory. This semantic map assigns each route/model family to adapt, replace, retire, exclude, defer, or investigate under a named target ticket. Individual source paths not repeated in prose inherit the disposition of their family and remain addressable in the manifest.
