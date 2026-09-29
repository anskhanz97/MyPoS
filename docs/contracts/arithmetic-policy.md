# Arithmetic, rounding and valuation policy

Version: 1.0 | Frozen: 2026-09-29 | Ticket: [T004](../tickets/004-freeze-domain-contracts.md)

## Numeric representation

No authoritative calculation uses PHP/JavaScript binary floating point. API and fixture decimals are plain base-10 strings without exponent notation.

| Concept | MySQL/storage scale | API scale/rule | Business limit |
|---|---:|---|---:|
| Document/receipt/tender PKR total | `DECIMAL(19,2)` | exactly 2 decimals | absolute <= `999999999999.99` |
| Unit price, line intermediate, cost, allocation | `DECIMAL(19,4)` or higher calculator precision | up to 4 decimals | absolute <= `999999999999.9999` |
| Quantity/base quantity | `DECIMAL(19,6)` | up to 6 decimals | absolute <= `999999999999.999999` |
| Unit-conversion ratio | `DECIMAL(19,8)` | up to 8 decimals; positive | <= `99999999999.99999999` |
| Tax/discount/percentage rate | `DECIMAL(12,6)` | up to 6 percentage decimals | `0`–`100` unless rule explicitly permits surcharge |
| Moving-average unit cost | `DECIMAL(19,6)` | up to 6 decimals internally | non-negative except explicit correction record |
| Loyalty points | `DECIMAL(19,4)` | exactly/up to 4 decimals | program bound |

Calculators use at least 34 significant decimal digits. Database column scale is not a license to round early. Validate business limits before posting; never rely on SQL truncation.

PKR uses two receipt decimals. `0.01` is one paisa. Currency is fixed to PKR for launch.

## Rounding mode and stages

Default rounding is round half away from zero (`HALF_UP` in decimal libraries). A fiscal rule may specify another mode only through a versioned tax policy and golden fixtures.

1. Parse strings and normalize units without rounding except to reject excess input precision.
2. Convert sale quantity to base units and round to 6 decimals. If conversion error exceeds `0.000001` base unit, reject rather than silently change quantity.
3. Calculate gross line value, promotion candidates and taxes at calculator precision.
4. Allocate cart-level discounts across eligible lines by their eligible pre-discount value using largest remainder in paisa. Stable tie break: line creation sequence, then line public ID.
5. Compute tax per tax component/line from its post-discount basis. Round component tax to paisa using the rule's rounding mode.
6. Derive line net/gross so the identity holds exactly at 2 decimals. Any residual paisa is assigned by largest remainder with the stable tie break.
7. Sum rounded lines to document totals. Do not independently round the document formula to a different value.
8. Allocate a partial return from the original stored line allocations using cumulative entitlement, so all partial returns together equal the original line amount exactly.

## Price and tax formula

For quantity `q`, unit price `p`, pre-tax line discount `d`, and percentage tax rate `r` where `r` is expressed as a fraction:

### Tax-exclusive price

```text
base_before_discount = q × p
taxable_base = base_before_discount - d
tax = round_money(taxable_base × r)
gross = round_money(taxable_base) + tax
```

### Tax-inclusive price

```text
inclusive_after_discount = q × p - d
tax = round_money(inclusive_after_discount × r / (1 + r))
net = round_money(inclusive_after_discount) - tax
gross = net + tax
```

Fixed taxes/fees are separate components with their own applicability and are never disguised as a percentage. Exempt and zero-rated lines both yield zero tax but retain different classification.

## Discount order

Frozen launch order:

1. Resolve base/store/scheduled price from the published snapshot.
2. Apply automatic line promotions by priority and exclusivity group.
3. Apply bundle/multibuy/cart promotions.
4. Apply coupon allocations.
5. Apply authorized manual line/cart discount.
6. Calculate tax on the discounted basis unless the versioned fiscal rule states a legally different basis.

