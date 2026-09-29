# Domain data and invariants

Status: design contract guide, not final migrations. T004 freezes the schema/contracts; owning tickets extend them with explicit compatibility notes.

## Logical entities

- Platform: platform operators, merchants, plans, entitlements, subscriptions, subscription payments and support audit events.
- Organization: stores, registers, provisioned devices, staff accounts, employment records, merchant memberships and settings versions.
- Catalog: products, variants, barcodes, units, unit conversions, fixed bundle components, extra groups/options, store availability and scoped price lists.
- Pricing: published price/promotion versions, rules, schedules, coupon definitions, usage records and line/cart discount allocations.
- Customers: merchant/store operational customers and independently merchant-scoped loyalty members and links.
- Inventory: locations, stock movements, balances, batch records, counts, adjustments, transfer shipments/receipts and cost adjustments.
- Purchasing: suppliers, purchase orders/lines, receipts/lines, supplier charges/credits, purchase returns and supplier payments/allocations.
- Checkout: carts/lines, quotes, sales/lines, tender records, return lines, exchange links, receipt snapshots and offline submission records.
- Cash: register sessions, cash movements, counts, expected totals, variances and approvals.
- Loyalty: programme configuration, points ledger, reward definitions, redemption reservations/commits, expiry entries and reversal links.
- Workforce: staff records, rota assignments, clock events, attendance corrections, leave types/requests/approvals and payroll periods/runs/items/payouts.
- Finance: expense categories/records, settlement batches, external payment references and reconciliation exceptions.
- Operations: outbox/inbox records, jobs, notifications/deliveries, exports/import batches, audit events, print jobs, fiscal submissions and diagnostics.

## Non-negotiable invariants

### Money and sale integrity

For a finalized sale, allocated discounts and taxes reconcile to the frozen sale total. Accepted tenders settle exactly the amount due; cash change is explicit, not silently counted as revenue. External tenders record their manual-confirmation provenance. Loyalty is accounted for using one documented treatment, without also counting redeemed value as fresh cash.

Repeated finalize requests create one sale and one set of inventory/loyalty effects. A return references original eligible lines, preserves their monetary allocations and cannot exceed remaining quantity or refundable value. Cumulative component entitlement is `round(original component × cumulative returned quantity ÷ original quantity, 2)` and the current document receives that entitlement minus all prior posted amounts. This rule applies independently to pre-discount, discount, net, tax and gross. An exchange links the committed return and new fully settled sale in the same transaction and stores `new sale gross - return gross` as a magnitude and direction. Posted financial records cannot be erased through ordinary CRUD.

A restocked sale return never uses the ledger's one-to-one reversal link, because one sale movement can have several valid partial returns. Instead, each return stock effect links the return line, original sale movement and appended return movement with its six-decimal quantity. The sum linked to an original movement cannot exceed the cumulative proportional return entitlement. Non-restock dispositions have no inventory effect. Original batch identity is retained and an expired batch cannot re-enter sellable stock.

Published pricing versions are append-only. A mode or rule change creates a new effective version and never rewrites a completed sale or silently changes an open cart. Quotes carry the composite merchant/store pricing signature; online finalization rejects a stale signature until the cart is visibly repriced. Coupon evaluation during quote is non-consuming. Limited coupon usage is claimed only inside the future sale-finalization transaction with merchant, rule and client-transaction idempotency.

Cart lines store display snapshots but retain tenant-owned variant/unit/extra/batch references. Quantities are accepted as decimal strings, converted once to base quantity with the configured direct conversion and never trusted from client-computed totals. Batch-tracked products require a same-store, same-location, same-variant, exact-quantity, unexpired server selection snapshot. Extras must be active options from the line product and satisfy every group minimum/maximum. Manual discounts require `discount.override`; the server quote independently rejects discounts above remaining value. Cart quotes are immutable and coupon-non-consuming. A quote, suspended cart or scanner acknowledgement never means inventory, cash or a sale was posted.

Promotion discounts never reduce a line below zero. Same-priority rules in one exclusivity group select the customer-lower payable result, with immutable rule ID as tie break. Cart allocations conserve every paisa by largest remainder. Taxes use the post-discount basis unless a versioned fiscal rule explicitly requires another legal basis. Manual discounts require a server permission and retain their source separately from automatic rule allocations.

### Stock and cost

Quantities use defined base units and reversible conversions. Buying a carton and selling a piece must not create or destroy units. Fixed bundles consume explicitly configured finished-product components, not recipes. Stock movements reference source documents and are idempotent. Batches belong to the correct tenant/product/location, and expiry is checked at the relevant sale time.

