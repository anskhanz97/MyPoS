# T016 — Implement stock transfers, stocktakes and reconciliation

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Provide controlled movement between stores and physical stock counts without losing inventory history or assuming independent catalogs use identical IDs.

## Dependencies and decisions

- Prerequisites: [T014](014-inventory-ledger.md), [T015](015-batch-and-expiry-tracking.md)
- User-decision references: Q010, Q025, Q027, Q029 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create transfer headers/lines, shipment/receipt states, in-transit quantities, explicit independent-catalog product/UOM mappings, stocktake sessions/count lines and posted count adjustments.

## Required implementation or research work

- Implement draft → dispatched → received/partially received transfer lifecycle with cancellation/correction rules. Ship and receive effects post separately and idempotently. Handle short/excess receipt using documented authorized resolution, not untracked quantity edits.
- Shared products can map directly when valid. Independent stores require explicit product/variant/UOM mapping and compatible batch transfer. Do not match on names or barcodes silently. Preserve cost provenance for T018 valuation.
- Build stocktake opening, counting, review and posting. Define concurrency policy while live sales continue: compare to a captured baseline plus intervening movements or use a controlled count window as frozen by T004. Do not overwrite live balances with stale counts.
- Provide discrepancies, source/target store permissions and status visibility. No local-network offline stock transfer execution is required.

## Interfaces, permissions and user experience

Operation APIs for dispatch, receive and postCount use state/version checks. Repeated commands return original results. Count/transfer exports are scoped. Audit mappings and reasons for discrepancies.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test partial transfer receipt, duplicate dispatch/receive, different store product IDs, unit conversion and expired batch handling. Simulate a sale during count and reconcile correctly. Unauthorized destination access fails. Review transfer/count screens in light/dark.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: executed 2026-09-29 in the current `C:\MyPoS` working tree; this repository has no initial commit, so verification is tied to the recorded files and test evidence rather than a commit hash.
- Files/modules changed: added inventory variant mappings, transfer headers/lines/receipts, stocktake headers/lines, transfer and stocktake domain services/enums/requests/controllers/factories, scoped API routes, the transfer/count Vue preview, responsive two-theme styles, feature/browser tests and supporting architecture/invariant/security/scenario documentation.
- Schema/API/event changes: added seven migrations for directional mappings, transfer state/line/receipt evidence and count snapshots. Added scoped mapping, transfer list/create/dispatch/receipt/cancel and stocktake list/open/show/count/review/post APIs. OpenAPI now documents 82 paths and 166 schemas. Inventory movements gained the `stocktake` kind. No outbox event was introduced because these commands currently complete inside the authoritative database transaction and no T016 external consumer exists.
- Tests actually run and results: MySQL 8 migration from an empty `mypos_test` schema passed for all 62 migrations. Focused T016 passed 6 tests / 81 assertions. Full PHPUnit passed 115 tests / 700 assertions, including the existing real six-process movement concurrency test. PHPStan passed with zero errors and Pint passed. Prettier, Vue typecheck and ESLint passed; Vitest passed 21 tests; Vite production build passed; Playwright passed all 15 journeys. Documentation validation passed 71 Markdown files / 720 links, and every internal OpenAPI reference resolved.
- Light/dark visual evidence: visually inspected [light transfer/count evidence](../evidence/t016-transfers-counts-light.png) and [dark transfer/count evidence](../evidence/t016-transfers-counts-dark.png). Both show mapped transfer provenance, a resolved audited shortage with zero remaining transit, the movement-based count calculation and posted discrepancy without theme or layout loss.
- Hardware/provider evidence: not applicable. Transfers and stocktakes are online application/database operations; no hardware or external provider was used or claimed.
- Decisions/contract deviations and downstream impact: dispatch and receipt are separate idempotent ledger commands. A draft alone can be cancelled. Partial, exact, zero-received short-close and excess receipt paths retain explicit evidence; excess cannot close while another line is under-received. Shared catalogs retain a direct variant/factor-one mapping, while independent stores require an audited directional variant and base-unit factor that is frozen onto the line. Batch identity and expiry facts are preserved; expired batches can enter only quarantine/unavailable locations. Stocktakes lock the captured projection dimensions before recording a baseline movement ID, derive expected-at-count through a per-line cutoff and append only the discrepancy, preserving later sales. Cost fields remain owned by T018.
- Remaining blockers/limitations and next action: no T016 blocker. Remote transfers remain intentionally online-only. T018 must supply moving-average cost propagation for transfer dispatch/receipt and subsequent cost reconciliation. Proceed to T017 suppliers and purchase orders.
