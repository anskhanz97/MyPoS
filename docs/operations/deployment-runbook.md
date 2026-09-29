# Deployment and rollback runbook

Version 1.0 — 2026-09-29

This runbook prepares MyPoS for a controlled pilot. It does not authorize a production deployment. A release operator records the artifact digest, Git commit, migration list, database backup identifier, supported client schema versions and named approver before changing an environment.

## Runtime shape

Deploy the same immutable application artifact to the web and worker roles. The minimum topology is an HTTPS reverse proxy, Laravel web processes, at least one database-backed queue worker, one scheduler process, MySQL 8, private file storage and an SMTP provider. Run the scheduler once per minute and supervise workers so they restart after failure. Keep the public `/api/v1/health` liveness probe separate from the protected `/api/v1/health/ready` dependency probe.

Production configuration requires `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, an HTTPS `APP_URL`, secure HTTP-only same-site cookies, a random `OPS_HEALTH_KEY`, database/cache/queue credentials from the platform secret store, `LOG_CHANNEL=daily` or a structured central sink, and a real mail transport. Do not place secrets in the artifact, process arguments, repository, support exports or screenshots. Rotate a disclosed secret and treat logs containing it as compromised.

The application database account needs CRUD and index/migration privileges on the application schema according to the chosen migration method. The steady-state web and worker accounts should not have global `PROCESS`, `FILE`, `SUPER`, user-management or arbitrary database creation privileges. The backup script uses `--no-tablespaces`, so it also does not need global `PROCESS`.

## Pre-deployment gate

1. Identify the exact commit and build dependencies with `composer install --no-dev --classmap-authoritative` and `npm ci && npm run build` in a clean build environment.
2. Run the T037 acceptance commands and attach their machine-readable outputs. Resolve every critical failure. A named limitation is not a pass.
3. Confirm the target has a recent encrypted backup and a separately stored recovery key. Confirm a restore drill within the last quarter.
4. Confirm fiscal configuration is appropriate for each merchant. This build permits only FBR DI v1.12 sandbox activation; production FBR and provincial activation remain blocked.
5. Confirm Windows browser, printer/helper, cash drawer and customer display combinations in the pilot hardware matrix. Simulation does not qualify physical hardware.
6. Check unresolved offline commands, fiscal unknown outcomes, failed jobs and active imports. Planned work must not strand or duplicate them.
7. Review every pending migration for table rebuilds, long locks and destructive changes. Schedule incompatible or long-running DDL outside trading hours.

## Deployment sequence

1. Put the new artifact beside the current release. Populate infrastructure secrets through the environment manager and validate configuration without displaying values. Merchant provider credentials are entered by an authorized owner in the integration hub and stored encrypted; do not duplicate them into deployment variables.
2. Run `php artisan config:cache`, `route:cache`, `view:cache` and `event:cache`. Do not cache configuration before environment values are final.
3. Run `php artisan migrate --force` once from a dedicated release process. Stop if any migration fails; capture the exact migration and database error.
4. Atomically switch the web release. Restart web processes, then queue workers with `php artisan queue:restart`. Keep old code available for a forward fix or code rollback.
5. Start or verify `php artisan schedule:work` and supervised `php artisan queue:work --tries=3 --timeout=90`. Run `php artisan ops:heartbeat` once if scheduler startup is delayed.
6. Check liveness and readiness using the health key. Verify a login, catalog read, quote, synthetic cash sale, receipt projection and owner report in the designated pilot tenant.
7. Watch checkout errors/latency, failed jobs, queue age, offline pending age, fiscal unknown/rejected documents, email/SMS/WhatsApp/print failures, rejected callbacks and balance reconciliation for at least one full pilot trading cycle.

## Compatibility and rollback

Pending offline commands are versioned evidence. A release must continue accepting every client schema version advertised by the previously supported bootstrap until those queues are confirmed empty or migrated locally. Never clear service-worker/IndexedDB state as an update step. Restriction or subscription changes still allow recovery submission of already accepted offline sales.

Prefer expand/backfill/switch/contract migrations: add nullable columns or new tables first, deploy dual-compatible code, backfill in bounded batches, then remove old structures only after old clients and workers are retired. Migrations that drop or reinterpret financial fields are prohibited during the pilot.

If the release is unhealthy before an incompatible schema change, switch web and workers back to the prior artifact, restart workers, and verify readiness. If schema is forward-compatible, leave it in place. If data semantics changed, deploy a forward fix. Use a database restore only for confirmed corruption or loss under the incident commander; it can discard post-backup transactions and requires replay/reconciliation. Laravel migration `down()` is not a financial recovery strategy.

## Pilot shutdown

Stop new selling, allow offline devices to reconnect, drain the queue, reconcile unknown fiscal and payment states, export the pilot merchant, take an encrypted backup and preserve logs/evidence according to retention policy. Never delete the merchant merely because the pilot ended.
