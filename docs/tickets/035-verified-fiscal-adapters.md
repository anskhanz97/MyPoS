# T035 — Implement applicable Pakistan fiscal adapters and exception handling

Version: 1.0 | Created: 2026-09-28 | Status: Verified code 2026-09-29 — production gates open

## Outcome

Implement only fiscal regimes whose applicability and technical contracts have been verified in T003. Integrate sales, corrections and receipts without equating a local sale with an accepted fiscal submission.

## Dependencies and decisions

- Prerequisites: [T003](003-pakistan-fiscal-research.md), [T004](004-freeze-domain-contracts.md), [T022](022-sale-finalization-and-tenders.md), [T023](023-offline-pwa-and-reconciliation.md), [T026](026-returns-and-exchanges.md), [T024](024-receipts-printing-and-drawer.md), [T032](032-standard-reporting.md)
- User-decision references: Q003, Q008, Q024, Q040 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create scoped fiscal configurations, protected credentials, immutable submission snapshots, provider references, statuses/attempts and correction/reconciliation links. Preserve authority/jurisdiction/version evidence.

## Required implementation or research work

- Map sale/tax/item classifications into verified payloads. Use documented authentication, timeout/retry/idempotency/reference lookup behaviour. Preserve unknown outcome rather than blindly creating a second invoice. Separate retryable transport failures from rejected business data.
- Implement linked returns/corrections as required by verified regime. Receipt fields/QR/numbering display actual acceptance or legally supported pending state. Apply researched outage policy for offline tills; if a regime disallows a proposed behaviour, enforce the documented constraint and flag affected merchant launch readiness.
- Provide authorized fiscal status/configuration/exception views and manual repair paths with audit. Never expose credentials or allow arbitrary payload tampering that disconnects tax evidence from a sale.
- Run sandbox scenarios where access exists. Production registration/credentials/provider approvals are explicit launch gates; code completion without them is not compliance certification.

## Interfaces, permissions and user experience

FiscalAdapter implements T003/T004 interface with per-authority mapping, outbox consumer and status DTOs. Sale posting coupling/decoupling must match verified legal behaviour, not generic async assumptions. Third-party accounting remains deferred.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test accepted/rejected/timeout/duplicate/unknown submission, refund correction, credential error and offline reconciliation using fixtures plus real sandbox where available. Check receipt/report agreement and scope isolation. Record exact source/API versions and pending production gates.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

Do not fabricate missing credentials or regulatory conclusions. Complete supported code/tests and leave precise integration/launch gate evidence.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified code 2026-09-29; production compliance is intentionally not claimed.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; repository has no baseline commit.
- Files/modules changed: fiscal configuration/mapping/document/attempt schema, FBR DI v1.12 mapper/transport, outbox consumer, submission job, receipt/status projection, API/controller, Vue readiness view, tests and documentation.
- Schema/API/event changes: added encrypted store-authority configuration, effective mappings, immutable fiscal documents/attempts and outbox consumption; added redacted readiness/config/mapping/consume/retry/reconcile APIs.
- Tests actually run and results: `FiscalTest` 2 tests / 36 assertions across focused runs; accepted/rejected/timeout-unknown/manual reconciliation/config gates passed using fake HTTP; scoped PHPStan, clean MySQL migration, TypeScript, ESLint, Vite and Playwright passed.
- Light/dark visual evidence: `docs/evidence/t035-fiscal-light.png` and `docs/evidence/t035-fiscal-dark.png`, generated and visually reviewed.
- Hardware/provider evidence: simulated contract-shape evidence only. No real FBR, PRAL, integrator or provincial sandbox/production endpoint was called.
- Decisions/contract deviations and downstream impact: only FBR DI v1.12 sandbox activation is coded. Production FBR and every provincial authority remain blocked until the exact T003 external gates are evidenced. Unknown outcomes cannot auto-retry. Active required offline sales use `block_sale`.
- Remaining blockers/limitations and next action: licensed-integrator onboarding, merchant classification/credentials, approved scenario/mappings, QR/duplicate/lookup/correction/outage contract and successful sandbox verification are mandatory T037 launch gates. KPRA/PRA/SRB/BRA/ICT require canonical current onboarding contracts before adapters can be added.
