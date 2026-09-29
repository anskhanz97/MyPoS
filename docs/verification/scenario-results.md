# S01–S28 integrated scenario results

Version 1.0 — 2026-09-29

Environment: Windows development host, PHP 8.3.30, Laravel 13, MySQL 8.4 on port 3307, Chromium through Playwright, synthetic data, array/mock mail and HTTP transports. “Passed locally” includes automated MySQL/domain/API/browser evidence. “Partial” names the real-world evidence still missing.

| ID | Result | Integrated evidence and remaining boundary |
|---|---|---|
| S01 | Passed locally | Tenancy, register, settings, pricing, cash session and sale-finalization tests prove locked shared mode and atomic sale. |
| S02 | Passed locally | Tenant constraints, customer links and loyalty tests preserve independent operational collisions with one merchant-wide points identity. |
| S03 | Passed locally | Barcode/bundle, batch, receiving/valuation and inventory tests cover carton/weight conversions, expiry, cost and stock conservation. |
| S04 | Passed locally | PO, partial receipt, duplicate retry, supplier return/payment and concurrency tests preserve stock/liability/allocation invariants. |
| S05 | Passed locally | Pricing-version and cart tests preserve published modes, explicit reprice behavior and immutable historical quote/receipt evidence. |
| S06 | Passed locally | Golden pricing arithmetic and sale-return tests cover deterministic promotion allocation and cumulative partial-return entitlement. |
| S07 | Passed locally | Cash-session and sale tests prove split tender settlement, staff-declared external reference, change and net drawer cash. |
| S08 | Passed locally | Sale idempotency and multi-process finalization prove one sale, receipt, stock, cash and outbox effect after replay. |
| S09 | Passed in browser simulation | IndexedDB offline sale survives reload and reconciles once. Real Windows device/power-loss qualification remains in the pilot. |
| S10 | Passed locally | Inventory/batch concurrency exercises last-stock serialization, audited negative stock and expired-batch rejection. |
| S11 | Passed in simulation | Versioned offline snapshot, price evidence, clock/lease validation and conflict preservation are tested. |
| S12 | Passed in simulation | Cash/offline tests distinguish `close_pending_sync`, blind count and later server reconciliation. |
| S13 | Partial | Receipt/helper replay and second-display privacy pass simulation and both themes. Physical printer/driver/drawer/helper combinations are unqualified. |
| S14 | Passed locally | Partial/excess/late-approved return, external pending action and exchange settlement pass API and concurrency tests. |
| S15 | Passed locally | Merchant-wide earning/redemption, cross-store locking and spent-points return reversal pass without creating monetary debt. |
| S16 | Passed locally | Public join concurrency/idempotency, masked lookup and rate limits pass; no phone-ownership claim is made. |
| S17 | Passed locally | Tenancy, role, exports/jobs, records and support diagnostics deny merchant/store substitutions without returning scoped data. |
| S18 | Passed locally | Workforce/payroll tests freeze attendance/leave inputs, post immutable payslips and allocate split payouts/corrections. |
| S19 | Passed locally | Finance tests link drawer expense exactly once and keep supplier/payroll source ledgers distinct. |
| S20 | Passed locally | Reporting tests reconcile sales/tax/tender/return/stock/margin views with drill-down, privacy and freshness/cost caveats. |
| S21 | Passed locally | Data-transfer tests prove bounded CSV dry-run, row errors, atomic/idempotent commit and formula-safe exports. |
| S22 | Partial | Durable receipt email plus optional SMS/WhatsApp dedupe, retry-safe attempts, consent/opt-out, authenticated callback handling and immutable receipt references pass with safe transports. Real SMTP/Meta/Twilio provider delivery remains unqualified. |
| S23 | Passed locally | Subscription and offline tests permit recovery of accepted pending evidence while denying unrelated new restricted operations. |
| S24 | Partial | FBR DI v1.12 mapper and accepted/rejected/unknown/reconcile paths pass mocked sandbox transport. Real sandbox, production FBR and provincial authority gates remain open. |
| S25 | Passed locally | Full Chromium suite passes state/focus preservation, AA semantic contrast, both themes, responsive layout and white print media. |
| S26 | Partial | Encrypted database restore reproduced 150 tables/107 migrations and hash verification; sale/return/outbox tests pass separately. A hosted database plus object-store/PITR restore followed by the smoke journey is still required. |
| S27 | Partial | Versioned offline payload/replay and update-safe browser storage pass simulation. A deployed old-client-to-new-server update with pending records remains a pilot exercise. |
| S28 | Partial | Simulated storage quota, missing helper, print failure and unknown external/fiscal outcomes remain truthful. Physical device loss and hardware failure need pilot drills; erased local evidence is unrecoverable. |

Totals: 22 scenarios passed locally or in the explicitly stated browser simulation; six are partial because physical hardware, external provider, deployed update or hosted recovery evidence is still required. No scenario is recorded as failed, and partial does not mean production-approved.
