# Monitoring and alerting

Version 1.0 — 2026-09-29

The public liveness endpoint is `/api/v1/health`. The protected readiness endpoint is `/api/v1/health/ready` with `X-Health-Key`; it checks database, cache, private storage and a scheduler heartbeat. A missing scheduler heartbeat older than 180 seconds makes readiness fail. Load balancers may use liveness to decide whether the process responds; deployment orchestration should use readiness to stop routing traffic when dependencies or scheduled work are unhealthy.

Run `ops:heartbeat` every minute and `ops:prune-expired-artifacts` daily. Monitor the scheduler process itself so a broken scheduler cannot hide behind its final cached heartbeat. Workers use bounded attempts and timeouts; restart them after deployment and alert on failed jobs rather than retrying unknown fiscal outcomes blindly.

## Required signals

- HTTP request count, error rate and p50/p95/p99 duration by normalized route; never use raw IDs as metric labels.
- Sale finalization and quote duration/error rate, duplicate/idempotent recovery count and lock/deadlock retries.
- Oldest queued job age, ready/failed job count and worker heartbeat.
- Pending offline command count and age, quarantined/conflicting commands and device lease failures.
- Fiscal queued/rejected/unknown counts and oldest unknown age by authority/environment, without payloads or tokens.
- Print and receipt email/SMS/WhatsApp failure rate, oldest undelivered notification, callback authentication failures/deduplication rate and repeated helper rejection.
- Inventory, loyalty, supplier and cash reconciliation mismatches.
- Backup success, artifact size/hash publication, age of last successful backup and restore-drill age.

Initial alert thresholds are conservative hypotheses: page on five consecutive readiness failures, checkout error rate above 2% for five minutes, any fiscal unknown older than five minutes while required, any reconciliation mismatch, failed backup, or queue age above five minutes. Warn on p95 checkout above two seconds for ten minutes, pending offline age above 30 minutes after connectivity returns, any enabled receipt channel age above 15 minutes, repeated invalid provider callbacks, and scheduler heartbeat above 180 seconds. Replace thresholds with observed pilot baselines and documented business tolerance.

Alerts need an owner, severity, runbook link and deduplication key. Do not include customer contact, employee values, receipt lines, credentials or full request bodies. Correlate with `X-Request-Id`, public document IDs and immutable event IDs.
