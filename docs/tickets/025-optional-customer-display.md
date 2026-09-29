# T025 — Implement optional second-screen customer display

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Support a merchant/register-controlled read-only customer display when a second screen exists. Checkout remains fully usable without it. This is part of launch capability, not a mandatory peripheral.

## Dependencies and decisions

- Prerequisites: [T021](021-checkout-cart-and-quotes.md), [T022](022-sale-finalization-and-tenders.md), [T024](024-receipts-printing-and-drawer.md)
- User-decision references: Q034; D005 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create display enablement/pairing settings, read-only register display session, minimal basket projection and disconnect/idle/completed states. Keep display credentials distinct from staff sessions.

## Required implementation or research work

- Provide enable/disable, open/pair display and connection status controls. Use a tested second browser window/session approach suitable for Windows if selected in T004. Enabling must not require directly connected scales.
- Show product names, quantities, applicable discounts and total; optionally a neutral completion message. Do not show internal costs, payroll, staff-only approvals, customer phone/email, loyalty private data or manual external-payment secrets.
- Handle cart updates, suspended cart, cancellation, payment complete, reconnect and staff/register switching. Clear prior customer's content at the defined idle/completion transition. Use its own supported light/dark preference and readable large typography.
- Design offline register-to-display updates for supported same-device context; do not claim networked kiosk features. Read-only session cannot issue finalize/refund commands.

## Interfaces, permissions and user experience

Publish a minimal display projection via the chosen local/session channel with origin/register checks. Do not broadcast full internal cart objects. Pairing expires/revokes and cross-register display access is rejected.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test no second screen, enable/disable, disconnect/reconnect, wrong register, read-only tampering, privacy clear and light/dark. Verify display actions never change the basket. Record actual two-window/monitor behaviour and limitations.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: executed 2026-09-29 in the current `C:\MyPoS` working tree; no application commit exists yet.
- Files/modules changed: added customer-display pairing/snapshot migrations and models, domain service, authenticated issue/revoke/publish controllers, public consume/read controllers, validation/rate limits, a typed same-device channel, cashier controls, a dedicated read-only display window and an independent display theme preference.
- Schema/API/event changes: added `customer_display_pairings` and `customer_display_snapshots`, with database-enforced one-active-per-register and composite ownership. Added one-time consume, display-token read, staff issue/revoke and server-derived projection routes. OpenAPI now parses as 118 paths/238 schemas. Pairing lifecycle actions use security audit events; high-frequency projections do not create broad outbox payloads.
- Tests actually run and results: focused MySQL feature tests passed 3 tests/46 assertions. A clean disposable-MySQL build passed all 97 migrations. Strict Composer validation, Pint, PHPStan, TypeScript, ESLint and Prettier passed; PHPUnit passed 152 tests/1,243 assertions in 1,382.694 seconds; Vitest passed 40 tests; the production build passed; Playwright passed all 25 scenarios in 1.5 minutes, including the real popup/BroadcastChannel flow. Documentation validation passed 71 Markdown files and 737 local links (88,845 words at execution); OpenAPI parsed as 118 paths/238 schemas.
- Light/dark visual evidence: [light display](../evidence/t025-display-light.png) and [dark display](../evidence/t025-display-dark.png) were captured from the actual second browser window and visually inspected. The display theme changed without changing the cashier window.
- Hardware/provider evidence: Chromium popup/BroadcastChannel/localStorage behavior was tested across two same-origin windows. No physical second monitor or networked kiosk was used or claimed.
- Decisions/contract deviations and downstream impact: selected the allowed second-browser-window approach. Online projections are derived from authoritative cart quotes/sales. Same-device offline projections use only the random channel, register ID, monotonic sequence and minimal display payload; no staff/display credential or contact data is broadcast. Pairing is optional and checkout remains usable when off or disconnected.
- Remaining blockers/limitations and next action: T037 must exercise the popup on the chosen Windows/browser build and a real extended desktop, including popup policy, monitor placement, reboot and display reconnect. Networked remote displays and proprietary monitor protocols remain outside launch. Proceed to T026.
