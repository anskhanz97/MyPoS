<?php

namespace App\Domain\Shared\Math;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class GoldenArithmeticCalculator
{
    /**
     * @param  array{operation: string, input: array<string, mixed>}  $case
     * @return array<string, mixed>
     */
    public function calculate(array $case): array
    {
        return match ($case['operation']) {
            'exclusive_percentage_tax' => $this->exclusiveTax($case['input']),
            'inclusive_percentage_tax' => $this->inclusiveTax($case['input']),
            'largest_remainder_allocation' => $this->largestRemainder($case['input']),
            'split_tender_settlement' => $this->splitTender($case['input']),
            'cumulative_partial_return' => $this->cumulativeReturn($case['input']),
            'unit_conversion' => $this->unitConversion($case['input']),
            'moving_weighted_average_positive_stock' => $this->movingAverage($case['input']),
            'purchase_receipt_carton_moving_average' => $this->purchaseReceiptCarton($case['input']),
            'fractional_purchase_receipt' => $this->fractionalPurchaseReceipt($case['input']),
            'negative_stock_partial_resolution' => $this->negativeStockPartialResolution($case['input']),
            'negative_stock_cross_zero' => $this->negativeStockCrossZero($case['input']),
            'purchase_receipt_idempotency' => $this->purchaseReceiptIdempotency($case['input']),
            'independent_catalog_transfer_valuation' => $this->independentTransferValuation($case['input']),
            'latest_receipt_reversal' => $this->latestReceiptReversal($case['input']),
            'cumulative_purchase_return_credit' => $this->cumulativePurchaseReturnCredit($case['input']),
            'supplier_balance_projection' => $this->supplierBalanceProjection($case['input']),
            'supplier_payment_reversal' => $this->supplierPaymentReversal($case['input']),
            'cash_session_expected' => $this->cashSessionExpected($case['input']),
            'business_date' => $this->businessDate($case['input']),
            'exchange_difference' => $this->exchangeDifference($case['input']),
            'payroll_net' => $this->payrollNet($case['input']),
            'loyalty_earn_floor_4' => $this->loyaltyEarn($case['input']),
            default => throw new InvalidArgumentException('Unsupported golden operation: '.$case['operation']),
        };
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function exclusiveTax(array $input): array
    {
        $preDiscount = $this->number($input['quantity'])->multipliedBy($this->number($input['unit_price']));
        $taxableNet = $preDiscount->minus($this->number($input['discount']));
        $tax = $taxableNet->multipliedBy($this->rate($input['tax_rate_percent']));
        $roundedNet = $taxableNet->toScale(2, RoundingMode::HalfUp);
        $roundedTax = $tax->toScale(2, RoundingMode::HalfUp);

        return [
            'pre_discount' => Decimal::fixed($preDiscount, 2),
            'taxable_net' => (string) $roundedNet,
            'tax' => (string) $roundedTax,
            'gross' => (string) $roundedNet->plus($roundedTax),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function inclusiveTax(array $input): array
    {
        $inclusive = $this->number($input['quantity'])->multipliedBy($this->number($input['unit_price']))->minus($this->number($input['discount']));
        $rate = $this->rate($input['tax_rate_percent']);
        $tax = $inclusive->multipliedBy($rate)->dividedBy(BigDecimal::one()->plus($rate), Decimal::CalculatorScale, RoundingMode::HalfUp)->toScale(2, RoundingMode::HalfUp);
        $gross = $inclusive->toScale(2, RoundingMode::HalfUp);

        return [
            'inclusive_after_discount' => (string) $gross,
            'net' => (string) $gross->minus($tax),
            'tax' => (string) $tax,
            'gross' => (string) $gross,
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function largestRemainder(array $input): array
    {
        $cents = (int) (string) $this->number($input['amount'])->multipliedBy('100')->toScale(0);
        $totalWeight = BigDecimal::zero();
        foreach ($input['lines'] as $line) {
            $totalWeight = $totalWeight->plus($this->number($line['weight']));
        }

        $allocations = [];
        $allocated = 0;
        foreach ($input['lines'] as $line) {
            $exact = BigDecimal::of($cents)->multipliedBy($this->number($line['weight']))->dividedBy($totalWeight, Decimal::CalculatorScale, RoundingMode::HalfUp);
            $base = (int) (string) $exact->toScale(0, RoundingMode::Floor);
            $allocated += $base;
            $allocations[] = ['line' => $line, 'cents' => $base, 'remainder' => $exact->minus($base)];
        }

        usort($allocations, function (array $left, array $right): int {
            $remainder = $right['remainder']->compareTo($left['remainder']);

            return $remainder !== 0 ? $remainder : [$left['line']['sequence'], $left['line']['line_id']] <=> [$right['line']['sequence'], $right['line']['line_id']];
        });
        $remaining = $cents - $allocated;
        for ($index = 0; $index < $remaining; $index++) {
            if (! isset($allocations[$index])) {
                throw new InvalidArgumentException('Allocation remainder exceeds the number of lines.');
            }

            $allocation = $allocations[$index];
            $allocation['cents']++;
            $allocations[$index] = $allocation;
        }
        usort($allocations, fn (array $left, array $right): int => [$left['line']['sequence'], $left['line']['line_id']] <=> [$right['line']['sequence'], $right['line']['line_id']]);

        return [
            'allocations' => array_map(fn (array $item): array => ['line_id' => $item['line']['line_id'], 'amount' => Decimal::fixed(BigDecimal::of($item['cents'])->dividedBy('100', 2), 2)], $allocations),
            'sum' => Decimal::fixed($this->number($input['amount']), 2),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function splitTender(array $input): array
    {
        $received = BigDecimal::zero();
        $cash = BigDecimal::zero();
        foreach ($input['tenders'] as $tender) {
            $received = $received->plus($this->number($tender['amount']));
            if ($tender['type'] === 'cash') {
                $cash = $cash->plus($this->number($tender['amount']));
            }
        }
        $change = $this->number($input['cash_change']);
        $settled = $received->minus($change);

        return ['settled' => $settled->compareTo($this->number($input['sale_total'])) === 0, 'settled_amount' => Decimal::fixed($settled, 2), 'expected_drawer_increase' => Decimal::fixed($cash->minus($change), 2)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function cumulativeReturn(array $input): array
    {
        $returnedQuantity = BigDecimal::zero();
        $alreadyRefunded = BigDecimal::zero();
        $refunds = [];
        foreach ($input['return_quantities_in_order'] as $quantity) {
            $returnedQuantity = $returnedQuantity->plus($this->number($quantity));
            $entitlement = $this->number($input['original_refundable'])->multipliedBy($returnedQuantity)->dividedBy($this->number($input['original_quantity']), Decimal::CalculatorScale, RoundingMode::HalfUp)->toScale(2, RoundingMode::HalfUp);
            $refunds[] = (string) $entitlement->minus($alreadyRefunded);
            $alreadyRefunded = $entitlement;
        }

        return ['refunds_in_order' => $refunds, 'total_refunded' => (string) $alreadyRefunded, 'remaining' => Decimal::fixed($this->number($input['original_refundable'])->minus($alreadyRefunded), 2)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function unitConversion(array $input): array
    {
        return ['base_quantity' => Decimal::fixed($this->number($input['sale_quantity'])->multipliedBy($this->number($input['base_per_sale_unit'])), 6)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function movingAverage(array $input): array
    {
        $quantity = $this->number($input['old_quantity'])->plus($this->number($input['received_quantity']));
        $value = $this->number($input['old_quantity'])->multipliedBy($this->number($input['old_average']))->plus($this->number($input['received_quantity'])->multipliedBy($this->number($input['landed_unit_cost'])));

        return ['new_quantity' => Decimal::fixed($quantity, 6), 'new_average' => Decimal::fixed($value->dividedBy($quantity, Decimal::CalculatorScale, RoundingMode::HalfUp), 6)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function purchaseReceiptCarton(array $input): array
    {
        $receivedQuantity = $this->number($input['purchase_quantity_uom'])->multipliedBy($this->number($input['factor_to_base']));
        $merchandiseValue = $this->number($input['purchase_quantity_uom'])->multipliedBy($this->number($input['unit_cost_uom']));
        $receiptValue = $merchandiseValue->plus($this->number($input['landed_cost_total']));
        $quantity = $this->number($input['old_quantity_base'])->plus($receivedQuantity);
        $value = $this->number($input['old_quantity_base'])->multipliedBy($this->number($input['old_average']))->plus($receiptValue);

        return [
            'received_quantity_base' => Decimal::fixed($receivedQuantity, 6),
            'inventory_unit_cost_base' => Decimal::fixed($receiptValue->dividedBy($receivedQuantity, Decimal::CalculatorScale, RoundingMode::HalfUp), 6),
            'new_quantity' => Decimal::fixed($quantity, 6),
            'new_inventory_value' => Decimal::fixed($value, 6),
            'new_average' => Decimal::fixed($value->dividedBy($quantity, Decimal::CalculatorScale, RoundingMode::HalfUp), 6),
            'supplier_charge' => Decimal::fixed($receiptValue->plus($this->number($input['recoverable_tax_total'])), 2),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function fractionalPurchaseReceipt(array $input): array
    {
        $receivedQuantity = $this->number($input['purchase_quantity_uom'])->multipliedBy($this->number($input['factor_to_base']));
        $inventoryValue = $this->number($input['purchase_quantity_uom'])->multipliedBy($this->number($input['unit_cost_uom']))->plus($this->number($input['landed_cost_total']));

        return [
            'received_quantity_base' => Decimal::fixed($receivedQuantity, 6),
            'inventory_value' => Decimal::fixed($inventoryValue, 6),
            'inventory_unit_cost_base' => Decimal::fixed($inventoryValue->dividedBy($receivedQuantity, Decimal::CalculatorScale, RoundingMode::HalfUp), 6),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function negativeStockPartialResolution(array $input): array
    {
        $oldQuantity = $this->number($input['old_quantity']);
        $receivedQuantity = $this->number($input['received_quantity']);
        $resolvedQuantity = $receivedQuantity->isLessThan($oldQuantity->abs()) ? $receivedQuantity : $oldQuantity->abs();
        $adjustment = $this->number($input['actual_unit_cost'])->minus($this->number($input['provisional_average']))->multipliedBy($resolvedQuantity);

        return [
            'new_quantity' => Decimal::fixed($oldQuantity->plus($receivedQuantity), 6),
            'remaining_average' => Decimal::fixed($this->number($input['provisional_average']), 6),
            'resolved_negative_quantity' => Decimal::fixed($resolvedQuantity, 6),
            'cost_adjustment_value' => Decimal::fixed($adjustment, 6),
            'cost_state' => 'provisional',
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function negativeStockCrossZero(array $input): array
    {
        $oldQuantity = $this->number($input['old_quantity']);
        $receivedQuantity = $this->number($input['received_quantity']);
        $actualCost = $this->number($input['actual_unit_cost']);
        $resolvedQuantity = $receivedQuantity->isLessThan($oldQuantity->abs()) ? $receivedQuantity : $oldQuantity->abs();
        $quantity = $oldQuantity->plus($receivedQuantity);
        $adjustment = $actualCost->minus($this->number($input['provisional_average']))->multipliedBy($resolvedQuantity);

        return [
            'new_quantity' => Decimal::fixed($quantity, 6),
            'new_average' => Decimal::fixed($actualCost, 6),
            'new_inventory_value' => Decimal::fixed($quantity->multipliedBy($actualCost), 6),
            'resolved_negative_quantity' => Decimal::fixed($resolvedQuantity, 6),
            'cost_adjustment_value' => Decimal::fixed($adjustment, 6),
            'cost_state' => 'final',
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function purchaseReceiptIdempotency(array $input): array
    {
        $submissions = (int) $input['identical_submissions'];
        $persistedCount = $submissions > 0 ? 1 : 0;

        return [
            'receipt_count' => $persistedCount,
            'stock_movement_count' => $persistedCount,
            'cost_entry_count' => $persistedCount,
            'supplier_charge_count' => $persistedCount,
            'outbox_event_count' => $persistedCount,
            'second_submission_replayed' => $submissions > 1,
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function independentTransferValuation(array $input): array
    {
        $sourceQuantity = $this->number($input['source_quantity']);
        $sourceCost = $this->number($input['source_unit_cost']);
        $factor = $this->number($input['factor_to_destination_base']);

        return [
            'destination_quantity' => Decimal::fixed($sourceQuantity->multipliedBy($factor), 6),
            'destination_unit_cost' => Decimal::fixed($sourceCost->dividedBy($factor, Decimal::CalculatorScale, RoundingMode::HalfUp), 6),
            'value_conserved' => Decimal::fixed($sourceQuantity->multipliedBy($sourceCost), 6),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function latestReceiptReversal(array $input): array
    {
        if ((int) $input['later_cost_entry_count'] !== 0) {
            throw new InvalidArgumentException('Only a tail-safe receipt can be reversed.');
        }

        $previousQuantity = $this->number($input['previous_quantity']);
        $previousValue = $this->number($input['previous_value']);

        return [
            'resulting_quantity' => Decimal::fixed($previousQuantity, 6),
            'resulting_value' => Decimal::fixed($previousValue, 6),
            'resulting_average' => Decimal::fixed($previousValue->dividedBy($previousQuantity, Decimal::CalculatorScale, RoundingMode::HalfUp), 6),
            'append_only_reversal' => true,
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function cumulativePurchaseReturnCredit(array $input): array
    {
        $returned = BigDecimal::zero();
        $credited = BigDecimal::zero();
        $credits = [];
        foreach ($input['return_quantities_base_in_order'] as $quantity) {
            $returned = $returned->plus($this->number($quantity));
            $entitlement = $this->number($input['supplier_charge_total'])->multipliedBy($returned)->dividedBy($this->number($input['receipt_quantity_base']), 2, RoundingMode::HalfUp);
            $credits[] = Decimal::fixed($entitlement->minus($credited), 2);
            $credited = $entitlement;
        }

        return ['credits_in_order' => $credits, 'total_credit' => Decimal::fixed($credited, 2), 'remaining_eligible_quantity' => Decimal::fixed($this->number($input['receipt_quantity_base'])->minus($returned), 6)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function supplierBalanceProjection(array $input): array
    {
        $charges = $this->sum($input['charges']);
        $credits = $this->sum($input['confirmed_credits']);
        $payments = $this->sum($input['payments']);
        $allocations = $this->sum($input['allocations']);

        return ['balance' => Decimal::fixed($charges->minus($credits)->minus($payments), 2), 'unallocated_payments' => Decimal::fixed($payments->minus($allocations), 2)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function supplierPaymentReversal(array $input): array
    {
        $unallocatedReversal = $this->number($input['payment_reversal'])->minus($this->number($input['allocation_reversal']));

        return [
            'balance' => Decimal::fixed($this->number($input['opening_balance'])->plus($this->number($input['payment_reversal'])), 2),
            'net_allocated' => Decimal::fixed($this->number($input['allocation_reversal'])->minus($this->number($input['allocation_reversal'])), 2),
            'unallocated_payments' => Decimal::fixed($this->number($input['previous_unallocated'])->minus($unallocatedReversal), 2),
            'append_only_reversal' => true,
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function cashSessionExpected(array $input): array
    {
        $expected = $this->number($input['opening_float'])->plus($this->sum($input['signed_cash_movements']));

        return [
            'expected_amount' => Decimal::fixed($expected, 2),
            'variance_amount' => Decimal::fixed($this->number($input['counted_amount'])->minus($expected), 2),
            'external_tender_drawer_effect' => '0.00',
        ];
    }

    /** @param array<mixed> $values */
    private function sum(array $values): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($values as $value) {
            $total = $total->plus($this->number($value));
        }

        return $total;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function businessDate(array $input): array
    {
        $local = (new DateTimeImmutable($input['utc_instant']))->setTimezone(new DateTimeZone($input['timezone']));
        $date = $local->format('Y-m-d');
        if ($local->format('H:i:s') < $input['cutoff_local']) {
            $date = $local->sub(new DateInterval('P1D'))->format('Y-m-d');
        }

        return ['local_instant' => $local->format('Y-m-d\TH:i:sP'), 'business_date' => $date];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function exchangeDifference(array $input): array
    {
        $difference = $this->number($input['new_sale_total'])->minus($this->number($input['return_refund_total']));

        return ['difference' => Decimal::fixed($difference, 2), 'direction' => $difference->isGreaterThanOrEqualTo(0) ? 'customer_pays' : 'merchant_refunds'];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function payrollNet(array $input): array
    {
        $net = $this->number($input['gross_pay'])->plus($this->number($input['allowances']))->minus($this->number($input['deductions']))->minus($this->number($input['advance_recovery']))->minus($this->number($input['statutory_input']));

        return ['net_pay' => Decimal::fixed($net, 2)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function loyaltyEarn(array $input): array
    {
        $raw = $this->number($input['eligible_net_spend'])->multipliedBy($this->number($input['points_per_pkr']));

        return ['raw_points' => Decimal::fixed($raw, 8), 'posted_points' => Decimal::floorFixed($raw, 4)];
    }

    private function rate(mixed $percentage): BigDecimal
    {
        return $this->number($percentage)->dividedBy('100', Decimal::CalculatorScale, RoundingMode::HalfUp);
    }

    private function number(mixed $value): BigDecimal
    {
        return Decimal::of((string) $value);
    }
}
