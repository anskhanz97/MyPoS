<?php

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\CashSession;
use App\Models\CashSessionCount;
use App\Models\OutboxEvent;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SubmitCashSessionCount
{
    public function __construct(private readonly CashSessionLedger $ledger) {}

    /** @param array<int|string, int>|null $denominations
     * @return array{count: CashSessionCount, session: CashSession, replayed: bool}
     */
    public function handle(CashSession $session, User $actor, string $idempotencyKey, int $expectedVersion, string $countedAmount, ?array $denominations, int $pendingServerSales, int $pendingOfflineSales): array
    {
        $counted = BigDecimal::of($countedAmount)->toScale(2);
        $normalized = $this->denominations($denominations, $counted);
        $hash = hash('sha256', json_encode(['version' => $expectedVersion, 'counted_amount' => Decimal::fixed($counted, 2), 'denominations' => $normalized, 'pending_server_sales' => $pendingServerSales, 'pending_offline_sales' => $pendingOfflineSales], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($session, $actor, $idempotencyKey, $expectedVersion, $counted, $normalized, $pendingServerSales, $pendingOfflineSales, $hash): array {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $existing = CashSessionCount::query()->where('cash_session_id', $locked->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof CashSessionCount) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The close idempotency key was reused with different data.');
                }

                return ['count' => $existing, 'session' => $locked, 'replayed' => true];
            }
            if (! in_array($locked->status, [CashSessionStatus::Open, CashSessionStatus::ClosePendingSync], true)) {
                throw new ConflictHttpException('The cash session cannot accept a blind count in its current state.');
            }
            if ($locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The cash session changed; refresh before submitting the count.');
            }
            abort_unless($locked->current_custodian_user_id === $actor->id, 403, 'Only the current custodian can submit the drawer count.');
            $cutoff = now();
            $highWatermark = (int) ($locked->movements()->max('id') ?? 0);
            $count = CashSessionCount::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'cash_session_id' => $locked->id,
                'counted_by_user_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash,
                'counted_amount' => Decimal::fixed($counted, 2), 'denominations' => $normalized, 'cutoff_at' => $cutoff,
                'movement_high_watermark' => $highWatermark, 'pending_server_sale_count' => $pendingServerSales, 'pending_offline_sale_count' => $pendingOfflineSales,
            ]);
            $updates = [
                'counted_amount' => Decimal::fixed($counted, 2), 'close_cutoff_at' => $cutoff, 'count_submitted_at' => now(),
                'pending_server_sale_count' => $pendingServerSales, 'pending_offline_sale_count' => $pendingOfflineSales, 'version' => $locked->version + 1,
            ];
            if ($pendingServerSales > 0 || $pendingOfflineSales > 0) {
                $updates += ['status' => CashSessionStatus::ClosePendingSync, 'expected_amount' => null, 'variance_amount' => null];
            } else {
                $expected = $this->ledger->expected($locked);
                $variance = $counted->minus($expected);
                $updates += [
                    'status' => $variance->isZero() ? CashSessionStatus::Closed : CashSessionStatus::VarianceReview,
                    'active_guard' => $variance->isZero() ? null : 1,
                    'expected_amount' => Decimal::fixed($expected, 2), 'variance_amount' => Decimal::fixed($variance, 2),
                    'closed_by_user_id' => $variance->isZero() ? $actor->id : null, 'closed_at' => $variance->isZero() ? now() : null,
                ];
            }
            $locked->forceFill($updates)->save();
            if ($locked->status === CashSessionStatus::Closed) {
                OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'cash.session.closed', 'aggregate_type' => 'cash_session', 'aggregate_id' => $locked->public_id, 'payload' => ['cash_session_id' => $locked->public_id, 'expected_amount' => $locked->expected_amount, 'counted_amount' => $locked->counted_amount, 'variance_amount' => '0.00', 'business_date' => $locked->business_date->toDateString()], 'occurred_at' => now(), 'available_at' => now()]);
            }
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'cash.session.count_submitted', 'outcome' => 'succeeded', 'metadata' => ['cash_session_id' => $locked->public_id, 'pending_server_sales' => $pendingServerSales, 'pending_offline_sales' => $pendingOfflineSales], 'occurred_at' => now()]);

            return ['count' => $count, 'session' => $locked->refresh(), 'replayed' => false];
        }, attempts: 3);
    }

    /** @param array<int|string, int>|null $denominations
     * @return array<int|string, int>|null
     */
    private function denominations(?array $denominations, BigDecimal $counted): ?array
    {
        if ($denominations === null) {
            return null;
        }
        ksort($denominations);
        $sum = BigDecimal::zero();
        foreach ($denominations as $denomination => $quantity) {
            $denominationValue = (string) $denomination;
            abort_unless(preg_match('/^[1-9][0-9]{0,5}(\.[0-9]{1,2})?$/', $denominationValue) === 1, 422, 'Each denomination must be a positive decimal value.');
            $sum = $sum->plus(BigDecimal::of($denominationValue)->multipliedBy($quantity));
        }
        abort_unless($sum->toScale(2)->isEqualTo($counted), 422, 'Denomination totals must equal the counted amount.');

        return $denominations;
    }
}
