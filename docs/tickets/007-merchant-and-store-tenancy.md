# T007 — Implement merchants, stores and locked data-ownership modes

Version: 1.1 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Implement the tenant foundation, owner onboarding and store ownership rules used by every later module. Shared/independent choice is made during setup and cannot be casually switched after configuration.

## Dependencies and decisions

- Prerequisites: [T005](005-application-foundation.md), [T006](006-frontend-shell-and-themes.md)
- User-decision references: Q001, Q025, Q027, Q041 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Persist merchants, stores, selected operational data mode, merchant memberships and settings ownership. Introduce reusable scope/authorization helpers, composite ownership constraints and tenant-aware job/cache/storage conventions.

## Required implementation or research work

- Build merchant setup and store management with clear explanation of shared products/customers versus independent store records. Present a review step before locking the selection. Block unsupported later mode mutation in the API as well as UI.
- Define owner/store membership and central reporting context. Shared mode still has per-store stock and price overrides. Independent mode preserves distinct operational IDs even when SKU/name/phone match.
- Reserve merchant-wide loyalty identity and explicit links to local customers; do not deduplicate all customer records across stores as a shortcut. Ensure background jobs and object paths carry tenant context; no fallback to an arbitrary first merchant.

## Interfaces, permissions and user experience

Merchant/store administration APIs follow T004; authorized context is server-derived. Store switching clears incompatible transient UI state and cannot mix caches. Cross-tenant object IDs always fail authorization. No live store-mode conversion endpoint.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Create two merchants with both modes and colliding local identifiers. Test API/list/cache/job scoping and invalid related-record IDs. Attempt locked mode change and reject it. Validate store switch and both themes. Data model permits shared loyalty without shared operational customer history.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; migrations, domain services, API routes/resources, Vue tenancy preview, tests and evidence images are present in the working tree.
- Files/modules changed: added `Merchant`, `Store`, `MerchantMembership`, `StoreAssignment` and append-only settings-version ownership records; binary UUIDv7 casting and route binding; onboarding/store actions; tenant access/context/namespace services; operational-customer and merchant-loyalty boundary records; API controllers, requests and resources; and the interactive tenancy setup/store-context preview. The application service provider now prevents accidental lazy loading outside production.
- Schema/API/event changes: added eight MySQL tables with bigint internal keys, `BINARY(16)` public UUIDs, composite merchant ownership foreign keys, generated owner-scope keys and settings-scope checks. Added authenticated `GET/POST /api/v1/merchants`, `GET/POST /api/v1/merchants/{merchant}/stores`, rejected `PUT /api/v1/merchants/{merchant}/store-data-mode`, and validated `POST /api/v1/merchants/{merchant}/stores/{store}/context`. Updated the OpenAPI contract with these resources. No domain event was introduced in this ticket.
- Tests actually run and results: PHPUnit passed 22 tests / 86 assertions against disposable MySQL 8.4, including both ownership modes, colliding identifiers, cross-merchant assignment and loyalty substitutions, scoped lists/context, review validation, API and model-level mode locking, loyalty links without customer merging, and distinct cache/job/object tenant namespaces. PHPStan passed with zero errors and Pint passed. Vue typecheck, ESLint, production Vite build and Vitest passed; Vitest totals 19 tests. Playwright Chromium passed 6 tests including mode review/lock validation, transient-state clearing, theme persistence, keyboard behavior, narrow/print checks and light/dark contrast.
- Light/dark visual evidence: inspected [tenancy light](../evidence/t007-tenancy-light.png) and [tenancy dark](../evidence/t007-tenancy-dark.png). Both show the locked independent-mode review, canonical store context, cleared draft/cache generation, and loading/empty/error states. Existing theme, public isolation, narrow and print evidence also remained green.
- Hardware/provider evidence: browser simulation only; this ticket has no hardware/provider integration. Physical till sizing remains part of T024/T037 qualification.
- Decisions/contract deviations and downstream impact: external aggregate identifiers use UUIDv7 in `BINARY(16)` columns while internal joins use bigint keys. Composite foreign keys include `merchant_id`, so cross-merchant relations fail at the database boundary. Store mode is locked during the reviewed activation transaction and guarded again on model updates. Settings use generated store-scope keys so merchant-level version uniqueness is enforceable despite nullable store IDs. No scope exclusions were reintroduced.
- Remaining blockers/limitations and next action: the working Vue flow uses synthetic preview data because staff sign-in and browser session wiring belong to T008; the equivalent server operations and authorization boundaries are live and tested with authenticated Laravel users. T011–T014 will attach catalogue, prices, stock and full customer behavior to the established ownership keys, while T027 will expand the reserved loyalty boundary. Proceed to T008 authentication and fixed roles.