Rules declare `stackable`, priority, exclusive group, eligibility and cap. If two non-stackable rules share priority, choose the customer-lower payable result; tie break by immutable rule public ID. A discount cannot reduce an eligible line below zero. The allocated discount equals the approved discount total exactly.

Manual-only mode skips automatic promotion/coupon evaluation but allows permitted manual discounts. Switching mode publishes a new pricing version; it does not change existing posted/suspended data silently.

## Tender and change equations

For finalized sale:

```text
sale_total = sum(line_gross)
tender_received = sum(accepted tender amount)
cash_change >= 0
tender_received - cash_change = sale_total
expected_drawer_increase = cash_tender_received - cash_change
```

Non-cash/manual external tenders cannot generate cash change. A split tender must settle exactly after change. Overpayment is accepted only on a cash component sufficient to cover the change.

Example: sale `100.00`, external tender `30.00`, cash received `100.00`, cash change `30.00`. Settled amount is `30 + 100 - 30 = 100`; drawer increases `70.00`.

## Returns and exchanges

- Refund entitlement uses original sale line quantity, discounts, taxes and paid total, less already posted returns.
- For a partial quantity, calculate cumulative entitlement for total returned quantity, then subtract amounts already returned. This ensures the final unit absorbs residual paisa and total never exceeds the original.
- Restocking affects stock separately from refundable money.
- A return can reverse original tax only under its applicable fiscal policy.
- An exchange is `new_sale_total - return_refund_total`. Positive difference requires tender; negative difference requires refund; zero requires neither. Both documents remain visible.

## Weighted quantities and packs

- Product base unit defines stock. Sale units have an immutable conversion `base_per_sale_unit`.
- `base_quantity = sale_quantity × conversion_ratio`, stored to 6 decimals.
- Each product/unit declares allowed sale input decimals from 0 through 3 at launch. Storage remains 6 for conversions/aggregation.
- A scanner-encoded weight is parsed into a decimal string and validated by the same command; direct scale integration is excluded.
- Pack conversion examples: one carton at ratio `12.00000000` adds 12 base pieces; selling 1.5 kg with base gram ratio `1000.00000000` consumes 1500 grams.
- A fixed bundle posts stock movements for configured finished components. It has no yield, ingredient or wastage calculation.

## Moving weighted average baseline

For positive on-hand quantity before a normal receipt:

```text
new_quantity = old_quantity + received_quantity
new_average = (old_quantity × old_average + received_quantity × landed_unit_cost) / new_quantity
stored_average = round(new_average, 6, HALF_UP)
```

Sale issue value uses the stored average at posting and records that cost snapshot. Batch choice does not change the accounting method.

Example: 10 units at `100.000000`, receive 5 at `130.000000`; new average is `(1000 + 650) / 15 = 110.000000`.

T018 must define and fixture: negative opening stock, receipt crossing zero, backdated receipt/sale, purchase return after sales, landed-cost correction, transfer cost, receipt reversal and rounding reconciliation. Until reconciled, cost/margin state is `provisional`; reports never substitute zero.

T018 freezes the operational policy as follows:

- Receipt base quantity is named purchase-UOM quantity multiplied by the PO's frozen direct factor. Merchandise value is purchase-UOM quantity multiplied by actual purchase-UOM unit cost. Inventory value added is merchandise plus explicit landed cost. Recoverable tax is recorded in the supplier charge and excluded from inventory value.
- Positive stock with known value uses the equation above. Zero stock starts at the inbound inventory unit cost. Existing positive stock with unknown historical cost remains `provisional` with a null average/value after receiving; known receipt value does not justify inventing the missing historical value.
- An outbound movement snapshots the current six-decimal average. If quantity becomes negative, that snapshot remains provisional. A receipt resolves `min(received, abs(old negative))`; the adjustment is `resolved quantity × (actual receipt cost - provisional issue cost)`. Earlier issue rows are never rewritten. If the receipt crosses zero, positive remainder uses actual receipt cost.
- Effective time and application sequence are distinct. A backdated receipt records `backdated_current_sequence` and affects the current projection; it does not reorder or recalculate intervening entries silently.
- A direct receipt reversal is allowed only when its entries are still the latest cost entries for every affected product and the received quantity remains in the original location/batch. It appends stock, cost and supplier-charge reversals and restores the prior valuation snapshots exactly. Otherwise a reasoned adjustment workflow is required.
- Transfer out uses the source average without changing it. When one source base unit maps to `factor` destination base units, destination unit cost is `source unit cost / factor`, so total value is conserved. Destination moving average then follows the ordinary inbound rule. A transfer creates no supplier charge.
- Six-decimal quantity, unit cost, value and average persistence uses `HALF_UP`; supplier charge totals post at two decimals. The immutable entry also retains its six-decimal source total so future allocation/reconciliation can explain rounding.

