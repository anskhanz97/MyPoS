# Security hardening record

Version 1.0 — 2026-09-29

## Implemented controls

All responses receive a request identifier and defensive headers: `nosniff`, frame denial, a same-origin opener policy, strict referrer policy, restricted browser permissions and a CSP that permits only same-origin application assets plus data/blob images and websocket connections. HSTS is emitted for HTTPS and production requests. The CSP retains inline styles because the current Vue/print implementation requires them; removing that allowance is a future hardening task after nonce or static-style migration.

Browser APIs use the `web` session middleware, authentication, active-user check, fixed server-side permission checks and merchant/store relationship checks. Mutations are CSRF protected. Device/helper/public surfaces use explicit credentials and route-specific throttles. Public health reveals only service state and request identity; readiness requires an exact `X-Health-Key`, returns 404 for missing/wrong keys and never returns credentials.

The daily and single log channels pass structured context through `RedactSensitiveLogs`. It recursively replaces password, PIN, device key, bearer/authorization, cookie, card and credential fields and masks card-like digit sequences in strings. This is defense in depth: callers must still avoid logging request bodies or secrets. Existing audit models deliberately store bounded identifiers/reasons rather than reusable credentials.

Imports cap size and rows, require versioned headers, validate/stage before an atomic commit and keep files private. Exports neutralize spreadsheet formulas and expire after seven days. PDF rendering disables remote content. Fiscal, email and merchant integration credentials use encrypted model casts and are absent from API DTOs. Provider callbacks verify configured challenges/signatures, rate limit requests and deduplicate external event identities.

## Deployment checks

- Terminate TLS with a current configuration and redirect HTTP to HTTPS before enabling HSTS.
- Set `APP_DEBUG=false`; verify exception responses expose no stack, SQL or environment values.
- Use distinct keys for application encryption, health probes, device credentials and provider tokens. Store them in the platform secret manager.
- Grant the runtime database user only its schema. Use a separate, audited migration identity if the platform supports it.
- Restrict private storage and backups to service/recovery identities. Deny direct web serving.
- Disable directory listing, server version banners and arbitrary proxy forwarding. Configure trusted proxies/hosts for the selected hosting platform rather than guessing values in source.
- Apply dependency and operating-system security updates through a tested release. Review `composer audit` and `npm audit` output; assess exploitability rather than hiding advisories.

## Verification performed

`ProductionHardeningTest` exercises public header coverage, readiness concealment, stale/healthy scheduler behavior and recursive secret/card redaction. The broader feature suite supplies horizontal merchant/store substitution, fixed-role access, token replay, idempotency and state-conflict tests. T037 records the final aggregate counts.

This is an engineering control review, not a penetration test or compliance certification. External penetration testing, host/container hardening and provider configuration remain pilot deployment work.
