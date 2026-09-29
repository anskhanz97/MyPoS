# End-to-end scenario register

Status: executed locally on 2026-09-29. Detailed classifications and environmental gates are recorded in [T037 scenario results](verification/scenario-results.md); owning-ticket evidence remains the source for individual invariants.

## Scenario IDs

- S01: Provision merchant, choose shared mode, create two stores/registers, reject a later mode switch, configure pricing and complete a cash sale.
- S02: Provision independent stores with overlapping SKU/phone values; preserve separate operational data and one merchant-wide loyalty identity.
- S03: Receive cartons with batch/expiry; sell pieces and weighted goods; reconcile unit conversion, cost and stock.
- S04: Partial PO receipt, over-receipt attempt, repeated receipt retry, supplier return and partial supplier payment; no duplicate liabilities or stock.
- S05: Switch manual to advanced promotion mode; existing cart treatment is explicit; historical receipts unchanged.
- S06: Apply overlapping coupons/multibuy/quantity rules and manual discount; reconcile allocations/tax and a later partial return exactly.
- S07: Split a fully paid sale between cash and recorded external tender; distinguish manual confirmation, cash change and expected drawer cash.
- S08: Finalize sale, lose response, retry with same idempotency key; exactly one sale/stock/earn effect.
- S09: Lose internet, restart provisioned till, sell for cash with cached rules, reconnect; preserve local receipt identity and resolve server acknowledgement.
- S10: Two tabs/registers sell last stock; permitted negative stock has warnings and audit; expired batches remain blocked.
- S11: Offline sale spans price change/clock drift; reconciliation preserves original evidence and the documented policy.
- S12: Close cash session with unsent sales, blind count and variance; distinguish local provisional close from reconciled server totals.
- S13: Print failure after successful sale; retry/reprint without repeating payment/stock; second display shows correct state.
- S14: Partial return inside ordinary window, late manager exception, attempted excessive return and exchange with difference settlement.
- S15: Earn points, spend them at another independent store, then return the original sale; apply defined reversal policy without duplicating monetary debt or redemption.
- S16: New member joins; repeat submit does not duplicate membership/bonus; public lookup returns only masked identity, points and rewards.
- S17: Probe another merchant/store IDs via UI/API/export/job; deny access without leaking existence or data.
- S18: Attendance and approved leave feed payroll; adjust before posting, post once, record split payouts, then correct without rewriting old payslips.
- S19: Record expense paid from drawer; reconcile expense and cash movement exactly once, alongside supplier/payroll cash payments.
- S20: Standard sales/tax/tender/return/stock/margin reports agree for the same business date and display cost/report freshness limitations.
- S21: Import mixed valid/invalid catalog/customer/opening-stock rows; dry-run has no effects, commit is idempotent and errors identify source rows.
- S22: Email/SMS/WhatsApp delivery retries while the sale remains available; channel configuration, consent/preferences, deduplication and callback truthfulness are respected; SMS/WhatsApp remain absent from launch dependencies.
- S23: Change subscription state while a register has pending offline sales; recover existing records without granting unrelated new access.
- S24: Apply verified fiscal rules for applicable merchant; unavailable/rejected/uncertain fiscal responses remain visible and retry-safe, according to the researched legal contract.
- S25: Switch light/dark on active cart, payroll form, report and member page; preserve data, focus and contrast; print/PDF stays readable on white.

T021 verification covers the cart portions of S03, S05 and S25: a configured scale scan carries six-decimal quantity, repeated identical scans increment one configuration, extras remain attached to their line, expired batch evidence blocks addition, a stale cart version conflicts, suspend/resume creates no stock ledger entry, and theme/network/staff presentation changes preserve the basket. Sale/stock/tender assertions remain reserved for T022/T023.

T022 verifies the online portions of S07 and S08: a split staff-declared external/cash payment plus change settles exactly; only net cash enters the drawer; sale, receipt, stock and outbox effects occur once; a lost response replays the committed result; changed-body reuse conflicts; and parallel identical commands serialize to one post plus one replay. Store/register substitutions and underpayment fail without partial effects. Printing remains `not_requested`, fiscal remains `not_required`, and offline ingestion remains T023.

T023 verifies the implemented portions of S09–S12, S23 and S28. A cash-only local sale and printable snapshot commit atomically with its outbox command, survive reload, retain theme state and use one writer across two tabs. Exact server retry posts sale, stock and drawer effects once. Altered identities, stale fences and limited coupons are rejected or excluded while evidence remains visible. Quota failure does not display completion. The recovery UI exports unresolved records without credentials. Browser tests use Chromium/IndexedDB simulation; physical Windows device, printer/drawer and fiscal outage qualification remain T024/T035/T037.

T026 verifies the monetary and concurrency portions of S06 and S14. Partial returns use frozen original allocations after a later price publication, repeated partials sum to the original entitlement, an excess return conflicts and parallel distinct commands allow only one consumer of the remaining quantity. Cash posts into the active session, external actions remain explicitly pending until evidence is confirmed, late approval is command-bound and replay-safe, and an exchange commits its return and fully paid replacement together while exposing the difference direction. T027 and T035 still own the downstream loyalty and fiscal consumers of the emitted events.

T027 completes S15's loyalty portion. The same merchant member may link separate independent-store customer profiles; store history remains local while the points balance is shared. Parallel reward attempts in two stores lock one balance and only one can consume the final available points. A finalized online quote commits the exact reserved points and discount, while a repeated sale command creates no second earn or redemption. Accepted offline sales earn through server finalization but carry no redemption. A return reverses the proportional original earn exactly once even after those points were spent; the negative result is a points repayment state with no monetary receivable. Expiry uses FIFO consumption evidence and an earn-bound replay key.

T028 completes S16. The public URL resolves an active programme before accepting a phone. A new phone creates one member and a zero balance; two simultaneous joins resolve to that same member and create no welcome credit. An existing phone returns the same allowlisted masked projection. Refresh calls lookup rather than enrollment and leaves membership, enrollment and points ledgers unchanged. Another merchant's store attribution is rejected. Disabled programmes and unknown slugs do not search other merchants. Per-slug/IP join and lookup windows return `429` after their bounded thresholds, while operational abuse evidence keeps no raw phone or IP.
- S26: Restore an isolated backup, reconcile ledgers, run smoke sale/return and recover pending outbox jobs without duplicate effects.
- S27: Deploy a compatible client/server update with an old offline queue; submit successfully or use the explicit supported migration path.
- S28: Lost storage/device, missing printer or unknown external payment outcome produces truthful recovery instructions, not an invented success.

## Evidence format

For each scenario record test/data version, environment, steps, expected result, actual result, evidence links, outstanding defects and owning ticket. Mock-only results must be labelled. A test plan is not a passed test.
