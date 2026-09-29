# T001 — Inventory and audit the five reference projects

Version: 1.0 | Created: 2026-09-28 | Status: Verified — completed 2026-09-28

## Outcome

Create a reproducible evidence-backed map of relevant XEPOS functionality without editing or operating the source applications. Distinguish working rules from models, placeholder screens, ingestion handlers and historical artifacts. This is a research ticket, not application implementation.

## Dependencies and decisions

- Prerequisites: None — start here.
- User-decision references: Q004, Q005; D001–D003 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Produce source-baseline.md, source-module-map.md, source-schema-map.md and source-capability-register.md under docs/research. Capture Git roots/revisions where available; otherwise use a dated file-hash manifest excluding secrets and generated dependencies. Record why the XEPOS root HEAD does not resolve rather than inventing a commit.

## Required implementation or research work

- Enumerate all five projects' application routes, screens, middleware, requests, controllers, models, migrations, observers, services, jobs, commands and tests. Skip vendor/node_modules/build artifacts and credentials. Trace relevant screen → request → domain logic → persistence → side effects.
- Classify every discovered capability as implemented, partial, placeholder, ingestion-only, excluded or unverified. Start with known payroll/rota/leave placeholders and the public member join flow. An excluded source feature gets a disposition, not a target specification.
- Reconcile migration histories, model assumptions, tenant/store identifiers and money/quantity casts. Record source-to-target reuse/adapt/replace/retire recommendations. Inspect test isolation before any runtime check; do not launch code using its existing environment or integration credentials.
- Trace purchase receiving, stock movement, invoice writing, refund handling, loyalty and reporting where present. Missing C# rules are not an investigation task. Flag source behaviour that needs a newly designed web counterpart.

## Interfaces, permissions and user experience

No application endpoint changes. Evidence entries include path/line or symbol, claim, confidence, scope decision and owning ticket. Separate source contracts from proposed target contracts. Review route aliases and duplicate concepts across projects without assuming similar names imply identical semantics.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Every application route group and relevant model family has an entry or an explicit exclusion. Payroll/rota/leave placeholders are not called complete. No secrets are included. Source fingerprints before/after remain unchanged. Identify unresolved schema/runtime facts rather than claiming a working migration from inspection alone.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

C:\XEPOS\api-retail-project; portal-retail-xepos; xepos-hq-hospitality; api-loyalty-xepos; loyalty-portal

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — completed.**
- Execution date / application commit or working-tree evidence: 2026-09-28. Five nested Git revisions and per-file SHA-256 manifests are recorded in [source baseline](../research/source-baseline.md). Independent before/after manifests matched for repository HEAD, included file count and aggregate SHA-256 in every project. Full Git status also matched: four clean worktrees and the same pre-existing untracked `api-loyalty-xepos/docs/Loyalty _TILL_API_INTEGRATION.md` file.
- Files/modules changed: added [source baseline](../research/source-baseline.md), [module map](../research/source-module-map.md), [schema map](../research/source-schema-map.md), [capability register](../research/source-capability-register.md), machine-readable evidence under `docs/research/evidence`, and the read-only [audit generator](../../scripts/audit_xepos.py). No file under `C:\XEPOS` changed.
- Schema/API/event changes: none. This was static research only.
- Tests actually run and results: `scripts/check-docs.py` PASS — 55 Markdown files, 38 tickets, 577 local links, zero errors. `scripts/audit_xepos.py before` and `after` completed; explicit comparison returned `FINGERPRINT_COMPARISON=PASS`. No XEPOS suite was run. Retail and loyalty declare SQLite memory databases, while hospitality comments those settings out and includes `DatabaseTransactions` integration tests that may reach its configured database.
- Light/dark visual evidence: not applicable; no UI was introduced. The two-theme contract remains mandatory for later UI tickets.
- Hardware/provider evidence: not applicable; no hardware or provider operation was performed.
- Decisions/contract deviations and downstream impact: no contract deviation. Recipes/ingredients/preparation, reservations/queues/delivery, and missing C# rule recovery remain completely excluded. T004 must replace ambiguous source tenant IDs, float money casts, editable journals and separate invoice/sale/tender writes with canonical target contracts. T014, T018, T022, T026 and T027 receive the detailed ledger/transaction findings.
- Remaining blockers/limitations and next action: source runtime correctness, deployed database state and provider behavior remain intentionally unverified and are not T001 acceptance blockers. Proceed to T002 when requested; do not execute it as part of T001.
