# Domain event and outbox catalogue

Version: 1.0 | Frozen: 2026-09-29 | Ticket: [T004](../tickets/004-freeze-domain-contracts.md)

## Envelope

Every committed event is an outbox record created in the same MySQL transaction as its aggregate change.

```json
{
  "event_id": "uuidv7",
  "event_name": "sales.sale.finalized",
  "event_version": 1,
  "occurred_at": "2026-09-28T10:15:30.123456Z",
  "merchant_id": "uuid",
  "store_id": "uuid-or-null",
  "aggregate_type": "sale",
  "aggregate_id": "uuid",
  "aggregate_version": 1,
  "correlation_id": "uuid",
  "causation_id": "command-or-event-uuid",
  "actor": {"type":"staff|device|system|platform","id":"uuid-or-null"},
  "payload": {}
}
```

Payloads contain stable public IDs and data needed by the declared consumer, never secrets, full model serialization or arbitrary database columns. Money/quantity values are decimal strings. Tenant scope is mandatory for merchant events.

## Delivery and compatibility

- Delivery is at least once. Consumer effects are idempotent on `(consumer_name, event_id)`.
- A consumer records receipt only after its transaction commits.
- Retryable failure uses bounded exponential backoff with jitter; permanent schema/business rejection goes to a visible failed-event state.
- Ordering is guaranteed only per aggregate through `aggregate_version`. Cross-aggregate consumers handle reordering.
- Additive optional payload fields may remain in the same version. Removing, renaming or changing meaning/type increments `event_version` or creates a new event.
- Outbox retention and replay policy preserve audit/recovery. Replay never changes `event_id`.

## Platform, identity and device events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `platform.merchant.activated` | ProvisionMerchant | merchant, plan, store mode, activation time | onboarding, audit, notifications |
| `platform.merchant.restricted` | RestrictNewOperations | merchant, reason code, effective time, pending-sale recovery allowance | auth/entitlements, operations |
| `platform.subscription.changed` | subscription command | subscription, old/new state, effective time | entitlements, notifications |
| `identity.membership.changed` | membership command | membership, user, role, status, store assignments version | auth cache invalidation, audit |
| `devices.enrollment.activated` | EnrollRegisterDevice | device, register, store, lease expiry, protocol range | offline provisioning, operations |
| `devices.enrollment.revoked` | RevokeDevice | device, reason, revoked time | auth/cache, offline conflict monitoring |
| `settings.version.published` | PublishSettings | scope, version, effective time, changed capability keys | client bootstrap, cache invalidation |

## Catalogue and pricing events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `catalog.product.published` | PublishCatalogueItem | product, scope, version, barcode/version summary | offline snapshot, search projection |
| `catalog.product.availability_changed` | assortment command | product, store, availability version | offline snapshot, checkout cache |
| `pricing.version.published` | PublishPricingVersion | scope, mode, version, effective interval | offline snapshot, cart reprice notices |
| `tax.version.published` | PublishTaxVersion | jurisdiction config, version, effective interval | checkout, fiscal reference projection |

## Sales, returns and cash events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `sales.sale.finalized` | FinalizeSale/IngestOfflineSale | sale, store/register/session, business date, totals, customer/member refs, snapshot refs, source online/offline | reports, fiscal queue, loyalty earn, email/receipt |
| `sales.return.posted` | PostReturn | return, original sale, store, totals, line references/dispositions, manager-exception flag | inventory/reporting, loyalty reversal, fiscal adjustment |
| `sales.exchange.posted` | PostExchange | exchange, return, new sale, difference | reporting/audit |
| `cash.session.opened` | OpenCashSession | session, register, cashier, opening amount/business date | operations/reporting |
| `cash.movement.posted` | PostCashMovement/sale/refund | movement, session, type, amount, source document | cash projections/reporting |
| `cash.session.closed` | SubmitCashSessionCount/ReviewCashSession | session, expected/count/variance, review state, business date | reports, alerts |

`sales.sale.finalized` may include summarized totals but not every customer detail or tender secret. A consumer needing full authorized detail reads the owning Sales interface.

T022 emits `sales.sale.finalized` in the same transaction as the sale and includes public sale/store/register/session identities, business date, gross total and `source=online`. It intentionally excludes external tender references, customer contact details and receipt content. Cash posting also emits the existing source-linked `cash.movement.posted` event; consumers deduplicate by immutable event ID/source identity.

## Inventory and purchasing events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `inventory.movement.posted` | stock-owning command | movement, product, location, batch, quantity, unit cost state, source | balance projection, reports/alerts |
| `inventory.negative_stock_recorded` | permitted issue | product/location/batch, resulting quantity, sale/source, actor | manager alert, cost reconciliation |
| `inventory.stocktake.posted` | PostStocktake | stocktake, store, movement IDs, variance summary | reporting/audit |
| `inventory.transfer.shipped` | ShipStockTransfer | transfer/shipment, source, lines/mapping version | destination workflow, reporting |
| `inventory.transfer.received` | ReceiveStockTransfer | transfer/receipt, destination, movements, discrepancies | reporting/audit |
| `purchasing.order.issued` | IssuePurchaseOrder | PO, supplier, store, ordered summary | notifications/reporting |
| `purchasing.receipt.posted` | ReceivePurchaseOrder | receipt, PO, supplier/store, stock movements, supplier charge, cost state | inventory/reporting |
| `purchasing.receipt.reversed` | ReversePurchaseReceipt | receipt, PO/version and reversal identity; detailed reason remains authorized domain data | inventory/reporting/reconciliation |
| `purchasing.return.posted` | PostPurchaseReturn | return, receipt/source, movements, supplier credit | reporting/reconciliation |
| `purchasing.return.reversed` | ReversePurchaseReturn | return and linked reversal identities | reporting/reconciliation |
| `purchasing.supplier_credit.confirmed` | ConfirmSupplierCredit | credit, return and confirmation state | reconciliation/reporting |
| `purchasing.supplier_payment.recorded` | PostSupplierPayment | payment, supplier and allocation evidence | reconciliation/reporting |
| `purchasing.supplier_payment.reversed` | ReverseSupplierPayment | payment and reversal identities | reconciliation/reporting |

