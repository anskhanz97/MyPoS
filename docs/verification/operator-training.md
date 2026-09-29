# Operator training guide

Version 1.0 — 2026-09-29

## Owner and manager session

Train the owner to provision the merchant, choose shared or independent operational data once, create stores/registers, publish settings/prices, enroll a device with the one-time code, add fixed-role staff and verify store assignments. Explain that loyalty is merchant-wide even when operational customer/catalog data is independent.

Practice opening a cash session, scanning/searching products, handling weighted/pack goods, checking batch expiry, applying permitted discounts, suspending/resuming a cart, choosing a customer and fully settling with cash or a recorded external reference. Staff must distinguish sale success, receipt readiness, print state, email state and fiscal state. A print/email/fiscal failure must never trigger a second payment.

Practice a lost-response lookup/retry using the same transaction identity, a partial return, excessive-return denial, manager late approval and an exchange difference. Manual external refund/payment evidence stays pending until the defined staff workflow confirms it; the screen does not claim provider success.

## Offline and device recovery

Train cashiers to recognize online, offline-ready, pending sync, conflict and storage-risk states. Offline checkout accepts only the documented cash/cached-policy path. Do not clear browser data, reinstall the browser, switch device clocks or create a fresh transaction after an uncertain response. Keep the local receipt/command identity and reconnect for reconciliation.

If the browser reports quota/persistence failure, stop offline selling. If a device is lost, revoke its enrollment and understand that unsynced evidence erased with the device is not recoverable. Subscription restriction still permits submission of previously accepted offline work.

## Cash, stock and purchasing

Practice custody handover, paid-in/paid-out permissions, blind close count, variance review and `close_pending_sync`. Managers should trace a cash movement to its sale/return/expense/supplier/payroll source and never enter an offsetting duplicate.

Practice PO issue, partial receipt, retry, supplier return/credit, payment allocation/reversal, transfer dispatch/receipt and stocktake count-time discrepancy. Posted records are corrected with linked append-only actions, not edited or deleted.

## Payroll, reports and exports

Only owners see salary/payroll details. Train rota/clock/leave correction before payroll freeze, review the calculation breakdown, post once and record payouts as evidence rather than bank transfer. Reports must be read with scope, business date, source drill-down, cost/freshness labels and payroll privacy. Imports are always dry-run/review before commit; export files are private, formula-safe and expire.

## Incident drill

Every operator practices: keep selling stopped when finality is uncertain; record request/document IDs; avoid screenshots containing contacts or credentials; notify the designated manager; follow the incident runbook; and resume only after readiness plus ledger reconciliation. Theme choice is per staff/public/display scope and can be toggled without losing active form/cart state.