Machine-readable cases A016-A022 cover carton/piece receiving, fractional quantity, partial/negative resolution, duplicate submission, independent-catalog transfer and safe reversal.

## Cash, supplier and payroll allocation

- One cash movement has one signed 2-decimal amount and one source. A linked expense/supplier/payroll payout cannot generate another independent drawer effect.
- Register expected cash is `sum(all signed cash movements in the session)`. The opening float is the first positive movement. Paid-in and net cash sale are positive; paid-out, cash refund and linked cash payouts are negative; no-sale-open is zero. Manual card/bank/other external tenders contribute zero. Close variance is `counted cash - expected cash`. A026 freezes this equation.
- Supplier payment allocation across charges uses explicit 2-decimal allocations whose sum cannot exceed payment or open charges.
- Purchase-return supplier credit is cumulative entitlement: `round(original receipt charge × cumulative returned base quantity / received base quantity, 2) - prior posted credits`. This makes the final eligible return reconcile exactly despite partial-return rounding. Inventory leaves at the current moving-average snapshot; supplier entitlement never substitutes that cost.
- Operational supplier balance is `signed charges - confirmed credits - signed payments`. Expected credits remain separately disclosed. Unallocated payment is `signed payments - signed allocations`; append-only reversal contributes equal negative payment and allocation entries.
- Payroll item calculations may use 4-decimal rates/hours but posted net pay and payout allocations settle at 2 decimals. Statutory input is recorded as approved amount, not recalculated by MyPoS.

## Loyalty arithmetic

- Points use four decimals and program-defined earn rounding. Launch default is floor to four decimals after applying the earning rate to eligible net spend; the exact program version is stored.
- Redemption commits a fixed points amount and fixed monetary discount allocation before sale posting.
- Refund reversal uses original earned entry and the return's eligible proportion. If points were spent, T027 applies its explicit negative-points/exception policy without creating customer money debt.

## Business date

The business date is calculated once when a document posts:

```text
local = utc_instant converted to store IANA timezone
business_date = local calendar date
if local time < configured cutoff: business_date -= 1 day
```

The cutoff version is stored. Default `00:00`; configurable no later than `06:00`. Reports filter the stored date using half-open date ranges `[from, to)` for instants and inclusive discrete business-date bounds in the UI.

## Required conservation assertions

- `line_net + line_tax + line_fixed_tax = line_gross`.
- `sum(line discounts by source) = document discount by source`.
- `sum(line gross) = sale total`.
- `tenders - change = sale total`.
- original refundable amount = posted returns + remaining entitlement.
- base units received/issued equal document quantities times frozen conversion.
- stock projection equals ledger sum for the same scope/cutoff.
- supplier payment equals allocations plus unallocated amount.
- payroll payout equals payout allocations plus unallocated amount.
- points balance equals posted ledger sum, not a mutable independent truth.

Machine-readable examples live in [arithmetic-golden.json](fixtures/arithmetic-golden.json) and are normative for PHP and TypeScript implementations.

Erratum, 2026-09-29: A015 was corrected from `1.52499025`/`1.5249` to the exact product `123.45 × 0.012345 = 1.52399025`, floored to `1.5239`. Independent Brick Math and Decimal.js runs exposed the transcription error during T005.
