# T008 — Implement staff authentication, fixed roles and approvals

Version: 1.0 | Created: 2026-09-28 | Status: Verified — executed locally 2026-09-29

## Outcome

Provide owner, manager and cashier access with registered-till PIN switching and auditable manager approvals. Keep platform operator access distinct; do not build custom role design.

## Dependencies and decisions

- Prerequisites: [T007](007-merchant-and-store-tenancy.md)
- User-decision references: Q020, Q037, Q040 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Persist user/staff identity links, merchant/store access, fixed role assignments, device-bound staff sessions, PIN hashes, approval records and security audit events. Employment records later created in T029 can exist without login access.

## Required implementation or research work

- Implement secure login/logout/recovery, CSRF/session controls and rate limits using researched stack practices. PIN switching is allowed only on an authorized device and for permitted staff. Do not treat a short PIN as a public internet password.
- Enforce the frozen permission matrix server-side and mirror it in navigation. Restrict payroll/salary configuration and approval to owners by default; managers can manage authorized attendance/leave/operating functions. Cashiers cannot browse administrative financial data.
- Create reusable approval UX requiring the correct manager identity and a reason where applicable. Re-authentication/approval must not lose the cart. Audit failed and successful sensitive actions without logging secrets. Document offline authorization snapshot contract for T023.

## Interfaces, permissions and user experience

Auth endpoints never reveal secret hashes. Approval tokens are short-lived, action/scope-bound and single-use where needed, not blanket admin sessions. User deletion/deactivation preserves financial attribution. Owner/admin enhanced authentication is researched/implemented according to T004, not implied by a role name.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test every role against sensitive API operations, direct URLs and other-store IDs. Verify PIN attempt limits, session revocation and approval replay rejection. Payroll amounts stay hidden from unauthorized users. Staff switch preserves register context safely and applies theme preference without mixing carts.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; migrations, fixed-role authorization services, session and approval actions, API routes/resources, Vue access preview, tests, contracts and evidence images are present in the working tree.
- Files/modules changed: added the `Auth` domain permission matrix and authorizers; password login/logout/recovery controllers; active-user middleware; device PIN session, approval issue/consume and membership administration actions; authentication requests/resources; platform-operator separation; rate limits; and a role-aware access preview whose staff switch preserves the active cart. Updated the shared security/offline contract and OpenAPI document.
- Schema/API/event changes: added registers, device enrollments, staff device sessions, scoped approvals, security audit events and platform operators; added active/deactivation fields to users and PIN/revocation fields to merchant memberships. Added login/logout/reset endpoints, registered-device PIN switching, five-minute one-time approvals, owner-only membership administration and a fixed-permission endpoint. Tokens, PINs and passwords are hash-only or one-time response values and never appear in resources or audit metadata.
- Tests actually run and results: PHPUnit passed 47 tests/189 assertions against disposable MySQL 8.4, including active/inactive login shape, recovery non-enumeration, throttles, the full fixed-role matrix, payroll confidentiality, platform-operator separation, direct URL and cross-store/tenant denial, PIN switching/revocation, approval expiry/scope/target/replay/self-approval rejection, membership administration and preservation of attribution. PHPStan passed with zero errors and Pint passed. Prettier, Vue TypeScript and ESLint passed; Vitest passed 21 tests; production Vite build passed; Playwright Chromium passed 7 tests. The OpenAPI YAML parsed as 3.1.0 with 32 paths/47 schemas, and the documentation validator passed 71 Markdown files and 703 local links.
- Light/dark visual evidence: inspected [access light](../evidence/t008-access-light.png) and [access dark](../evidence/t008-access-dark.png). Both show login/recovery, a registered till, cashier/manager/owner switching, retained cart state, role-filtered navigation, owner-only payroll visibility, consumed approval state and loading/empty/error examples. Theme preference remains independent of cart and authorization state.
- Hardware/provider evidence: Chromium simulation only. PIN entry is constrained by an enrolled-device secret in the server contract, but physical Windows till/device provisioning remains T010 and offline browser storage/lease behavior remains T023.
- Decisions/contract deviations and downstream impact: fixed owner/manager/cashier roles are server-authoritative; there is no custom role editor. Platform operators use a separate record and gain no merchant membership permissions. Owners alone administer staff and see/manage payroll amounts by default; managers retain operational approval powers and only their own payroll view; cashiers retain narrow checkout/own-record access. Approval tokens are permission/scope/target bound, five minutes long and single-use. The T023 offline authorization snapshot is now frozen as signed, versioned, encrypted and device-bound, with no raw/reusable PIN verifier or payroll/secret data exposed.
- Remaining blockers/limitations and next action: register enrollment lifecycle and secret rotation are intentionally deferred to T010; durable offline authorization storage, expiry behavior and reconnect reconciliation are intentionally deferred to T023. No external identity provider or physical-device claim is made. Proceed to T009 platform administration and manual SaaS subscriptions.
