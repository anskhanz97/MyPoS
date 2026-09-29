<?php

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Shared\Math\Decimal;
use App\Models\CashSession;
use App\Models\CashVarianceReview;
use App\Models\OutboxEvent;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReviewCashSession
{
    public function __construct(private readonly CashSessionLedger $ledger) {}

    /** @return array{review: CashVarianceReview, session: CashSession, replayed: bool} */
    public function handle(CashSession $session, User $actor, string $idempotencyKey, int $expectedVersion, string $decision, string $reason): array
    {
        $hash = hash('sha256', json_encode(['version' => $expectedVersion, 'decision' => $decision, 'reason' => $reason], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($session, $actor, $idempotencyKey, $expectedVersion, $decision, $reason, $hash): array {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $existing = CashVarianceReview::query()->where('cash_session_id', $locked->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof CashVarianceReview) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The review idempotency key was reused with different data.');
                }

                return ['review' => $existing, 'session' => $locked, 'replayed' => true];
            }
            if (! in_array($locked->status, [CashSessionStatus::CountSubmitted, CashSessionStatus::VarianceReview], true)) {
                throw new ConflictHttpException('This cash session is not ready for review.');
            }
            if ($locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The cash session changed; refresh before reviewing it.');
            }
            $expected = $this->ledger->expected($locked);
            $counted = BigDecimal::of((string) $locked->counted_amount);
            $variance = $counted->minus($expected);
            $review = CashVarianceReview::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'cash_session_id' => $locked->id,
                'reviewed_by_user_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash,
                'decision' => $decision, 'reason' => $reason, 'expected_amount' => Decimal::fixed($expected, 2),
                'counted_amount' => Decimal::fixed($counted, 2), 'variance_amount' => Decimal::fixed($variance, 2), 'reviewed_at' => now(),
            ]);
            $closed = $decision === 'approve';
            $locked->forceFill($closed ? [
                'status' => CashSessionStatus::Closed, 'active_guard' => null, 'expected_amount' => Decimal::fixed($expected, 2),
                'variance_amount' => Decimal::fixed($variance, 2), 'closed_by_user_id' => $actor->id, 'closed_at' => now(), 'version' => $locked->version + 1,
            ] : [
                'status' => CashSessionStatus::Open, 'active_guard' => 1, 'expected_amount' => null, 'counted_amount' => null,
                'variance_amount' => null, 'close_cutoff_at' => null, 'count_submitted_at' => null, 'pending_server_sale_count' => 0,
                'pending_offline_sale_count' => 0, 'closed_by_user_id' => null, 'closed_at' => null, 'version' => $locked->version + 1,
            ])->save();
            $event = $closed ? 'cash.session.closed' : 'cash.session.reopened';
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => $event, 'aggregate_type' => 'cash_session', 'aggregate_id' => $locked->public_id, 'payload' => ['cash_session_id' => $locked->public_id, 'expected_amount' => Decimal::fixed($expected, 2), 'counted_amount' => Decimal::fixed($counted, 2), 'variance_amount' => Decimal::fixed($variance, 2), 'business_date' => $locked->business_date->toDateString()], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => $event, 'outcome' => 'succeeded', 'metadata' => ['cash_session_id' => $locked->public_id, 'reason' => $reason, 'variance_amount' => Decimal::fixed($variance, 2)], 'occurred_at' => now()]);

            return ['review' => $review, 'session' => $locked->refresh(), 'replayed' => false];
        }, attempts: 3);
    }
}
