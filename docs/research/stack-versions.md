# Technical stack versions and package policy

Version: 1.0 | Researched: 2026-09-28 | Ticket: [T002](../tickets/002-technical-and-product-research.md)

## Recommendation

Build the first production line on Laravel 13, PHP 8.5, Vue 3.5, TypeScript 6.0, Vite 8, Node.js 24 LTS, MySQL 8.4 LTS and Redis 8.2 Extended. Commit Composer and npm lockfiles and deploy exact resolved patches. Major and minor upgrades require their own compatibility run; production must never resolve floating versions during deployment.

This recommendation uses supported stable releases available on 2026-09-28. A package's presence in this document authorizes evaluation, not blind installation. T005 must install only packages used by the scaffold.

## Runtime baseline

| Component | Baseline to declare | Deployment pin | Evidence and rationale |
|---|---:|---:|---|
| PHP | 8.5 | Exact OS package patch | Laravel 13 supports PHP 8.3–8.5. PHP 8.5 has active support through 2027-12-31 and security support through 2029-12-31. |
| Laravel | 13.x | Exact Composer lock | Laravel 13 released 2026-03-17 and has security fixes through 2028-03-17. Laravel 12's bug-fix window ended before this research date. |
| MySQL | 8.4 LTS | Provider-supported 8.4 patch | MySQL 8.4 is the LTS documentation line. Use InnoDB, strict SQL mode and utf8mb4. |
| Redis | 8.2 Extended | Exact managed/server patch | Redis documents 8.2 as an Extended release with support to 2030-09-01. Horizon requires Redis and is not compatible with Redis Cluster. Start non-clustered with HA supplied by the service where needed. |
| Node.js | 24 LTS | Exact CI/container patch | Node recommends LTS lines for production. Node 24 is LTS on the research date and satisfies Vite's engine range. |
| Vue | 3.5.43 | `3.5.43` initially | Vue 3.5.43 is the stable core release dated 2026-09-17; 3.6 was still a release candidate. |
| TypeScript | 6.0.x | Exact npm lock | TypeScript 6 is the current stable generation. Keep strict mode enabled and resolve one tested patch in T005. |
| Vite | 8.3.1 | `8.3.1` initially | npm reported 8.3.1 as current. Lock it and review minors deliberately because Vite publishes frequently. |

