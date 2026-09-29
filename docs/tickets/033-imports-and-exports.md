# T033 — Implement validated imports, opening balances and portable exports

Version: 1.0 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Enable new merchants to import catalogs/customers/opening stock and export operational records. Full historical XEPOS migration and live connectors remain outside launch.

## Dependencies and decisions

- Prerequisites: [T011](011-catalog-and-product-types.md), [T013](013-customers-and-local-records.md), [T015](015-batch-and-expiry-tracking.md), [T018](018-receiving-and-moving-average-cost.md), [T032](032-standard-reporting.md)
- User-decision references: Q005, Q024, Q031 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create import jobs, staging rows, validation errors, mapping presets, commit IDs, source hashes and export job/file metadata. Keep opening stock/cost as explicit authorized ledger entries; do not import customer debt.

## Required implementation or research work

- Provide documented CSV/XLSX formats as supported by selected libraries, sample templates and bounded upload validation. Preview column mapping, units, scope, duplicates, tax references and batch/expiry before commit. Dry-run cannot mutate operational balances.
- Commit imports idempotently with row-level outcomes and transactional boundaries appropriate to size. Define partial failure/retry policy; never silently skip financial rows. Shared/independent stores require explicit ownership mapping. Normalize phone without cross-merchant merges.
- Opening stock includes quantities, batches and starting cost; supplier opening balances may use a separate audited supported template if defined, while customer credit balances are rejected. Existing sale history is not migrated.
- Export catalog, customers, operational finance and reports with scope checks, formula-injection protection, encoding and bounded background jobs. Retention and download authorization are explicit. Payroll exports need elevated permissions.

## Interfaces, permissions and user experience

Import API separates validate/preview from commit. Export jobs carry explicit actor/scope and expire downloadable artifacts according to policy. Document stable schema/version for future accounting integration; no arbitrary SQL export endpoint.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test malformed/oversized file, duplicate key, bad UOM/batch, wrong store, Unicode, retry and mixed-validity rows. Dry-run makes zero ledger changes. Opening cost/quantity reconciles. Exports cannot leak other tenants or execute spreadsheet formulas. Both-theme progress/error review is verified.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; repository has no baseline commit.
- Files/modules changed: transfer migrations/models, import/export services, queued export job, controller/routes, CSV templates, Vue preview, API/feature/browser tests and implementation reference.
- Schema/API/event changes: added durable transfer jobs, validation rows and personal mapping-preset storage; added validate/commit/template/status/download endpoints and queued export creation.
- Tests actually run and results: `DataTransferTest` 3 tests / 27 assertions across focused runs; scoped PHPStan zero errors; clean MySQL migration, TypeScript, ESLint, Vite and Playwright passed.
- Light/dark visual evidence: `docs/evidence/t033-data-transfer-light.png` and `docs/evidence/t033-data-transfer-dark.png`, generated and visually reviewed.
- Hardware/provider evidence: not applicable.
- Decisions/contract deviations and downstream impact: launch format is versioned UTF-8 CSV; XLSX is deliberately not advertised. Entire preview commit is atomic, so mixed-invalid financial/stock imports never partially post. Exports expire after seven days.
- Remaining blockers/limitations and next action: no T033 blocker; run a queue worker in deployed environments. T034 adds email receipt and in-app notification delivery.
