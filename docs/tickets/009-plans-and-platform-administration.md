# T009 — Implement platform administration and manual SaaS subscriptions

Version: 1.0 | Created: 2026-09-28 | Status: Verified — executed locally 2026-09-29

## Outcome

Allow authorized platform administrators to provision merchants, define plans/entitlements, record subscription payments and activate/renew service. No automated recurring billing or payment acquisition is introduced.

## Dependencies and decisions

- Prerequisites: [T008](008-authentication-and-fixed-roles.md)
- User-decision references: Q026, Q024 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create plans, entitlement definitions, subscriptions, subscription payment records, lifecycle history and support audit records. Keep platform billing separate from merchant sales, supplier balances and payroll.

## Required implementation or research work

- Build platform screens for merchant state, plan assignment, renewal dates and manual payment recording. Validate duplicate references and audit all entitlement changes. Define trial/grace/restricted/cancelled states from T004 without inventing legal billing terms.
- Show owners a clear read-only subscription/status view and actionable renewal information. Restriction behaviour must preserve export/support access and submission of valid pending offline sales; it cannot erase merchant records or silently grant unlimited new selling.
- Provide controlled support diagnostics, with tenant context and access audit. Define offboarding/export and retention hooks for later operational ticket. Avoid exposing production secrets or cross-merchant reports through support UI.

## Interfaces, permissions and user experience

Platform APIs require platform authorization rather than merchant-owner role. Entitlement checks are domain services used by later modules, not only hidden menu items. Add idempotent manual payment/renewal commands and a compatibility hook for pending offline submissions.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test unauthorized platform access, duplicate renewal submission, grace/restriction transition, and separation from POS tender records. Ensure a changed subscription does not delete or misassign pending transaction evidence. Verify admin/owner views in light and dark.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; platform domain services, migrations, API controllers/resources, entitlement checks, support controls, Vue platform preview, tests, OpenAPI updates and visual evidence are present in the working tree.
- Files/modules changed: added the `Platform` domain subscription states/capabilities, manual payment and transition actions; platform-operator middleware; plan, provisioning, subscription, payment, support-grant and diagnostics controllers; validated requests/resources; owner read-only subscription endpoint; and the two-theme platform/owner preview. Expanded the shared operations contract with restriction and offboarding hooks.
- Schema/API/event changes: added plans, plan entitlements, one subscription per merchant, subscription payments, immutable lifecycle history, expiring support grants and a separate platform audit stream. Added nine platform/owner API paths for plans, atomic merchant/owner/store/subscription provisioning, assignment, lifecycle transitions, idempotent manual renewal, grants, bounded diagnostics and owner status. Manual payment uniqueness is merchant-scoped by idempotency key and external reference; a reused key with changed details returns conflict. Merchant status now includes the frozen `restricted` and `closed` values, while no closure command is exposed before export/retention gates exist.
- Tests actually run and results: MySQL 8.4 `migrate:fresh` passed all 25 migrations. PHPUnit passed 60 tests/252 assertions, including platform access denial, operator/owner separation, provisioning and owner notification, duplicate subscription prevention, payment replay and changed-payload/reference conflicts, lifecycle validity, grace/restriction capability behavior, owner/manager visibility and purpose-bound diagnostics audit. PHPStan passed with zero errors and Pint passed. Prettier, Vue TypeScript and ESLint passed; Vitest passed 21 tests; the production Vite build passed; Playwright Chromium passed all 8 browser journeys. OpenAPI 3.1 parsed with 41 paths/61 schemas and the documentation validator passed 71 Markdown files/705 local links before this completion update.
- Light/dark visual evidence: inspected [platform light](../evidence/t009-platform-light.png) and [platform dark](../evidence/t009-platform-dark.png). Both show merchant plan/renewal state, effective new-operation and recovery capabilities, one-time payment result, reasoned restriction control, the read-only owner view and loading/empty/duplicate-reference states. The global theme toggle remains immediate and persisted.
- Hardware/provider evidence: not applicable. Payments are manually recorded external evidence; no billing gateway, recurring charge, bank verification or production provider was contacted.
- Decisions/contract deviations and downstream impact: plans describe operational limits but do not split retail/hospitality features; the agreed application remains one capability set. Only platform operators can use platform APIs, and provisioning creates the designated owner without adding the operator as a merchant member. `trial`, `active` and `grace` allow new operations; `restricted` and `cancelled` deny them while export, support and pending-offline submission remain allowed. Support diagnostics require an unexpired merchant/scope/purpose grant and expose bounded counts/status only. `closed` remains a recognized frozen state but is not callable until T033/T036 fulfill export and retention prerequisites.
- Remaining blockers/limitations and next action: no automated recurring billing or payment acquisition exists by design. T023 must apply the compatibility capability while ingesting pending offline commands; T033/T036 must implement the export/retention closure gate. Proceed to T010 register provisioning and versioned settings.
