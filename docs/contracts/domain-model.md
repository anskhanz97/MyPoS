# Frozen domain model and command boundaries

Version: 1.0 | Frozen: 2026-09-29 | Ticket: [T004](../tickets/004-freeze-domain-contracts.md)

## Contract authority

This document freezes the shared model for T005 onward. Owning tickets may add fields and internal implementation detail, but changing an invariant, identifier, ownership rule, transaction boundary or published state requires an ADR and compatibility impact note.

Excluded features do not have entities, flags, placeholder tables or permissions: recipes/ingredients/preparation/wastage, tables/kitchen, reservations/queues/delivery, customer debt/advance sales and missing C# behavior.

## Identity and common columns

Every aggregate has:

- internal MySQL `BIGINT UNSIGNED` primary key for joins;
- public UUIDv7 stored as `BINARY(16)` and rendered as canonical lowercase UUID text at interfaces;
- owning `merchant_id` where merchant data is involved;
- `created_at` and `updated_at` UTC instants with microsecond precision;
- explicit state/status and state-change metadata where the aggregate has a lifecycle;
- optimistic `lock_version` where an editable draft can be updated concurrently.

Public IDs are immutable, never recycled and reveal no table count. Offline-created commands and sales use client-generated UUIDv7 IDs. An internal key is never accepted as public identity.

Posted records add `posted_at`, `business_date`, `store_timezone`, `posted_by_staff_id`, and source snapshot/version references. Posted economic fields are immutable. A correction creates a linked reversal or adjustment.

## Scope and ownership

### Trusted context

The authenticated server context resolves:

```text
ActorContext
  actor_user_id
  staff_id nullable for platform actor
  merchant_id nullable for platform-only action
  authorized_store_ids
  active_store_id nullable
  register_id nullable
  device_id nullable
  role: platform_admin | owner | manager | cashier
  approval_context nullable
```

Client merchant/store/register IDs are selectors to validate against context, not authority. Related records must share compatible ownership in database and service validation.

### Store-mode ownership

`merchant.store_data_mode` is `shared` or `independent`, chosen during onboarding and immutable after activation.

| Record | Shared mode owner | Independent mode owner |
|---|---|---|
| Product/variant/barcode/unit/bundle/extras | Merchant | Store |
| Operational customer | Merchant | Store |
| Price book | Merchant definition with store assignment/override | Store |
| Supplier | Merchant by default, with store availability | Store unless explicitly merchant administrative record |
| Stock, batch, register, sale, purchase receipt | Store/location | Store/location |
| Loyalty member/program/points | Merchant | Merchant |
| Staff employment | Merchant with store assignments | Merchant with store assignments |
| Reports | Authorized projection across stores | Authorized projection; unmapped data remains distinct |

Scoped catalogue/customer tables use `scope_type` (`merchant` or `store`) and `scope_id` plus a generated/validated merchant boundary. A shared-mode record cannot point to a store owner; an independent record cannot be silently promoted to merchant scope.

In independent mode, cross-store product equivalence uses an explicit `product_mapping` with source product/UOM and destination product/UOM. Matching SKU/barcode text is not identity. Loyalty-member links connect the merchant member to zero or more operational customer records without merging those records.

## Aggregate catalogue

### Platform and tenancy

- `PlatformOperator`: privileged platform identity; no implicit merchant impersonation.
- `Plan`, `Entitlement`, `MerchantSubscription`, `SubscriptionPayment`: administrator-managed SaaS access and recorded payments.
- `Merchant`: legal/business account, locked store mode, default timezone/currency and settings-version pointers.
- `Store`: authorized operational boundary, address/jurisdiction and local settings pointers.
- `Register`: named till within one store.
- `DeviceEnrollment`: device key/lease/register assignment and revocation.
- `SettingsVersion`: append-only published merchant/store configuration snapshot.

### Identity and workforce

- `UserAccount`: online credential identity.
- `MerchantMembership`: fixed owner/manager/cashier role and status.
- `Staff`: employment/operational identity; may exist without login.
- `StaffStoreAssignment`: active authorized stores.
- `CashierPinCredential`: merchant/device-constrained verifier; never a global password.
- `Approval`: approver, permission, target command, reason, expiry and use record.
- `ClockEvent`, `AttendanceCorrection`, `RotaAssignment`, `LeaveRequest`.
- `PayrollPeriod`, `PayrollRun`, `PayrollItem`, `PayrollPayout`, `PayrollCorrection`.

### Catalogue and pricing

