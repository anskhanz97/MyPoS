# T022 — Implement atomic sale posting and fully settled split tenders

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Turn an authorized quote/cart into exactly one authoritative sale with inventory and cash effects, fully settled through cash and recorded external payments. Establish reliable asynchronous hooks for receipts, loyalty and fiscal work.

## Dependencies and decisions

- Prerequisites: [T021](021-checkout-cart-and-quotes.md), [T014](014-inventory-ledger.md), [T018](018-receiving-and-moving-average-cost.md), [T020](020-cash-register-sessions.md)
- User-decision references: Q014, Q015, Q016, Q028 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create sales/line snapshots, tender records, cash change, idempotency records/fingerprints, inventory source links, finalized receipt data and transactional outbox events. Preserve public sale/receipt identifiers.

## Required implementation or research work

- Validate store/register/session, permissions, active pricing quote, batches, decimal totals and full settlement server-side. Support cash and manually confirmed card/wallet/bank references, including split payment. Do not accept customer debt or partial unpaid sale.
- Use one authoritative transaction for core sale/tender/stock/cash/outbox effects with deterministic locks. Pricing and coupon usage claims finalize here. Record manual confirmation provenance and uncertain external-payment handling; no UI may imply bank verification.
- Resolve retry/timeouts by idempotency lookup. Same key/body returns original result; changed body conflicts. Prepare receipt view/reprint data from immutable snapshots. Later T027 attaches loyalty reservation/commit without trusting a browser discount claim.
- Voiding a draft differs from returning/reversing a completed sale. Do not delete a paid sale to fix an error. Preserve enough metadata for offline synchronization and fiscal adapters without old till ingestion flags.

## Interfaces, permissions and user experience

POST sale command and GET by client transaction identity implement T004 schema. Outbox consumers are retry-safe. The response clearly identifies posted state; receipt printing and email are separate outcomes. External refs are limited metadata, never PAN/CVV.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- MySQL tests simulate concurrent finalization, crash/response loss after commit, duplicate key, altered retry body, split cash/external and cash change. All posted effects occur once or none. Tenant/register substitutions fail. Sale detail and tender status are truthful in both themes.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current `C:\MyPoS` working tree (repository has no baseline commit/remote yet).
- Files/modules changed: four sale/receipt migrations; sale, line, extra, tender and receipt models; atomic `FinalizeSale` domain service; validated controller/routes; sale finalization and two-process concurrency tests; checkout payment/result workspace; browser coverage; synchronized architecture, invariants, security, research, scenario, state/domain/event and OpenAPI contracts.
- Schema/API/event changes: added `sales`, `sale_lines`, `sale_line_extras`, `sale_tenders` and `receipt_snapshots`, with tenant/store ownership keys and merchant-wide client/idempotency uniqueness. Added register-scoped finalize, sale-detail and client-transaction lookup APIs. `sales.sale.finalized` and source-linked `cash.movement.posted` are written transactionally with their owning records.
- Tests actually run and results: clean MySQL migration passed with 90 migrations; sale-focused coverage passed within the complete suite, including split cash/external/change, lost-response replay, altered retry, store/register substitution, underpayment, coupon claim and a real two-process duplicate race; full backend suite passed 143 tests/1,075 assertions in 1,243.85 seconds. Full PHPStan and Pint passed; strict Composer validation passed; Vue typecheck, ESLint, formatting, 32 Vitest tests and production build passed; complete Playwright suite passed 21 tests; OpenAPI parsed at 108 paths/227 schemas; documentation checker passed 71 Markdown files, 725 local links and 85,974 words.
- Light/dark visual evidence: `docs/evidence/t022-sale-light.png` and `docs/evidence/t022-sale-dark.png`, captured after a finalized split-tender sale and visually inspected. Both show receipt readiness, printing `not requested`, fiscal `not required` and external-payment `staff declaration` separately.
- Hardware/provider evidence: manual external payment is staff-declared metadata only; no bank, card terminal, wallet, printer or fiscal provider was contacted. Physical printing remains T024, offline ingestion T023, and fiscal submission T035.
- Decisions/contract deviations and downstream impact: replaced the provisional unscoped sale paths with merchant/store/register-scoped endpoints and added lookup by client transaction ID. Finalization consumes an exact unexpired quote, requires exact settlement, records net cash after change, snapshots immutable receipt data and commits sale/stock/valuation/cash/coupon/audit/outbox effects together. T023 may submit cash-only offline commands through this domain boundary but must preserve device/snapshot/fencing evidence and conflicts.
- Remaining blockers/limitations and next action: no T022 blocker. Proceed to T023 offline PWA, durable local outbox and reconciliation; receipt hardware outcome remains explicitly unclaimed until T024.
