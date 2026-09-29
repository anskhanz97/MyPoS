# T034 — Implement email receipts and in-app operational alerts

Version: 1.0 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Deliver launch email/PDF receipts and operational in-app alerts through a retry-safe notification service. SMS/WhatsApp remain off until T038 is explicitly requested.

## Dependencies and decisions

- Prerequisites: [T024](024-receipts-printing-and-drawer.md), [T027](027-loyalty-points-and-rewards.md), [T030](030-operational-payroll.md), [T033](033-imports-and-exports.md), [T009](009-plans-and-platform-administration.md)
- User-decision references: Q032, Q030 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create notification preferences, templates, delivery jobs/attempts, deduplication identities and in-app alert read states. Separate transactional receipt requests from marketing consent.

## Required implementation or research work

- Implement email sending through a configured provider abstraction, safe test sink and durable outbox. Receipt send/retry is independent of sale success and cannot repeat payment/stock. Use sale snapshots and authorized short-lived links or controlled attachments.
- Provide email settings/test facility limited to authorized users, receipt send/resend action and truthful delivery status. Acceptance by an email provider is not proof the customer read a receipt. Do not email real customers while running tests.
- Add useful in-app alerts for expiring batches, unresolved offline submissions, failed printing/fiscal/email work and other agreed operating exceptions. Scope alerts by role/store and avoid noisy duplicates. Sensitive payroll notifications must not disclose pay amounts in broad alerts.
- Enable optional purchase-order/payslip sending only within their existing workflow permissions. Define future channel interface and toggles without rendering working SMS/WhatsApp features prematurely.

## Interfaces, permissions and user experience

Notification requests reference source entity, audience, channel and deduplication key. Webhooks, if selected provider uses them, are authenticated/idempotent. Never include provider secrets in frontend settings responses or logs.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test duplicate event, provider timeout/retry, unsubscribe/transactional preference separation, unauthorized resend and invalid recipient. Verify PDF/link authorization and sensitive content. UI states/alerts work in light/dark. Use test sink; record real-provider validation separately.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Verified 2026-09-29.
- Execution date / application commit or working-tree evidence: 2026-09-29 working tree; repository has no baseline commit.
- Files/modules changed: notification migrations/models, delivery service/job, receipt mailable/template, notification controller/routes, Vue center, tests and documentation.
- Schema/API/event changes: added non-secret email settings, per-user operational preferences, delivery/attempt outbox and role/store alerts with per-user read state; added receipt/test/settings/status/alerts APIs.
- Tests actually run and results: `NotificationTest` 1 integrated test / 36 assertions; scoped PHPStan zero errors; clean migration, TypeScript, ESLint, Vite and Playwright passed.
- Light/dark visual evidence: `docs/evidence/t034-notifications-light.png` and `docs/evidence/t034-notifications-dark.png`, generated and visually reviewed.
- Hardware/provider evidence: Laravel `Mail::fake` is the safe sink; no real customer or provider was contacted. Real provider credentials, sender-domain verification and deliverability remain deployment qualification gates.
- Decisions/contract deviations and downstream impact: provider acceptance is recorded as `sent` but never presented as read. Transactional receipts ignore marketing opt-out by design; operational preferences do not create marketing consent. Payroll alert text is sanitized server-side.
- Remaining blockers/limitations and next action: no code blocker; production transport qualification belongs to T037. T035 implements only fiscally applicable verified adapters.
