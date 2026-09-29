# T021 — Build cashier checkout, scanning, carts and quotes

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Create fast staff counter checkout with real catalog/pricing/stock integration. This ticket delivers cart/quote workflows; final posting follows T022. No tables, kitchen, ordering portal or credit-sale UI.

## Dependencies and decisions

- Prerequisites: [T006](006-frontend-shell-and-themes.md), [T011](011-catalog-and-product-types.md), [T012](012-pricing-and-promotion-modes.md), [T013](013-customers-and-local-records.md), [T015](015-batch-and-expiry-tracking.md), [T020](020-cash-register-sessions.md)
- User-decision references: Q009, Q012, Q014, Q015, Q018 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create carts/lines, suspended-cart state, customer association, quote snapshots/version, scan processing and selected batch/variant/extra data. Cart is mutable; finalized sale will be immutable.

## Required implementation or research work

- Build product search/category shortcuts, barcode scanning, repeated scan increment, variant/extras selection, weighted quantity entry, editable line quantities and permission-controlled discounts. Preserve scanner focus without stealing an active text input. Show price/tax/discount explanation and totals.
- Associate optional customer, suspend/resume authorized carts, handle unavailable product/rule changes and stock warnings. Expired batches block selection. Concurrent cart edits have version checks. A suspended cart does not reserve goods indefinitely or create layaway/deposit obligations.
- Quotes use authoritative server calculations and published version references. Surface reprice conflicts clearly. Provide the same golden calculation fixtures to the future offline engine. Payment button is integrated only when T022 is ready; no fake successful sale in production UI.
- Theme toggle, staff switch and transient network failure must preserve unsaved work appropriately. Keep pending/retry status visible and understandable.

## Interfaces, permissions and user experience

Cart/quote APIs accept quantities/product identifiers rather than trusted final totals. Quote result includes allocations, selected versions and validation issues. Define required full-settlement input contract for T022; payment entry cannot store card secrets.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Browser tests cover scan/manual search, intentional repeated barcode, variants, weighted items, bundles/extras, suspend/resume and expired stock block. Golden quote matches engine. Test theme toggle on populated basket, keyboard flow, narrow supported checkout size and stale-cart conflict.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current `C:\MyPoS` working tree (repository has no baseline commit/remote yet).
- Files/modules changed: four cart migrations; cart aggregate models/status; `CartWorkflow`; validated requests/controller/routes; pricing support for server-resolved extra price deltas; checkout Vue workspace; feature/browser tests; synchronized architecture, invariant, security, research, scenario, OpenAPI and status records.
- Schema/API/event changes: added `carts`, `cart_lines`, `cart_line_extras`, `cart_quotes`; added scoped list/create/read/replace/scan/suspend/resume/quote APIs. No sale, inventory, tender, coupon-usage or outbox event is created by T021.
- Tests actually run and results: clean MySQL migration passed with 86 migrations; focused `CheckoutCartTest` passed 3 tests/34 assertions; full backend suite passed 139 tests/1,007 assertions; full PHPStan and Pint passed; strict Composer validation passed; Vue typecheck, ESLint, formatting, 32 Vitest tests and production build passed; complete Playwright suite passed 20 tests including the checkout journey; OpenAPI parsed at 107 paths/224 schemas; documentation checker passed 71 Markdown files and 725 local links.
- Light/dark visual evidence: `docs/evidence/t021-checkout-light.png` and `docs/evidence/t021-checkout-dark.png`, captured by Playwright after populated/weighted scan, expired-batch, suspend/resume and network-state interactions and visually inspected.
- Hardware/provider evidence: scanner behavior is keyboard-wedge simulation only. No physical scanner, scale, printer or payment provider was claimed; physical qualification remains T024/T037.
- Decisions/contract deviations and downstream impact: replaced the provisional unscoped `/checkout/quotes` contract with register-scoped cart commands. Quote snapshots are append-only and valid for five minutes; cart mutations use optimistic versions. Extras increase the server-resolved unit price before discount/tax. T022 must consume an exact unexpired cart quote, lock/claim coupon and batch/stock state, post stock/cash/tenders atomically and mark the cart converted.
- Remaining blockers/limitations and next action: no T021 blocker. T022 owns sale finalization and enables the disabled payment action.
