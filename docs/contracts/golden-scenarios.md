# Golden fixtures and cross-module acceptance map

Version: 1.0 | Frozen: 2026-09-29 | Ticket: [T004](../tickets/004-freeze-domain-contracts.md)

## Normative fixture use

[Arithmetic fixture JSON](fixtures/arithmetic-golden.json) is the shared input for PHP and TypeScript. T005 copies or imports it into both test runners without transcribing values. Each runner dispatches by `operation`, parses every decimal as an arbitrary-precision decimal, compares canonical strings and asserts the conservation rules in [arithmetic policy](arithmetic-policy.md).

Passing JSON parsing is not a business test. A fixture passes only when the production calculator used by the application produces the expected output.

## Arithmetic cases

| ID | Purpose | Critical assertion | Owning tickets |
|---|---|---|---|
| A001 | Exclusive tax on fractional quantity after discount | `22.50 + 4.05 = 26.55` | T012, T021, T022 |
| A002 | Inclusive 18% exact split | `100 + 18 = 118` | T012, T022 |
| A003 | Inclusive tax after discount | discounted gross splits to `90 + 16.20` | T012, T026 |
| A004 | Largest-remainder allocation | 5 paisa allocated exactly and tie order stable | T012 |
| A005 | Split tender and change | settlement `100`, drawer increase `70` | T020, T022 |
| A006 | Three partial returns | refunds sum exactly to original `10.00` | T026 |
| A007 | Carton to pieces | 2 cartons consume 24 pieces | T011, T014, T018 |
| A008 | Weighted sale | 1.25 kg consumes 1250 g | T011, T014 |
| A009 | Positive-stock moving average | new average `110.000000` | T018 |
| A016-A017 | Carton and fractional purchase receipts | base quantity, capitalized value, average and supplier charge reconcile | T018 |
| A023-A025 | Partial supplier return, statement and payment reversal | cumulative credit, balance, allocation and reversal reconcile | T019 |
| A026 | Register cash close | opening float plus signed cash movements yields expected cash; external tenders contribute zero; count minus expected yields variance | T020 |
| A018-A019 | Negative stock partial/cross-zero resolution | provisional snapshot retained; explicit adjustment; positive remainder at receipt cost | T018 |
| A020 | Duplicate purchase receipt | one receipt, stock movement, cost entry, charge and outbox event | T018 |
| A021 | Independent-catalog transfer valuation | mapped quantity changes unit cost while conserving `240.000000` value | T018 |
| A022 | Latest safe receipt reversal | append-only reversal restores prior quantity/value/average | T018 |
| A010–A011 | Business cutoff | before/after 02:00 map to correct stored date | T020, T032 |
| A012–A013 | Exchange difference | customer pay versus merchant refund direction | T026 |
| A014 | Operational payroll | recorded inputs reconcile to net | T030 |
| A015 | Loyalty earning | program floor-to-four rule reproducible | T027 |

T012 adds machine-readable [promotion combinatorics](fixtures/promotion-golden.json). T018 extends the arithmetic fixture to schema version 2 with receipt, negative/correction, transfer and reversal cases while retaining A001-A015 unchanged. T027 adds spent-points return behavior. Future changed meanings require another schema-version compatibility note.

## Cross-module S01–S28 ownership

All scenarios originate in [cross-module scenario register](../08-cross-module-scenarios.md). “Primary evidence” means the ticket that must first produce a working integrated proof; T037 reruns the complete suite.

