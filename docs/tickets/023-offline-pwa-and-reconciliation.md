# T023 — Implement offline cash checkout, durable queue and recovery

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Make previously provisioned Windows registers continue core cash sales through an internet outage and restart, preserving evidence until authoritative reconciliation. Do not extend offline access to every back-office module.

## Dependencies and decisions

- Prerequisites: [T022](022-sale-finalization-and-tenders.md), [T010](010-register-provisioning-and-settings.md), [T012](012-pricing-and-promotion-modes.md)
- User-decision references: Q002, Q028, Q029 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Implement service-worker/app caches, versioned bootstrap snapshots, IndexedDB transactions/outbox, device/client transaction IDs, acknowledgements, reconciliation states, register ownership leases and diagnostic metrics.

## Required implementation or research work

- Download only authorized catalog/pricing/tax/staff context and define lease/age limits from T002/T004. Implement browser arithmetic from the same golden fixtures as PHP. Offline allows cash and cached valid promotions/manual discounts; loyalty redemption and external-payment confirmation are disabled. Limited-use coupons need the frozen offline policy.
- Commit local sale and printable snapshot atomically before success. Retry original IDs with backoff; distinguish queued, submitting, accepted and needs-reconciliation. Never drop rejected/conflicting cash-sale evidence. Reconnect must not duplicate stock, cash or later loyalty earning.
- Support valid offline restart, multi-tab ownership and clear pending counts. Handle quota errors, denied persistence, stale authorization, clock drift, source changes and subscription restriction. Do not silently clear data on logout or update. A lost/cleared device has explicitly limited recovery.
- Create a controlled unresolved-sales screen and safe diagnostic/export procedure. Define provisional closeout integration and update compatibility. Do not promise offline local-network shared-store synchronization.

## Interfaces, permissions and user experience

Bootstrap and batch/single submission APIs validate device and tenant, preserve per-command result and request fingerprint, and accept supported older formats. Server decides posted/reconcile state. Public connectivity indicators reflect actual request reachability, not navigator.onLine alone.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test network loss before/after local commit and server commit, browser restart, duplicate submission, two tabs, stale catalog, expired batch, negative stock and disk-full simulation. PHP/TS fixture parity passes. Toggling themes offline preserves cart/queue. Clearly label hardware/fiscal cases pending T024/T035.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified 2026-09-29.**
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree. The repository has no initial commit or remote; verification refers to the current files and generated evidence.
- Files/modules changed: added device-scoped offline snapshot and command-ledger migrations/models; canonical payload hashing; bootstrap builder; reconciliation ingestion; public device-key API requests/controller/rate limit; offline sale source support; IndexedDB store, lease/pricing/connectivity helpers, Web Locks/BroadcastChannel ownership, PWA manifest/service worker, recovery UI, PHP/TypeScript/browser tests and shared contract documentation.
- Schema/API/event changes: clean MySQL schema now has 93 migrations, including `offline_bootstrap_snapshots`, `offline_commands` and `sales.source`. Implemented `POST /api/v1/offline/bootstrap` and `POST /api/v1/offline/transactions` with `X-Device-Key`, schema v1, batches of at most 100, stable per-command results and rate limiting. Added `offline.command.acknowledged` and `offline.command.conflicted` outbox production. OpenAPI remains valid at 108 paths / 227 schemas.
- Tests actually run and results: focused `OfflineReconciliationTest` passed 3 tests / 52 assertions; complete backend passed 146 tests / 1,127 assertions in 888.372s. A clean 93-migration MySQL rebuild passed. Full PHPStan, Pint, strict Composer validation, Vue TypeScript, ESLint, Prettier, 35 Vitest tests, production build and all 23 Playwright tests passed. Documentation checker passed 71 Markdown files, 725 local links and 87,127 words before this completion update.
- Light/dark visual evidence: [light offline recovery](../evidence/t023-offline-light.png) and [dark offline recovery](../evidence/t023-offline-dark.png) were captured from Chromium and visually inspected. Both show cash-only policy, lease/storage/writer state, pending/accepted/conflict counts, controlled actions and retained conflict evidence without clipping or illegible contrast.
- Hardware/provider evidence: Chromium on the local Windows development host verified IndexedDB persistence across reload, strict local workflow behavior, Web Locks two-tab exclusion, storage-failure presentation and service-worker-compatible production build. No printer, drawer, fiscal authority, power-loss laboratory or managed-device qualification is claimed; T024, T035 and T037 own those gates.
- Decisions/contract deviations and downstream impact: no product-scope deviation. The frozen 72-hour register/catalog/pricing lease, 24-hour staff authorization age, 10,000/100 MiB pending bounds and 250 MiB/20% storage policy are implemented in the snapshot contract. Offline is cash-only; external tender confirmation, loyalty redemption and limited coupons remain unavailable. Exact retries reuse the original command and client transaction IDs; changed reuse does not alter the original result. Current server pricing, device fence, lease, staff role/PIN/assignment, session, batch and total are rechecked. Conflicts remain unresolved cash-session evidence. Later release work must retain schema-v1 ingestion throughout its published lease/update overlap.
- Remaining blockers/limitations and next action: the service worker and browser storage are verified in Chromium simulation, not through abrupt Windows power removal or qualified till hardware. Local storage lost through device/site-data destruction cannot be reconstructed; the UI and documentation state this limitation. Physical receipts/drawer transport follows in T024; customer display in T025; loyalty earning after acknowledged offline sale in T027; fiscal outage constraints in T035; recovery/load/update qualification in T036/T037. Proceed to T024.