The stock movement ledger is the quantity authority. A movement cannot be updated or deleted; a correction is a new opposite-signed movement with a unique reversal link. Its effective identity is merchant + store + canonical source key, and its fingerprint includes the converted base quantity and all business-significant fields. An identical retry returns the stored movement, while a different payload under the same identity fails. Balance rows are projections only. Posting locks the location/variant balance dimension before source lookup and increments quantity, movement count and projection version atomically. Reconciliation exposes drift before an explicit, reasoned rebuild derives balances from the ledger.

Inventory locations never cross stores and have one of three dispositions: sellable, unavailable or quarantine. All quantities are stored at six-decimal base-unit precision after a direct, variant-specific conversion. Bundle stock effects flatten to stable finished-product leaves and post once per source line/effect. Negative quantities are valid recorded states and must remain visible as warnings. Adjustment reasons are merchant-defined, direction-constrained and may require a scope-bound, single-use manager approval. Cost is a separate immutable store/variant ledger and projection; its sequence, snapshots and correction links cannot be inferred from a current product field or physical batch order.

For a batch-tracked product, every post-conversion stock movement carries a batch that belongs to the same merchant and variant. The per-batch balance projection must reconcile to batch-tagged movement sums, while aggregate balances continue to reconcile to every movement. Existing positive stock can be converted only when allocations equal every non-zero balance exactly; negative stock blocks conversion. The conversion's release and batch additions conserve the aggregate quantity and its idempotency key/fingerprint are stored on the product. A repeated identical conversion returns the original state and changed reuse conflicts.

Expiry uses the store timezone and a date-only boundary: `expiry_date < business_date` is expired, while equality remains sellable for that day. FEFO never includes archived, expired, zero or negative batch balances. Manual selection cannot cross product, merchant, store, location or expiry boundaries. Batch-tracked shortages conflict instead of using an unnamed or expired batch, even where ordinary untracked negative stock is allowed. Returns and transfers retain the original batch when known; a return may post the same batch into an unavailable or quarantine location. A correction retains and reverses the original movement's batch.

Transfer dispatch and receipt are distinct ledger events with distinct idempotency identities. A draft may be cancelled only before dispatch. The source is reduced exactly once at dispatch; the destination increases only for posted receipt lines. Expected destination quantity is the dispatched source quantity multiplied by the transfer line's frozen mapping factor. Partial receipts retain the remaining in-transit quantity. A short close or accepted excess records the explicit quantity and reason instead of editing requested or dispatched history. An expired batch can enter only an unavailable or quarantine destination. Independent-store mappings are directional, audited and frozen onto each line so later mapping edits cannot rewrite an active or historical transfer.

A stocktake never assigns its counted quantity directly to the live balance. Opening captures a baseline ledger position and quantity by location, variant and optional batch. Count entry captures its own ledger cutoff and computes `expected_at_count = baseline + movements after baseline through cutoff`; the posted adjustment is `counted - expected_at_count`. Posting appends that discrepancy once and leaves every later movement in force. Every line must be counted before review, and only a reviewed, version-matching stocktake can post.

A purchase order is a planning record and never proves receipt, inventory ownership, tax liability or supplier payment. Ordered quantity is captured in its named purchase UOM and as a frozen six-decimal base quantity using one direct variant conversion. Received, returned and cancelled quantities remain separate. Remaining quantity is `max(ordered - received - cancelled, 0)`; a purchase return does not silently reopen the original order. An amendment cannot change an existing line's variant/UOM identity, remove a fulfilled line or reduce ordered base quantity below received base quantity. Every create, amendment, issue and cancellation has a versioned snapshot. Supplier archive prevents new orders or supplier edits but never removes historical purchase-order references.

A goods receipt may post only against an issued or partially received, version-matching order. It cannot exceed remaining base quantity; verified excess requires an order amendment first. Named-UOM quantity, frozen conversion, actual unit cost, landed cost, recoverable tax, batch facts, stock movement and valuation entry remain on each immutable receipt line. Merchandise plus landed cost enters inventory value; recoverable tax is excluded. The rounded supplier charge is evidence for T019 and is not a payment. Stock, PO progress, charge and outbox evidence commit together and an identical idempotent retry creates none of them twice.

A purchase return cannot exceed the receipt line's quantity after prior non-reversed returns and cannot remove stock unavailable in the original store/location/batch. Its inventory issue snapshots the current moving average; its supplier-credit amount uses the original receipt charge proportion rather than current cost. Cumulative two-decimal entitlement prevents repeated partial returns from accumulating rounding drift. Physical return, expected credit and confirmed supplier document are distinct states. Return reversal is append-only and tail-safe.

Supplier balance is an operational ledger, not a general ledger or bank feed. Charges add to balance; confirmed credits and recorded payments reduce it; expected credits are disclosed but excluded. Payment allocations are explicit two-decimal rows and cannot exceed payment or charge open amount. Unallocated payment remains visible. Payment reversal appends equal negative payment/allocation rows. Posted entries have no edit/delete path, and customer records gain no debt account.