- `Product`: standard, variant parent, weighted, pack, fixed bundle; no service/recipe type.
- `Variant`, `Barcode`, `Unit`, `UnitConversion`.
- `BundleComponent`: finished catalogue component and fixed quantity; stock consumption follows configured finished goods.
- `ExtraGroup`, `ExtraOption`: selectable sale-line additions; no ingredient semantics.
- `StoreAssortment`: availability and local flags.
- `PriceBook`, `PriceVersion`, `PromotionVersion`, `PromotionRule`, `Coupon`, `CouponUse`.
- `PriceSnapshot`: immutable rules used by quote/offline sale.
- `DiscountAllocation`: source/rule, line allocation and manual approval/provenance.
- `TaxRuleVersion`: authority/jurisdiction/classification, inclusive/exclusive basis, rate/rounding/effective range and evidence reference.

Promotion mode is a published setting: `manual_only` or `advanced`. Merchant default may have a store override. Existing carts retain their recorded snapshot until explicit reprice; finalization rejects an expired or disallowed snapshot rather than silently changing totals.

### Customers and loyalty

- `OperationalCustomer`: scoped according to store mode, with local reference, original/canonical contact values, receipt preference, explicit marketing-consent metadata, archive time and optimistic version.
- `CustomerGroup`: named classification in the same merchant/store scope as its operational customers.
- `LoyaltyProgram`, `LoyaltyMember`: merchant-owned.
- `CustomerMemberLink`: explicit link across the ownership boundary.
- `PointsLedgerEntry`: earn, redeem, expire, refund reversal, manual adjustment.
- `RewardDefinition`, `RedemptionReservation`, `RedemptionCommit`.

Phone normalization produces E.164-style Pakistan-aware canonical value where possible plus original display value. Operational profile phone/email values are indexed for exact duplicate review but are not unique identity keys and never trigger automatic merging. A customer-member link requires an explicit authorized action, compatible merchant and matching canonical phone. Unique membership is merchant/program plus canonical phone. Public lookup returns a dedicated limited DTO only.

Customer archive is reversible and preserves links and historical references. A new sale may proceed without a customer; when a customer is selected, the sale copies a minimal immutable snapshot rather than relying on mutable profile fields. Email receipt preference and marketing consent remain separate. Marketing opt-in stores source and time, and opting out clears that consent metadata. Customer credit, debt, unpaid statements and layaway are absent from this model.

### Inventory and purchasing

- `InventoryLocation`, `Batch`, `StockMovement`, `StockBalanceProjection`.
- `Stocktake`, `StocktakeLine`, `InventoryAdjustment`.
- `StockTransfer`, `TransferLine`, `TransferShipment`, `TransferReceipt`.
- `Supplier`, `PurchaseOrder`, `PurchaseOrderLine`.
- `GoodsReceipt`, `GoodsReceiptLine`, `PurchaseReturn`.
- `SupplierCharge`, `SupplierCredit`, `SupplierPayment`, `SupplierAllocation`.
- `CostLayerAdjustment`/`CostReconciliation` for moving-average corrections.

The stock ledger is authoritative. A movement has source document, source line, product/base unit, location, optional batch, signed quantity and cost evidence. Unique source keys make posting idempotent.

### Checkout, cash and returns

- `Cart`, `CartLine`: mutable working aggregate; not financial truth.
- `SuspendedCart`: persisted cart snapshot, not a sale or advance.
- `Sale`, `SaleLine`, `Tender`, `ReceiptSnapshot`.
- `Return`, `ReturnLine`, `RefundTender`.
- `Exchange`: links exactly one posted return and one new sale; difference settles in the new sale/refund path.
- `CashSession`, `CashMovement`, `CashSessionCount`, `CashSessionHandover`, `CashVarianceReview`.
- `ExternalPaymentRecord`: manual declaration, method, external reference and confirming staff; no card secrets/provider authorization claim.

A finalized sale is fully settled. `sum(accepted tender amounts) - cash_change = sale_total`. Cash change is represented explicitly and reduces expected drawer cash.

T022 implements `Sale`, `SaleLine`, `SaleLineExtra`, `SaleTender` and `ReceiptSnapshot` as merchant/store-owned immutable posting records. The source cart and quote remain linked evidence; conversion prevents reuse. `client_transaction_id`, `idempotency_key` and `payload_hash` resolve uncertain results and reject altered retries. The receipt snapshot stores the same authoritative line/tender/totals facts behind a version and content hash, while later print attempts reference it without reposting the sale.

### Finance, reporting and operations

