# Launch acceptance report

Version 1.0 — 2026-09-29

## Decision

The agreed MyPoS application scope is implemented and passes local automated acceptance. It is ready for a controlled, non-production pilot preparation phase. It is not approved for live merchant rollout because physical Windows printer/drawer qualification, deployed SMTP, production-sized load/hosting recovery, merchant-specific fiscal applicability and real authority/integrator evidence remain open gates.

No recipe/ingredient/preparation/waste, reservation/queue/delivery, missing C# reconstruction, customer debt/layaway, serial tracking or SMS/WhatsApp launch dependency was introduced.

## Verified build

The working tree contains Laravel 13/PHP 8.3, Vue 3/TypeScript, Tailwind CSS 4 and MySQL 8 integration. There is no supplied Git release commit or remote, so this report identifies the 2026-09-29 working tree rather than inventing a revision. A release candidate must be committed and assigned an immutable artifact digest before deployment.

Backend acceptance passed 181 tests and 1,673 assertions: 164 feature tests/1,577 assertions, eight multi-process concurrency tests/46 assertions, and nine unit tests/50 assertions. PHPStan completed with zero errors. Pint formatting was applied and rechecked. Composer reported no known security advisories.

Frontend acceptance passed 40 Vitest tests across seven files, Vue TypeScript checking, ESLint, the Vite production build and 35 Playwright Chromium scenarios. Browser coverage includes light/dark state persistence, WCAG AA semantic color checks, responsive overflow, white print media, keyboard/focus behavior and every module preview. npm reported zero vulnerabilities.

OpenAPI and documentation validation pass. The current contract parses to 182 paths and 248 schemas after the T038 integration endpoints were added. The documentation checker verifies ticket/Q&A/scenario/link coverage.

## Release gates

The following are mandatory before any live pilot transaction:

1. Commit/tag the reviewed working tree and reproduce build/test evidence from a clean checkout.
2. Deploy an isolated pilot environment with TLS, secret management, supervised web/worker/scheduler processes, MySQL backups/PITR and central monitoring.
3. Run production-sized workload tests and establish real alert baselines. Local integration timing is not a capacity claim.
4. Qualify the exact Windows browser, thermal printer/helper, cash drawer and optional second display combinations.
5. Configure and prove the chosen SMTP provider without exposing credentials or customer data.
6. Determine merchant/store fiscal applicability with qualified advice. Complete real FBR/integrator sandbox tests and production gates; do not activate unsupported provincial adapters.
7. Execute the pilot runbook, operator training and an isolated full backup/object restore drill on the chosen hosting platform.

## Defect disposition

Aggregate acceptance found and fixed two release-level issues. Mixed database test isolation caused repeated schema rebuilds; PHPUnit now separates transactional feature tests from committed-state concurrency tests. The CSP blocked the original inline theme bootstrap; it is now a same-origin external script, and theme persistence/contrast passed the complete rerun.

No unresolved code defect is classified as critical in the locally exercised scope. Open items are environmental/provider/hardware evidence or deliberately blocked capabilities, listed in [known limitations](known-limitations.md).

## Post-acceptance optional extension

After this report, T038 added an owner-managed integration hub and optional SMS/WhatsApp receipt delivery without making either channel a launch dependency. Focused backend/frontend/browser checks passed, secrets are encrypted/redacted and provider callbacks authenticate/deduplicate. Real SMTP, Meta, Twilio, Google, Microsoft and Stripe credential checks remain external gates. The next release candidate must rerun the complete acceptance suite from a clean committed checkout so the aggregate numbers above cover T038 as well.
