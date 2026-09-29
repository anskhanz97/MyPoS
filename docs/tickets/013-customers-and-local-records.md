# T013 — Implement customer profiles and store-scoped operational records

Version: 1.0 | Created: 2026-09-28 | Status: Verified — 2026-09-29

## Outcome

Manage customer profiles and purchase association without customer credit accounts. Preserve independent-store data boundaries while enabling later merchant-wide loyalty links.

## Dependencies and decisions

- Prerequisites: [T007](007-merchant-and-store-tenancy.md), [T008](008-authentication-and-fixed-roles.md)
- User-decision references: Q014, Q017, Q025, Q041 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create operational customer records, permitted contact/preferences fields, customer groups and optional loyalty identity links. Normalize phone data with Pakistan-aware parsing; do not merge unrelated merchants or stores by phone alone.

## Required implementation or research work

- Build customer list/search/create/edit/archive, duplicate review and sale association APIs. Shared mode shares operational profiles; independent mode keeps each store's record distinct. Central authorized views label store ownership.
- Capture only useful contact fields and explicit notification/marketing preferences. An email receipt request and marketing consent are not the same preference. Customer absence must not block anonymous cash sales.
- Define safe links to later loyalty members, never automatic cross-store exposure of full profiles/history. Customer edit/archive cannot rewrite historical sale customer snapshots. No debt balance, customer credit limits, unpaid statements or layaway screens.

## Interfaces, permissions and user experience

Customer API is authenticated and scoped. Public membership endpoints are not implemented here and must not reuse this full response serializer. Purchase-history association will integrate after T022. Search is bounded and normalized without leaking cross-tenant matches.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test same phone across merchants and independent stores, shared-mode lookup, archive/history preservation, consent separation and anonymous checkout support. Unauthorized store lookup fails. Form validation and error/empty states work in both themes.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified — 2026-09-29.
- Execution date / application commit or working-tree evidence: executed 2026-09-29 in the application working tree; repository has no baseline commit yet, so file state and the commands below are the evidence.
- Files/modules changed: customer groups/profile migrations and models; `App\Domain\Customers` scope, normalization, writer and snapshot services; customer/group/loyalty-link requests, controllers, resource and routes; `CustomerProfileTest`; customer Vue preview, shared styles and browser journey; OpenAPI, architecture, domain-invariant and research records.
- Schema/API/event changes: two migrations create scoped customer groups and extend operational customers with canonical contact values, address, independent receipt/marketing preferences, archive/version and group ownership constraints. Six OpenAPI path families now cover groups, customer collection/detail, duplicate review, sale snapshot and loyalty linking. The maintained contract parses with 65 paths and 112 schemas. Customer mutations append security audit records; no outbox event or sale table was invented ahead of its owning ticket.
- Tests actually run and results: MySQL `migrate:fresh --force` passed all 45 migrations; focused customer suite passed 5 tests/53 assertions; final full PHPUnit passed 97 tests/513 assertions; PHPStan passed with 0 errors; Pint passed; Prettier check, Vue typecheck and ESLint passed; Vitest passed 21 tests; Vite production build passed; Playwright passed 12 browser journeys; documentation checker passed 71 Markdown files, 714 local links and all 36 launch dependency predecessors; Symfony YAML parsed the OpenAPI contract successfully.
- Light/dark visual evidence: [light customer workspace](../evidence/t013-customers-light.png) and [dark customer workspace](../evidence/t013-customers-dark.png), both captured by the passing Playwright journey and visually inspected for readable hierarchy, forms, state, consent and duplicate-review panels.
- Hardware/provider evidence: not applicable; phone normalization is structural and does not claim number assignment, reachability, SMS delivery or identity verification.
- Decisions/contract deviations and downstream impact: operational profiles follow the locked shared/independent mode; exact phone/email duplicate review is advisory within that scope. Pakistan phone values normalize to `+92`; receipt email and marketing consent are independent. Cashiers receive/write the minimal checkout subset. Explicit loyalty links require the same merchant and canonical phone but do not merge or expose other store profiles. T021/T022 must keep customer optional and copy the sale-association snapshot into the sale. T027 may use the link but must retain merchant-wide ledger authorization. No excluded customer credit/debt/layaway capability was added.
- Remaining blockers/limitations and next action: purchase history is intentionally unavailable until T022 creates posted sales; public loyalty uses its own limited serializer in T028. T013 has no blocker. Continue with T014.