- `Expense`, `SettlementRecord`, `ReconciliationException`.
- `ReportDefinition`, `SavedFilter`, `ReportProjection`, `ExportJob`, `ImportBatch`.
- `OutboxMessage`, `InboxReceipt`, `AuditEvent`, `Notification`, `DeliveryAttempt`.
- `MerchantIntegration`: one versioned merchant/provider configuration with safe settings, encrypted credentials, state, capability metadata and connection-test evidence.
- `NotificationConsent`: merchant/channel/purpose/recipient consent or opt-out evidence with source and effective time.
- `IntegrationCallback`: authenticated provider event identity, bounded payload evidence and processing outcome; unique within an integration.
- `PrintJob`, `FiscalDocument`, `FiscalAttempt`, `OfflineCommand`, `OfflineConflict`.
- `SupportAccessGrant`, `OperationalIncident`.

## Command contract catalogue

Every command declares an authenticated actor, permission, aggregate/state precondition, idempotency scope and one authoritative transaction. External work is appended to the outbox and runs after commit.

| Command | Minimal intent payload | Permission | Authoritative transaction |
|---|---|---|---|
| `ProvisionMerchant` | plan, legal/display data, `store_data_mode`, first owner/store | platform admin | merchant, subscription, owner membership, first store, audit |
| `EnrollRegisterDevice` | store, register, device public key/name | owner | device assignment/lease and audit |
| `PublishSettings` | expected settings version, changed settings | owner; scoped manager settings where granted | new settings version and outbox |
| `PublishCatalogueItem` | product draft fields and expected version | owner/manager | product/version/barcodes and audit |
| `PublishPricingVersion` | mode, effective interval, rules | owner/manager with pricing permission | validated immutable snapshot and outbox |
| `CreateOperationalCustomer` | scoped contact/profile fields and optional group | cashier for checkout-safe fields; owner/manager for full profile | customer, version and audit |
| `UpdateOperationalCustomer` | expected version, changed fields or archive state | cashier for checkout-safe fields; owner/manager for full profile | locked customer update/version and audit |
| `LinkCustomerLoyaltyMember` | customer, member or null, expected version | owner/manager with loyalty permission | compatible link/unlink, customer version and audit |
| `OpenCashSession` | register, opening float, command ID/time | `cash.operate` in assigned store | one active session, opening movement, audit/outbox |
| `QuoteCart` | lines, customer/member reference, snapshot intent | cashier | no financial post; may persist cart/quote snapshot |
| `FinalizeSale` | cart/quote snapshot, tenders, client command ID | cashier; approval for restricted discount/stock | sale/lines/tenders, stock/cash, receipt, outbox, audit |
| `PostReturn` | original sale lines/quantities, disposition, refund tenders, reason | cashier inside policy; manager approval outside | return/refund, stock/cash, loyalty reversal request, fiscal/receipt outbox |
| `PostExchange` | return intent plus new sale intent and settlement | same as return and sale | linked return, sale, effects and outbox atomically where feasible |
| `CreatePurchaseOrder` | supplier, store, lines, expected dates | owner/manager | purchase order and audit; no stock |
| `ReceivePurchaseOrder` | PO, receipt lines, batch/expiry/cost, client command ID | owner/manager | receipt, stock/cost movements, supplier charge and outbox |
| `PostPurchaseReturn` | source receipt lines, quantities, reason | owner/manager | supplier credit, outbound stock/cost movement, audit |
| `PostInventoryAdjustment` | count/reference, product/batch/location quantity delta, reason | manager; owner for high-impact threshold | stock movements, projection, audit |
| `ShipStockTransfer` | transfer/version, source allocations | manager | source stock movements and shipment state |
| `ReceiveStockTransfer` | shipment/version, received mappings/quantities | manager | destination movements and receipt state |
| `SubmitCashSessionCount` | counted denominations/total, pending-sale counts, expected version | `cash.operate`; current custodian | immutable count/cutoff and pending, variance or closed projection, audit/outbox |
| `ReviewCashSession` | approve/reopen, expected version, reason | `cash.variance.approve` | immutable review, recomputed expected/variance, close/reopen projection and outbox |
| `HandoverCashSession` | target active store membership, reason | `cash.operate`; current custodian | immutable handover and updated custodian |
| `PostCashMovement` | type, amount, reason/source | paid-in `cash.operate`; paid-out/supplier `cash.manage`; no-sale `drawer.no_sale` | one append-only drawer effect, linked source identity, audit/outbox |
| `JoinLoyaltyMember` | program, phone, minimal profile, request key | public rate-limited/staff | unique member/link/bonus and audit |
| `LookupPublicLoyalty` | program slug, phone | public rate-limited | read only; limited DTO |
| `ReserveLoyaltyRedemption` | member, reward/points, sale/cart | cashier online | locked balance/reservation with expiry |
| `PostPayrollRun` | period/version, approved items | owner | immutable run/items, audit/outbox |
| `RecordPayrollPayout` | run/items, amounts/method/reference | owner | payouts/allocations and optional cash effects |
| `IngestOfflineSale` | command envelope and immutable local sale | enrolled device, valid lease | same finalize domain transaction plus acknowledgement/conflict |
| `RequestReceiptDelivery` | posted document, channel, destination/consent | staff/customer workflow as authorized | delivery request/outbox; never repost sale |
| `ConfigureMerchantIntegration` | provider, expected version, enable state, safe settings and explicit secret changes | owner | versioned encrypted configuration and audit; no provider-side business mutation |
| `RecordIntegrationCallback` | integration, authenticated provider event identity and bounded outcome | verified provider callback | deduplicated callback and monotonic delivery evidence |
| `RequestPrint` | receipt, copy/original intent, printer/register | cashier; manager for special/no-sale | signed job/audit, after sale commit |
| `QueueFiscalDocument` | posted sale/adjustment, applicable config | system | fiscal document/outbox; external acceptance later |

