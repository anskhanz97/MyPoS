# T024 — Implement thermal/PDF receipts, print helper and cash drawer

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29 — physical hardware qualification remains a pilot gate

## Outcome

Print reliable sale receipts and PDFs from frozen sale data with optional local helper and drawer control. A committed sale survives printing failure; printing status is not payment status.

## Dependencies and decisions

- Prerequisites: [T022](022-sale-finalization-and-tenders.md), [T023](023-offline-pwa-and-reconciliation.md), [T010](010-register-provisioning-and-settings.md)
- User-decision references: Q007, Q030, Q032, Q034 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create receipt templates/configuration, print job identities/attempts, helper pairing configuration, device diagnostics, reprint audit and PDF generation. Record submission/acknowledgement/unknown physical completion distinctly.

## Required implementation or research work

- Implement selected helper transport and offline authorization established by T002. Do not expose signing private keys in frontend bundles or require an unavailable cloud signer for every offline print. Qualify a named Windows printer/driver/paper width combination.
- Render merchant/store details, sale identifiers, lines, discounts, tax, tenders/change and relevant verified fiscal fields. Offline receipts truthfully reflect pending status under T035 rules. Historical receipts use snapshots, not today's product names/prices.
- Add explicit reprint marked as copy where appropriate, print queue retry with duplicate protection and a manual recovery path for uncertain completion. Drawer commands require permission and reason when not tied to a sale; reconnect must not repeatedly open drawer.
- English templates preserve Unicode names as supported/tested. PDF/thermal output remains black-on-white regardless of screen theme. Email attachment/link integration follows T034. Avoid OS-wide unsafe configuration changes.

## Interfaces, permissions and user experience

Print API/bridge accepts narrowly scoped validated jobs from authorized origins/registers. Expose diagnostic states without leaking helper credentials. Printing cannot call sale finalize again. Local job IDs correlate to sale and attempt, not duplicate financial commands.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test helper absent, printer offline/out of paper, lost acknowledgement, explicit reprint, offline printing and drawer permissions. Render PDFs and inspect actual layout. Physical qualification must state hardware tested; simulation alone is not certification. Review setup/queue in both themes.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If no printer is attached, implement and simulate safely; keep physical-device acceptance explicitly pending.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29. Application behavior, contracts, rendered output and simulated helper recovery meet the software acceptance boundary. Physical hardware remains explicitly unqualified.
- Execution date / application commit or working-tree evidence: executed 2026-09-29 in the current `C:\MyPoS` working tree. The project has no application commit yet, so file/test evidence is authoritative.
- Files/modules changed: added `PrintJob`, `PrintAttempt` and `DrawerCommand` models/migrations; printing domain services for thermal rendering, PDF, Ed25519 authorization, job/attempt and drawer commands; receipt/helper/drawer controllers, requests and routes; offline print capability; `PrintOperationsPreview.vue`, helper bridge/types, backend/frontend/browser tests and evidence artifacts.
- Schema/API/event changes: added `print_jobs`, `print_attempts` and `drawer_commands`. Added authenticated receipt print/show/PDF and manual drawer routes plus device-key helper/drawer outcome callbacks. The OpenAPI contract now has 113 paths and 234 schemas. Added `printing.job.requested`, `printing.job.outcome_recorded`, `printing.drawer.authorized` and `printing.drawer.outcome_recorded` outbox contracts.
- Tests actually run and results: focused T024 feature suite passed 3 tests/70 assertions; existing offline reconciliation passed 3 tests/52 assertions. A clean disposable-MySQL migration passed all 95 migrations. Repository-wide PHPStan, Pint, strict Composer validation, TypeScript, ESLint and Prettier passed; PHPUnit passed 149 tests/1,197 assertions in 928.595 seconds; Vitest passed 37 tests; the production build passed; Playwright passed all 24 browser scenarios after scoping an older sale-outcome locator made ambiguous by the new printing panel. Documentation validation passed 71 Markdown files and 735 local links (88,310 words at execution); OpenAPI parsed as 113 paths/234 schemas.
- Light/dark visual evidence: [light print operations](../evidence/t024-print-light.png) and [dark print operations](../evidence/t024-print-dark.png) were rendered and visually inspected. Both show a posted sale, frozen receipt, helper outcome, explicit marked-copy reason and independent drawer evidence.
- Hardware/provider evidence: [sample 80 mm PDF](../evidence/t024-receipt-sample.pdf) was rendered and its [PNG](../evidence/t024-receipt-sample.png) visually inspected. The local helper boundary and paper-out/unavailable/unknown recovery are simulated. No QZ license/certificate, Windows printer driver, thermal printer, cutter or cash drawer was installed or physically tested; no model is called supported.
- Decisions/contract deviations and downstream impact: retained QZ Tray as an evaluation candidate rather than silently selecting a commercial/helper dependency. Implemented a provider-neutral loopback TLS protocol at version 1, Ed25519 signed jobs, device-key callbacks and a signed offline capability. Drawer results use their own callback identity instead of being embedded in a print attempt, preventing reconnect from reopening the drawer. Email delivery stays owned by T034; fiscal fields stay owned by T035.
- Remaining blockers/limitations and next action: T037 must select and pin the helper implementation/version and run the documented Windows/printer/driver/paper/drawer qualification matrix, including 100 sequential receipts, unplug/paper-out/spooler/reboot/security cases. T025 may reuse the device capability approach for the optional display. Proceed to T025.
