# Pakistan fiscal launch gates and operating checklist

Version: 1.0 | Researched: 2026-09-28 | Ticket: [T003](../tickets/003-pakistan-fiscal-research.md)

## Launch rule

MyPoS may launch a merchant/store only in one of two truthful modes:

1. **Fiscal integration not applicable**, supported by a dated, approved applicability assessment; or
2. **Fiscal production ready**, with authority/onboarding confirmation, credentials, mappings, sandbox evidence, receipt verification and outage procedure complete.

An unknown, partially configured or sandbox-only state cannot be marketed or displayed as compliant. Creating a generic adapter interface does not satisfy a legal or production gate.

## Gate owners

| Gate | Responsible owner | Evidence approver |
|---|---|---|
| Merchant facts | Merchant owner/admin | MyPoS onboarding reviewer |
| Legal/tax applicability | Merchant's qualified adviser or written authority/integrator decision | Merchant owner plus MyPoS compliance owner |
| API and credentials | MyPoS integration engineer / licensed integrator | Technical owner |
| Tax/classification mapping | Merchant tax adviser/data owner | Merchant owner |
| Sandbox and receipt | Integration QA | Technical owner plus merchant representative |
| Outage/correction procedure | Compliance and operations | Authority/integrator evidence owner |
| Production activation | MyPoS authorized platform operator | Two-person approval |

## Gate A — Merchant classification

Required evidence:

- legal entity name/type and verified NTN/CNIC;
- STRN and provincial/territorial registrations or an approved not-applicable decision;
- every store's address and jurisdiction;
- goods/services/mixed activity and registered authority activity codes;
- FBR sales-tax profile, importer/manufacturer/retailer/Tier-1 status and prior POS integration;
- turnover/category facts relevant to a cited notification;
- authority/integrator/adviser decision with date, effective period and source.

Pass: every live store/activity has one applicability state for each potentially relevant authority. Fail: missing registration, conflicting facts, advice without cited basis, or reliance on a software salesperson's generic claim.

## Gate B — Current legal and technical pack

Capture:

- effective act/rules/notification/order;
- current authority API/schema/user guide and version/date;
- registered outlet/POS identifiers;
- sandbox and production endpoint instructions;
- licensed-integrator status where required;
- invoice fields, numbering/reference, logo/QR/fee and verification method;
- credit/debit note, cancellation/edit and return procedure;
- retention period;
- outage/store-and-forward rule and transmission deadline;
- support/escalation route and credential renewal process.

Pass: documents are current for the merchant's authority/activity and any ambiguity is resolved in writing or by a canonical onboarding pack. Fail: blogs/resellers alone, an older API assumed current, or copied credentials.

## Gate C — Tax and product/service mappings

For every sellable item/activity in scope, record:

- authority classification such as HS, UOM, sale type, SRO or service CPC/tariff;
- taxable, exempt or zero-rated status;
- dated rate and price-inclusive/exclusive rule;
- payment-method-dependent treatment where applicable;
- rounding and discount allocation;
- buyer-data rule;
- valid-from/to and approving adviser.

Pass: no item falls through to an invented default. Fail: one universal Pakistan rate, product name guessing, stale reference data or inability to reproduce a posted calculation.

## Gate D — Credentials and isolation

Pass criteria:

- separate sandbox and production credentials/endpoints;
- secrets stored outside repository/browser/logs;
- authority, merchant, store/outlet and POS scope recorded;
- least-privilege access and two-person production activation;
- rotation/revocation runbook tested;
- cross-merchant/store substitution tests fail safely;
- certificate and endpoint allowlist monitoring configured.

No production credential is required or used during T003.

## Gate E — Sandbox scenario suite

Required successful evidence:

- normal cash sale and every approved manual external tender mapping;
- registered/unregistered end-consumer patterns applicable to the merchant;
- each approved tax classification, rate, exemption and zero-rate case;
- discount and promotion cases;
- fractional/pack quantities where the authority UOM permits them;
- invalid field and partial-line error handling;
- exact duplicate, changed duplicate, concurrent retry and timeout-after-send;
- full/partial return or authority credit/debit note;
- correction inside/outside any prescribed window;
- QR/reference verification on the official surface;
- locally printed, PDF and emailed receipt evidence;
- reference-data refresh and effective-date transition.

Pass: expected authority-level acceptance/rejection is captured with redacted hashes and no duplicate. Fail: only HTTP status checked, sample payload copied without merchant scenario, or authority verification absent.

## Gate F — Offline and outage decision

For each adapter, answer:

1. May a sale be completed while the authority/integrator is unavailable?
2. What local receipt text/reference is permitted before acceptance?
3. What official middleware or durable store-and-forward mechanism is authorized?
4. What is the maximum upload delay and ordering rule?
5. How are duplicates and unknown outcomes resolved?
6. What happens when a pending record is definitively rejected?
7. How may the merchant continue during planned authority maintenance?

Status on 2026-09-28:

| Regime | Evidence | Gate result |
|---|---|---|
| FBR DI | Rules/docs require real-time electronic invoicing; reviewed FAQ gives no precise automated offline procedure | **Blocked for fiscal-required offline launch** pending written licensed-integrator/FBR contingency and sandbox proof |
| Legacy FBR Tier-1 POS | Existing real-time POS obligation/verification documented; exact transition/outage process merchant-specific | **Blocked** until integrator confirms the active protocol and outage rule |
| KPRA RIMS | Official guide documents a Windows utility that queues invoices and syncs after connectivity returns | **Conditionally feasible** after canonical schema, installer/security and real outage/replay test |
| SRB | Real-time reporting obligation documented; approved offline behavior not verified | **Blocked** pending SRB onboarding instruction and test |
| PRA | System existence/enforcement context confirmed; current public outage/API contract unavailable | **Blocked** pending PRA pack |
| BRA | Base e-invoice power/invoice fields confirmed; current integration/outage instruction unavailable | **Blocked** pending BRA decision |
| ICT | Tax regime confirmed; integration/outage mapping unresolved | **Blocked** pending FBR/licensed-integrator decision |
| AJK / GB | Current authoritative technical evidence not obtained | **Unsupported** pending authority evidence |

