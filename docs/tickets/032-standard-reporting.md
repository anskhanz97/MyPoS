# T032 — Implement standard reports, saved filters and drill-down

Version: 1.0 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Build trustworthy standard reporting for merchant/store operations with saved filters, exports and source-document drill-down. Do not build an arbitrary report designer or dashboard-layout builder.

## Dependencies and decisions

- Prerequisites: [T026](026-returns-and-exchanges.md), [T027](027-loyalty-points-and-rewards.md), [T030](030-operational-payroll.md), [T031](031-expenses-and-settlement-records.md), [T016](016-stock-transfers-and-counts.md), [T018](018-receiving-and-moving-average-cost.md)
- User-decision references: Q031, Q025, Q038, Q041 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create metric definitions, authorized report queries/projections, refresh/reconciliation metadata and per-user saved filters. Reporting data is derived from authoritative ledgers/snapshots, not a new editable source of truth.

## Required implementation or research work

- Implement sales, discounts, tax, tenders, returns, gross margin, stock/expiry/movements, purchases/supplier balances, expenses, loyalty, attendance/leave/payroll and cash closeout reports. Define gross/net/tax/discount/refund semantics once.
- Handle business-day cutoff, store timezone, return date versus original sale date, cash change, external recorded tenders and provisional cost adjustments. Show report freshness/limitations where asynchronous or unresolved records matter.
- Support store-specific and consolidated views in both ownership modes. Independent-store identical SKUs are not automatically one product; label mapping/aggregation explicitly. Merchant-wide loyalty totals must not be counted once per store.
- Implement bounded pagination, indexed filters and background large exports using T033 infrastructure. Avoid heavy dashboard queries on checkout write paths. Save filter definitions, not unauthorized cached result sets. Payroll report permissions remain restricted.

## Interfaces, permissions and user experience

Report APIs expose fixed approved metrics, bounded date ranges/pagination and clear totals. Every aggregate can be reconciled/drilled down to source documents within user scope. Export jobs must re-check scope at execution and download.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Seed golden cross-module dataset and reconcile sale/tax/tender/return/stock/cost/payroll/cash totals. Test midnight cutoff, partial returns, negative cost adjustments and both store modes. Test report authorization and measured query plans. Charts/tables/exports work in both themes with non-color cues.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; repository has no baseline commit.
- Files/modules changed: saved-filter migration/model, `StandardReportService`, `ReportingController`, API routes, Vue reporting preview, feature/browser tests and reporting documentation.
- Schema/API/event changes: added per-user saved report definitions; added fixed report, CSV export and saved-filter endpoints. No new editable reporting ledger or events.
- Tests actually run and results: `ReportingTest` 2 tests / 20 assertions; scoped PHPStan zero errors; TypeScript, ESLint and Vite build passed; Playwright reporting scenario passed.
- Light/dark visual evidence: `docs/evidence/t032-reporting-light.png` and `docs/evidence/t032-reporting-dark.png`, generated and visually reviewed.
- Hardware/provider evidence: not applicable; all values are authoritative database projections.
- Decisions/contract deviations and downstream impact: synchronous CSV handles the bounded summary; T033 provides durable background jobs for large row-level exports. Loyalty remains merchant-wide and workforce totals remain payroll-restricted.
- Remaining blockers/limitations and next action: no T032 blocker; T033 implements validated import and portable export jobs.
