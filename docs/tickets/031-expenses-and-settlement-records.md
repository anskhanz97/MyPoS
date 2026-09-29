# T031 — Implement expenses and operational payment reconciliation

Version: 1.0 | Created: 2026-09-28 | Status: Verified — 2026-09-29

## Outcome

Provide categorized expenses and reconciliation of recorded external payments/settlements without building general-ledger accounting or automatically moving money.

## Dependencies and decisions

- Prerequisites: [T019](019-purchase-returns-and-supplier-balances.md), [T020](020-cash-register-sessions.md), [T022](022-sale-finalization-and-tenders.md), [T030](030-operational-payroll.md)
- User-decision references: Q035, Q015 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create expense categories/records, attachments, payment-source links, settlement batches/lines, fees/differences and reconciliation exceptions. Reuse existing supplier/payroll/cash source identities.

## Required implementation or research work

- Build expense create/review/correct views with date, category, amount, payee and payment source. Validate attachments and scope. Cash-paid expense posts one linked cash movement, not a duplicate independent outflow.
- Record settlement statements/references for manually recorded external tenders and identify unmatched/mismatched records, fees and timing differences. A matching user-entered record is operational reconciliation, not cryptographic bank confirmation. Keep provenance visible.
- Supplier and payroll payments stay in their owning ledgers; reports classify their effects without double-counting expenses. No chart of accounts, general journals or full balance-sheet claims.
- Posted corrections are audited. Provide operational exports and clean hooks for later accounting connector tickets only if separately authorized.

## Interfaces, permissions and user experience

Expense/settlement operations are scoped, idempotent and permission controlled. Reconciliation status changes preserve original tender metadata and user identity. File downloads never accept arbitrary filesystem paths.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test duplicate expense submission, cash link once, attachment validation, partial/unmatched settlement, fee difference and reversal. Combined cash/supplier/payroll/expense totals reconcile. Sensitive reports and light/dark financial screens pass checks.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified — 2026-09-29.
- Execution date / application commit or working-tree evidence: migration `2026_09_29_106000_create_expense_and_settlement_tables.php` plus finance domain/controller/UI/tests.
- Files/modules changed: expense/category/attachment and settlement schema/models, operational finance service/API, gallery and tests.
- Schema/API/event changes: idempotent expense records, private attachment metadata, correction links, batches/lines with fee/difference/exception state.
- Tests actually run and results: clean MySQL migration; `OperationalFinanceTest` 2 tests/15 assertions; scoped PHPStan 0; browser scenario 1 passed; Pint, TypeScript, ESLint, Prettier and build passed.
- Light/dark visual evidence: `docs/evidence/t031-finance-light.png` and `docs/evidence/t031-finance-dark.png` generated and visually reviewed through the passing browser flow.
- Hardware/provider evidence: settlements are marked user-entered; no bank/card provider confirmation is claimed.
- Decisions/contract deviations and downstream impact: supplier and payroll payments remain in their source ledgers; cash classification uses linked movements, preventing duplicate expense outflows.
- Remaining blockers/limitations and next action: none for T031; T032 derives reports from these authoritative ledgers.
