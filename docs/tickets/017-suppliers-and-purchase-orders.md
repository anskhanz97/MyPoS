# T017 — Implement suppliers and purchase order lifecycle

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Provide supplier records and purchase-order planning with explicit statuses and remaining quantities. Receiving and liabilities integrate in T018/T019 rather than pretending an order itself received stock.

## Dependencies and decisions

- Prerequisites: [T011](011-catalog-and-product-types.md), [T013](013-customers-and-local-records.md), [T008](008-authentication-and-fixed-roles.md)
- User-decision references: Q013 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create supplier records/contact data, scoped purchase orders/lines, ordered UOM/quantity, expected unit costs/tax references, dates and status history. Preserve referenced suppliers after archiving.

## Required implementation or research work

- Build supplier list/detail/edit and PO draft/edit/issue/cancel/detail workflows. Validate product/store/UOM and amount limits. Allow partial future receipts and track ordered/received/returned separately.
- Define edits after issue/partial receipt: prevent reductions below already fulfilled quantities and preserve history. Cancellation only affects outstanding units, not existing receipts or financial records. No advanced approval-threshold or three-way invoice-matching module is required.
- Generate a readable purchase-order PDF/export with supplier and merchant details; email send hook integrates at T034. Do not send external emails during tests. Provide source document references and reconciliation links for later tickets.

## Interfaces, permissions and user experience

PO APIs expose domain operations and remaining quantities, not arbitrary status overwrites. Supplier balance UI may show an explicit not-yet-integrated state during this ticket; it must be integrated in T019 before launch. Customer credit remains absent.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test create/issue/cancel, invalid UOM/scope, editing after simulated receipt, duplicate issue and supplier archive. PDF uses light print styling independent of UI theme. Supplier/PO pages pass permission and light/dark checks.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified 2026-09-29**.
- Execution date / application commit or working-tree evidence: implemented and verified in the current `C:\MyPoS` working tree on 2026-09-29. The repository has no application commit yet, so the files and commands below are the evidence anchor.
- Files/modules changed: supplier and purchase-order migrations, models, factories, policies/requests, domain services, API controllers/routes, audit integration, the purchasing component-gallery preview, print template, OpenAPI contract, architecture/invariant/security/research documentation, and focused feature/browser tests. Dompdf 3.1.6 is locked in Composer for server-side A4 generation.
- Schema/API/event changes: four scoped tables were added for suppliers, purchase orders, lines and immutable version snapshots. Eight store-scoped supplier/PO operations were added, including explicit issue, cancel and PDF commands. The contract now exposes 89 paths and 180 schemas, and all internal references resolve. PO issue is idempotent and has no inventory effect; each mutation advances an optimistic version and stores a full history snapshot.
- Tests actually run and results: focused supplier/PO suite passed 4 tests with 53 assertions. The complete PHP suite passed 119 tests with 753 assertions; Pint and PHPStan passed. Vitest passed 21 tests, Playwright passed 16 browser scenarios, and Prettier, Vue type checking, ESLint and the production Vite build passed. A clean MySQL 8.4 rebuild ran all 66 migrations. `composer validate --strict --no-check-publish` passed. The documentation checker passed 71 Markdown files and 722 internal links before this completion entry; OpenAPI validation passed independently.
- Light/dark visual evidence: the populated purchasing workflow was inspected in [light](../evidence/t017-purchasing-light.png) and [dark](../evidence/t017-purchasing-dark.png) themes after correcting table overflow. Values, statuses, focus affordances, archive context and the PDF-ready state remain legible at the tested viewport.
- Hardware/provider evidence: no hardware or external provider is involved. No email was sent. The generated [sample purchase order](../../output/pdf/t017-sample-purchase-order.pdf) is one A4 page, PDF 1.7, 27,120 bytes, with no encryption, forms or JavaScript. Its rendered page and extracted text were inspected for clipping, overlap and replacement characters.
- Decisions/contract deviations and downstream impact: purchase orders remain planning records. Supplier balance intentionally reports `not_integrated_until_t019`; T018 owns real receiving and moving-average inventory valuation, T019 owns returns/liabilities/payments, and T034 owns external email delivery. Supplier archival preserves existing references and blocks new orders. Amendments after issue require a reason and cannot remove or reduce fulfilled quantities.
- Remaining blockers/limitations and next action: no T017 blocker remains. Execute T018 to replace simulated received quantities with an idempotent receipt command, stock movements and moving weighted-average cost updates.