Official sources, all accessed 2026-09-28: [Laravel release policy](https://laravel.com/framework/docs/releases), [Laravel deployment requirements](https://laravel.com/framework/docs/deployment), [PHP supported versions](https://www.php.net/supported-versions.php), [MySQL 8.4 Reference Manual](https://dev.mysql.com/doc/refman/8.4/en/), [Laravel database support](https://laravel.com/framework/docs/database/schema), [Vue core changelog](https://github.com/vuejs/core/blob/main/CHANGELOG.md), [Node release status](https://nodejs.org/en/about/previous-releases), [Vite releases](https://vite.dev/releases.html), [Vite npm package](https://www.npmjs.com/package/vite), [TypeScript 6 release notes](https://www.typescriptlang.org/docs/handbook/release-notes/typescript-6-0.html), and [Redis version management](https://redis.io/docs/latest/operate/oss_and_stack/install/version-mgmt/).

## Application packages

### Authentication and API access

- Use Laravel's session guard, secure cookies and CSRF protection for the same-origin web application.
- Use Laravel Sanctum for first-party SPA session integration where needed and for revocable API/device tokens. Browser authentication remains cookie based; do not store bearer tokens in localStorage.
- Keep the application and API under the same top-level domain. If an operational reason later separates subdomains, configure stateful domains, CORS and cookie scope explicitly.
- Personal/device tokens carry narrow abilities, expiry, merchant/device identity and revocation. Server policy checks remain authoritative because token abilities alone do not prove tenant access.

Sources: [Laravel 13 authentication](https://laravel.com/framework/docs/13.x/authentication) and [Laravel Sanctum SPA authentication](https://laravel.com/framework/docs/12.x/sanctum), accessed 2026-09-28.

### Queues and scheduling

- Use Redis-backed Laravel queues and Laravel Horizon for queue visibility. Keep queue payloads small; pass record identifiers rather than serialized models containing stale state.
- Use the transactional outbox for domain events. A worker publishes or handles committed outbox records with idempotency keys.
- Separate critical queues (`sales-posted`, `offline-ingest`, `inventory-ledger`) from recoverable background queues (`exports`, `email`, `reports`). Configure retry budgets and dead-letter/failed-job review per job type.
- Deployments must restart queue workers. Health checks must include worker age, failed-job count and oldest queued-job age.
- Do not enable Redis Cluster while Horizon's documented incompatibility remains. This is an architecture constraint, not a reason to put financial truth in Redis.

Sources: [Laravel queues](https://laravel.com/framework/docs/queues) and [Laravel Horizon](https://laravel.com/framework/docs/horizon), accessed 2026-09-28.

### Money and decimal arithmetic

- Store monetary amounts as integer minor units where the currency has a fixed minor unit and store quantities/cost rates as fixed-scale MySQL `DECIMAL` columns according to the domain contract.
- On PHP, use `brick/math` 0.19.x with exact Composer lock and string inputs for arbitrary-precision intermediate arithmetic. Its repository documents PHP 8.2+ and the current 0.19 release line.
- In the browser, use `decimal.js` 10.6.0 for quantity, tax, discount and price presentation calculations. Construct decimals from strings. The server recalculates and authorizes every posted total.
- Never use JavaScript binary floating point or PHP floats as the authoritative representation for money.

Sources: [brick/math](https://github.com/brick/math) and [decimal.js 10.6.0](https://www.npmjs.com/package/decimal.js?activeTab=versions), accessed 2026-09-28.

### PDF and printing

- Use browser HTML/CSS print for interactive A4 reports where it is sufficient.
- Evaluate `barryvdh/laravel-dompdf` 3.x for server-generated simple invoices, purchase documents and exports. Pin the exact Composer resolution in T005. Keep remote resource fetching disabled and use only controlled assets.
- Thermal receipt output goes through the local helper as structured receipt data rendered to ESC/POS. A server PDF is not the thermal print protocol.
- Do not add a headless Chromium PDF service at scaffold time. Introduce it only if a later ticket proves DOMPDF cannot meet a defined document requirement.

Source: [Laravel DOMPDF wrapper](https://github.com/barryvdh/laravel-dompdf), accessed 2026-09-28.

### Test stack

| Test layer | Package / tool | Initial pin | Purpose |
|---|---|---:|---|
| PHP unit/domain | Pest | 5.x exact Composer lock | Pure policies, value objects, calculators and services |
| Laravel integration | Pest + Laravel testing | Framework lock | MySQL transactions, tenant filters, permissions, HTTP contracts, queues |
| Frontend unit | Vitest | 5.0.2 | Stores, composables, formatters, calculations and state transitions |
| Vue components | Vue Test Utils with Vitest | Exact npm lock | Keyboard behavior, validation, permissions presentation and theme-independent semantics |
| End to end | Playwright Test | 1.63.0 | Chromium till flows, Firefox/WebKit smoke where applicable, light/dark and offline/reload cases |
| Static analysis | PHPStan/Larastan, ESLint, `vue-tsc` | Exact locks | Type and contract errors before runtime |

Pest 5 requires PHP 8.4+ and PHPUnit 13, which PHP 8.5 satisfies. Vue's official testing guide recommends Vitest for Vite projects and identifies Playwright as an end-to-end option. Playwright can emulate light and dark color schemes.

Sources: [Pest support policy](https://pestphp.com/docs/support-policy), [Pest upgrade guide](https://pestphp.com/docs/upgrade-guide), [Vue testing guide](https://vuejs.org/guide/scaling-up/testing), [Vitest 5.0.2](https://www.npmjs.com/package/vitest), [Playwright Test 1.63.0](https://www.npmjs.com/package/%40playwright/test), and [Playwright emulation](https://playwright.dev/docs/emulation), accessed 2026-09-28.

## Frontend rules

- Vue Single File Components use TypeScript and Composition API.
- Pinia may be added for client state when the first real workflow requires cross-page state. Do not put server truth into a global store by default.
- Vue Router is appropriate for application navigation. Route metadata may control presentation, but server middleware/policies control permission.
- Build light and dark themes from semantic CSS custom properties. The theme selector supports light, dark and system; the user's explicit choice persists and applies before first paint.
- Meet WCAG 2.2 AA. Test focus visibility, 200% zoom, keyboard-only operation, error association, accessible names, target size and contrast in both themes.
- Do not add a broad component framework until the UI foundation ticket evaluates its bundle, accessibility and theming behavior. Prefer owned primitives and a small icon set.

Source: [WCAG 2.2](https://www.w3.org/TR/wcag/), accessed 2026-09-28.

## Database and infrastructure rules

- MySQL is authoritative. Financial, stock and audit records use transactions, foreign keys, unique constraints and immutable posting rules.
- Redis contains caches, locks, sessions where configured, rate-limit counters and queue data. Loss of Redis may reduce availability but must not corrupt posted business data.
- Object storage holds exports and controlled uploaded assets. Store metadata and tenant ownership in MySQL.
- Development and CI must run MySQL rather than substituting SQLite for transaction or constraint tests.
- Use UTC timestamps internally and retain business date, store timezone and original offset where reporting depends on local trade date.

## Version and dependency policy

1. Commit `composer.lock` and the npm lockfile.
2. CI uses clean frozen installs and fails if lockfiles drift.
3. Renovation is a separate reviewed change with changelog review, unit/integration/E2E tests and both-theme smoke tests.
4. Apply security patches promptly within the supported major/minor line. Major/minor changes require a compatibility ticket.
5. Produce a software bill of materials and dependency audit in CI.
6. Remove unused packages. Every runtime package must have an owner, purpose and update path.
7. Do not install packages to solve one trivial helper that can be expressed and tested locally.

## T005 validation outcomes

- Exact dependency patches are recorded by `composer.lock` and `package-lock.json`.
- Pinia was not needed; focused Vue composables/state are used.
- Laravel Pint, PHPStan/Larastan, ESLint, Prettier, TypeScript, Vitest and Playwright form the current check set.
- The production deployment image/base operating-system patch remains an environment decision for the release candidate.

The application/tooling choices are resolved in the working tree. Revisit them only through a reviewed dependency change. The production image remains part of the open hosting gate.
