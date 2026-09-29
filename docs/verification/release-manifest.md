# Verification manifest

Executed 2026-09-29 against the uncommitted local working tree.

- Backend feature: 164 passed, 1,577 assertions, 217.523 s.
- Backend multi-process concurrency: 8 passed, 46 assertions, 210.865 s.
- Backend unit: 9 passed, 50 assertions, 1.121 s.
- PHPStan: zero errors.
- Pint: formatting applied and final check passed.
- Vitest: 7 files, 40 tests passed.
- Vue TypeScript and ESLint: passed.
- Playwright Chromium: 35 passed, 2.2 minutes on the final rerun.
- Vite production build: 102 modules; JS 223.79 kB (70.32 kB gzip), CSS 121.72 kB combined before gzip.
- Composer audit: no known security advisories.
- npm production audit: zero vulnerabilities.
- Encrypted MySQL restore: 311,320-byte artifact; source/restored 150 tables and 107 migration rows; SHA-256 verified; local combined grant/restore/check command 19.8 s.
- External evidence not exercised: production hosting/PITR/object restore, physical printer/drawer, deployed SMTP, real fiscal sandbox/production, production-sized load.

## Post-T037 integration supplement

T038 was implemented after the full acceptance run above. Its focused verification passed three integration-hub backend tests/30 assertions, the combined integration and notification set at four tests/66 assertions, all 40 Vitest tests, Vue type checking, ESLint, a production build and one focused Playwright integration-hub case in both themes. The post-T038 build contains 105 modules; JavaScript is 232.43 kB (72.93 kB gzip), with 53.19 kB and 76.46 kB CSS outputs. The OpenAPI contract and 90-file documentation graph were revalidated after reconciliation.

These focused results are a delta, not a replacement full-suite total. A clean-checkout full run remains part of the first immutable release-candidate gate.
