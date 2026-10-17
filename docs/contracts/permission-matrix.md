# Fixed-role permission matrix

Version: 1.0 | Frozen: 2026-09-29 | Ticket: [T004](../tickets/004-freeze-domain-contracts.md)

## Enforcement model

Roles are fixed: platform administrator, merchant owner, manager and cashier. Staff attendance identity may exist without a login. UI visibility follows permissions, while server policies and domain services enforce them.

Every merchant operation requires active membership and authorized store scope. Owner does not imply platform access. Platform administrator does not silently become a merchant user; support access requires a recorded grant, purpose and audit.

Legend: **A** allowed; **S** allowed within assigned store; **P** requires explicit approval/reason; **—** denied.

## Platform and organization

| Action | Platform admin | Owner | Manager | Cashier |
|---|---:|---:|---:|---:|
| Create/activate/suspend merchant | A | — | — | — |
| Record plan/subscription payment | A | view own | — | — |
| View merchant plan/entitlements | A | A | view operational entitlement | — |
| Change locked store-data mode | — (migration ticket only) | — | — | — |
| Create/edit stores/registers | support-only audited | A | S if delegated | — |
| Enroll/revoke register device | support-only audited | A | S if delegated | — |
| Manage owner membership | support recovery with audit | A except last-owner safeguards | — | — |
| Manage managers/cashiers/store assignment | support-only audited | A | S for cashiers if delegated | — |
| View/change integration credentials | metadata/support only | A | — | — |
| Send configured transactional receipt channel | — | A | S | S for assigned-store receipts |
| Record customer channel opt-out | — | A | S | S during authorized customer/receipt workflow |

## Catalogue, pricing and customers

| Action | Platform admin | Owner | Manager | Cashier |
|---|---:|---:|---:|---:|
| View catalogue/prices | support grant | A | S | S till view |
| Create/edit/publish products | — | A | S | — |
| Import catalogue/opening stock | — | A | S with import permission | — |
| Publish price/tax version | — | A | S with pricing permission | — |
| Change promotion mode/default/override | — | A | S with pricing permission | — |
| Manual discount within cashier limit | — | A | A | S |
| Override discount limit/restricted rule | — | A/P | P | — |
| View/create/edit operational customers | support grant | A | S | S minimal checkout fields |
| Export customer data | support grant | A | S if delegated | — |
| Merge/dedupe customers | — | A | S with reason | — |

## Sales, returns and cash

| Action | Platform admin | Owner | Manager | Cashier |
|---|---:|---:|---:|---:|
| Open own register session | — | S | S | S |
| Sell/fully settle | — | S | S | S |
| Record manual external tender | — | S | S | S with configured permission |
| Sell into negative stock | — | S | S | S if merchant permits; audit warning |
| Sell expired batch | — | — | — | — |
| Suspend/resume own cart | — | S | S | S |
| View sale details | support grant | A | S | S limited/assigned history |
| Return inside window/allowance | — | A | S | S if configured |
| Late-return exception | — | P | P | — |
| Return beyond remaining quantity/value | — | — | — | — |
| Exchange | — | A | S | S under return rules |
| Reprint receipt | support grant | A | S | S with copy marker/audit |
| Open drawer for cash sale | — | S | S | S |
| No-sale drawer open | — | P | P | — unless approved one-time action |
| Open/operate assigned cash session, paid-in, custody handover | — | A | S | S |
| Cash paid-out or linked supplier cash payment | — | A | S | — |
| Submit blind close | — | S | S | S while current custodian |
| Approve cash variance | — | A | P within threshold | — |

## Inventory and purchasing

| Action | Platform admin | Owner | Manager | Cashier |
|---|---:|---:|---:|---:|
| View stock/batches/expiry | support grant | A | S | S availability only |
| Create/issue purchase order | — | A | S | — |
| Receive/partially receive PO | — | A | S | — |
| Over-receive beyond configured tolerance | — | P | P if delegated | — |
| Purchase return | — | A | S | — |
| Record supplier payment/allocation | — | A | S if delegated | — |
| Start/enter stocktake | — | A | S | assigned count entry if enabled |
| Post stocktake/adjustment | — | A | P | — |
| Ship/receive transfer | — | A | S | — |
| Override expired-batch block | — | — | — | — |

## Loyalty and public surface