## Customer and loyalty events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `customers.customer.changed` | customer command | customer, scope, version, safe search fields | search/offline snapshot where authorized |
| `loyalty.member.joined` | JoinLoyaltyMember | member, program, masked/contact-routing metadata, bonus entry if any | notification/reporting |
| `loyalty.redemption.reserved` | ReserveLoyaltyRedemption | reservation, member, points, expires, cart | checkout |
| `loyalty.redemption.committed` | FinalizeSale | entry/reservation, member, sale, points | member balance/reporting |
| `loyalty.points.earned` | earn worker | ledger entry, member, sale, points, program version | member balance/notifications |
| `loyalty.points.reversed` | return/correction worker | reversal entry, original entry, return, points, exception state | member balance/alerts |

Public member responses are never events. Contact data in queued notifications uses an authorized delivery record rather than broad event payload.

## Workforce, payroll and expenses events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `workforce.clock_event.recorded` | clock command | staff, store, event type/time/source | attendance projection |
| `workforce.leave.approved` | leave approval | leave, staff, interval/type | rota/attendance/payroll input |
| `payroll.run.posted` | PostPayrollRun | run, period, aggregate totals, item IDs | payslip delivery, reports |
| `payroll.payout.posted` | RecordPayrollPayout | payout, run, amount, allocations, cash source | reconciliation/reporting |
| `expenses.expense.posted` | PostExpense | expense, store/category, amount, payment source | cash/reports |
| `finance.settlement.reconciled` | settlement command | settlement, method/provider, totals, exceptions | reports/alerts |

Payroll event payloads exclude individual salary amounts unless the named consumer has a documented confidential channel. General reporting receives aggregate totals.

## Offline, print, fiscal and delivery events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `offline.command.acknowledged` | offline ingest | command, state, resulting aggregate type/public ID | device sync/operations |
| `offline.command.conflicted` | offline ingest | command, state, safe conflict code | device UI/manager queue |
| `printing.job.requested` | RequestPrint | print job, receipt, register, copy status, signed-payload reference | local-helper dispatch |
| `printing.job.outcome_recorded` | helper callback/status | print job, attempt, reported outcome/error | till UI/operations |
| `printing.drawer.authorized` | drawer command | drawer command, register, reasoned kind | local helper/audit |
| `printing.drawer.outcome_recorded` | helper callback/status | drawer command, reported physical outcome | till UI/operations |
| `fiscal.document.queued` | QueueFiscalDocument | fiscal document, local document, adapter/config version | fiscal worker |
| `fiscal.document.accepted` | fiscal worker | fiscal document, authority, authority reference/date, receipt-evidence version | receipt/reconciliation/reporting |
| `fiscal.document.rejected` | fiscal worker | fiscal document, safe category/code, remediation state | owner/manager alerts |
| `fiscal.document.outcome_unknown` | fiscal worker | fiscal document, attempt, since time, lookup capability | operations/reconciliation |
| `notifications.delivery.requested` | RequestReceiptDelivery/domain rule | delivery, template/version, channel, destination reference, consent basis | channel worker |
| `notifications.delivery.outcome` | channel worker/authenticated callback | delivery, provider reference, accepted/sent/delivered/read/failed/cancelled | portal/operations |
| `integrations.callback.recorded` | authenticated provider callback | integration, provider event identity, outcome and delivery reference | delivery projection/audit |

## Report and import/export events

| Event v1 | Produced by | Minimal payload | Intended consumers |
|---|---|---|---|
| `reporting.projection.refresh_requested` | domain consumers | projection, scope, through event/instant | reporting worker |
| `imports.batch.committed` | CommitImport | batch, type, accepted/rejected row counts, effects summary | audit/notifications |
| `exports.job.ready` | export worker | job, scope, object reference, expiry | authorized notification/UI |
| `exports.job.failed` | export worker | job, safe error category | UI/operations |

## Forbidden event patterns

- `model.updated` with arbitrary before/after serialization.
- Events containing passwords, PIN verifiers, provider tokens, signing keys, card data or raw authority payloads.
- Event consumers that post a second financial effect without inbox idempotency.
- Business-critical effects triggered only by in-process observers before transaction commit.
- Reusing an event name/version for a changed semantic meaning.

## Consumer failure classifications

- `retryable_infrastructure`: temporary database/network/provider failure.
- `dependency_not_ready`: required earlier aggregate/event missing; retry with bound.
- `permanent_contract`: unsupported event version or invalid payload; dead-letter and alert.
- `permanent_business`: domain precondition makes action invalid; record explicit exception.
- `unknown_external_outcome`: external commit may have occurred; move to reconciliation, not blind retry unless contract permits.
