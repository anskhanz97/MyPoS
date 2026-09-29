# T028 — Build the single-form public loyalty join and limited profile page

Version: 1.0 | Created: 2026-09-28 | Status: Verified — 2026-09-29

## Outcome

Recreate the agreed existing loyalty experience in Vue: merchant URL, one joining/returning form and a view-only points/rewards page. Phone-only lookup is intentionally limited, not authenticated identity verification.

## Dependencies and decisions

- Prerequisites: [T027](027-loyalty-points-and-rewards.md), [T006](006-frontend-shell-and-themes.md)
- User-decision references: Q033, Q036, Q039, Q041 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create public merchant/program slug mapping, minimal request/response DTOs, member enrollment idempotency/deduplication and throttling records. Keep enrollment commands separate from lookup refresh.

## Required implementation or research work

- Inspect the existing React Join.tsx, Laravel JoinController and success view for UX context. New visitors submit required phone and optional allowed profile fields; existing members see only points, available rewards and masked identity. Use Pakistan-aware normalization, not the original UK default.
- The UI may use one form, but repeated refresh must call a read-only lookup endpoint. Repeated join does not create duplicate member or welcome credit. Scope to active merchant/program and validate any optional store attribution against the merchant.
- Do not return full member model, full contact details, history, receipts, notes, editable fields, redemption token or QR credential usable as authorization. No customer ordering/redemption/profile editing. Avoid claims that entering a phone proves identity or makes the page private.
- Add bounded lookup/enrollment rates and abuse monitoring without introducing mandatory SMS/PIN contrary to the chosen flow. Provide friendly invalid/inactive state and responsive mobile layout with light/dark toggle. Separate marketing consent from enrollment.

## Interfaces, permissions and user experience

Proposed public endpoints: GET programme info; POST enrollment; POST limited lookup to avoid phone in URL/query logs. Responses are explicit allowlisted DTOs. No endpoint should search arbitrary merchants if slug is missing. Do not leak matched-by-email private profile data.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Verify new/returning flows, duplicate concurrent join, phone normalization, disabled programme, cross-merchant/store manipulation, rate limits and refresh without writes. Assert response field allowlist and masked identity. Test mobile/light/dark and no member action can finalize a sale or redeem.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

C:\XEPOS\loyalty-portal\src\pages\Join.tsx; api-loyalty-xepos\app\Http\Controllers\Public\JoinController.php

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — 2026-09-29.**
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current MyPoS working tree; file paths, tests and captured evidence identify the uncommitted implementation.
- Files/modules changed: public enrollment/access-event migration and models; `PublicLoyaltyService`; `PublicLoyaltyController`; public rate limiters/routes; `PublicLoyaltyPreview.vue`; public feature/concurrency/browser tests; OpenAPI and shared contracts.
- Schema/API/event changes: added idempotent enrollment facts with optional same-merchant store attribution and independent marketing consent, plus HMAC-only public access telemetry. Added active programme GET, join POST and read-only lookup POST routes. Public DTOs are allowlisted to masked identity, points and affordable reward labels/thresholds; they contain no redemption authority.
- Tests actually run and results: clean MySQL migration rebuild passed. `PublicLoyaltyTest` passed **2 tests / 52 assertions** covering new/returning/replay flow, changed-key conflict, Pakistan normalization, response allowlist, refresh without membership/ledger writes, other-merchant store rejection, disabled programme, unknown phone and the 10/min lookup boundary. `PublicLoyaltyConcurrencyTest` passed **1 test / 4 assertions**, proving two parallel joins create one member, two enrollment facts and no welcome credit. PHPStan passed with zero errors; Pint, TypeScript, ESLint, Prettier, production build and OpenAPI parse (**127 paths / 248 schemas**) passed.
- Light/dark visual evidence: focused Playwright public flow passed one test at 390px width. Reviewed [light mobile](../evidence/t028-public-loyalty-light-mobile.png) and [dark mobile](../evidence/t028-public-loyalty-dark-mobile.png); masked identity, view-only reward language, theme toggle and responsive card layout are legible.
- Hardware/provider evidence: not applicable; no SMS, identity provider or redemption provider is part of the selected flow.
- Decisions/contract deviations and downstream impact: phone-only lookup remains intentionally limited and is visibly described as non-verifying. `display_name` is optional; a non-sensitive fallback is generated for a new member. Marketing consent is not required for enrollment. Unknown phone uses a normal 404 with no member fields; rate limiting and HMAC telemetry mitigate abuse without introducing the rejected PIN/OTP dependency. Refresh uses lookup and writes only separate abuse telemetry, never member, enrollment, balance or ledger state.
- Remaining blockers/limitations and next action: no T028 blocker. The Vue artifact is a contract-backed interaction preview inside the current shell; live environment routing/API hydration is part of final integrated wiring and T037 validation. Proceed to T029 workforce attendance, rota and leave.
