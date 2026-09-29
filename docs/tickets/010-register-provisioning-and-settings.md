# T010 — Implement registers, device provisioning and versioned settings

Version: 1.0 | Created: 2026-09-28 | Status: Verified — executed locally 2026-09-29

## Outcome

Create register/device identity and configuration needed for checkout, offline bootstrap, printers and cash sessions. This ticket configures devices; actual printing and second-display operation follow later.

## Dependencies and decisions

- Prerequisites: [T008](008-authentication-and-fixed-roles.md), [T009](009-plans-and-platform-administration.md)
- User-decision references: Q006, Q007, Q022, Q023, Q034 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Persist registers, provisioned device credentials, revocation history, settings versions and register capabilities. Store timezone/business-day settings, feature options and merchant/store setting precedence with explicit ownership.

## Required implementation or research work

- Build register setup, device pairing/provisioning, revocation and diagnostics. Initial provisioning requires connectivity. Avoid placing unlimited merchant credentials in local storage; use scoped credentials/session mechanisms chosen in T004.
- Expose printer/drawer/customer-display settings without pretending unsupported hardware is connected. Provide clear device health placeholders only as actual unconfigured states. Pricing mode is merchant default plus store override, never per register.
- Design active-register ownership and multi-tab coordination hooks. Record the device/config version for future sale/offline commands. Settings changes are audited and effective-time/versioned; disabling a capability must not make outstanding records inaccessible.

## Interfaces, permissions and user experience

Device bootstrap returns only authorized store/register data and published configuration. Pair/revoke APIs use secure one-time flows. Offline leases and printing transport are finalized by T023/T024; this ticket provides their identity contract.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Pair valid device, reject cross-merchant pairing, revoke device and verify online denial. Concurrent tabs have identifiable ownership. Test merchant/store setting precedence, retained historical versions and theme persistence. No device credential appears in logs/exports.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; register/device domain actions, migrations, scoped APIs, versioned settings resolver, Vue register preview, tests, OpenAPI contract, operations notes and visual evidence are present in the working tree.
- Files/modules changed: added pairing-code and device-lifecycle models/migrations; extended registers and device enrollments with capability, credential, protocol, fence, settings-version and lease data; added issue/consume/revoke/publish actions, credential and settings resolvers, validated requests, minimal resources and controllers. Added the `Registers` management preview, responsive styles and browser journey. Existing PIN/approval session resolution now also denies inactive, revoked or lease-expired devices.
- Schema/API/event changes: added one-time hashed pairing codes and immutable device lifecycle events; added register capabilities/fencing and device credential/config/protocol/lease/revocation fields; added settings effective time with migration backfill. Added eight OpenAPI paths for register list/create/read, pairing issue/consume, device revoke/bootstrap and merchant/store settings history/publication, bringing the contract to 49 paths and 80 schemas. Lifecycle codes are `devices.enrollment.activated`, `.replaced` and `.revoked`; security audit codes include `register.created`, `device_pairing.issued` and `settings.version.published`.
- Tests actually run and results: MySQL 8.4 `migrate:fresh` passed all 28 migrations. The focused T010 suite passed 13 tests/88 assertions, covering owner-only register management, scoped uniqueness, fixed-role denial, one-time pairing/replay, hash-only storage, public throttling, tenant substitutions, controlled replacement, staff-session revocation, lease/revocation denial, safe bootstrap, settings precedence/history/effective time/version conflicts and rejection of register/secret settings. The full PHPUnit suite passed 73 tests/340 assertions. PHPStan passed with zero errors and Pint passed. Prettier, Vue TypeScript and ESLint passed; Vitest passed 21 tests; the Vite production build passed; Playwright Chromium passed all 9 browser journeys. The documentation validator passed 71 Markdown files and 709 local links.
- Light/dark visual evidence: inspected [register setup light](../evidence/t010-registers-light.png) and [register setup dark](../evidence/t010-registers-dark.png). Both show explicit hardware states, one-time pairing, active/read-only tab identity, fence/config context, merchant/store promotion-mode scope, version publication and loading/empty/error states. Theme switching remains immediate and persistent.
- Hardware/provider evidence: simulated configuration only. No scanner, printer, drawer, second display or Windows helper was connected or claimed. Actual receipt transport/drawer qualification belongs to T024 and display operation to T025.
- Decisions/contract deviations and downstream impact: device keys use a 64-character one-time secret with only an adaptive hash retained; pairing codes expire after ten minutes and are stored only as lookup hashes. Active device leases currently expire after 72 hours, while T023 owns renewal, encrypted offline bootstrap and the final outage policy. Device bootstrap publishes a stable writer-lock name, fencing epoch and composite `m{merchant}-s{store}` settings version; T023 must implement browser election and stale-fence rejection. Promotion mode remains merchant default plus store override with no register endpoint. Future-effective settings are retained without applying early.
- Remaining blockers/limitations and next action: T010 provides the identity/configuration contract and an identifiable multi-tab UI state, but does not yet arbitrate tabs or execute hardware operations. T023 implements actual offline lease renewal and Web Locks/BroadcastChannel ownership; T024 implements printer/drawer transports; T025 implements the optional display. Proceed to T011 catalog and product types.
