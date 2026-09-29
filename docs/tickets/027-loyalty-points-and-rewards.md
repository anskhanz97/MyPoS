# T027 — Implement merchant-wide points, rewards and refund reversals

Version: 1.0 | Created: 2026-09-28 | Status: Verified — 2026-09-29

## Outcome

Provide merchant-specific points/rewards across all stores, including independent operational catalogs/customers. Earn/redeem/expire/reverse are distinct ledger operations; no tiers, visit schemes, gift-card balances or payment-card linking.

## Dependencies and decisions

- Prerequisites: [T022](022-sale-finalization-and-tenders.md), [T026](026-returns-and-exchanges.md), [T013](013-customers-and-local-records.md)
- User-decision references: Q017, Q019, Q028, Q041 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create programme/rule versions, merchant-wide members, local customer links, points ledger/projection, reward definitions, redemption reservations/commits and expiry/reversal references. Normalize phone within merchant scope.

## Required implementation or research work

- Configure earning/redemption/expiry and reward eligibility with clear monetary/tax treatment from T004. Prevent double earning on retry and double spending under concurrent checkout. Quote may reserve temporarily; finalization commits or releases according to the frozen lifecycle.
- Integrate staff checkout online member lookup/redemption and sale/return events. Offline queues earn only after authoritative acceptance; redemption is blocked offline. Fully paid sale equations account for points consistently without creating customer monetary debt.
- Define reversal when earned points were already spent, negative points handling and later earning repayment if selected by contract. Partial returns reverse allocated earning exactly once. Expiry operations are scheduled/idempotent and retain reasons.
- Build manager member/reward/rule/ledger views and cashier limited lookup. Separate merchant-wide identity from store-specific customer history. Cross-merchant member matching is forbidden. Public portal follows T028 and cannot reuse private DTOs.

## Interfaces, permissions and user experience

Internal loyalty commands require tenant and sale identity; member search can accept normalized phone only within authorized context. Ledger rebuild must equal projection. Public reward labels are separate from secret redemption credentials. Outbox ordering/retry handles late sale/return events.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test cross-store earn/redeem in independent mode, concurrent redemption, duplicate earn, expiry replay, refund after redemption and offline earn replay. Check cash totals/report reconciliation and tenant isolation. Rules/ledger/cashier view pass both-theme visual checks.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — 2026-09-29.**
- Execution date / application commit or working-tree evidence: 2026-09-29 in the current MyPoS working tree; the repository still has no baseline commit, so file paths, passing commands and captured evidence identify this execution.
- Files/modules changed: `2026_09_29_102000_create_loyalty_points_tables.php`; loyalty programme/version, reward, point balance/entry/consumption and redemption-reservation models; `LoyaltyService`; loyalty and redemption controllers/routes; cart pricing, sale-finalization and sale-return integrations; expiry/reconciliation console schedules; `LoyaltyPreview.vue`; focused feature/concurrency/browser tests; OpenAPI and shared contract documents.
- Schema/API/event changes: added one merchant programme, append-only programme versions, reward definitions, four-decimal point ledger and projection, FIFO earn-consumption evidence, and five-minute cart reservation records. Added authenticated programme/member/ledger/adjustment routes and register/cart reserve/release routes. Quote payloads freeze reward identity/points/discount; finalization commits redemption and sale earn atomically; returns append cumulative source-linked reversal; expiry and reconciliation commands are scheduled. Ledger writes emit `loyalty.points.{kind}` outbox events.
- Tests actually run and results: clean MySQL migration rebuild passed. `LoyaltyPointsTest` plus `LoyaltyConcurrencyTest` passed **3 tests / 55 assertions** covering earn/redeem retry, original-return reversal after spending, negative repayment state, expiry replay, projection repair, manual adjustment permission/idempotency, tenant lookup and parallel cross-store reservation. The focused offline reconciliation case passed **1 test / 30 assertions**, including server-only offline earning and replay. Related sale-finalization/return regression selection passed seven tests before the corrected concurrency rerun. PHPStan passed with zero errors; Pint, TypeScript, ESLint, Prettier, production build, OpenAPI YAML parse (**126 paths / 246 schemas**) and the documentation checker passed.
- Light/dark visual evidence: focused Playwright loyalty flow passed one test. Reviewed [light](../evidence/t027-loyalty-light.png) and [dark](../evidence/t027-loyalty-dark.png) captures at desktop width; the responsive layout, negative-points explanation, ledger, reward reservation and both theme palettes remain legible.
- Hardware/provider evidence: not applicable. Loyalty is internal application state; no external provider or till hardware is claimed.
- Decisions/contract deviations and downstream impact: points are floored to four decimals from sale net using the sale-time eight-decimal earning rate. Redemption uses a current locked read of active reservations; this was required because a repeatable-read aggregate could miss a reservation committed while waiting for the member lock. FIFO consumption prevents an already-spent earn from expiring a later balance. Returns may create negative points, which later earns repay without money debt. Offline commands can earn only after authoritative finalization and cannot redeem. T028 may expose only the explicit masked DTO and must not reuse the authenticated ledger/member response.
- Remaining blockers/limitations and next action: no T027 blocker. Public join/phone-only limited lookup, public abuse controls and the public mobile UI belong to T028. Full release-scale load, worker/scheduler supervision and recovery qualification remain T036/T037 gates. Proceed to T028.
