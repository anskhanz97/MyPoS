<?php

namespace App\Domain\Purchasing;

use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierChargeEntry;
use App\Models\SupplierCredit;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

final class SupplierStatement
{
    /** @return array{currency: string, charges: string, confirmed_credits: string, expected_credits: string, payments: string, balance: string, unallocated_payments: string, entries: list<array<string, mixed>>} */
    public function build(Supplier $supplier, ?Store $store): array
    {
        $scope = fn ($query) => $store === null ? $query : $query->where('store_id', $store->id);
        /** @var Collection<int, SupplierChargeEntry> $charges */
        $charges = $scope(SupplierChargeEntry::query()->where('merchant_id', $supplier->merchant_id)->where('supplier_id', $supplier->id))->orderBy('occurred_at')->orderBy('id')->get();
        /** @var Collection<int, SupplierCredit> $credits */
        $credits = $scope(SupplierCredit::query()->where('merchant_id', $supplier->merchant_id)->where('supplier_id', $supplier->id))->orderBy('occurred_at')->orderBy('id')->get();
        /** @var Collection<int, SupplierPayment> $payments */
        $payments = $scope(SupplierPayment::query()->where('merchant_id', $supplier->merchant_id)->where('supplier_id', $supplier->id))->with('allocations')->orderBy('paid_at')->orderBy('id')->get();

        $chargeTotal = $charges->reduce(fn (BigDecimal $sum, SupplierChargeEntry $entry): BigDecimal => $sum->plus((string) $entry->amount), BigDecimal::zero());
        $confirmedCredits = $credits->filter(fn (SupplierCredit $credit): bool => in_array($credit->status, [SupplierCreditStatus::Confirmed, SupplierCreditStatus::Reversed], true))->reduce(fn (BigDecimal $sum, SupplierCredit $credit): BigDecimal => $sum->plus((string) $credit->amount), BigDecimal::zero());
        $expectedCredits = $credits->filter(fn (SupplierCredit $credit): bool => $credit->status === SupplierCreditStatus::Expected)->reduce(fn (BigDecimal $sum, SupplierCredit $credit): BigDecimal => $sum->plus((string) $credit->amount), BigDecimal::zero());
        $paymentTotal = $payments->reduce(fn (BigDecimal $sum, SupplierPayment $payment): BigDecimal => $sum->plus((string) $payment->amount), BigDecimal::zero());
        $allocated = SupplierPaymentAllocation::query()->where('merchant_id', $supplier->merchant_id)->where('supplier_id', $supplier->id)->when($store !== null, fn ($query) => $query->where('store_id', $store->id))->sum('amount');
        /** @var list<array<string, mixed>> $entries */
        $entries = [];
        foreach ($charges as $entry) {
            $entries[] = ['id' => $entry->public_id, 'type' => $entry->entry_kind, 'reference' => $entry->source_reference, 'amount' => Decimal::fixed(BigDecimal::of($entry->amount), 2), 'occurred_at' => $entry->occurred_at->toAtomString()];
        }
        foreach ($credits as $entry) {
            $entries[] = ['id' => $entry->public_id, 'type' => 'supplier_credit', 'status' => $entry->status->value, 'reference' => $entry->supplier_reference, 'amount' => Decimal::fixed(BigDecimal::of($entry->amount)->negated(), 2), 'occurred_at' => $entry->occurred_at->toAtomString()];
        }
        foreach ($payments as $entry) {
            $entries[] = ['id' => $entry->public_id, 'type' => 'supplier_'.$entry->entry_kind, 'reference' => $entry->reference, 'amount' => Decimal::fixed(BigDecimal::of($entry->amount)->negated(), 2), 'occurred_at' => $entry->paid_at->toAtomString()];
        }
        usort($entries, fn (array $left, array $right): int => [$left['occurred_at'], $left['id']] <=> [$right['occurred_at'], $right['id']]);

        return [
            'currency' => 'PKR', 'charges' => Decimal::fixed($chargeTotal, 2), 'confirmed_credits' => Decimal::fixed($confirmedCredits, 2),
            'expected_credits' => Decimal::fixed($expectedCredits, 2), 'payments' => Decimal::fixed($paymentTotal, 2),
            'balance' => Decimal::fixed($chargeTotal->minus($confirmedCredits)->minus($paymentTotal), 2),
            'unallocated_payments' => Decimal::fixed($paymentTotal->minus((string) $allocated), 2), 'entries' => $entries,
        ];
    }
}
