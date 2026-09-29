# T001 source schema map

## Scope

This is a static reconciliation of migration history and model assumptions. It is not a promise that the source migrations replay cleanly. The exhaustive migration-to-table extraction is stored in [the source inventory](evidence/source-inventory-and-fingerprint-before.json).

## Migration inventory

| Project | Migration files | Unique created tables | Unique altered tables | Notable history condition |
|---|---:|---:|---:|---|
| Retail API | 257 | 145 | 63 | Seven migration filenames end in `.php.php`; historical naming and later alignment migrations require a fresh replay test before reuse |
| Hospitality | 259 | 127 | 73 | `central_export_close_out_report_tender` is created twice in extracted history; legacy and central-export duplicates coexist |
| Loyalty API | 46 | 26 | 9 | Multiple later migrations evolve program/member/transaction scope and external integration fields |
| Retail portal | 0 | 0 | 0 | API-owned persistence |
| Loyalty portal | 0 | 0 | 0 | API-owned persistence |

### Retail table families

- Identity and SaaS: users, sessions, tokens, apps, subscriptions, registrations, setup and usage fees.
- Tenancy and synchronization: stores, local setup, data-sync routing, record tracker, API logs, XE Connect logs/transactions and central export mappings.
- Catalog: products, items, departments, categories, brands, classes, groups, item groups, UOM, tax headers/details/mappings, tags and labels.
- Pricing/promotion: sale/future prices, discounts and restrictions/dates/times, coupons, mix-and-match, buy-N-get-free, delivery charges and fees.
- Parties/workforce: customers, vendors/vendor parts, employees, security groups/permissions, shifts, holidays and attendance.
- Inventory/procurement: stock journal, stocktake header/details, transfer header/details, PO header/details and purchase return header/lines.
- Selling/cash: invoices, invoice items, sales, tenders/types, transactions, void invoices, card authorization/transactions, cash float, closeout and dashboard records.
- Specialty/historical: orders, appointments, work orders, trade accounts/receivables, repairs/rentals/layaway-related closeout fields and gift/store credit.
- Integrations: Xero, QuickBooks, Shopify, WooCommerce, eBay and Linnworks connection/mapping/log tables.

### Hospitality table families

- Identity/SaaS and stores; catalog/taxes/UOM; customer/workforce/security; prices/promotions; invoice/tender/transaction/card; stock and closeout.
- Restaurant-only families: allergens, product modifiers/schedules, product types, meal deals/periods, tables, delivery and tips. These are excluded from the target.
- Ingestion/report duplication: operational-looking tables coexist with `central_export_*` and closeout breakup/aggregate tables. They are read models or imported artifacts, not a target write model.
- Provider families: Xero, Sage, QuickBooks, Shopify, Skedda, webhooks and Xonder Pay. These do not belong in the initial core schema.

### Loyalty table families

- Identity/settings: users, sessions/tokens, user settings, business settings, stores.
- Loyalty: programs, program-store pivot, members, QR codes, member cards, transactions, rewards, offers, rules and products.
- Excluded extensions: visits and visit entitlements.
- Operations: EPOS transaction logs, admin notifications, jobs/cache.

## Tenant and store identifiers

The source projects do not have one stable tenant key.

| Source | Observed rule | Risk | Target rule |
|---|---|---|---|
| Retail | authenticated `user_id` commonly scopes merchant-owned records; `store_id` is often string-like and can arrive in query, `X-Store-ID`, body aliases or till IDs (`HasStoreFilter.php:12-40`) | staff identity and merchant ownership can be conflated; explicit store can silently fall back to first store for portal requests (`:43-74,131-137`) | every owned aggregate carries canonical `merchant_id`; store-bound aggregates also carry canonical `store_id`; authorization validates both |
| Retail till sync | till store values are normalized through `StoreIdResolver`, with `_source=till` or `X-Terminal-ID` changing behavior (`HasStoreFilter.php:142-168`) | external and internal IDs share a field boundary | external IDs live in mapping/import metadata, never canonical foreign keys |
| Hospitality | account owner is obtained from the user, requested stores are checked against `stores.user_id`, then several active/fallback selections are attempted (`StoreContextResolver.php:24-70`) | an omitted or invalid store can select another store, unsuitable for mutation commands | mutation commands require an explicit authorized store/register context; reads declare merchant-wide or store-scoped behavior |
| Loyalty | `business_id`, `external_user_id`, source `user_id`, platform and cross-database store IDs coexist (`TenantContext.php:38-59,70-127`) | cross-database identity drift and platform-specific fallbacks | loyalty is in the same target database and always scoped by canonical `merchant_id`; optional earning store remains canonical `store_id` |
| Loyalty middleware | authenticated user `business_id` overrides or header supplies context (`EnsureBusinessContext.php:17-34`) | header-derived tenant input can be trusted too early | authenticated membership establishes tenant; headers only select among authorized contexts |

