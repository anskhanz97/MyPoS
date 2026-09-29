# T020 — Implement cash sessions, floats, cash movements and closeout

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Manage register-based cash accountability, with one explicit active cash session per register according to the frozen concurrency rule. Staff identity is recorded on each movement even when a drawer is shared.

## Dependencies and decisions

- Prerequisites: [T010](010-register-provisioning-and-settings.md), [T008](008-authentication-and-fixed-roles.md), [T019](019-purchase-returns-and-supplier-balances.md)
- User-decision references: Q023, Q015 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create opening floats, cash sessions, paid-in/out records, reasons/source links, denomination counts if useful, blind closing declarations, expected totals, variances and manager review records.

## Required implementation or research work

- Implement open-session, paid-in/out and close-review flows. Cashier sees a blind count entry before expected amount is revealed as allowed by role. Distinguish cash session from employee attendance shift.
- Define handover and concurrent cashier rules. A close request uses a cutoff and pending-sale status; no unexplained missing sales. T022 contributes cash sale/change/refund effects, T023 supplies provisional offline close and later reconciliation. Do not call an offline unresolved close fully reconciled.
- Link supplier/payroll/expense cash outflows to the same movement identity as later modules integrate. Never count both a payment record and its drawer movement as two expenses. Manager variance review requires reason/audit; closed sessions are corrected through documented adjustments.

## Interfaces, permissions and user experience

Cash-session APIs reject incompatible register/store and stale status. saleFinalized/refundPosted consumers are idempotent. Expected cash equations distinguish gross tender, change and refunds. Provide source links and report cutoff metadata.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test duplicate opening, simultaneous open attempts, paid-out permissions, blind count, variance review, handover and late-arriving sale fixture. Verify expected cash arithmetic and no hidden external tender counted as cash. UI works in both themes and with keyboard input.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current uncommitted working tree. The repository still has no baseline commit or remote; migrations, domain/API code, golden fixtures, tests, contracts and screenshots are the reviewable evidence.
- Files/modules changed: added cash-session, movement, immutable count, custody-handover and variance-review records; cash enums and exact ledger arithmetic; open, movement, handover, blind-count and review services; fixed-role permission expansion; scoped requests/controller/routes; two-theme cash-control workbench; A026 PHP/TypeScript golden case; and synchronized architecture, state, permission, event, research and security contracts.
- Schema/API/event changes: a clean MySQL build applies 82 migrations. Added seven scoped register cash-session routes covering list/open/detail/movement/handover/count/review. The database unique active guard enforces one active session per register. `cash.session.opened`, `cash.movement.posted`, `cash.session.closed` and reasoned reopen events use the outbox. The parsed OpenAPI bundle has 102 paths and 215 schemas.
- Tests actually run and results: focused shared arithmetic, permission and cash behavior/concurrency checks passed 18 tests with 90 assertions, including a real two-process MySQL open race; complete PHP suite passed 136 tests with 973 assertions; all 32 frontend unit/golden cases, PHPStan, Pint, TypeScript, ESLint, Prettier, strict Composer validation and production build passed; Playwright passed all 19 journeys; documentation validation passed 71 Markdown files and 725 local links before this completion-record wording was added and is rerun below.
- Light/dark visual evidence: `docs/evidence/t020-cash-session-light.png` and `docs/evidence/t020-cash-session-dark.png`, backed by the Playwright cash-control journey and direct visual inspection. The light image shows variance review after the blind count; the dark image shows the same session closed without losing state.
- Hardware/provider evidence: no physical drawer, printer or payment provider was invoked. The screen and browser journey are explicit simulations; T024 owns qualified printer/drawer transport.
- Decisions/contract deviations and downstream impact: no user decision changed. Added `cash.operate` to cashier/manager/owner roles; paid-out and supplier cash movements require `cash.manage`, no-sale uses `drawer.no_sale`, and variance review uses `cash.variance.approve`. Expected cash is the sum of signed drawer movements and excludes external tenders. Opening float is one movement, source keys prevent duplicate owning-document effects, and a supplier cash movement must exactly equal its linked payment. The launch close tolerance is zero: exact counts close, non-zero variance requires manager/owner review. Custody is separate from attendance and each movement retains its actor.
- Remaining blockers/limitations and next action: no T020 blocker. T021 can build the cart/quote workspace. T022 must emit idempotent `sale_cash`/`refund_cash` movements using the reserved internal source contract. T023 must replace the currently declared pending-sale counts with verified durable device-queue state and owns provisional offline close/reconciliation. Payroll and expense cash links remain with T030/T031. Physical cash-drawer actuation remains T024.