Generic table-level write endpoints are prohibited for posted business aggregates.

## Idempotency and fingerprints

Mutating commands that can be retried require an `Idempotency-Key` UUID and canonical request fingerprint.

Uniqueness scope:

```text
(merchant_id, command_name, caller_scope_type, caller_scope_id, idempotency_key)
```

The fingerprint is SHA-256 of canonical JSON after validation/default expansion, excluding transport metadata. Object keys are sorted; decimals use canonical non-exponent strings; UUIDs lowercase; arrays preserve meaningful order. The record stores command version, fingerprint, state and final response reference.

- Identical retry returns the original status/body and no new effects.
- Same key with different fingerprint returns `409 idempotency_key_reused`.
- In-progress key returns `409 command_in_progress` with safe retry guidance.
- Unknown client result is resolved by idempotency status/resource lookup, never a new key.

## Audit and reversal

Audit event fields: actor, effective staff, merchant/store/register, action, aggregate type/public ID, reason, approval ID, before/after field subset, request/correlation ID, UTC time, business date and source (`online`, `offline`, `system`, `support`). Secrets and unnecessary PII are redacted.

Drafts may be cancelled/deleted under their owning ticket. Posted sales, returns, tenders, cash, inventory, loyalty, payroll, expense and fiscal records cannot be hard/soft deleted through normal application flows. Reversal entries reference the original and record reason/approval.

## Time and reporting boundaries

- Store timezone is an IANA name; launch default `Asia/Karachi`.
- All instants are stored in UTC with microseconds.
- Every posted business document stores an explicit local `business_date` determined at posting using the store's published cutoff configuration.
- Launch default cutoff is local 00:00. A store may configure one cutoff in `00:00`–`06:00`; a change applies to new documents only and is versioned.
- A session keeps the opening settings version and spans its actual instants; daily reports group by stored business date, not by recomputing historical timestamps.
- Fiscal date/time follows the applicable adapter contract and may further constrain offline/posting behavior.

## API compatibility

- Public and device APIs live under `/api/v1`.
- Additive optional response fields are backward compatible. Removing/renaming fields, changing meaning/scale/state or making optional input required needs a new contract version/migration.
- Offline command schemas declare `schema_version`; the server supports every version within the published offline lease plus deployment overlap or supplies a tested local migration/recovery path.

T023 adds `OfflineBootstrapSnapshot` and `OfflineCommand`. A snapshot belongs to one merchant/store/register/device and records schema version, opaque version, content hash, signed payload and lease window. A command belongs to the same scope and optionally a cash session; it records the client command UUID/type, local commit time, snapshot/fence, payload/hash, attempts, stable state, result aggregate or safe conflict. Sales record `source=online|offline`. These records preserve reconciliation evidence and do not create a second stock, cash or pricing implementation.
- Events use immutable name plus integer schema version. Consumers ignore documented additive fields but reject unsupported breaking versions visibly.

## External gates

Code-ready requirements are frozen here. Fiscal provider behavior, physical hardware compatibility, email deliverability and achieved performance remain evidence gates owned by T024/T034/T035/T036/T037. Their unknowns cannot be replaced with successful states in code or fixtures.
