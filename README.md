# MyPoS project memory

Last reconciled with the working tree and the complete `docs/` library on 2026-10-04.

## What this project is

MyPoS is a multi-merchant, browser-based point-of-sale and operations platform for Pakistan. It replaces the old till-to-cloud split with one Laravel application: the browser is the till and the same server owns back-office workflows, reporting and integrations. It supports retail and hospitality-style product selling under one feature set, while recipes/kitchen production, reservations and delivery operations are explicitly excluded.

The stack is Laravel 13/PHP 8.3, Vue 3/TypeScript, Tailwind CSS 4 and MySQL 8.4, with a database queue and IndexedDB for bounded offline cash sales. The interface has complete bright and dark copper themes that switch without a reload or loss of active work. Management uses an expanded-by-default two-stage navigation shell: a compact module rail opens a contextual page panel, which can be collapsed globally.

## Current state

Tickets T001–T038 are implemented in the current working tree. T037 completed broad local acceptance; T038 subsequently added the merchant integration hub and optional SMS/WhatsApp receipt delivery. The repository has not yet received its first immutable Git commit, and the application is not approved for live merchant rollout.

The current uncommitted UI pass replaces the former green theme with copper and neutral surfaces, moves management page navigation into the global two-stage sidebar, and lazy-loads feature previews. The initial production JavaScript entry is approximately 132 KB instead of 232 KB before gzip. Local Vite is pinned to `127.0.0.1:5173`, with a local-only Content Security Policy allowance. The repository CI workflow has been removed by explicit request; verification is currently local.

Implemented capabilities include:

- Platform administration, merchants, plans, stores, registers, devices and versioned settings.
- Secure authentication, fixed owner/manager/cashier roles, PIN switching and scoped approvals.
- Catalog, variants, barcodes, weighted labels, units, bundles, extras, prices, taxes and promotions.
- Customers, merchant-wide loyalty, rewards and limited public join/lookup.
- Inventory, batches/expiry, transfers, stocktakes, purchasing, receiving, moving-average cost and supplier balances.
- Cash sessions, checkout, immutable sales/tenders/receipts, split payments, returns and exchanges.
- Bounded offline cash checkout with durable reconciliation and conflict recovery.
- Thermal/PDF receipts, print-helper and drawer protocols, and optional customer display.
- Workforce, attendance, rota, leave, operational payroll, expenses and settlement evidence.
- Standard reports, imports/exports, in-app alerts and durable receipt delivery.
- FBR DI v1.12 sandbox adapter framework, with production/provincial gates closed.
- An integration hub for SMTP, Meta WhatsApp, Twilio SMS, Google Drive, Microsoft OneDrive, Stripe configuration, and manual bank/Raast/Easypaisa/JazzCash destinations.

## Integration rules

Merchant owners configure integrations inside the application. Provider credentials are encrypted at rest, never returned by APIs, and may be preserved during updates. Each provider stays disabled until required configuration is valid. Staff receive only safe active-capability metadata.

SMS and WhatsApp receipt messages are optional. Delivery is idempotent, respects opt-out and retains provider outcomes without reposting a sale. Meta and Twilio callbacks authenticate and deduplicate. Sandbox transports contact no provider. Stripe setup and credential checking do not collect payments; checkout records cash and staff-confirmed external tenders only.

## Non-negotiable domain rules

- Every merchant-owned read/write is tenant scoped; store access is derived server-side.
- Posted sales, returns, inventory, cash, payroll and supplier facts are immutable; corrections are linked append-only records.
- Currency and quantities use fixed decimal arithmetic, never binary floating point.
- Retriable commands use tenant/caller-scoped idempotency keys and canonical payload fingerprints.
- External work occurs after the authoritative database commit through durable delivery/outbox records.
- A sale posts sale, stock, valuation, cash, receipt, audit and outbox effects atomically.
- Manual external-payment references are staff evidence, not provider authorization or settlement proof.
- Offline checkout is cash-only and bounded by signed device/configuration snapshots; conflicts stay visible.
- Theme changes, logout and updates must not erase unresolved offline evidence.
- Secrets and private customer/payroll data must not enter logs, callbacks, exports or support diagnostics.

