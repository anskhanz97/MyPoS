# T006 — Build the UI foundation with complete light and dark themes

Version: 1.0 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Create the shared Vue design system and shells that every feature uses. Both bright/light and dark modes are first-class and easily toggled before business screens are added.

## Dependencies and decisions

- Prerequisites: [T005](005-application-foundation.md)
- User-decision references: Q006, Q030; D005 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create semantic theme tokens, preference handling, shared components, typography/layout standards, component gallery, management/checkout/public shells and screenshot baselines. Document the chosen visual direction and how to extend tokens.

## Required implementation or research work

- Apply frontend-design skill with operational clarity. Implement all semantic surfaces, text, borders, focus, inputs, status and chart colors. Avoid hard-coded component-specific light-only colors. Choose readable licensed typography with offline fallback.
- Provide accessible visible theme toggles, persistence and pre-paint restoration. Switching preserves cart/form state and focus. Define staff preference and offline register fallback; public view preference is separate. Keep print/PDF styles white-background and readable.
- Build buttons, forms, quantity/money fields, dialogs, approval prompts, tables, filters, tabs, badges, toasts and loading/empty/error states. Show store/register context and reserved areas for connectivity/pending work. Navigation grows as tickets implement real modules.
- Qualify checkout layout on Windows till sizes and responsive management/member screens. Keyboard navigation, reduced motion, touch targets and contrast are acceptance requirements, not optional polish.

## Interfaces, permissions and user experience

Provide a typed theme preference API/store and token conventions. No business setting changes when toggling appearance. Do not add restaurant navigation or unavailable feature teasers. Component tests can use fixtures but user-facing operational screens must be truthful.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Light/dark screenshots cover component states and shells. Contrast/focus checks pass; keyboard toggle works. Reload restores choice without flash. Toggling a populated test form retains content. Narrow layout has no trapped controls. Print preview remains black-on-white. Record actual visual evidence.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; implementation and screenshot baselines are present under `resources/js`, `resources/css`, `tests` and `docs/evidence`.
- Files/modules changed: added the [register-ledger design contract](../frontend/design-system.md), semantic CSS tokens, typed staff/register/public theme preference service, pre-paint restoration, reusable button/badge/state/dialog/toast/theme controls, responsive management/checkout/public shell, and interactive component gallery.
- Schema/API/event changes: none. Theme state is local display preference only. Staff choices update a staff key and offline register fallback; public preference uses an independent key. No authorization or business setting is derived from theme state.
- Tests actually run and results: TypeScript check, ESLint and production Vite build passed. Vitest passed 17 tests including all 15 arithmetic fixtures and two theme-preference cases. Playwright Chromium passed 5 tests covering keyboard theme toggle, form retention, focus retention, reload restoration, staff/public isolation, 360px overflow, black-on-white print media, light/dark contrast at WCAG AA threshold, roving tab keyboard behavior, dialog Escape and focus restoration. Backend regression remained 7 PHPUnit tests/40 assertions at the T005 baseline.
- Light/dark visual evidence: inspected [management light](../evidence/t006-management-light.png), [management dark](../evidence/t006-management-dark.png), [checkout dark](../evidence/t006-checkout-dark.png), [public light](../evidence/t006-public-light.png), [narrow management](../evidence/t006-management-narrow.png), [states light](../evidence/t006-states-light.png) and [states dark](../evidence/t006-states-dark.png). Print media is captured in [print](../evidence/t006-print.png). Public preview contains no sale/void/table staff controls.
- Hardware/provider evidence: simulated 1280×720 Chromium and 360×800 responsive view only. Physical Windows till displays and peripherals remain T024/T037 evidence; no hardware/provider claim is made here.
- Decisions/contract deviations and downstream impact: no product-scope deviation. Local Windows faces (`Aptos`, `Segoe UI`, `Bahnschrift`) with system fallbacks avoid a network font dependency. The shell exposes only the truthful UI gallery until later tickets add authorized modules.
- Remaining blockers/limitations and next action: authenticated server-side preference mirroring cannot exist before T008, so the typed local/register fallback contract is ready for that integration. Proceed to T007 merchant/store tenancy and locked ownership mode.
