# T026 — Implement original-sale returns, exchanges and deadline approvals

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Support linked partial/full returns and exchanges while conserving original money, quantities and stock effects. Default ordinary window is 7 days, configurable up to 30, with manager late-return exception.

## Dependencies and decisions

- Prerequisites: [T022](022-sale-finalization-and-tenders.md), [T024](024-receipts-printing-and-drawer.md), [T020](020-cash-register-sessions.md)
- User-decision references: Q016, Q019, Q038, Q040 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create return documents/lines, original allocation references, refund tenders, exchange links, stock disposition, policy snapshots and manager approval/audit records. Keep remaining refundable quantities/value projections.

## Required implementation or research work

- Build original-sale lookup, eligible line/quantity selection, reason/disposition entry, refund tender recording and receipt. Apply original tax/discount allocations, not current promotions/prices. Repeated partial returns cannot exceed original eligible amounts.
- Enforce ordinary window server-side; late request requires action-bound manager approval and reason. Do not silently expand policy for all sales. Research statutory exceptions from T003 remains authoritative.
- Exchange coordinates a return and fully paid new sale, settles the difference and links documents. For external methods record actual refund confirmation provenance; do not fabricate automatic provider refund. No store-credit/gift-card issuance or unlinked returns.
- Restock only to permitted original/appropriate non-expired batch disposition. Adjust cash session and valuation via domain contracts. Emit loyalty reversal event for T027 and fiscal correction event for T035; later integration tests must verify them.

## Interfaces, permissions and user experience

Return/exchange commands use idempotency and lock original remaining returnable amounts. Distinguish pending external action from completed monetary refund. Closed-session refunds occur in the active authorized session with references, not by rewriting old closeout.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test partial return rounding, repeated/concurrent return, return after price change, deadline boundaries, manager exception replay, exchange differences and non-restockable batch. No excess refund/quantity. Receipt, approval and detail UI reviewed in both themes.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified 2026-09-29**.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; repository still has no application commit or configured remote.
- Files/modules changed: `PostSaleReturn`, `PostSaleExchange`, `ConfirmExternalReturnRefund`; return/exchange models, requests, controller and routes; return schema migration; inventory cost kind; settings validation; permissions; OpenAPI and shared contracts; `SaleReturnsPreview.vue`; feature/concurrency/browser tests.
- Schema/API/event changes: added `sale_returns`, `sale_return_lines`, `sale_return_tenders`, `sale_return_stock_effects` and `sale_exchanges`; added register-scoped return list/post/detail, external-confirmation and exchange endpoints; settings accept `returns.window_days` 1–30; emitted `sales.return.posted`, `sales.return.refund_confirmed`, `loyalty.sale.returned` and `fiscal.sale.returned` outbox events. OpenAPI parses at 120 paths / 246 schemas.
- Tests actually run and results: clean 98-migration MySQL rebuild; focused return suite 4 tests / 78 assertions; separate process-concurrency test 1 / 9; exchange test 1 / 20; browser return workflow 1 passed with evidence; PHPStan 0 errors; Pint, Vue typecheck, ESLint, Prettier and Vite build passed. Full repository regression is run at the final verification checkpoint after downstream tickets.
- Light/dark visual evidence: [light](../evidence/t026-returns-light.png) and [dark](../evidence/t026-returns-dark.png), captured from the interactive late-approval and pending-external-refund state in Chromium and visually reviewed.
- Hardware/provider evidence: cash and external-refund provider effects are application-ledger/manual-evidence simulations. No automatic card/wallet/bank refund or physical drawer action is claimed.
- Decisions/contract deviations and downstream impact: partial returns cannot reuse the stock ledger's deliberately one-to-one reversal FK. `sale_return_stock_effects` therefore records proportional links from each appended return movement to the original sale movement while preserving the strict correction invariant. T027 and T035 consume the emitted loyalty/fiscal events; neither effect is fabricated here.
- Remaining blockers/limitations and next action: no T026 blocker. Run T027 loyalty reversal/earn/redeem logic next; physical drawer/provider and integrated fiscal qualification remain T037/T035 gates.
