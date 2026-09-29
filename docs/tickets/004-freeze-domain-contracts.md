# T004 — Freeze schema, domain contracts and acceptance fixtures

Version: 1.0 | Created: 2026-09-28 | Status: Verified — completed 2026-09-29

## Outcome

Convert the approved product scope and research into the shared implementation contracts used by all later tickets. This is the last design-only foundation ticket; no blank or speculative contract should masquerade as decision-complete.

## Dependencies and decisions

- Prerequisites: [T001](001-source-audit.md), [T002](002-technical-and-product-research.md), [T003](003-pakistan-fiscal-research.md)
- User-decision references: All confirmed Q&A; engineering defaults in docs/03 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create docs/contracts/domain-model.md, permission-matrix.md, api.yaml, event-catalog.md, state-machines.md, arithmetic-policy.md and golden-scenarios.md. Add architectural decision records with rationale and downstream impact. Choose final numeric scales and limits with examples.

## Required implementation or research work

- Resolve merchant/store scope, independent/shared catalog/customer ownership and separately merchant-wide loyalty links. Define stable IDs, idempotency keys/fingerprints, statuses, audit/reversal rules and source-document references.
- Freeze exact price/tax/discount/rounding and weighted-quantity rules, sale/tender/refund equations and snapshot requirements. Set moving-average baseline and identify detailed valuation cases owned by T018. Specify business date versus timestamp and report period boundaries.
- Define quote/finalize/return/exchange, stock movement/receive/transfer, loyalty and offline submission commands with minimal public payloads and error semantics. Define print/fiscal/email outbox contracts. Every command declares authorization and transaction boundary.
- Create golden fixtures executable in PHP and TypeScript for arithmetic; map cross-module S01–S28 scenarios to tickets. Fix deployment/workload assumptions and distinguish external gates from code-ready requirements. Resolve routine choices through evidence, not repeated product questions.

## Interfaces, permissions and user experience

OpenAPI defines common pagination, money-as-string representation, validation/conflict/retryable errors, request IDs and versioning. Public membership DTO is explicitly limited. Freeze module interfaces, not unrestricted CRUD endpoints for every table. Version changes after this ticket require documented migration/compatibility effects.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- All launch modules have entities, commands, states, permissions and acceptance scenarios. Both store modes and both themes are represented. No unsupported legal constants. Fixtures conserve totals and quantities. No critical implementation choice remains ambiguous except explicitly gated provider/hardware facts.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — completed.**
- Execution date / application commit or working-tree evidence: completed 2026-09-29. This workspace has no Git metadata; evidence is the saved contract set, machine-readable fixture and passing validations.
- Files/modules changed: added [domain model](../contracts/domain-model.md), [permission matrix](../contracts/permission-matrix.md), [OpenAPI 3.1 contract](../contracts/api.yaml), [event catalogue](../contracts/event-catalog.md), [state machines](../contracts/state-machines.md), [arithmetic policy](../contracts/arithmetic-policy.md), [golden scenarios](../contracts/golden-scenarios.md), [architecture decisions](../contracts/architecture-decisions.md), and `docs/contracts/fixtures/arithmetic-golden.json`. Updated this completion record, [documentation index](../README.md) and [ticket status](../10-ticket-status.md).
- Schema/API/event changes: contracts frozen, but no migrations or runtime endpoints implemented. The contract selects public UUIDv7/internal bigint identity, locked shared/independent scope, merchant-wide loyalty links, scoped idempotency/fingerprints, append-only posted records, transactional outbox/inbox, stable states/events and `/api/v1` common envelopes.
- Tests actually run and results: fixture JSON parse/uniqueness PASS with 15 cases. Redocly CLI 2.55.0 reports `api.yaml` valid under its minimal rules with zero warnings after corrections. `scripts/check-docs.py` PASS — 70 Markdown files, 38 numbered tickets, 690 checked local links, 69,852 words and zero errors. S01–S28 all have primary tickets and frozen acceptance points. No application/domain test ran because no application exists yet.
- Light/dark visual evidence: not applicable; no UI was introduced. ADR-011 and scenario S25 make both complete themes, state preservation, keyboard/accessibility and black-on-white print evidence mandatory in every owning UI ticket.
- Hardware/provider evidence: not applicable; no hardware/provider action occurred. Hardware and fiscal unknowns remain explicit gates rather than invented contract outcomes.
- Decisions/contract deviations and downstream impact: no deviation from user scope. Numeric contract uses 2-decimal PKR settlement, 4-decimal internal money/allocation, 6-decimal quantity/cost, 8-decimal conversion and 6-decimal rates with decimal-string APIs and HALF_UP default. Store business cutoff defaults to 00:00 and may be versioned up to 06:00. T005 must implement the locked stack and import the same fixture JSON into PHP/TypeScript tests; later tickets extend contracts only through documented compatibility effects.
- Remaining blockers/limitations and next action: T018 still owns explicitly listed negative/backdated moving-average cases. T024/T035/T036/T037 own actual hardware, provider, capacity and pilot evidence. Proceed to T005 application implementation using these contracts.
