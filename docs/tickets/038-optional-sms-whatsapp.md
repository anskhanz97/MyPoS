# T038 — Merchant integration hub with opt-in SMS and WhatsApp

Version: 1.1 | Created: 2026-09-28 | Status: Implemented — provider sandbox validation pending merchant credentials

## Outcome

Provide optional provider-backed SMS/WhatsApp receipt channels and a merchant-owned integration hub with on/off settings. The implementation remains outside the launch dependency chain and does not alter phone-only member lookup.

## Dependencies and decisions

- Prerequisites: [T034](034-email-and-in-app-notifications.md), [T037](037-integrated-pilot-and-release-gate.md)
- User-decision references: Q032 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create channel-provider configurations, protected credentials, templates/approval metadata, consent records, usage/cost metadata, delivery attempts and callback state, reusing the notification abstraction.

## Required implementation or research work

- Research current Pakistan support, provider terms, template/consent requirements, pricing and delivery callbacks before selecting integration. Do not purchase messaging or contact providers without authorization.
- Allow authorized merchant setup/test, enable/disable per channel and allowed message class. Enabling without valid configuration must fail clearly. Disabling stops new sends and handles queued work according to a documented policy; it does not silently erase delivery history.
- Implement receipts/allowed alerts using minimum necessary data, idempotent outbox, opt-out handling and rate/cost controls. Keep transactional and marketing purposes separate. Phone-only loyalty lookup remains the chosen launch behaviour; do not change it to mandatory OTP as a side effect.
- Show truthful accepted/delivered/failed states based on provider evidence. Use a sandbox/test sink until real sends are explicitly authorized. Record provider-dependent restrictions and costs.

## Interfaces, permissions and user experience

Provider adapters implement existing NotificationChannel contract; callbacks authenticate and deduplicate. Settings responses never expose stored secrets. Feature entitlements/toggles work per merchant without affecting email/in-app alerts.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test toggles, incomplete setup, queued sends after disable, duplicate callbacks, invalid recipient, opt-out, failure retry and tenant isolation. No unintended real messages. Light/dark settings and delivery screens verified. Document actual provider validation separately.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

Every provider is disabled until an owner supplies valid configuration and activates it. Do not auto-enable a provider or turn messaging into a requirement for email, loyalty or checkout.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: Implemented in the working tree; live provider activation remains merchant-controlled.
- Execution date / application commit or working-tree evidence: 2026-09-29; first commit intentionally pending user review.
- Files/modules changed: merchant integration models and migration, provider catalog/manager/transports, message job, receipt-message and opt-out APIs, authenticated callback controller, integration hub Vue screen and browser coverage.
- Schema/API/event changes: added merchant integrations with encrypted credentials, notification consents, callback deduplication, message delivery/cost state, owner settings/test endpoints, staff-safe active configuration endpoint, receipt SMS/WhatsApp endpoint, opt-out endpoint and Meta/Twilio callbacks.
- Tests actually run and results: focused PHP feature coverage verifies owner authorization, tenant isolation, encryption/redaction, incomplete activation, secret preservation, optimistic versioning, sandbox delivery, disable behavior, callback authentication/deduplication and cost metadata. Frontend type checking and linting pass. Browser and build results are recorded in the completion report for this work session.
- Light/dark visual evidence: integration hub is included in the shared theme shell and exercised in both themes by Playwright.
- Hardware/provider evidence: simulated only. Sandbox sends never contact a provider. Live connection checks are read-only and do not send messages or create payments. Merchant Meta, Twilio, SMTP, Microsoft, Google and Stripe credentials are required for provider-side verification.
- Decisions/contract deviations and downstream impact: the requested hub also covers SMTP, Drive, OneDrive, Stripe connector configuration and manual bank/Raast/Easypaisa/JazzCash destinations from the Tasmiya reference. Stripe is configuration and read-only credential verification only; automatic card collection remains outside the approved POS payment scope.
- Remaining blockers/limitations and next action: review the local UI, then supply merchant provider credentials in the integration hub and validate each provider in sandbox before any live activation.
