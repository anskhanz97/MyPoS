# T029 — Implement staff records, attendance, rota and leave

Version: 1.0 | Created: 2026-09-28 | Status: Verified — 2026-09-29

## Outcome

Build the real workforce module requested by the user rather than reproducing existing coming-soon screens. Keep employment records and attendance separate from login rights and cash sessions.

## Dependencies and decisions

- Prerequisites: [T008](008-authentication-and-fixed-roles.md), [T007](007-merchant-and-store-tenancy.md)
- User-decision references: Q020, Q021, Q037 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create employment records, work schedules/rota assignments, clock-in/out events, attendance corrections, leave types/requests/balances/approvals and payroll input snapshots. Every correction records actor/reason.

## Required implementation or research work

- Build staff list/detail, rota calendar/list, clock actions, attendance review and leave request/approval screens. Owner manages employment/pay configuration; manager attendance/leave permissions follow the fixed matrix. Cashier sees only permitted own attendance/leave actions.
- Define overnight shifts, missed punches, duplicate clocks, breaks, lateness and overtime eligibility as configurable operational policy. Avoid inventing statutory rules; T003/T030 identify legal inputs separately. A cashier switching PIN is not automatically clocking in or opening a cash session.
- Handle overlapping rota assignments, leave conflicts, balances and approval cancellations with audit. Freeze approved payroll-period inputs and route later corrections into explicit adjustments. Do not silently rewrite posted salary results.
- Provide attendance exports and a typed payroll-input service. Privacy applies to salary/leave notes; do not expose sensitive notes on public displays or cashier lists.

## Interfaces, permissions and user experience

Authenticated workforce operations use scoped staff identities; staff ID is not proof of permission. Clock actions are retry-safe. This module remains online for writes at launch; offline checkout does not imply offline rota/payroll editing.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test duplicate/missing punches, overnight shift, leave overlap/approval/reversal, unauthorized salary field access and changed attendance after period freeze. Export scope is correct. Calendar/table/forms work in both themes and keyboard navigation.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified — 2026-09-29.
- Execution date / application commit or working-tree evidence: working tree on 2026-09-29; see migration `2026_09_29_104000_create_workforce_tables.php`, workforce domain/controller/models and `resources/js/views/WorkforcePreview.vue`.
- Files/modules changed: workforce schema/models/service/controller/routes, OpenAPI paths, management gallery, browser/API tests and `docs/frontend/workforce.md`.
- Schema/API/event changes: nine workforce tables; staff/policy/rota/attendance/leave/export/payroll-input endpoints; append-only attendance events and security-audit leave/correction events.
- Tests actually run and results: clean MySQL migration; `WorkforceTest` 3 tests/34 assertions after the corrected UTC fixture (focused overnight retest 1/12); workforce browser scenario 1 passed; scoped PHPStan 0 errors; Pint, TypeScript, ESLint, Prettier and Vite build passed. Final aggregate checks are recorded in T037.
- Light/dark visual evidence: `docs/evidence/t029-workforce-light.png` and `docs/evidence/t029-workforce-dark.png`, both manually inspected for contrast, layout and state clarity.
- Hardware/provider evidence: not applicable; workforce writes are online and use no external provider/hardware.
- Decisions/contract deviations and downstream impact: implements Q020/Q021/Q037; payroll remains input-only until T030. No statutory calculation and no automatic tie between PIN switching, clocking and cash sessions.
- Remaining blockers/limitations and next action: none for T029; execute T030 operational payroll using immutable workforce snapshots.