| Scenario | Primary ticket(s) | Frozen acceptance points |
|---|---|---|
| S01 shared merchant/two stores/sale | [T007](../tickets/007-merchant-and-store-tenancy.md), [T010](../tickets/010-register-provisioning-and-settings.md), [T022](../tickets/022-sale-finalization-and-tenders.md) | locked shared mode, authorized contexts, local stock/prices, atomic sale |
| S02 independent overlap/merchant loyalty | [T007](../tickets/007-merchant-and-store-tenancy.md), [T013](../tickets/013-customers-and-local-records.md), [T027](../tickets/027-loyalty-points-and-rewards.md) | overlapping SKU/phone remain distinct; explicit links; one merchant points ledger |
| S03 cartons/batch/weight/cost | [T011](../tickets/011-catalog-and-product-types.md), [T015](../tickets/015-batch-and-expiry-tracking.md), [T018](../tickets/018-receiving-and-moving-average-cost.md) | frozen conversion, batch ownership/expiry, exact base quantities and cost |
| S04 PO/partial receipt/retry/return/payment | [T017](../tickets/017-suppliers-and-purchase-orders.md), [T018](../tickets/018-receiving-and-moving-average-cost.md), [T019](../tickets/019-purchase-returns-and-supplier-balances.md) | PO no stock; idempotent receipt; stock/liability/payment conserved |
| S05 promotion-mode switch | [T012](../tickets/012-pricing-and-promotion-modes.md) | published version, explicit cart reprice/retention, history immutable |
| S06 overlapping promotions/partial return | [T012](../tickets/012-pricing-and-promotion-modes.md), [T026](../tickets/026-returns-and-exchanges.md) | priority/stacking deterministic; largest remainder; cumulative return entitlement |
| S07 split tender/drawer | [T020](../tickets/020-cash-register-sessions.md), [T022](../tickets/022-sale-finalization-and-tenders.md) | A005 equation, manual confirmation provenance, cash change separate |
| S08 lost response/idempotent retry | [T022](../tickets/022-sale-finalization-and-tenders.md) | same result, one sale/stock/cash/outbox/earn request |
| S09 restart offline/reconnect | [T023](../tickets/023-offline-pwa-and-reconciliation.md) | committed IndexedDB record survives restart; same command ID; visible acknowledgement |
| S10 concurrent last stock/expiry | [T014](../tickets/014-inventory-ledger.md), [T015](../tickets/015-batch-and-expiry-tracking.md) | locked posting, permitted audited negative, expired batch always blocked |
| S11 offline old snapshot/clock drift | [T023](../tickets/023-offline-pwa-and-reconciliation.md) | signed snapshot/evidence retained; conservative lease; no silent reprice |
| S12 close with unsent sales | [T020](../tickets/020-cash-register-sessions.md), [T023](../tickets/023-offline-pwa-and-reconciliation.md) | `close_pending_sync` differs from closed; count/variance later reconciles |
| S13 print failure/customer display | [T024](../tickets/024-receipts-printing-and-drawer.md), [T025](../tickets/025-optional-customer-display.md) | sale remains posted; idempotent print/reprint; display has no staff/private data |
| S14 returns/exchange | [T026](../tickets/026-returns-and-exchanges.md) | remaining entitlement, approval token/reason, linked documents, exact difference |
| S15 cross-store points then return | [T027](../tickets/027-loyalty-points-and-rewards.md) | locked redemption, linked reversal/exception, no money debt account |
| S16 join retry/public lookup | [T028](../tickets/028-public-member-join-and-lookup.md) | membership/bonus idempotent; purpose-built masked DTO; rate limit |
| S17 tenant/store probes | [T007](../tickets/007-merchant-and-store-tenancy.md), [T008](../tickets/008-authentication-and-fixed-roles.md) | all surfaces deny substitution without data/existence leak |
| S18 attendance/leave/payroll/correction | [T029](../tickets/029-workforce-attendance-rota-leave.md), [T030](../tickets/030-operational-payroll.md) | frozen inputs, owner approval, immutable payslip, allocated payouts/correction |
| S19 expense/drawer/double counting | [T031](../tickets/031-expenses-and-settlement-records.md) | expense and one linked cash effect; source-aware reporting |
| S20 report reconciliation | [T032](../tickets/032-standard-reporting.md) | business date/tax/tender/return/stock totals trace to ledgers; freshness/cost states shown |
| S21 dry-run/import idempotency | [T033](../tickets/033-imports-and-exports.md) | no dry-run effects, bounded rows, row errors, one commit per batch/row |
| S22 email retry | [T034](../tickets/034-email-and-in-app-notifications.md) | sale independent; same delivery identity; consent/preferences; no SMS launch dependency |
| S23 subscription versus pending offline | [T009](../tickets/009-plans-and-platform-administration.md), [T023](../tickets/023-offline-pwa-and-reconciliation.md) | recovery/sync permitted, unrelated new operations restricted |
| S24 fiscal unavailable/rejected/unknown | [T035](../tickets/035-verified-fiscal-adapters.md) | local/fiscal states separate; no invented acceptance; authority-specific gates |
| S25 theme switching | [T006](../tickets/006-frontend-shell-and-themes.md) and every UI ticket | no state/focus loss; WCAG both themes; white print output |
| S26 backup restore/outbox replay | [T036](../tickets/036-production-hardening-and-recovery.md) | measured restore, ledger reconciliation, inbox/outbox dedupe |
| S27 compatible offline deployment | [T023](../tickets/023-offline-pwa-and-reconciliation.md), [T036](../tickets/036-production-hardening-and-recovery.md) | schema range/migration/recovery; never erase queue |
| S28 device/storage/printer/payment unknown | [T023](../tickets/023-offline-pwa-and-reconciliation.md), [T024](../tickets/024-receipts-printing-and-drawer.md), [T022](../tickets/022-sale-finalization-and-tenders.md) | truthful irreversible-loss limits, diagnostics and unknown outcome; no success invention |

T016 extends the inventory evidence pack with two ticket-specific cases that T037 must rerun: (1) dispatch six source cartons through an explicit factor-12 independent-store mapping, receive a partial quantity, then close a verified shortage without duplicating either side on retry; and (2) open a stocktake at quantity 10, post a sale of 2 before count, record a physical count of 7, post another sale of 1, then append the computed −1 discrepancy to finish at 6. Batch variants repeat the transfer case with identity preservation and allow an expired lot only into quarantine or unavailable stock.

## Mandatory fixture dimensions

Owning tickets extend these scenarios with:

- shared and independent merchant/store modes;
- authorized and substituted tenant/store/register IDs;
- ordinary and approval-required actors;
- identical retry and changed-body key reuse;
- online, simulated offline and real browser restart where relevant;
- populated, empty, loading, validation, forbidden, conflict, retryable and unknown states;
- light and dark screens plus black-on-white print/PDF where UI exists;
- provider/hardware simulation explicitly distinguished from actual sandbox/device evidence.

## Evidence record

For each execution store:

```text
scenario/fixture ID
code revision and schema/fixture version
environment and MySQL/browser/device/provider details
preconditions and test data identifiers
steps and expected invariants
actual result and timing where relevant
logs/screenshots/receipt/provider references with secrets redacted
simulation versus real evidence
defects, owner and retest link
```

T004 defines expected behavior only. No scenario is marked passed until its owning implementation runs it; T037 is the launch-level integrated pass.