| Action | Platform admin | Owner | Manager | Cashier/public |
|---|---:|---:|---:|---:|
| Configure program/rewards | — | A | S if delegated | — |
| Join member | — | A | S | cashier S / public rate-limited |
| Staff member lookup | support grant | A | S | S limited checkout DTO |
| Public points/rewards lookup | — | — | — | public rate-limited, masked DTO |
| Redeem online at checkout | — | A | S | S with member confirmation policy |
| Redeem offline/public portal | — | — | — | — |
| Manual points adjustment | — | P | P if delegated | — |
| View loyalty ledger | support grant | A | S | current member limited view only |

## Workforce, payroll and finance

| Action | Platform admin | Owner | Manager | Cashier/staff |
|---|---:|---:|---:|---:|
| View attendance/rota/leave | support grant | A | S | own only |
| Correct attendance | — | A | P | request own correction |
| Approve leave/rota | — | A | S | — |
| View salary/payroll amounts | support grant only | A | — by default | own payslip only |
| Prepare/post payroll | — | A | — | — |
| Record payroll payout/correction | — | P | — | — |
| View/create expense | support grant | A | S | submit own/store expense if enabled |
| Approve/post expense | — | A | P within threshold | — |
| View supplier/payroll settlement | support grant | A | supplier only if delegated | — |

## Reporting, imports and operations

| Action | Platform admin | Owner | Manager | Cashier |
|---|---:|---:|---:|---:|
| Merchant consolidated reports | support grant | A | assigned stores; no payroll | — |
| Store operational reports | support grant | A | S | own session summary only |
| Export reports/data | support grant | A | allowed report scopes | — |
| View audit log | support grant | A | assigned operational events | own relevant events only |
| Retry email/print | support grant | A | S | own receipt as allowed |
| Retry fiscal submission | support diagnostics only | P | P if delegated | — |
| Change fiscal status manually | — | — | — | — |
| Resolve offline conflict | support grant | A | P | view own conflict/action prompt |
| View secrets/raw payload | secrets-admin metadata only; raw redacted | credential metadata only | — | — |
| Create support access grant | designated platform admin | owner consents/requests | — | — |

## Approval contract

An approval is server-issued and contains merchant/store, permission code, target command/aggregate, approver, reason, issued/expiry time and single-use token hash. The approver must be independently authenticated and cannot approve their own prohibited action. Frontend PIN prompts do not directly elevate a session role; they exchange proof for a narrowly scoped approval.

Approvals expire after five minutes by default and are consumed atomically with the command. Retries of the same idempotent command return the original result without consuming a second approval.

## Public loyalty DTO

The only phone-only lookup result is:

```json
{
  "member_public_id": "uuid",
  "masked_name": "A*** K***",
  "masked_phone": "+92*******123",
  "points_balance": "125.0000",
  "available_rewards": [{"public_id":"uuid","name":"...","points_required":"100.0000"}]
}
```

It excludes email, full identity/address, customer records, purchases, receipts, notes, ledger history, redemption credentials and other merchants. Responses are rate-limited and designed to reduce phone-number enumeration.

## Required policy tests

Every endpoint/command tests: anonymous access, wrong role, inactive membership, unauthorized store, another merchant's public ID, related-record substitution, stale approval, approval for another target, exported/background access and cache-key scope. A 404-style non-disclosure response is preferred where existence itself is sensitive.

## Expanded reporting workspace

Reports use `/reporting` merchant routes. Owners can consolidate across stores and view costs, financial worksheets, valuation and payroll. Managers are restricted to assigned stores and receive no cost or margin fields. Cashiers may view only their own sales summary, daily/product/employee/session activity, average transaction and payment-method reports; report exports are denied.

`report.costs` and `report.financial` are separate permissions. Payroll requires `payroll.manage`; workforce requires `attendance.manage`; audit reports require `audit.view`. Identifiable customer report exports require `customer.export`. The fixed role map currently grants sensitive financial/cost/customer export capabilities to owners. Every export rechecks current membership and store assignment when queued, processed and downloaded. Legacy download routes apply the same restrictions.

Reporting setup uses catalogue management permission in assigned scope; merchant-wide classifications and register-group assignment require owner authority. Metadata imports use the existing validate/commit workflow with resource `reporting_catalog`. Personal saved filters never grant access to their stored scope.