For positive known stock, moving-average value is `old value + inbound value` and average is that value divided by resulting base quantity, rounded half-up to six decimals. Outbound movements snapshot the current average. Negative stock carries a provisional average; receipts record resolved negative quantity and `actual minus provisional` cost adjustment without editing an earlier sale or issue. If a receipt crosses zero, residual positive stock uses the receipt cost. Unknown historical cost remains pending and reports cannot substitute zero. Backdated documents post in current valuation sequence. Safe receipt reversal is append-only and is rejected after later cost activity or when original stock/batch quantity is no longer available.

Transfers conserve value. Dispatch snapshots source cost; destination base-unit cost is divided by the frozen quantity mapping factor and then enters the destination moving average. No supplier charge is created. Batch/FEFO identity remains physically traceable but does not select accounting cost.

A product has exactly one default variant. Catalog SKUs and barcodes are unique within the effective shared-merchant or independent-store scope, and barcode values preserve leading zeros. Unit conversions are positive direct factors to a variant's base unit. Bundle graphs are acyclic even when bundles contain other fixed bundles. An extra can optionally reference a finished-product variant and positive base quantity; both values must be present together. Archived products remain readable for historical snapshots and cannot disappear from posted records.

Negative stock is permitted by policy with warnings/audit, but an expired batch is not made sellable by a negative-stock permission. Returns distinguish restockable stock from quarantined/unavailable stock without adding a dedicated wastage module. FIFO/FEFO physical selection does not silently replace moving-average accounting.

### Tenancy and stores

Every mutation and read checks merchant boundary, store authorization and related-record compatibility. Merely filtering list pages is insufficient. Shared operational data and independent operational data must both work. Merchant-wide loyalty is intentionally shared in either mode; it does not imply a right to view another branch's private customer history.

In independent mode, stock transfers require an explicit source-to-destination SKU/UOM mapping; unrelated product IDs cannot be treated as identical. Consolidated product reporting must label unmapped products separately instead of guessing equivalence.

### Payroll and expenses

Posted payroll records preserve salary basis, attendance inputs, adjustments and approval identity. Changing current salary or rota cannot rewrite an old payslip. Advances and deductions must not be applied twice. A recorded payout means staff recorded payment, not that an automated bank transfer occurred. Categorized expenses and supplier/payroll payments must not double-count cash outflows in reports.

### Loyalty and customer portal

Phone normalization is Pakistan-aware and merchant-scoped. Points ledger sums reconcile to balances. Earn, redeem, expiry and refund reversal have distinct references. Concurrent redemption cannot spend the same available balance twice. Offline sale acceptance can earn later but cannot authorize offline spending.

An operational customer follows the merchant's locked store-data mode. Shared mode uses one merchant-scoped profile; independent mode permits separate profiles and reused contact details per store. Duplicate matching is advisory and limited to exact normalized phone/email values inside that scope. It never automatically merges profiles. Customer groups cannot cross the profile's scope. A loyalty link is an explicit manager/owner operation, requires matching normalized phone and points to a loyalty member in the same merchant; it does not expose the other linked operational profiles.

Email receipt preference is not marketing consent. Marketing opt-in requires an email address, consent source and timestamp, while opting out clears the consent metadata. Cashiers receive and may update only the checkout-safe contact subset. Archived customers cannot be newly associated with a sale. A sale stores its own minimal customer snapshot so a later rename, contact change or archive cannot change historical documents. A sale without any customer remains valid; customer IDs are nullable at the checkout contract boundary. No customer debt balance, credit limit, unpaid statement or layaway record exists.

Phone-only public lookup returns a purpose-built DTO: limited points/reward data plus masked identity. Never serialize the full member model or return email, full name/address, receipts, history, private notes or redemption authorization. Redemption occurs only through authenticated staff checkout.

T028 public join uses one unique merchant/canonical-phone member identity and an independent merchant/idempotency-key enrollment command. Identical replay returns the same member; changed data under the key conflicts. Different concurrent keys for the same phone may create two enrollment facts but exactly one member and zero implicit points entries. Optional store attribution must resolve to an active store of the programme's merchant and never changes the merchant-wide ownership of points. Marketing consent is explicit, optional and does not affect joining. Lookup refresh is read-only for member, enrollment, balance and ledger records; abuse events are separate operational telemetry containing keyed phone/IP hashes rather than raw values.

### Historical time

Store timezone defaults to Asia/Karachi while timestamps are stored with an unambiguous standard and business date is explicit. Define business-day cutoff separately from calendar midnight. Closed sessions and reports must retain how historical transactions were assigned. Research legal fiscal date requirements rather than equating all reporting clocks.

## Worked-example pack to create

