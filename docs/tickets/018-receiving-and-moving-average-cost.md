# T018 — Implement partial receiving and moving weighted-average valuation

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Receive purchased goods with batches/expiry and update inventory/cost exactly once. Define and implement moving weighted-average valuation, including negative-stock and correction cases.

## Dependencies and decisions

- Prerequisites: [T017](017-suppliers-and-purchase-orders.md), [T014](014-inventory-ledger.md), [T015](015-batch-and-expiry-tracking.md), [T016](016-stock-transfers-and-counts.md)
- User-decision references: Q013, Q038 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create receipt headers/lines, PO allocation, batch allocations, receipt cost snapshots, stock source references, supplier charge entries and valuation/correction records. Keep posted cost evidence immutable.

## Required implementation or research work

- Build receive-PO screen with remaining quantity, partial receipt, batch/expiry and unit conversion. Validate over-receipt policy explicitly; do not silently accept more than ordered. Receipt posting atomically records stock effects and financial source entries, with outbox events after commit.
- Implement average-cost equations for positive stock and defined policy for zero/negative stock. Preserve provisional/adjusted cost history when receipt later resolves negative inventory; reports must reveal pending corrections. Sales keep original cost snapshot plus explicit adjustment linkage, never an unexplained rewrite.
- Define landed cost/tax treatment only to the agreed operational scope and researched contracts. Backdated or corrected receipt follows reversal/adjustment workflow and cannot corrupt intervening averages. Transfers carry cost consistently between source/destination without treating transfer as a new supplier purchase.
- Create golden examples for carton receipt/piece sale, fractional quantity, partial receiving and duplicate submission. Integrate PO progress and stock views.

## Interfaces, permissions and user experience

postReceipt command has idempotency key, expected PO state/version, decimal quantities/costs and batch allocations. Domain transaction locks relevant balance/cost rows in deterministic order. Supplier charge is not a recorded supplier payment.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test duplicate receipt, concurrent receipts, exact weighted-average examples, zero/negative stock corrections, reversal and independent-catalog transfer valuation. Expired/missing batch capture follows policy. Receipt and valuation detail visible in both themes; no float drift.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current uncommitted working tree. The repository has no baseline commit or remote, so the changed migrations, domain services, models, controllers, requests, routes, contracts, tests and evidence files are the reviewable evidence.
- Files/modules changed: added the purchase-receipt, immutable inventory-cost, supplier-charge and outbox models/migrations; `PostPurchaseReceipt`, `ReversePurchaseReceipt` and `PostInventoryValuation`; receiving/valuation APIs; transfer-cost integration; shared PHP/TypeScript arithmetic evaluators; the purchasing receiving workbench; focused feature and concurrency tests; and contract/research documentation.
- Schema/API/event changes: a clean MySQL build applies 73 migrations. Added store-scoped receipt list/post/show/reverse and valuation read APIs, immutable supplier charge/cost entries, idempotent outbox evidence, and `purchasing.receipt.posted`/`purchasing.receipt.reversed`. The validated OpenAPI bundle has 92 paths and 190 schemas.
- Tests actually run and results: focused receiving/valuation/concurrency/transfer suite passed 13 tests with 179 assertions; shared arithmetic fixture passed 23 cases in PHP and all 28 frontend unit cases; complete PHP suite passed 126 tests with 858 assertions; PHPStan, Pint, TypeScript, ESLint, Prettier, production build and strict Composer validation passed; Playwright passed all 17 journeys; documentation validation passed 71 Markdown files, 725 local links and 81,499 words.
- Light/dark visual evidence: `docs/evidence/t018-receiving-valuation-light.png` and `docs/evidence/t018-receiving-valuation-dark.png`, backed by the Playwright receiving journey and direct visual inspection.
- Hardware/provider evidence: not applicable. This ticket performs no hardware operation, bank transfer, supplier contact or external provider call. Outbox rows are durable post-commit dispatch evidence only.
- Decisions/contract deviations and downstream impact: no user decision changed. Receipt merchandise and explicit landed cost enter inventory; recoverable tax enters only the supplier charge. Unknown historical cost stays pending, negative stock records an explicit correction, backdated entries keep current valuation sequence, and reversal is append-only/tail-safe. Transfer value is conserved through frozen unit conversion. T019 owns purchase returns, supplier credits, supplier balances/payment allocation and later landed-cost corrections; T022 owns finalized-sale cost snapshots/COGS linkage.
- Remaining blockers/limitations and next action: no T018 blocker. Execute T019; actual external accounting/fiscal export remains out of scope until its owning tickets.
