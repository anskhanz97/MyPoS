# T036 — Harden security, performance, deployment and recovery

Version: 1.1 | Created: 2026-09-28 | Status: Verified locally 2026-09-29 — deployment and physical/provider qualification remain pilot gates

## Outcome

Prepare the completed modules for a controlled deployment/pilot through measured performance, adversarial permission checks and reproducible recovery. Do not publish to production merely because this ticket is requested.

## Dependencies and decisions

- Prerequisites: [T005](005-application-foundation.md), [T007](007-merchant-and-store-tenancy.md), [T008](008-authentication-and-fixed-roles.md), [T009](009-plans-and-platform-administration.md), [T023](023-offline-pwa-and-reconciliation.md), [T024](024-receipts-printing-and-drawer.md), [T032](032-standard-reporting.md), [T033](033-imports-and-exports.md), [T034](034-email-and-in-app-notifications.md), [T035](035-verified-fiscal-adapters.md)
- User-decision references: Q002, Q006, Q026; cross-cutting requirements in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create deployment/environment runbooks, workload definition/results, security checklist/findings, backup/restore evidence, monitoring/alert configuration, retention policy, incident procedures and update compatibility matrix.

## Required implementation or research work

- Exercise horizontal/vertical access across APIs/jobs/cache/files/reports, session/PIN controls, public lookup limits, XSS/CSRF/upload protections and secret/log redaction. Verify database least privilege and configuration defaults; fix demonstrated defects within scope.
- Measure representative catalog/search/checkout/report/queue workloads on stated hardware. Tune indexes/eager loading/pagination and isolate expensive reporting. Track actual p95/error/concurrency results without inventing production scale. Keep cashier UI responsive in both themes.
- Configure reproducible server/worker/scheduler deployment, health checks, migration compatibility and rollback/forward approach. Validate old offline clients and pending queues through update. Verify print-helper update/security lifecycle on supported devices.
- Test encrypted backup restoration in isolation and tenant-specific recovery procedure. Set RPO/RTO/retention from verified deployment choices and report measured restoration. Include merchant onboarding, subscription restriction recovery, offboarding/export and incident triage.

## Interfaces, permissions and user experience

No broad new product module. Operational endpoints are restricted and expose necessary health/metrics only. Deployment automation uses environment-specific credentials supplied securely. Production side effects need authorization separate from local hardening.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Run scoped security/integration/load suite, restore backup and reconcile ledgers, restart workers without duplicate effects and test client update with unsent cash sale. Document untested hardware/provider limits. Critical defects fixed; no false claim that a checklist alone proves security/compliance.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified locally 2026-09-29 — deployment and physical/provider qualification remain pilot gates.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; no release commit was supplied.
- Files/modules changed: security-header and log-redaction middleware/processors, protected readiness probe, scheduler heartbeat, expired-transfer pruning, encrypted MySQL backup/restore scripts, operations configuration and seven operations runbooks.
- Schema/API/event changes: no schema or domain-event changes. Added `GET /api/v1/health/ready`; public liveness remains minimal. Added `ops:heartbeat` and `ops:prune-expired-artifacts` scheduled commands.
- Tests actually run and results: T036 adversarial feature selection passed 42 tests/287 assertions across hardening, authentication, tenant/store scope, roles, PIN/approval, public loyalty, data transfer and offline reconciliation. Scoped PHPStan passed. Encrypted restore reproduced 150 tables and 107 migration rows with an exact artifact SHA-256 check.
- Light/dark visual evidence: not applicable; this ticket introduces no product UI. Existing ticket evidence covers both themes.
- Hardware/provider evidence: none claimed. No physical printer/drawer, deployed SMTP, production hosting, object-store restore, point-in-time recovery, external penetration test or production fiscal provider was exercised.
- Decisions/contract deviations and downstream impact: the CSP temporarily permits inline styles for current Vue/print rendering. Provisional RPO 15 minutes and RTO two hours require a hosting/PITR choice. Runtime backup identity needs no global `PROCESS` privilege because dumps use `--no-tablespaces`.
- Remaining blockers/limitations and next action: run T037 aggregate acceptance, then qualify physical hardware, deployed provider configuration, production-sized load, object recovery and fiscal/legal gates before a pilot decision.