T004 provides machine-readable golden fixtures for price/tax rounding, fractional quantity and split tender. T012 adds promotion overlap and partial return allocation. T018 adds moving-average receipt/return/negative-stock cases. T023 reuses quote fixtures in the browser. T027 adds loyalty refunds after points were spent. T030 adds hourly/monthly payroll and corrections.

T027 stores points at four decimals and earning rates at eight decimals. Earned points are `floor(eligible sale net × the sale-time programme rate, 4)`; a retry of the same sale returns the existing ledger entry. Balance must equal the sum of all member ledger entries. A reward reserves its configured points and monetary discount, never an amount invented by the browser. Active reservations are subtracted from availability under a locked member balance, so parallel stores cannot reserve the same points. Finalization converts one reservation into one debit and records FIFO consumption against unexpired earn entries. Expiry can debit only the unconsumed remainder of its earn and cannot push a non-negative balance below zero. Refund reversal is linked to the original earn and uses cumulative returned net, allowing a negative points balance after prior spending. Negative points are repaid by later earns and are never represented as customer money debt.

T022 makes finalized sales immutable snapshots rather than renamed carts. Each sale is unique by merchant/client transaction ID and merchant/idempotency key, points to the exact cart quote and session used, and stores sale-time product, unit, extra, customer, pricing-allocation and tender facts. A sale is valid only when accepted tenders less explicit cash change equal its quoted total exactly. External references are limited staff-entered evidence and never contain PAN, CVV or provider-secret data. The receipt snapshot has a content hash and version; printing and fiscal states remain separate outcomes. Every source-linked stock, valuation, cash, coupon and outbox effect is created in the same transaction or none is retained.

At least one scenario must include two stores with the same local SKU or phone that refer to different operational records. Another must link the same member's loyalty points across independent stores without merging their customer profiles.

### Integration and messaging invariants implemented by T038

- `merchant_integrations` are merchant-owned and unique by merchant/provider. Configuration updates require the expected version; credentials are encrypted and omitted from serialized responses.
- A provider cannot become active until its required settings and credentials are complete. Disabling it blocks new work but never deletes delivery or callback history.
- Receipt messaging references the immutable receipt snapshot and has a tenant/channel/source/recipient idempotency boundary. It cannot post, alter or repeat a sale.
- Transactional receipt purpose, marketing consent and channel opt-out are distinct. An applicable opt-out prevents a new message even when the provider is active.
- Callback event identity is unique within the integration. Authentication happens before state mutation; duplicates are acknowledged without duplicating attempts or cost.
- Delivery state only advances from local queue/attempt evidence or authenticated provider evidence. Provider acceptance is not described as recipient delivery.
- Stripe and manual payment-destination configuration create no tender, capture, settlement or accounting effect.
### Cash-session invariants implemented by T020

- A register has at most one active cash session. `cash_sessions(register_id, active_guard)` is unique while `active_guard = 1`; closed rows set it to null. Register-row locking and the database constraint cover both ordinary and simultaneous opens.
- A session opening creates exactly one `opening_float` movement. Cash movements, blind counts, handovers and variance reviews are append-only evidence. The session row is the versioned current projection.
- Expected drawer cash is the sum of signed cash movements, including opening float, cash sale net of change, refunds and linked payouts. Manual external tenders have no cash movement and therefore zero drawer effect.
- Every owning document can contribute at most one movement through the merchant/store source key. A cash supplier payment's movement must equal the linked operational payment and cannot be recorded twice.
- Expected amount and variance stay null while a session is open and while unresolved server/offline sales remain. A blind count fixes the cutoff, movement high-water mark, pending counts, denominations when supplied, cashier and count amount before expected cash is exposed.

## Offline command and snapshot invariants

- `offline_bootstrap_snapshots` is immutable device/store/register evidence: schema version, opaque snapshot version, canonical content hash, payload, signed envelope and issue/expiry times.
- `offline_commands` has one `(merchant_id, command_id)` identity. Device, command type and canonical payload hash must also match on replay; changed reuse never mutates the original result.
- Local success requires one strict IndexedDB transaction containing both sale/receipt evidence and its outbox command. Server acknowledgement requires the existing authoritative sale or cash domain transaction to commit first.
- `pending` survives retryable infrastructure failure; `acknowledged` stores the aggregate public ID; `conflict` stores a bounded safe code/message. A conflict counts as unresolved for cash-session closeout and is not silently discarded.
- A valid offline cash sale is fully settled, cash-only, within the signed snapshot lease and current fence. External tender confirmation and loyalty redemption are never inferred from cached state.
- Count variance is `counted - expected`. Zero closes automatically under the launch zero-tolerance policy. Non-zero variance remains active in `variance_review` until an owner/manager records an approve or reopen reason. A late linked sale/refund forces recomputation before approval.
- Drawer custody and employee attendance are separate. Handover changes the named custodian without closing the session; each movement still records the acting user.
