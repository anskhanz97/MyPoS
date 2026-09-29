# T019 — Implement purchase returns, supplier balances and payments

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Complete procurement with linked purchase returns and operational supplier balances/payment allocations. Customer credit remains excluded; supplier liabilities are an intentional separate feature.

## Dependencies and decisions

- Prerequisites: [T018](018-receiving-and-moving-average-cost.md), [T017](017-suppliers-and-purchase-orders.md)
- User-decision references: Q013, Q014 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create purchase-return documents/lines linked to eligible receipts, stock/cost reversal entries, supplier credits, payment records, allocations and auditable supplier balance projections.

## Required implementation or research work

- Return only eligible remaining received quantities, with correct units/batches and stock availability policy. Distinguish physical goods returned from a supplier financial credit; uncertain/unmatched credit remains visible. Apply frozen valuation adjustment rules instead of refunding arbitrary current cost.
- Record supplier payments, partial allocations, references and payment method. Prevent duplicate payment recording and over-allocation; show unallocated amounts explicitly. A payment entry does not initiate a bank transfer.
- Build supplier ledger/balance/statement, overdue/due fields only where supported by the supplier document terms, purchase-return screen and payment-allocation screen. Correct posted entries through reversal, retaining attribution. Integrate cash-source links later with T020/T031 without double-counting.

## Interfaces, permissions and user experience

Operation APIs post purchase return and supplier payment atomically with idempotency. Supplier statements use authoritative charge/credit/payment entries. No unrestricted edit/delete of posted balances. Cross-store merchant policies are explicit.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test partial/repeated return, excessive return rejection, batch/cost linkage, partial/multiple payment allocation, reversal and rebuilt balance equality. Ensure customer module gains no debt account. Both-theme financial screens and exports stay scoped.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current uncommitted working tree. The repository still has no baseline commit or remote; migrations, domain services, APIs, tests, contracts and screenshots are the reviewable evidence.
- Files/modules changed: added purchase-return/line, supplier-credit, supplier-payment and allocation records; atomic post/reverse/confirm/payment services; store and owner-consolidated statement projection; requests/controllers/routes; cumulative arithmetic fixtures in PHP and TypeScript; and the two-theme supplier balance workbench.
- Schema/API/event changes: a clean MySQL build applies 78 migrations. Added receipt-return list/post, return show/reverse, credit confirm, supplier statement, payment post and payment reverse APIs. Added four purchasing outbox events with identifier-only payloads. The validated OpenAPI bundle has 99 paths and 207 schemas.
- Tests actually run and results: focused receiving/return/payment/purchasing and shared arithmetic set passed 13 tests with 220 assertions; a real two-process MySQL allocation race passed 1 test with 4 assertions; complete PHP suite passed 129 tests with 923 assertions; all 31 frontend unit cases, PHPStan, Pint, TypeScript, ESLint, Prettier, strict Composer validation and production build passed; Playwright passed all 18 journeys; documentation validation passed 71 Markdown files, 725 local links and 82,574 words.
- Light/dark visual evidence: `docs/evidence/t019-supplier-ledger-light.png` and `docs/evidence/t019-supplier-ledger-dark.png`, backed by the Playwright supplier-return/payment journey and direct visual inspection.
- Hardware/provider evidence: not applicable. Payment records describe external evidence only; no bank transfer, bank reconciliation, supplier message or other provider call was performed.
- Decisions/contract deviations and downstream impact: no user decision changed. Physical returns use exact receipt/batch eligibility and current moving-average issue cost; supplier credit entitlement uses cumulative original-receipt economics. Expected credits stay outside balance until confirmed. Payments and allocations are append-only, may remain partly unallocated and reverse through linked negative entries. Store statements require `purchasing.manage`; merchant-wide scope requires `report.consolidated`. No customer debt table or workflow was added. Due/overdue labels remain absent because launch supplier terms do not yet provide an authoritative due date.
- Remaining blockers/limitations and next action: no T019 blocker. T020 must link later supplier cash payouts to a single drawer movement without duplicating the supplier-payment entry. Bank-feed reconciliation and automatic funds transfer remain outside this operational ledger.
