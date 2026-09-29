# Incident response playbook

Version 1.0 — 2026-09-29

Declare an incident when selling, settlement integrity, tenant isolation, credential confidentiality, recoverability or legally required fiscal processing may be affected. Assign an incident commander and scribe. Record times in UTC, affected merchants/stores, first known request/event identifiers, release version and actions.

## First response

1. Preserve evidence. Restrict log and backup access; do not copy sensitive payloads into chat or tickets.
2. Bound impact using readiness, route errors, queue age, offline/fiscal states and reconciliation commands. Check for another-tenant exposure before treating a response problem as cosmetic.
3. Contain the fault. Stop a bad release, revoke affected credentials or disable a failing integration. Preserve already accepted local cash sales and pending commands.
4. Recover through idempotent replay, forward repair or isolated restore. Unknown fiscal outcomes require lookup/manual reconciliation; never blindly resubmit them.
5. Validate with ledger reconciliation and a controlled smoke journey. Keep the incident open until monitoring remains stable.

## Specific failures

For a database outage, prevent new finalization, keep permitted offline cash evidence locally, restore connectivity, verify locks/replication and reconcile pending commands. For queue outage, keep durable jobs, restart workers, inspect failed jobs and replay only operations whose idempotency contract is known. For suspected tenant leakage, disable the implicated route/export, preserve access logs, revoke generated artifacts and conduct row-level scope review. For lost device/browser data, revoke the enrollment; local evidence erased from the device cannot be reconstructed. For printer/helper failure, keep sale success separate from print state and use an explicit marked reprint. For fiscal unknowns, block or queue according to the configured verified policy and obtain official/integrator evidence before reconciliation. For a provider credential or callback incident, disable that merchant integration, rotate the provider secret, preserve callback/delivery identities, verify signature failures and reconcile outcome state before retrying; never resend an uncertain customer message blindly.

After recovery, document root cause, detection gap, financial/fiscal reconciliation, customer/merchant communication decision, corrective owner and deadline. Rotate compromised secrets, invalidate sessions/tokens where required and test the corrective control.
