# T037 — Run complete cross-module acceptance and prepare the pilot

Version: 1.1 | Created: 2026-09-28 | Status: Acceptance complete locally 2026-09-29 — live pilot blocked by named environment/provider gates

## Outcome

Verify the whole agreed counter POS as an integrated product, reconcile all module interfaces and produce a concrete pilot/release readiness report. This is not permission to expand scope or automatically deploy.

## Dependencies and decisions

- Prerequisites: [T025](025-optional-customer-display.md), [T026](026-returns-and-exchanges.md), [T027](027-loyalty-points-and-rewards.md), [T028](028-public-member-join-and-lookup.md), [T029](029-workforce-attendance-rota-leave.md), [T030](030-operational-payroll.md), [T031](031-expenses-and-settlement-records.md), [T032](032-standard-reporting.md), [T033](033-imports-and-exports.md), [T034](034-email-and-in-app-notifications.md), [T035](035-verified-fiscal-adapters.md), [T036](036-production-hardening-and-recovery.md)
- User-decision references: All launch decisions in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create docs/verification/launch-acceptance.md, scenario-results.md, known-limitations.md, operator-training.md and pilot-runbook.md. Update every ticket status with actual evidence and unresolved dependencies.

## Required implementation or research work

- Execute S01–S28 with realistic synthetic datasets in both store modes and both themes. Include cash/external split payment, cached offline promotions, restart/replay, original-sale returns/late approvals, cross-store loyalty, purchase/stock cost and payroll/cash expense reconciliation.
- Verify remaining downstream integrations: loyalty hooks in checkout/returns, fiscal corrections, receipt/email outbox, reports/exports and second display. Remove or clearly disable unfinished placeholder affordances; no required workflow may be substituted by demo data.
- Test onboarding to first sale, staff training flows, device setup, owner renewal, incident recovery and offboarding. Record actual printer/customer-display/browser configurations and sandbox results. Classify blockers versus known non-blocking limitations against agreed launch scope.
- Review all source-to-target decisions, Q&A traceability and excluded-feature boundaries. Provide an implementation/release summary with risks grounded in actual tests. User decides production/pilot rollout based on this concrete evidence.

## Interfaces, permissions and user experience

All documented public/domain contracts match running implementation. Generated API types, schema docs and golden fixtures are up to date. Any API change has compatible offline version handling. Deferred messaging/connectors remain outside launch sign-off.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- All required scenario outcomes have passed evidence or a named blocker. Report totals reconcile to source ledgers; no cross-tenant exposure; pending offline records recover safely. Both-theme visual review and PDF/thermal checks completed. No claim of readiness if critical fiscal/hardware prerequisites remain unmet.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Acceptance complete locally 2026-09-29 — live pilot blocked by named environment/provider gates.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; no immutable Git commit was supplied, which remains a release gate.
- Files/modules changed: aggregate test-suite isolation, CSP-compatible external theme bootstrap, launch acceptance, S01–S28 results, known limitations, operator training, pilot runbook and verification manifest.
- Schema/API/event changes: none. PHPUnit now separates 164 transactional feature tests from eight committed-state multi-process concurrency tests while retaining one configured suite set.
- Tests actually run and results: backend 181 tests/1,673 assertions passed (164 feature/1,577; 8 concurrency/46; 9 unit/50). PHPStan zero errors. Vitest 40, Playwright Chromium 35, Vue typecheck, ESLint, Vite build, Composer audit and npm production audit passed. OpenAPI/document checks passed. Exact results are in `docs/verification/release-manifest.md`.
- Light/dark visual evidence: all 35 final browser cases passed, including theme persistence, independent staff/public/display preferences, WCAG AA semantic contrast, keyboard/focus, 360 px responsive layout and black-on-white print. Existing module screenshots remain under `docs/evidence`.
- Hardware/provider evidence: simulations passed; no physical Windows printer/helper/drawer, deployed SMTP, hosted PITR/object restore, production-size load or real fiscal authority/integrator environment was exercised.
- Decisions/contract deviations and downstream impact: the agreed application scope is locally complete. S01–S28 classify as 22 locally/simulated passes and six partial environmental/provider/hardware gates. No excluded feature was reintroduced.
- Remaining blockers/limitations and next action: create an immutable release candidate, provision an isolated pilot environment, qualify the exact hardware/browser and SMTP, run hosted recovery/capacity tests, and complete merchant-specific fiscal/legal gates before the user authorizes a pilot.
