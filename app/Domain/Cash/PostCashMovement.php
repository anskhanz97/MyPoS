<?php

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\OutboxEvent;
use App\Models\SecurityAuditEvent;
use App\Models\SupplierPayment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PostCashMovement
{
    public function __construct(private readonly CashSessionLedger $ledger) {}

    /** @return array{movement: CashMovement, session: CashSession, replayed: bool} */
    public function handle(CashSession $session, User $actor, string $idempotencyKey, CashMovementKind $kind, string $amount, string $reason, string $occurredAt, ?string $sourceType = null, ?string $sourceId = null, bool $internal = false): array
    {
        $absolute = BigDecimal::of($amount)->toScale(2);
        abort_if($absolute->isNegative(), 422, 'Cash movement amount cannot be negative.');
        abort_if($absolute->isZero() && $kind !== CashMovementKind::NoSaleOpen, 422, 'Cash movement amount must be greater than zero.');
        $signed = match ($kind) {
            CashMovementKind::PaidOut, CashMovementKind::RefundCash, CashMovementKind::SupplierPayment, CashMovementKind::PayrollPayout, CashMovementKind::Expense => $absolute->negated(),
            CashMovementKind::NoSaleOpen => BigDecimal::zero()->toScale(2),
            default => $absolute,
        };
        if (! $internal && ! in_array($kind, [CashMovementKind::PaidIn, CashMovementKind::PaidOut, CashMovementKind::SupplierPayment, CashMovementKind::NoSaleOpen], true)) {
            abort(422, 'This cash movement kind is reserved for its owning module.');
        }
        $when = now()->parse($occurredAt);
        abort_if($when->isBefore($session->opened_at) || $when->isAfter(now()->addMinutes(5)), 422, 'Cash movement time must fall within the session and cannot be in the future.');
        $hash = hash('sha256', json_encode(['kind' => $kind->value, 'amount' => Decimal::fixed($absolute, 2), 'reason' => $reason, 'occurred_at' => $when->toIso8601String(), 'source_type' => $sourceType, 'source_id' => $sourceId], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($session, $actor, $idempotencyKey, $kind, $absolute, $signed, $reason, $when, $sourceType, $sourceId, $internal, $hash): array {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $existing = CashMovement::query()->where('cash_session_id', $locked->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof CashMovement) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The cash-movement idempotency key was reused with different data.');
                }

                return ['movement' => $existing, 'session' => $locked, 'replayed' => true];
            }
            $allowed = $internal
                ? [CashSessionStatus::Open, CashSessionStatus::ClosePendingSync, CashSessionStatus::CountSubmitted, CashSessionStatus::VarianceReview]
                : [CashSessionStatus::Open];
            if (! in_array($locked->status, $allowed, true)) {
                throw new ConflictHttpException('The cash session does not accept this movement in its current state.');
            }
            $sourcePublicId = $this->sourcePublicId($locked, $kind, $sourceType, $sourceId, $absolute, $internal);
            $sourceKey = $sourcePublicId === null ? null : $sourceType.':'.$sourcePublicId;
            if ($sourceKey !== null && CashMovement::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->store_id)->where('source_key', $sourceKey)->lockForUpdate()->exists()) {
                throw new ConflictHttpException('This source already has a cash movement.');
            }
            $movement = CashMovement::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'cash_session_id' => $locked->id,
                'recorded_by_user_id' => $actor->id, 'kind' => $kind, 'signed_amount' => Decimal::fixed($signed, 2), 'reason' => $reason,
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'source_type' => $sourceType,
                'source_public_id' => $sourcePublicId, 'source_key' => $sourceKey,
                'occurred_at' => $when,
            ]);
            if ($locked->status !== CashSessionStatus::Open) {
                $expected = $this->ledger->expected($locked);
                $locked->forceFill(['status' => CashSessionStatus::VarianceReview, 'expected_amount' => Decimal::fixed($expected, 2), 'variance_amount' => Decimal::fixed(BigDecimal::of((string) $locked->counted_amount)->minus($expected), 2), 'version' => $locked->version + 1])->save();
            }
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'cash.movement.posted', 'aggregate_type' => 'cash_movement', 'aggregate_id' => $movement->public_id, 'payload' => ['cash_session_id' => $locked->public_id, 'cash_movement_id' => $movement->public_id, 'kind' => $kind->value, 'signed_amount' => $movement->signed_amount, 'source_type' => $sourceType, 'source_id' => $sourcePublicId], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'cash.movement.posted', 'outcome' => 'succeeded', 'metadata' => ['cash_session_id' => $locked->public_id, 'cash_movement_id' => $movement->public_id, 'kind' => $kind->value], 'occurred_at' => now()]);

            return ['movement' => $movement, 'session' => $locked->refresh(), 'replayed' => false];
        }, attempts: 3);
    }

    private function sourcePublicId(CashSession $session, CashMovementKind $kind, ?string $sourceType, ?string $sourceId, BigDecimal $amount, bool $internal): ?string
    {
        if ($kind !== CashMovementKind::SupplierPayment) {
            abort_if($sourceId !== null && $sourceType === null, 422, 'A source type is required with a source identifier.');

            return $internal ? $sourceId : null;
        }
        abort_unless($sourceType === 'supplier_payment' && $sourceId !== null, 422, 'A supplier cash payment source is required.');
        $payment = (new SupplierPayment)->resolveRouteBinding($sourceId);
        abort_unless($payment instanceof SupplierPayment && $payment->merchant_id === $session->merchant_id && $payment->store_id === $session->store_id && $payment->payment_method === 'cash' && $payment->entry_kind === 'payment', 422, 'The supplier payment is not an eligible cash source.');
        $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
        abort_unless(BigDecimal::of($payment->amount)->isEqualTo($amount), 422, 'The drawer movement must equal the linked supplier cash payment.');

        return $payment->public_id;
    }
}
