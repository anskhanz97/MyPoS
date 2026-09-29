# T014 — Implement inventory movements, balances and audited adjustments

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Establish the authoritative finished-product inventory ledger and balance projection used by purchasing, sales, transfers and counts. This is not an ingredient/wastage-management module.

## Dependencies and decisions

- Prerequisites: [T011](011-catalog-and-product-types.md), [T008](008-authentication-and-fixed-roles.md)
- User-decision references: Q010, Q029, Q038 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create inventory locations, append-only stock movements, source references, balance projections, adjustment reasons and cost placeholders governed by T018. Use exact base-UOM quantities and tenant/store keys.

## Required implementation or research work

- Implement idempotent movement posting, reversible corrections, balance rebuild/reconciliation and safe concurrent updates. Do not allow arbitrary balance editing without source movements. Product scope/location must be checked server-side.
- Allow authorized negative-stock sales later with warning/audit, while administrative adjustments require a reason. Distinguish sellable and unavailable/quarantine disposition for returned or expired stock without inventing a recipe waste workflow.
- Build stock-on-hand and movement views with source-document drill-down, bounded filters and adjustment entry/approval permissions. Expose data freshness and unexplained discrepancies rather than silently overwriting projection values.
- Ensure fixed bundles expand to finished-product movements exactly once and pack conversions use catalog contracts. Batch dimensions are added compatibly by T015; do not duplicate quantity ledgers.

## Interfaces, permissions and user experience

Provide internal postMovement/reverseMovement contracts and read APIs; only trusted domain commands may post financial stock effects. Source-document uniqueness prevents retries duplicating stock. Inventory APIs never accept an unverified tenant scope from the body.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Concurrent movements preserve balances, duplicate source operations are idempotent, rebuild equals projection, and cross-store invalid product/location pairs fail. Quantity conversions conserve base units. Historical movements cannot be deleted through UI. Check stock/adjustment screens in both themes.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified 2026-09-29.** All acceptance criteria owned by this ticket passed against the working tree.
- Execution date / application commit or working-tree evidence: 2026-09-29; current uncommitted application working tree in `C:\MyPoS` (repository has no commits or remote yet).
- Files/modules changed: four inventory migrations; inventory enums/models/requests/resources/controllers; `InventoryScope`, `BaseQuantityConverter`, `PostStockMovement`, `ReverseStockMovement`, `PostInventoryEffect` and `InventoryBalanceRebuilder`; API routes; feature/concurrency tests; `InventoryPreview.vue`, component gallery, shared theme CSS and browser coverage; architecture, invariant and OpenAPI documents.
- Schema/API/event changes: added `inventory_locations`, `inventory_adjustment_reasons`, immutable `stock_movements` and projected `stock_balances`; added six `/inventory/*` endpoint families and 21 inventory schemas, bringing the parsed OpenAPI contract to 71 paths / 133 schemas with zero missing schema references. Security audit codes cover location/reason creation, adjustment posting and explicit balance rebuild. No outbox event is emitted before its owning transactional workflow exists.
- Tests actually run and results: MySQL 8 migration from empty schema passed for all 49 migrations on the isolated `mypos_test` database at port 3307. Focused inventory suite passed 6 tests / 51 assertions; its real six-process concurrency test also passed and proves six serialized writes produce quantity/count/version 6. Full PHPUnit passed 104 tests / 569 assertions. PHPStan passed with zero errors; Pint passed. Prettier, Vue typecheck and ESLint passed; Vitest passed 21 tests; Vite production build passed; Playwright passed 13 tests. Documentation validation passed 71 Markdown files / 716 local links; OpenAPI parsed successfully.
- Light/dark visual evidence: [light inventory ledger](../evidence/t014-inventory-light.png) and [dark inventory ledger](../evidence/t014-inventory-dark.png), both visually inspected at the captured desktop viewport. The automated flow also covers negative balances, source drill-down, explicit reconciliation, adjustment validation and posting.
- Hardware/provider evidence: not applicable. Inventory posting and reconciliation are server/database behavior and this ticket introduced no device or external-provider integration.
- Decisions/contract deviations and downstream impact: the ledger is authoritative; balances are rebuildable projections. Posting locks a balance dimension before source lookup to give concurrent first writes and retries deterministic lock ordering. Negative stock is retained with a visible warning. Corrections append linked inverse movements. Approval-required reasons fail before any stock effect without a valid bound single-use token. Cost fields remain pending/provisional/final/not-applicable placeholders for T018. T015 must extend this same ledger with batch identity rather than create another quantity ledger.
- Remaining blockers/limitations and next action: no T014 blocker. Batch allocation/expiry belongs to T015, purchase documents to T016, valuation to T018 and sale-generated stock movements to T022. Proceed to T015.
