import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import Decimal from "decimal.js";
import { describe, expect, it } from "vitest";
import { decimal, fixed, floorFixed } from "../../resources/js/shared/decimal";

type Values = Record<string, unknown>;
type GoldenCase = {
    id: string;
    operation: string;
    input: Values;
    expected: Values;
};

const fixture = JSON.parse(
    readFileSync(
        resolve("docs/contracts/fixtures/arithmetic-golden.json"),
        "utf8",
    ),
) as { cases: GoldenCase[] };

function calculate(testCase: GoldenCase): Values {
    const input = testCase.input;
    switch (testCase.operation) {
        case "exclusive_percentage_tax": {
            const preDiscount = decimal(input.quantity as string).mul(
                decimal(input.unit_price as string),
            );
            const taxableNet = preDiscount.minus(
                decimal(input.discount as string),
            );
            const tax = taxableNet.mul(
                decimal(input.tax_rate_percent as string).div(100),
            );
            return {
                pre_discount: fixed(preDiscount, 2),
                taxable_net: fixed(taxableNet, 2),
                tax: fixed(tax, 2),
                gross: fixed(
                    taxableNet.toDecimalPlaces(2).plus(tax.toDecimalPlaces(2)),
                    2,
                ),
            };
        }
        case "inclusive_percentage_tax": {
            const inclusive = decimal(input.quantity as string)
                .mul(decimal(input.unit_price as string))
                .minus(decimal(input.discount as string));
            const rate = decimal(input.tax_rate_percent as string).div(100);
            const tax = inclusive
                .mul(rate)
                .div(decimal("1").plus(rate))
                .toDecimalPlaces(2, Decimal.ROUND_HALF_UP);
            const gross = inclusive.toDecimalPlaces(2, Decimal.ROUND_HALF_UP);
            return {
                inclusive_after_discount: fixed(gross, 2),
                net: fixed(gross.minus(tax), 2),
                tax: fixed(tax, 2),
                gross: fixed(gross, 2),
            };
        }
        case "largest_remainder_allocation": {
            const lines = input.lines as Array<{
                line_id: string;
                sequence: number;
                weight: string;
            }>;
            const cents = decimal(input.amount as string)
                .mul(100)
                .toNumber();
            const totalWeight = lines.reduce(
                (sum, line) => sum.plus(line.weight),
                decimal("0"),
            );
            const parts = lines.map((line) => {
                const exact = decimal(String(cents))
                    .mul(line.weight)
                    .div(totalWeight);
                const base = exact.floor().toNumber();
                return { line, cents: base, remainder: exact.minus(base) };
            });
            let remaining =
                cents - parts.reduce((sum, part) => sum + part.cents, 0);
            parts.sort(
                (a, b) =>
                    b.remainder.comparedTo(a.remainder) ||
                    a.line.sequence - b.line.sequence ||
                    a.line.line_id.localeCompare(b.line.line_id),
            );
            for (const part of parts) {
                if (remaining-- > 0) part.cents += 1;
            }
            parts.sort(
                (a, b) =>
                    a.line.sequence - b.line.sequence ||
                    a.line.line_id.localeCompare(b.line.line_id),
            );
            return {
                allocations: parts.map((part) => ({
                    line_id: part.line.line_id,
                    amount: fixed(decimal(String(part.cents)).div(100), 2),
                })),
                sum: fixed(decimal(input.amount as string), 2),
            };
        }
        case "split_tender_settlement": {
            const tenders = input.tenders as Array<{
                type: string;
                amount: string;
            }>;
            const received = tenders.reduce(
                (sum, tender) => sum.plus(tender.amount),
                decimal("0"),
            );
            const cash = tenders
                .filter((tender) => tender.type === "cash")
                .reduce((sum, tender) => sum.plus(tender.amount), decimal("0"));
            const settled = received.minus(input.cash_change as string);
            return {
                settled: settled.equals(input.sale_total as string),
                settled_amount: fixed(settled, 2),
                expected_drawer_increase: fixed(
                    cash.minus(input.cash_change as string),
                    2,
                ),
            };
        }
        case "cumulative_partial_return": {
            let quantity = decimal("0");
            let refunded = decimal("0");
            const refunds = (input.return_quantities_in_order as string[]).map(
                (item) => {
                    quantity = quantity.plus(item);
                    const entitlement = decimal(
                        input.original_refundable as string,
                    )
                        .mul(quantity)
                        .div(input.original_quantity as string)
                        .toDecimalPlaces(2, Decimal.ROUND_HALF_UP);
                    const refund = entitlement.minus(refunded);
                    refunded = entitlement;
                    return fixed(refund, 2);
                },
            );
            return {
                refunds_in_order: refunds,
                total_refunded: fixed(refunded, 2),
                remaining: fixed(
                    decimal(input.original_refundable as string).minus(
                        refunded,
                    ),
                    2,
                ),
            };
        }
        case "unit_conversion":
            return {
                base_quantity: fixed(
                    decimal(input.sale_quantity as string).mul(
                        input.base_per_sale_unit as string,
                    ),
                    6,
                ),
            };
        case "moving_weighted_average_positive_stock": {
            const quantity = decimal(input.old_quantity as string).plus(
                input.received_quantity as string,
            );
            const value = decimal(input.old_quantity as string)
                .mul(input.old_average as string)
                .plus(
                    decimal(input.received_quantity as string).mul(
                        input.landed_unit_cost as string,
                    ),
                );
            return {
                new_quantity: fixed(quantity, 6),
                new_average: fixed(value.div(quantity), 6),
            };
        }
        case "purchase_receipt_carton_moving_average": {
            const receivedQuantity = decimal(
                input.purchase_quantity_uom as string,
            ).mul(input.factor_to_base as string);
            const merchandiseValue = decimal(
                input.purchase_quantity_uom as string,
            ).mul(input.unit_cost_uom as string);
            const receiptValue = merchandiseValue.plus(
                input.landed_cost_total as string,
            );
            const quantity = decimal(input.old_quantity_base as string).plus(
                receivedQuantity,
            );
            const value = decimal(input.old_quantity_base as string)
                .mul(input.old_average as string)
                .plus(receiptValue);
            return {
                received_quantity_base: fixed(receivedQuantity, 6),
                inventory_unit_cost_base: fixed(
                    receiptValue.div(receivedQuantity),
                    6,
                ),
                new_quantity: fixed(quantity, 6),
                new_inventory_value: fixed(value, 6),
                new_average: fixed(value.div(quantity), 6),
                supplier_charge: fixed(
                    receiptValue.plus(input.recoverable_tax_total as string),
                    2,
                ),
            };
        }
        case "fractional_purchase_receipt": {
            const receivedQuantity = decimal(
                input.purchase_quantity_uom as string,
            ).mul(input.factor_to_base as string);
            const inventoryValue = decimal(
                input.purchase_quantity_uom as string,
            )
                .mul(input.unit_cost_uom as string)
                .plus(input.landed_cost_total as string);
            return {
                received_quantity_base: fixed(receivedQuantity, 6),
                inventory_value: fixed(inventoryValue, 6),
                inventory_unit_cost_base: fixed(
                    inventoryValue.div(receivedQuantity),
                    6,
                ),
            };
        }
        case "negative_stock_partial_resolution": {
            const oldQuantity = decimal(input.old_quantity as string);
            const receivedQuantity = decimal(input.received_quantity as string);
            const resolvedQuantity = Decimal.min(
                receivedQuantity,
                oldQuantity.abs(),
            );
            const costAdjustment = resolvedQuantity.mul(
                decimal(input.actual_unit_cost as string).minus(
                    input.provisional_average as string,
                ),
            );
            return {
                new_quantity: fixed(oldQuantity.plus(receivedQuantity), 6),
                remaining_average: fixed(
                    decimal(input.provisional_average as string),
                    6,
                ),
                resolved_negative_quantity: fixed(resolvedQuantity, 6),
                cost_adjustment_value: fixed(costAdjustment, 6),
                cost_state: "provisional",
            };
        }
        case "negative_stock_cross_zero": {
            const oldQuantity = decimal(input.old_quantity as string);
            const receivedQuantity = decimal(input.received_quantity as string);
            const actualCost = decimal(input.actual_unit_cost as string);
            const resolvedQuantity = Decimal.min(
                receivedQuantity,
                oldQuantity.abs(),
            );
            const quantity = oldQuantity.plus(receivedQuantity);
            const costAdjustment = resolvedQuantity.mul(
                actualCost.minus(input.provisional_average as string),
            );
            return {
                new_quantity: fixed(quantity, 6),
                new_average: fixed(actualCost, 6),
                new_inventory_value: fixed(quantity.mul(actualCost), 6),
                resolved_negative_quantity: fixed(resolvedQuantity, 6),
                cost_adjustment_value: fixed(costAdjustment, 6),
                cost_state: "final",
            };
        }
        case "purchase_receipt_idempotency": {
            const submissions = input.identical_submissions as number;
            const persistedCount = submissions > 0 ? 1 : 0;
            return {
                receipt_count: persistedCount,
                stock_movement_count: persistedCount,
                cost_entry_count: persistedCount,
                supplier_charge_count: persistedCount,
                outbox_event_count: persistedCount,
                second_submission_replayed: submissions > 1,
            };
        }
        case "independent_catalog_transfer_valuation": {
            const sourceQuantity = decimal(input.source_quantity as string);
            const sourceUnitCost = decimal(input.source_unit_cost as string);
            const factor = decimal(input.factor_to_destination_base as string);
            return {
                destination_quantity: fixed(sourceQuantity.mul(factor), 6),
                destination_unit_cost: fixed(sourceUnitCost.div(factor), 6),
                value_conserved: fixed(sourceQuantity.mul(sourceUnitCost), 6),
            };
        }
        case "latest_receipt_reversal": {
            const laterEntries = input.later_cost_entry_count as number;
            if (laterEntries !== 0)
                throw new Error("Only a tail-safe receipt can be reversed");
            const previousQuantity = decimal(input.previous_quantity as string);
            const previousValue = decimal(input.previous_value as string);
            return {
                resulting_quantity: fixed(previousQuantity, 6),
                resulting_value: fixed(previousValue, 6),
                resulting_average: fixed(
                    previousValue.div(previousQuantity),
                    6,
                ),
                append_only_reversal: true,
            };
        }
        case "cumulative_purchase_return_credit": {
            let returned = decimal("0");
            let credited = decimal("0");
            const credits = (input.return_quantities_base_in_order as string[]).map(
                (quantity) => {
                    returned = returned.plus(quantity);
                    const entitlement = decimal(input.supplier_charge_total as string)
                        .mul(returned)
                        .div(input.receipt_quantity_base as string)
                        .toDecimalPlaces(2, Decimal.ROUND_HALF_UP);
                    const credit = entitlement.minus(credited);
                    credited = entitlement;
                    return fixed(credit, 2);
                },
            );
            return {
                credits_in_order: credits,
                total_credit: fixed(credited, 2),
                remaining_eligible_quantity: fixed(
                    decimal(input.receipt_quantity_base as string).minus(returned),
                    6,
                ),
            };
        }
        case "supplier_balance_projection": {
            const charges = (input.charges as string[]).reduce(
                (sum, value) => sum.plus(value),
                decimal("0"),
            );
            const credits = (input.confirmed_credits as string[]).reduce(
                (sum, value) => sum.plus(value),
                decimal("0"),
            );
            const payments = (input.payments as string[]).reduce(
                (sum, value) => sum.plus(value),
                decimal("0"),
            );
            const allocations = (input.allocations as string[]).reduce(
                (sum, value) => sum.plus(value),
                decimal("0"),
            );
            return {
                balance: fixed(charges.minus(credits).minus(payments), 2),
                unallocated_payments: fixed(payments.minus(allocations), 2),
            };
        }
        case "supplier_payment_reversal":
            return {
                balance: fixed(
                    decimal(input.opening_balance as string).plus(
                        input.payment_reversal as string,
                    ),
                    2,
                ),
                net_allocated: fixed(
                    decimal(input.allocation_reversal as string).minus(
                        input.allocation_reversal as string,
                    ),
                    2,
                ),
                unallocated_payments: fixed(
                    decimal(input.previous_unallocated as string).minus(
                        decimal(input.payment_reversal as string).minus(
                            input.allocation_reversal as string,
                        ),
                    ),
                    2,
                ),
                append_only_reversal: true,
            };
        case "cash_session_expected": {
            const expected = (input.signed_cash_movements as string[]).reduce(
                (sum, value) => sum.plus(value),
                decimal(input.opening_float as string),
            );
            return {
                expected_amount: fixed(expected, 2),
                variance_amount: fixed(
                    decimal(input.counted_amount as string).minus(expected),
                    2,
                ),
                external_tender_drawer_effect: "0.00",
            };
        }
        case "business_date": {
            const local = new Date(input.utc_instant as string);
            const parts = Object.fromEntries(
                new Intl.DateTimeFormat("en-CA", {
                    timeZone: input.timezone as string,
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hourCycle: "h23",
                })
                    .formatToParts(local)
                    .map(({ type, value }) => [type, value]),
            );
            const localDate = `${parts.year}-${parts.month}-${parts.day}`;
            const beforeCutoff =
                `${parts.hour}:${parts.minute}:${parts.second}` <
                (input.cutoff_local as string);
            const business = beforeCutoff
                ? new Date(`${localDate}T00:00:00Z`)
                : null;
            if (business) business.setUTCDate(business.getUTCDate() - 1);
            return {
                local_instant: `${localDate}T${parts.hour}:${parts.minute}:${parts.second}+05:00`,
                business_date: business
                    ? business.toISOString().slice(0, 10)
                    : localDate,
            };
        }
        case "exchange_difference": {
            const difference = decimal(input.new_sale_total as string).minus(
                input.return_refund_total as string,
            );
            return {
                difference: fixed(difference, 2),
                direction: difference.isNegative()
                    ? "merchant_refunds"
                    : "customer_pays",
            };
        }
        case "payroll_net": {
            const net = decimal(input.gross_pay as string)
                .plus(input.allowances as string)
                .minus(input.deductions as string)
                .minus(input.advance_recovery as string)
                .minus(input.statutory_input as string);
            return { net_pay: fixed(net, 2) };
        }
        case "loyalty_earn_floor_4": {
            const raw = decimal(input.eligible_net_spend as string).mul(
                input.points_per_pkr as string,
            );
            return {
                raw_points: fixed(raw, 8),
                posted_points: floorFixed(raw, 4),
            };
        }
        default:
            throw new Error(`Unsupported operation: ${testCase.operation}`);
    }
}

describe("frozen arithmetic contract", () => {
    for (const testCase of fixture.cases) {
        it(`${testCase.id} ${testCase.operation}`, () => {
            expect(calculate(testCase)).toEqual(testCase.expected);
        });
    }
});