This table can restrict the general cash-offline promise. The product must show that restriction before a store is activated.

## Gate G — Receipt and user experience

Required receipt review for each authority/configuration:

- business/legal name, address and correct registration number;
- local immutable receipt number and timestamp;
- required customer/buyer data without excess PII;
- description/classification/quantity and tax-exclusive/inclusive figures;
- discount, tax rate/amount, exemption/SRO and totals;
- payment method where required;
- authority invoice/reference only after confirmed acceptance;
- correct authority logo, QR dimensions/content and verification text;
- service/invoice fee where legally applicable;
- original reference and adjustment number on returns/corrections;
- clear copy/reprint marker.

UX acceptance:

- `pending`, `accepted`, `rejected`, `unknown` and correction states have distinct text and icon, with color never the sole signal;
- cashier cannot dismiss a rejected/unknown state as accepted;
- owner/manager portal exposes reconciliation counts and aging;
- actions are keyboard usable, focus managed, status announced and verified in light and dark themes;
- printed/PDF/email renderings match the accepted evidence and remain legible in grayscale;
- server permission controls retry, correction, credential and production activation.

## Gate H — Reconciliation and operations

Before go-live:

- queue/outbox health and oldest-pending alerts configured;
- daily local sale/tax/tender totals reconcile to accepted, pending, rejected and adjusted fiscal documents;
- unknown outcomes have an owner and escalation timer;
- authority maintenance handling is in the runbook;
- backup/restore preserves fiscal documents, attempts and encrypted credential references;
- six-year or longer applicable record retention is configured without treating operational logs as the sole evidence;
- support tools redact secrets/PII and audit every retry/manual status action;
- credential/certificate expiry alerts are tested;
- production rollback cannot resend accepted invoices.

## Gate I — Returns, refund policy and payroll inputs

Returns:

- merchant's ordinary return policy is visible before/at sale where applicable;
- 7-day proposed default and configurable maximum 30 days do not override statutory remedies;
- manager late exception and reason are audited;
- fiscal adjustment links to accepted original and follows authority timing/approval;
- cash/stock/loyalty effects remain separate but coordinated transactions.

Payroll:

- employer/legal jurisdiction, pay frequency and worker category are captured;
- statutory tax/social-security amounts are merchant-approved entered/imported values;
- source period, authority/reference, approver and payout reconciliation are retained;
- product does not label an amount legally calculated or filed unless a later verified statutory adapter owns it.

## Authority-specific go-live checklist

### FBR Digital Invoicing

- S.R.O. 1413 applicability confirmed.
- Licensed integrator or PRAL relationship confirmed and current list checked.
- FBR profile/business scenarios and HS/UOM/rate/SRO data approved.
- DI v1.12 or successor contract version confirmed.
- Sandbox registration, testing and merchant scenarios passed.
- Production token/endpoint and outlet/POS relationship verified.
- FBR invoice number, line statuses, logo and QR printed and independently verified.
- Duplicate/lookup/unknown outcome and outage instructions proven.
- Debit-note/credit-note/correction process, including the 72-hour STGO rule, proven.
- Existing Tier-1 POS transition resolved if applicable.

### SRB

- Service and store fall within current scheduled integration class.
- Current Online Integration Rules and API pack captured.
- SNTN, outlet/POS and credential enrollment complete.
- Rate/payment/classification and one-rupee service-fee treatment approved.
- SRB invoice/reference and QR verified.
- Credit-note return/exchange and no-prepayment rule reflected.
- Duplicate, lookup and offline procedure proven.

### KPRA

- Restaurant/hotel/food/similar activity and registration confirmed.
- MyPoS/software-provider KPRA eligibility and merchant POS ID issued.
- Canonical live and/or utility field schema confirmed despite public-doc inconsistency.
- Unique invoice-number behavior and credit-note idempotency tested.
- Local utility installation, credential storage, queue/restart/update and QR verification tested if selected.
- Payment-method rate mapping is approved for the merchant; no rate copied from examples.
- Direct API timeout and middleware outage recovery proven.

### PRA, BRA, ICT, AJK and GB

The common gates apply. Each remains blocked until the missing authority-specific applicability and current technical/outage pack identified in [applicability research](pakistan-fiscal-applicability.md) is supplied and tested.

## Release decision record

Each production activation creates an immutable record:

```text
merchant/store/activity
authority and registration
applicability decision + legal basis/effective date
adapter and schema version
credential reference and expiry
approved tax/reference snapshot version
sandbox evidence set
receipt verification evidence
offline/outage mode
approvers and activation timestamp
next mandatory review date
```

Reassess on authority rule/API change, merchant registration/activity change, new store/jurisdiction, integrator change, credential rotation, failed reconciliation or at least before the recorded review date.

## T003 completion boundary

This research establishes implementable internal contracts and explicit production gates. It does not establish that MyPoS is legally compliant, does not approve a merchant, does not obtain credentials, does not call a sandbox or production endpoint, and does not qualify a fiscal printer/receipt. Those are T035 and pilot responsibilities using current merchant-specific evidence.

