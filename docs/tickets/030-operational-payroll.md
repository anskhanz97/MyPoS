# T030 — Implement operational payroll, payslips and recorded payouts

Version: 1.0 | Created: 2026-09-28 | Status: Verified — 2026-09-29

## Outcome

Calculate and approve operational payroll using monthly/hourly pay, attendance/overtime, allowances, deductions and advances. Statutory figures are entered/imported; no automatic tax/contribution compliance engine is promised.

## Dependencies and decisions

- Prerequisites: [T029](029-workforce-attendance-rota-leave.md), [T020](020-cash-register-sessions.md)
- User-decision references: Q020, Q021, Q037 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create effective-dated pay terms, payroll periods/runs/items, input snapshots, manual statutory adjustments, advance ledger/allocations, approvals, payslip snapshots and payout records.

## Required implementation or research work

- Build owner-restricted pay setup, payroll draft calculation, review, adjustment, approve/post, payslip and payout flows. Define decimal rounding, partial periods, unpaid leave and overtime formula from frozen policy with worked examples.
- Snapshot attendance/salary basis and effective dates. Posted runs cannot be recomputed by editing current salary or attendance. Corrections create adjustment/reversal records and explain impact. Prevent duplicate period posting and repeat advance deductions.
- Support partial/recorded payout and outstanding payroll payable; distinguish employee liability from excluded customer credit. Recording a payout does not initiate a bank transfer. Link cash payouts to register movement exactly once where authorized, and avoid double-counting in expense reports.
- Generate confidential English payslips/PDFs with print-safe styling. Payroll visibility follows fixed role matrix; operational manager permissions do not automatically expose pay. Provide exports, reconciliation and auditable approval.

## Interfaces, permissions and user experience

Payroll calculation returns explainable line items; posting and payout APIs use idempotency/state/version checks. Inputs identify entered/imported statutory amounts explicitly. Secure downloads are authorized and time-limited where appropriate; public member portal has no access.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Golden tests cover monthly/hourly pay, overtime, leave, allowance/deduction, advances, partial payout, duplicate post and correction after posting. Payslip totals reconcile with payout/liability. Owner-only access and both-theme review screens are verified; inspect PDF layout.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified — 2026-09-29.
- Execution date / application commit or working-tree evidence: migration `2026_09_29_105000_create_payroll_tables.php`, payroll domain/controller/models/PDF/UI in the working tree.
- Files/modules changed: payroll schema, calculation/posting/payout service, protected API, A4 payslip, management gallery and feature/browser tests.
- Schema/API/event changes: effective pay terms, advances/allocations, runs/items/adjustments, payslip snapshots and payout records; owner management and own-payslip endpoints.
- Tests actually run and results: clean MySQL migration; `PayrollTest` 2 tests/25 assertions; browser payroll scenario 1 passed; scoped PHPStan 0; Pint, TypeScript, ESLint, Prettier and production build passed. Aggregate checks are recorded in T037.
- Light/dark visual evidence: `docs/evidence/t030-payroll-light.png` and `docs/evidence/t030-payroll-dark.png`, manually inspected. Payslip PDF response/rendering passed integration; final page image review is included in release verification.
- Hardware/provider evidence: external payout is explicitly a recorded reference; no banking provider was invoked.
- Decisions/contract deviations and downstream impact: monthly and hourly pay use half-up PKR rounding; overtime uses frozen minutes and the effective term multiplier; statutory values require entered/imported provenance and are not automatically calculated.
- Remaining blockers/limitations and next action: no T030 blocker; T031 classifies payroll payouts without duplicating them as expenses.
