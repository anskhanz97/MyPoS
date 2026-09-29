# T005 — Create Laravel/Vue/MySQL application and test foundation

Version: 1.0 | Created: 2026-09-28 | Status: Verified 2026-09-29

## Outcome

Bootstrap the new application in C:\MyPoS without copying source secrets or modifying XEPOS. Establish a runnable, documented local baseline and CI checks using the frozen stack.

## Dependencies and decisions

- Prerequisites: [T004](004-freeze-domain-contracts.md)
- User-decision references: Q001, Q004, Q006; D004 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create domain-organized application directories, environment examples without secrets, migrations, lockfiles, local development instructions and CI configuration. Add a disposable MySQL test database configuration, queue/scheduler entrypoints and generated API types where the chosen tooling supports them.

## Required implementation or research work

- Inspect current files before creating anything; preserve documentation. Install only the selected versions. Set same-origin development routing and authenticated/public API boundaries. Configure serialization/decimal utilities, error responses, request IDs and health endpoints.
- Set up unit, MySQL integration and browser test runners. Use factories with clearly synthetic merchants, stores and staff. Configure static analysis/lint/type checks, backend formatting and frontend build. Do not generate demo data that could be mistaken for real customers.
- Add migration workflow, worker/scheduler local commands and environment validation. No production endpoints or credentials may be inherited from XEPOS. Start with minimal modules and shared infrastructure rather than placeholder screens for every future feature.

## Interfaces, permissions and user experience

Expose only health/bootstrap infrastructure required at this stage. Follow T004 error/API conventions. A passing local skeleton is not an implemented POS. Record runtime versions and reproducible start/test commands.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Clean dependency install and frontend production build succeed. App boots, migrations run on disposable MySQL and test setup is isolated. A simple API integration test and browser shell smoke test pass. Missing environment variables yield clear errors. Existing docs remain intact.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: initialized the application as a local Git working tree at `C:\MyPoS`; no commit or remote was created. The ignored `.runtime` contains only disposable Composer/MySQL tooling. XEPOS was not read or modified during this ticket.
- Files/modules changed: Laravel 13.33 application skeleton and lockfile; Vue 3.5.43/TypeScript 6.0.3/Vite 8.3.1 frontend and lockfile; `App\Domain\Shared\Math`; request-ID middleware; versioned API controllers/routes; environment-check command; PHPUnit/Vitest/Playwright suites; Larastan, Pint, ESLint and Prettier configuration; CI workflow; local setup instructions and visual evidence.
- Schema/API/event changes: the standard Laravel user/cache/job migrations form the disposable foundation. Added public `GET /api/v1/health`, authenticated `GET /api/v1/bootstrap`, built-in `/up`, `X-Request-Id`, and the T004 `application/problem+json` shape. No POS domain tables, write endpoints or events were introduced.
- Tests actually run and results: Composer validation/install and `npm ci` passed with zero package audit findings; production Vite build passed. MySQL 8.4.11 `migrate:fresh` passed on isolated `mypos_test` port 3307. PHPUnit passed 7 tests/40 assertions including a real MySQL factory insert and missing-`APP_KEY` diagnostic. Pint passed; Larastan level 7 passed with zero errors; Vue TypeScript check and ESLint passed; Vitest passed all 15 shared arithmetic cases; Playwright Chromium passed the shell/theme smoke test.
- Light/dark visual evidence: Playwright exercised default light, keyboard-addressable toggle to dark, and persisted dark state after reload. Screenshots were inspected at 1280×720: [light](../evidence/t005-foundation-light.png) and [dark](../evidence/t005-foundation-dark.png). T006 still owns the complete component system and all loading/empty/validation/error states.
- Hardware/provider evidence: not applicable; no hardware, fiscal provider or production side effect belongs to this foundation ticket.
- Decisions/contract deviations and downstream impact: local PHP 8.3.30 is supported by Laravel 13; CI is configured for the selected PHP 8.5 production line. OpenAPI TypeScript generation was not installed because current `openapi-typescript` 7.13.0 declares TypeScript 5.x compatibility while the frozen stack uses TypeScript 6; the authoritative OpenAPI remains checked directly and generation is deferred until compatible tooling is available. A015 in the frozen arithmetic fixture was corrected from the impossible product `1.52499025` to `1.52399025` (posted `1.5239`); both Brick Math and Decimal.js independently exposed the transcription error, and the erratum is recorded in the arithmetic policy.
- Remaining blockers/limitations and next action: the GitHub Actions workflow is configured but has not run on a remote because this repository has no remote. Redis/Horizon, product authentication, tenancy and all POS workflows remain owned by later tickets. Proceed to T006 for the full frontend shell and two-theme component foundation.