The target’s onboarding choice for shared versus independent operational store data affects record visibility, not primary-key meaning. It is immutable after setup. Loyalty remains merchant-wide in both modes.

## Money and quantity representation

Source migrations commonly use MySQL decimals, but precision varies by generation and domain:

- Retail legacy selling/closeout values commonly use `decimal(15,3)` or `decimal(18,3)`; purchase and return additions use four decimal places.
- Retail order quantities include four decimal places; stocktake fields often use three.
- Hospitality closeout money commonly uses three decimal places; SaaS billing models use two.
- Loyalty monetary fields generally use two decimal places; points are integer-like while some offer configuration is cast to float.

Model casts are inconsistent with the columns. Retail contains many `float` casts for money, including `AcctRecv.amount`, `CashFloat.cash_float`, card amounts and closeout totals, even while corresponding migrations use decimal. Newer retail purchase models use `decimal:4`. Hospitality models declare few decimal casts, leaving many values to default hydration. Loyalty has `decimal:2` monetary casts but `LoyaltyOffer.bonus_value` uses float.

Target contracts must therefore be newly frozen in T004:

- Store money in integer minor units for PKR-facing transactional values where fractions are not permitted by the business rule, or in explicit fixed-point decimal columns where tax/cost calculations need sub-rupee precision.
- Never use binary floating point for persisted or authoritative money.
- Use explicit fixed-point quantities with a documented scale that supports units and pack conversion.
- Centralize rounding order and persist tax/discount calculation snapshots on sale lines.
- Convert imported source values through validated decimal strings and reject overflow or excess precision rather than silently coercing.

## Source-to-target aggregate mapping

| Target aggregate | Source evidence | Recommendation |
|---|---|---|
| Merchant, store, staff membership | users/stores plus loyalty business identity | Replace names and relationships with explicit merchant ownership and membership |
| Register and session | registration, local setup, cash float, closeout | Replace with provisioned register plus open/close session state machine |
| Product, variant, barcode, pack | products/items/UOM/vendor parts | Adapt field vocabulary; normalize identifiers and conversions |
| Price list and promotion | sale/future price, discount/coupon/mix/BNGF families | Replace with versioned price and two target promotion modes |
| Customer | customers and loyalty members | Adapt into customer plus merchant-wide loyalty identity; avoid duplicate customer/member PII |
| Inventory movement and balance | stock journal, stocktake, transfer records | Replace with immutable ledger and transactional balance projection |
| Batch/expiry | no coherent source aggregate | New design |
| Supplier, PO, receipt, supplier balance | vendors, PO, purchase-return records | Adapt supplier/PO vocabulary; newly design receipt, costing and supplier ledger |
| Sale, lines, tenders, returns | invoice/item/sale/tender/transaction/void records | Replace with atomic sale/return posting and immutable financial facts |
| Loyalty ledger | loyalty transactions/members/rewards | Adapt locking/idempotency; simplify scope and remove source-platform coupling |
| Workforce/payroll | employees/attendance/shifts/holidays plus placeholder UI | New target workflows using limited field evidence |
| Expense and settlements | closeout/acct-recv/provider records | New provider-neutral operational ledger |
| Reports | closeout/dashboard/report queries | Rebuild from target facts; do not migrate imported aggregate tables as sources of truth |

## Migration defects and cautions

- Retail’s seven `.php.php` filenames may not be discovered by normal Laravel migration loading. Their tables appear in the static inventory but cannot be assumed to exist in every deployed database.
- Retail includes a migration whose rollback changes `items.SKU` to decimal and `Price` to integer (`2026_07_23_171900_alter_items_sku_string_and_price_decimal.php:18,25-26`). This is historical evidence, not a contract to reproduce.
- Hospitality has a duplicate extracted create for `central_export_close_out_report_tender`; migration ordering and conditional guards require a clean MySQL replay to establish actual behavior.
- Both main APIs retain mixed naming conventions and pluralization. Target migrations use one naming standard and foreign keys with explicit constraints.
- Source soft deletes, hard-delete synchronization, and record tracker behavior vary. Target posted sales, payments, loyalty entries and inventory movements are immutable and corrected through reversals.
- Application-level ownership filters cannot substitute for database foreign keys and policy checks. T004 must define composite/unique constraints for tenant-safe references and idempotency.

## Facts deliberately unresolved at T001

- Whether each historical source migration replays successfully on its intended MySQL version.
- Which production databases contain all late migrations or locally patched columns.
- The exact deployed precision and collation for tables altered outside repository history.
- Runtime observer ordering and provider responses.
- Till-only transaction rules absent from these repositories.

None blocks the target. T004 creates canonical contracts; T005 runs them on an isolated MySQL test database. No source database is needed or authorized.