## Explicit exclusions

Do not add recipes/ingredients/preparation/yield/wastage; reservations/waitlists/table/kitchen/delivery operations; reconstruction of missing C# rules; customer debt/layaway/advance-sale liabilities; serial inventory; direct scale connectivity; direct card acquisition; a full accounting ledger; statutory payroll filing; or broad customer ordering unless the user explicitly opens new scope.

`C:\XEPOS` and `C:\Tasmiya Enterprise project` are read-only references. Never copy their credentials, run configured integrations or modify their databases.

## Repository map

- `app/` — Laravel domain actions, HTTP controllers, jobs, models, policies and services.
- `database/migrations/` — schema history and tenant/posting constraints.
- `resources/js/` — Vue screens, themes and POS/offline browser behavior.
- `routes/` — versioned API and application routes.
- `tests/` — PHP, Vitest and Playwright coverage.
- `docs/` — decisions, contracts, research, ticket evidence, operations and release verification.
- `scripts/` — documentation and operational verification helpers.

Start at [docs/README.md](docs/README.md). See [ticket status](docs/10-ticket-status.md), [open release gates](docs/verification/known-limitations.md) and the [integration reference](docs/frontend/integrations.md).

## Local setup

Prerequisites: PHP 8.3+, Composer 2.10+, Node.js 24 LTS, npm 12+ and MySQL 8.4. Copy `.env.example` to `.env`, configure a dedicated local database, then run:

```powershell
composer install
npm ci
php artisan key:generate
php artisan app:environment-check
php artisan migrate
composer run dev
```

Production uses separately supervised web, worker and scheduler processes. Locally, `composer run dev` starts Laravel, the queue listener, log viewer and Vite. Exercise scheduling with `php artisan schedule:work`.

When running the two development processes separately, use `php artisan serve` and `npm run dev`, then open `http://127.0.0.1:8000`. Keep `APP_URL` and `VITE_DEV_SERVER_URL` on the matching loopback hosts shown in `.env.example`; mixing `localhost`, `127.0.0.1` and `[::1]` can prevent Vite assets from loading under the development security policy.

## Verification

```powershell
vendor/bin/pint --format agent
vendor/bin/phpstan analyse
php artisan test --compact
npm run lint
npm run typecheck
npm test
npm run build
npx playwright install chromium
npm run test:browser
python scripts/check-docs.py
```

PHPUnit is configured for disposable MySQL test storage. Override it only in an uncommitted `.env.testing`; never point tests at a shared, reference or production database. The last full T037 run passed 181 PHP tests/1,673 assertions, 40 Vitest tests and 35 Playwright cases. T038 then passed focused backend, frontend and browser coverage; do not describe the historical totals as a post-T038 full-suite run.

## Work still required before a live pilot

1. Create the first reviewed Git commit and reproduce checks from a clean checkout.
2. Deploy an isolated TLS environment with secret management, supervised processes, central telemetry, private object storage, MySQL backups and point-in-time recovery.
3. Run production-sized load tests and set alert thresholds from evidence.
4. Qualify the exact Windows browser, thermal printer, helper, drawer and optional display hardware.
5. Validate SMTP, Meta, Twilio, Google, Microsoft and Stripe with merchant-owned test credentials.
6. Determine each merchant's fiscal applicability with qualified current advice; prove FBR in a real sandbox and add provincial adapters only from current contracts.
7. Execute the controlled pilot, operator training and hosted database/object restore drill.

These are external evidence gates, not hidden unfinished modules. Keep them visible until evidence closes them.

## How to continue

Before changing behavior, read this file, the relevant ticket completion record, the shared contracts and the current code. Update contracts, tests, ticket/status evidence and this memory when scope or state changes. Add a new numbered ticket for a new module instead of rewriting historical completion records. Never claim provider, hardware, legal, deployment or load validation without direct evidence.
