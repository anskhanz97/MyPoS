# T015 — Implement batches, expiry and sellable-stock selection

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Add optional product batch/expiry tracking with expired-batch sale blocking and traceable receiving/selling/returning. Do not add serial-number tracking.

## Dependencies and decisions

- Prerequisites: [T014](014-inventory-ledger.md), [T011](011-catalog-and-product-types.md)
- User-decision references: Q010, Q029 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create product batch definitions, per-location batch balances, expiry dates, batch movement references and batch selection snapshots. Keep moving-average valuation separate from physical batch choice.

## Required implementation or research work

- Define expiry-date boundary in store business time, mandatory capture rules for tracked products and display labels. Validate received batch consistency. Enable tracking on existing stock only through a controlled allocation procedure, never by leaving unexplained unbatched units.
- Provide availability/expiry views and proposed earliest-expiry selection, with permitted manual selection of non-expired batches. Expired stock is visible but unavailable for sale. Negative-stock policy cannot authorize sale from an expired batch; unallocated shortage handling must be explicit.
- Record original batch on sales and returns where known; returns can route to unavailable stock. Snapshot expiry facts for offline validation and server reconciliation. Missing/changed batch information is an actionable conflict, not silent reassignment.

## Interfaces, permissions and user experience

Batch read/write APIs are scoped to product/store. Reservation/selection contracts feed T021/T022/T023. Explain difference between a warning for soon-expiring stock and a hard block for expired stock. No unsupported pharmaceutical traceability certification claims.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test two batches with different expiry, expiry exactly at boundary, transfers/returns preserving identity and attempted expired sale online/offline fixture. Validate no batch from another product/store can be selected. Check stock alerts/labels in both themes, not color alone.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified 2026-09-29.** All acceptance criteria owned by this ticket passed against the working tree.
- Execution date / application commit or working-tree evidence: 2026-09-29; current uncommitted application working tree in `C:\MyPoS` (repository has no commits or remote yet).
- Files/modules changed: batch definition, batch balance, selection snapshot/line models and six migrations; batch-aware movement model/resource/posting/reversal/reconciliation; expiry, FEFO selection, snapshot validation and controlled conversion domain services; batch requests/controllers/routes; catalog conversion guard; focused tests; OpenAPI, architecture, invariant, offline and research documents; inventory batch UX, responsive styles and Playwright coverage.
- Schema/API/event changes: added `product_batches`, `batch_stock_balances`, `batch_selection_snapshots` and `batch_selection_lines`; added optional batch identity to the authoritative stock ledger and immutable conversion evidence to products. Added batch list/create, selection and tracking-conversion operations, bringing the parsed API contract to 74 paths / 143 schemas with zero missing schema references. Audit events record batch creation and tracking enablement. No serial-number schema or separate quantity ledger was introduced.
- Tests actually run and results: MySQL 8 migration from empty schema passed for all 55 migrations on isolated `mypos_test` at port 3307. Focused T015 suite passed 5 tests / 50 assertions and the combined T014/T015 inventory suites passed 11 tests / 101 assertions. Full PHPUnit passed 109 tests / 619 assertions, including the existing real six-process movement concurrency test. PHPStan passed with zero errors and Pint passed. Prettier, Vue typecheck and ESLint passed; Vitest passed 21 tests; Vite build passed; Playwright passed 14 tests. Documentation validation passed 71 Markdown files / 720 links; OpenAPI parsed successfully.
- Light/dark visual evidence: [light batch workspace](../evidence/t015-batches-light.png) and [dark batch workspace](../evidence/t015-batches-dark.png), both visually inspected. The browser flow verifies text-labelled available/soon/expired states, the hard-block explanation, FEFO proposal, non-reserving disclosure and retained selection after theme change.
- Hardware/provider evidence: not applicable. The offline case is a deterministic server-domain fixture: a snapshot valid on the printed expiry date is rejected after the store-local midnight boundary. T023 owns browser offline transport and queue evidence.
- Decisions/contract deviations and downstream impact: an expiry date is sellable through that store-local date and blocked from the next local day (`expiry_date < business_date`). Thirty days is the default warning window and the read API permits a bounded override. FEFO orders by expiry, then code and immutable ID; manual allocation is allowed only for current non-expired quantities. Selection snapshots reserve no stock and expire after 15 minutes. Batch-tracked shortage conflicts rather than creating unnamed or expired negative stock. Shared-product conversion covers every holding store and requires the actor's inventory permission in each. Transfers, returns and reversals retain batch identity; returns may use quarantine/unavailable locations. Physical selection stays separate from T018 valuation.
- Remaining blockers/limitations and next action: no T015 blocker. T016 wires transfer documents and stocktakes to preserved batch identity; T018 purchase receiving supplies mandatory batch facts; T021/T022 consume and lock selection snapshots; T023 transports expiry facts offline. Proceed to T016.
