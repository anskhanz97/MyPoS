# Controlled pilot runbook

Version 1.0 — 2026-09-29

## Entry criteria

Select one merchant, one or two low-risk stores, named owner/manager/cashiers, exact devices and support hours. Complete every launch gate in `launch-acceptance.md`; record an immutable release artifact, environment inventory, fiscal applicability decision, hardware matrix and recovery contacts. Use synthetic transactions first.

## Setup day

Deploy through the operations runbook. Confirm HTTPS, debug-off behavior, liveness/readiness, worker/scheduler supervision, backup alerts and secret redaction. Provision the merchant and stores, lock the intended data mode, configure business date/tax/prices, enroll registers, assign staff, configure opening stock and validate private exports.

Run one cash sale, one split manual-external sale, one receipt print/reprint, one email, one return and the applicable fiscal sandbox path. Reconcile sale, tender, drawer, stock, tax, report, outbox and notification records. Exercise light and dark modes on the active pilot devices.

## Recovery day

Disconnect one designated register, make a permitted synthetic offline cash sale, restart the browser without clearing data, deploy the compatible client/server candidate and reconnect. Verify one server sale and one stock/cash effect. Simulate helper failure and SMTP retry. Create an uncertain fiscal response only in sandbox/mock conditions and reconcile through official/integrator evidence.

Restore the latest encrypted database and required private objects into isolation, verify hashes/schema/ledger totals, drain copied queues without external side effects, then run disposable sale/return smoke checks. Record achieved backup age and restoration time.

## Live pilot controls

Start with a capped transaction window and an on-call owner/engineer. Monitor checkout p95/error, database connections/locks, queue age, pending offline commands, fiscal exceptions, print/email failures and ledger mismatches. Stop new selling on any tenant leak, duplicated/missing financial effect, unrecoverable posting ambiguity, required fiscal failure outside the approved policy or backup/readiness failure.

At close, sync every register, resolve conflicts/unknowns, close cash sessions, reconcile ledgers/reports and record operator feedback. Expand only after the review owner signs the evidence. Roll back the application artifact only under the compatibility rules; prefer a forward data fix over reversing financial schema changes.

## Exit

Export pilot data, preserve immutable evidence, revoke unused devices/provider credentials and retain or close the merchant according to the approved offboarding decision. A successful pilot supports a separate production decision; it does not automatically authorize deployment.
