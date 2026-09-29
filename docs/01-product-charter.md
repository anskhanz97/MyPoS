# Product charter and authority

Document version: 1.1 — 2026-09-29. Status: agreed product direction; T001–T038 implemented in the working tree, with external launch gates still open.

## Purpose

Build a unified, multi-merchant, browser-based counter POS for Pakistan using Vue, Laravel and MySQL. Existing web projects in `C:\XEPOS` provide reference material. They are not a contract to reproduce their database, desktop architecture or every historical feature.

The ticket library remains the implementation and evidence history. The application has since been built through T038. Provider activation, production deployment, purchases and changes to XEPOS still require their own authority and evidence; XEPOS remains read-only reference material.

## Authority and interpretation

1. Later explicit user instructions override earlier decisions.
2. Confirmed answers in [the Q&A](02-conversation-and-decisions.md) and the exclusions below define product scope.
3. [Engineering defaults](03-architecture-and-contracts.md) are proposed implementation choices, clearly distinguished from the user's words. The research/freeze tickets validate them before application coding.
4. A source-code field, menu item, competitor capability or earlier broad research suggestion does not create a product requirement.
5. Ticket acceptance criteria are required. Do not silently omit them to finish a ticket quickly. Report a precise blocker if a real external dependency prevents validation.
6. This documentation is not a completed comprehensive source audit, security audit, hardware certification or legal opinion. Initial findings are recorded with their evidence level.

## Audience and primary operations

Platform administrators provision merchants and record SaaS subscription payments. Merchant owners manage stores, staff, settings and finances. Managers perform authorized operating functions and approvals. Cashiers use fast counter checkout. Customers can join loyalty and view a limited points/rewards page; they cannot place orders or redeem through that public page.

No retail/hospitality product distinction or entitlement split. The application provides the agreed capabilities under one roof. Feature configuration is explicit and audited where it changes business behaviour.

## Confirmed platform decisions

- Vue frontend, Laravel backend, MySQL authoritative transactional database.
- Windows Chrome/Edge tills first, with responsive back-office pages.
- Cash checkout during internet outages; a local printing helper is acceptable.
- Modernize useful source code selectively. New merchants first. No full historical migration at launch.
- Pakistan nationwide fiscal research: federal and relevant provincial/territorial applicability must be distinguished.
- English UI and receipt templates. Preserve Unicode input; an English-only UI is not a reason to corrupt names.
- Two complete visual themes: bright/light and dark, switched easily without reload, losing a cart, or changing business settings.

## Store modes

Owner chooses shared or independent operational data during onboarding; the choice locks once configured. Later conversion is not a normal toggle and requires a separately scoped migration.

Shared mode: merchant-level products and operational customers, with local stock, prices and availability. Independent mode: separate store catalogs and operational customer records. Merchant-wide loyalty exists in both modes; keep loyalty identity distinct from store customer records. Central administration and consolidated reporting remain available in both.

## Included modules

- Merchant provisioning, stores, registers, fixed roles and administrator-managed plans/subscriptions.
- Standard products, variants, weighted goods, pack conversions, fixed bundles and selectable extras.
- Product inventory, optional batches/expiry, stocktakes, transfers and ordinary audited adjustments.
- Purchase orders, partial receiving, purchase returns, supplier balances and supplier payment records.
- Counter carts, scanning, suspended carts, full settlement, split tenders and sale lookup.
- Cash and externally completed card/wallet/bank payment records; cashier confirmation is not provider verification.
- Manual-discount mode and advanced-promotion mode; merchant default with store overrides.
- Sale-linked partial/full returns and exchanges; ordinary configurable refund window up to 30 days, proposed default 7, manager late-return exception.
- Customers, merchant-wide loyalty points/rewards, expiry, refund reversals and limited public join/lookup portal.
- Register cash sessions, floats, paid-in/out, blind counts, variances and closeout.
- Staff identities, PIN switching, attendance, rota, leave and operational payroll.
- Business expenses and settlement/reconciliation records; exports rather than full general-ledger accounting.
- Standard reports, saved filters, drill-down and exports.
- USB keyboard-style scanners, thermal printing, cash drawers, PDF receipts and optional second customer display.
- Email receipts, in-app alerts, catalog/customer/opening-stock imports and documented exports.
- Offline cash sale durability, recovery, replay, monitoring and deployment operations.

## Explicit exclusions

Do not implement or research as candidate app features: recipes, ingredient consumption, substitutions, preparation batches, yield/portion accounting, ingredient costing and wastage-management workflows. Ordinary product-level stock corrections remain included.

Do not implement: reservations, waitlists, seating planning, table bills/service, kitchen printers/displays, booking deposits, no-shows, delivery zones, dispatch/tracking, collection-slot scheduling or customer ordering.

Do not investigate missing C# source or require desktop manuals, examples, demonstrations or legacy-rule reconstruction. Design absent web functionality from confirmed requirements and primary-source research.

Also excluded from launch: quotations, preorders, deposits/layaway, customer debt/credit sales, repairs/rentals, non-stock service jobs, kiosks, an online storefront, serial-number tracking, directly connected scales, gift cards, store-credit balances, loyalty tiers/visit programmes/payment-card-linked membership, custom dashboard/report builders, full statutory payroll automation, full accounting ledger, automatic SaaS billing, automatic payment acquisition and live accounting/commerce connectors.

SMS/WhatsApp were delivered as an optional, merchant-controlled extension in T038 and remain outside the launch dependency chain. Core customer-display support is included and optional per register.

## Completion principles

Sales, refunds, payroll and inventory require state transitions and trustworthy records, not CRUD forms alone. Preserve historical calculations. Enforce tenant boundaries server-side. Treat external outcomes and physical printing honestly: submitting a job is not proof that a receipt printed or that a bank paid.

No feature is complete until its data rules, screens, permissions, failures and tests work together. Hardware and legal/provider claims remain unverified until supported by the relevant evidence.
