# T012 — Implement versioned prices and two promotion modes

Version: 1.0 | Created: 2026-09-28 | Status: Verified — executed locally 2026-09-29

## Outcome

Build a deterministic pricing engine and management UI with Manual Discounts and Advanced Promotions. Mode changes are an owner/authorized-manager configuration action, not a destructive deletion of rules.

## Dependencies and decisions

- Prerequisites: [T011](011-catalog-and-product-types.md), [T008](008-authentication-and-fixed-roles.md)
- User-decision references: Q018, Q022, Q028 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create price lists/store overrides, customer-group prices, scheduled price versions, coupon definitions/usages, quantity/mix-and-match/buy-X-get-Y rules, rule priority and allocated discount snapshots.

## Required implementation or research work

- Implement merchant default/store override mode and publish/version workflow. Manual mode uses standard prices and permitted manual line/cart discounts. Advanced mode adds the agreed rule set with documented precedence, stacking limits and eligibility.
- Resolve arithmetic and tax ordering using frozen fixtures. Discount allocation must permit exact partial refunds, including free/discounted items, caps and rounding residue. Coupon usages are claimed idempotently during finalize, not permanently consumed by a quote.
- Specify active-cart behaviour on mode/rule changes: show a visible reprice/version change and require a fresh valid quote before online finalization; historical completed sales never reprice. Offline uses downloaded valid snapshots with explicitly documented restrictions for globally limited coupons.
- Create understandable rule forms, effective date/time preview and a calculation explanation useful to managers. Avoid introducing a generic scripting engine or invisible surprise stacking.

## Interfaces, permissions and user experience

Expose quote-calculation service and serialized rule snapshot contract for browser fixtures. Finalizing services revalidate an online quote version. Store-only setting changes cannot leak to another store. Record which rules/manual approval produced each allocation.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Golden tests cover overlapping promotions, threshold boundaries, fractional quantities, free items, time windows, mode switches and partial-return allocations. Repeated quote does not consume coupons. Unauthorized manual discount fails server-side. Both-theme rule editor and checkout explanation remain legible.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; append-only pricing schema, publication and resolution services, deterministic quote calculator, scoped APIs, golden fixture, Vue pricing preview, tests, OpenAPI changes and visual evidence are present in the working tree.
- Files/modules changed: added price-book versions, variant/customer-group prices, promotion rules and coupon-usage records; pricing mode and rule-type enums; publication, effective-version resolution, largest-remainder allocation and quote calculation services; validated requests, resource/controller/routes and pricing test fixtures. Added the responsive `Pricing` management preview with mode selection, effective-time/version summary, understandable rule cards, quote allocation explanation and loading/empty/conflict states.
- Schema/API/event changes: added four pricing tables with tenant-composite foreign keys, immutable versions/content hashes, merchant/store scope, effective windows, configured price/tax snapshots, rule priority/stacking/exclusivity and future finalization-time coupon idempotency. Added pricing-history/publication and quote paths, bringing OpenAPI to 59 paths and 98 schemas. `pricing.version.published` audits actor, scope, mode and version. Manager fixed-role permissions now include `pricing.publish`, matching the frozen matrix; cashier publication and unrestricted manual discount remain denied.
- Tests actually run and results: MySQL 8.4 `migrate:fresh` passed all 43 migrations. The focused pricing suite passed 8 tests/53 assertions, covering owner/manager publication, cashier denial, optimistic conflicts, manual-mode rule rejection, future-effective activation, merchant/store inheritance and isolation, independent-store scope, all five rule families, best same-priority exclusive offer, expired windows, fractional thresholds, post-discount tax, buy-X-get-Y, repeated non-consuming coupon quotes, stale-version conflicts, authorized manual discounts, stable paisa allocation and cumulative partial-return entitlement. The full PHPUnit suite passed 92 tests/459 assertions. PHPStan passed with zero errors and Pint passed. Prettier, Vue TypeScript and ESLint passed; Vitest passed 21 tests; the Vite production build passed; Playwright Chromium passed all 11 browser journeys. The OpenAPI document parsed successfully at 59 paths/98 schemas. The documentation validator passed 71 Markdown files and 714 local links.
- Light/dark visual evidence: inspected [pricing light](../evidence/t012-pricing-light.png) and [pricing dark](../evidence/t012-pricing-dark.png). Both show persistent mode selection, composite version, publication timing, scheduled/quantity/coupon/buy-X-get-Y rules, quote allocation/tax explanation and recovery states with clear contrast.
- Hardware/provider evidence: not applicable. Pricing and promotion behavior was exercised through real MySQL/API and browser tests; no hardware or external provider is involved.
- Decisions/contract deviations and downstream impact: manual and advanced are published modes, not destructive toggles. Advanced evaluation follows frozen stages: scheduled/line rules, mix-and-match/buy-X-get-Y, coupons, authorized manual discounts, then tax. Same-priority exclusive rules select the greatest discount and public-ID tie break; non-stackable winners lock affected lines. Exact prices preserve four decimals, quantities six and receipt allocations two. Server time selects online effective versions. Quotes expose `coupon_consumed: false`; T022 must revalidate the version and atomically claim limited coupon usage with its sale transaction. Globally limited coupons must be unavailable offline unless T023 defines a safe allocation mechanism; ordinary downloaded non-limited rules remain eligible under snapshot age policy.
- Remaining blockers/limitations and next action: this ticket creates pricing snapshots and quote calculations, but does not persist carts or finalize sales. T021 stores the quote/version on carts and implements visible reprice interaction; T022 revalidates and snapshots allocations, claims coupons idempotently and posts the sale; T023 packages acceptable pricing snapshots for bounded offline cash sales. Proceed to T013 customer profiles and store-scoped operational records.
