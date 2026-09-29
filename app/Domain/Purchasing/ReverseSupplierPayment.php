<?php

namespace App\Domain\Purchasing;

use App\Domain\Shared\Math\Decimal;
use App\Models\OutboxEvent;
use App\Models\SecurityAuditEvent;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReverseSupplierPayment
{
    /** @return array{payment: SupplierPayment, replayed: bool} */
    public function handle(SupplierPayment $payment, User $actor, string $idempotencyKey, string $reason, string $reversedAt): array
    {
        $effectiveAt = CarbonImmutable::parse($reversedAt);
        $payloadHash = hash('sha256', json_encode(['payment' => $payment->public_id, 'reason' => $reason, 'reversed_at' => $effectiveAt->toIso8601String()], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($payment, $actor, $idempotencyKey, $reason, $effectiveAt, $payloadHash): array {
            $locked = SupplierPayment::query()->with(['supplier', 'allocations.charge'])->lockForUpdate()->findOrFail($payment->id);
            abort_if($locked->entry_kind !== 'payment', 422, 'Only an original supplier payment can be reversed.');
            $existing = SupplierPayment::query()->where('reverses_payment_id', $locked->id)->with(['supplier', 'allocations.charge'])->lockForUpdate()->first();
            if ($existing instanceof SupplierPayment) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('This supplier payment was already reversed with different data.');
                }

                return ['payment' => $existing, 'replayed' => true];
            }
            $reversal = SupplierPayment::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'supplier_id' => $locked->supplier_id, 'recorded_by_user_id' => $actor->id,
                'reverses_payment_id' => $locked->id, 'number' => 'SPR-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'entry_kind' => 'reversal',
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'currency' => 'PKR', 'amount' => Decimal::fixed(BigDecimal::of($locked->amount)->negated(), 2),
                'payment_method' => $locked->payment_method, 'reference' => $locked->reference, 'notes' => $reason, 'paid_at' => $effectiveAt,
            ]);
            foreach ($locked->allocations as $allocation) {
                SupplierPaymentAllocation::query()->create([
                    'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'supplier_id' => $locked->supplier_id, 'supplier_payment_id' => $reversal->id,
                    'supplier_charge_entry_id' => $allocation->supplier_charge_entry_id, 'reverses_allocation_id' => $allocation->id,
                    'entry_kind' => 'reversal', 'amount' => Decimal::fixed(BigDecimal::of($allocation->amount)->negated(), 2),
                ]);
            }
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'purchasing.supplier_payment.reversed', 'aggregate_type' => 'supplier_payment', 'aggregate_id' => $reversal->public_id, 'payload' => ['supplier_payment_id' => $locked->public_id, 'reversal_id' => $reversal->public_id], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'purchasing.supplier_payment.reversed', 'outcome' => 'succeeded', 'metadata' => ['supplier_payment_id' => $locked->public_id, 'reversal_id' => $reversal->public_id, 'reason' => $reason], 'occurred_at' => now()]);

            return ['payment' => $reversal->load(['supplier', 'allocations.charge']), 'replayed' => false];
        }, attempts: 3);
    }
}
