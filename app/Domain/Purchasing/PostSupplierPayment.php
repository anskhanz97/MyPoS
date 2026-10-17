<?php

namespace App\Domain\Purchasing;

use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\Math\Decimal;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierChargeEntry;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostSupplierPayment
{
    /**
     * @param  list<array{supplier_charge_id: string, amount: string}>  $allocations
     * @return array{payment: SupplierPayment, replayed: bool}
     */
    public function handle(Merchant $merchant, Store $store, Supplier $supplier, User $actor, string $idempotencyKey, string $amount, string $method, string $reference, string $paidAt, ?string $notes, array $allocations): array
    {
        abort_unless($supplier->merchant_id === $merchant->id, 404);
        $paymentAmount = BigDecimal::of($amount)->toScale(2);
        $effectiveAt = CarbonImmutable::parse($paidAt);
        abort_if($effectiveAt->isAfter(now()->addMinutes(5)), 422, 'Supplier payment time cannot be in the future.');
        $normalized = array_map(fn (array $allocation): array => ['supplier_charge_id' => (string) $allocation['supplier_charge_id'], 'amount' => Decimal::fixed(BigDecimal::of($allocation['amount']), 2)], $allocations);
        usort($normalized, fn (array $a, array $b): int => $a['supplier_charge_id'] <=> $b['supplier_charge_id']);
        $payloadHash = hash('sha256', json_encode(['supplier' => $supplier->public_id, 'amount' => Decimal::fixed($paymentAmount, 2), 'method' => $method, 'reference' => $reference, 'paid_at' => $effectiveAt->toIso8601String(), 'notes' => $notes, 'allocations' => $normalized], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $supplier, $actor, $idempotencyKey, $paymentAmount, $method, $reference, $effectiveAt, $notes, $normalized, $payloadHash): array {
            $existing = SupplierPayment::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('idempotency_key', $idempotencyKey)->with(['supplier', 'allocations.charge'])->lockForUpdate()->first();
            if ($existing instanceof SupplierPayment) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('The supplier-payment idempotency key was reused with different data.');
                }

                return ['payment' => $existing, 'replayed' => true];
            }
            $totalAllocated = BigDecimal::zero();
            $resolved = [];
            $seen = [];
            foreach ($normalized as $input) {
                abort_if(isset($seen[$input['supplier_charge_id']]), 422, 'A supplier charge may appear only once per payment.');
                $seen[$input['supplier_charge_id']] = true;
                $charge = (new SupplierChargeEntry)->resolveRouteBinding($input['supplier_charge_id']);
                abort_unless($charge instanceof SupplierChargeEntry && $charge->merchant_id === $merchant->id && $charge->store_id === $store->id && $charge->supplier_id === $supplier->id && $charge->entry_kind === 'charge', 422, 'A payment allocation references an unavailable supplier charge.');
                $charge = SupplierChargeEntry::query()->lockForUpdate()->findOrFail($charge->id);
                $reversal = SupplierChargeEntry::query()->where('reverses_entry_id', $charge->id)->lockForUpdate()->sum('amount');
                $allocated = SupplierPaymentAllocation::query()->where('supplier_charge_entry_id', $charge->id)->lockForUpdate()->sum('amount');
                $open = BigDecimal::of($charge->amount)->plus((string) $reversal)->minus((string) $allocated);
                $allocationAmount = BigDecimal::of($input['amount']);
                if ($allocationAmount->isGreaterThan($open)) {
                    throw new ConflictHttpException('A supplier-payment allocation exceeds the charge open amount.');
                }
                $totalAllocated = $totalAllocated->plus($allocationAmount);
                $resolved[] = ['charge' => $charge, 'amount' => $allocationAmount];
            }
            if ($totalAllocated->isGreaterThan($paymentAmount)) {
                throw new ConflictHttpException('Supplier-payment allocations exceed the payment amount.');
            }
            $payment = SupplierPayment::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $supplier->id, 'recorded_by_user_id' => $actor->id,
                'number' => app(DocumentNumber::class)->next($merchant->id, 'SPY', $effectiveAt), 'entry_kind' => 'payment', 'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash, 'currency' => 'PKR', 'amount' => Decimal::fixed($paymentAmount, 2), 'payment_method' => $method,
                'reference' => $reference, 'notes' => $notes, 'paid_at' => $effectiveAt,
            ]);
            foreach ($resolved as $item) {
                SupplierPaymentAllocation::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'supplier_id' => $supplier->id, 'supplier_payment_id' => $payment->id, 'supplier_charge_entry_id' => $item['charge']->id, 'entry_kind' => 'allocation', 'amount' => Decimal::fixed($item['amount'], 2)]);
            }
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'store_id' => $store->id, 'event_type' => 'purchasing.supplier_payment.recorded', 'aggregate_type' => 'supplier_payment', 'aggregate_id' => $payment->public_id, 'payload' => ['supplier_payment_id' => $payment->public_id, 'supplier_id' => $supplier->public_id], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.supplier_payment.recorded', 'outcome' => 'succeeded', 'metadata' => ['supplier_payment_id' => $payment->public_id, 'supplier_id' => $supplier->public_id, 'allocated' => Decimal::fixed($totalAllocated, 2)], 'occurred_at' => now()]);

            return ['payment' => $payment->load(['supplier', 'allocations.charge']), 'replayed' => false];
        }, attempts: 3);
    }
}
