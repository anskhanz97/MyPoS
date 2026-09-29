<?php

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\CashSessionHandover;
use App\Models\MerchantMembership;
use App\Models\SecurityAuditEvent;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class HandoverCashSession
{
    /** @return array{handover: CashSessionHandover, session: CashSession, replayed: bool} */
    public function handle(CashSession $session, User $actor, User $toUser, string $idempotencyKey, ?string $note): array
    {
        $hash = hash('sha256', json_encode(['to_user_id' => $toUser->id, 'note' => $note], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($session, $actor, $toUser, $idempotencyKey, $note, $hash): array {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $existing = CashSessionHandover::query()->where('cash_session_id', $locked->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof CashSessionHandover) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The handover idempotency key was reused with different data.');
                }

                return ['handover' => $existing, 'session' => $locked, 'replayed' => true];
            }
            if ($locked->status !== CashSessionStatus::Open) {
                throw new ConflictHttpException('Only an open cash session can be handed over.');
            }
            abort_unless($locked->current_custodian_user_id === $actor->id, 403, 'Only the current custodian can hand over this cash session.');
            abort_if($locked->current_custodian_user_id === $toUser->id, 422, 'The target user already holds this cash session.');
            $membership = MerchantMembership::query()->where('merchant_id', $locked->merchant_id)->where('user_id', $toUser->id)->where('status', 'active')->first();
            abort_unless($membership instanceof MerchantMembership && StoreAssignment::query()->where('merchant_membership_id', $membership->id)->where('store_id', $locked->store_id)->exists(), 422, 'The target user is not active at this store.');
            $handover = CashSessionHandover::query()->create([
                'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'cash_session_id' => $locked->id,
                'from_user_id' => $locked->current_custodian_user_id, 'to_user_id' => $toUser->id, 'recorded_by_user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'note' => $note, 'occurred_at' => now(),
            ]);
            $locked->forceFill(['current_custodian_user_id' => $toUser->id, 'handover_count' => $locked->handover_count + 1, 'version' => $locked->version + 1])->save();
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'cash.session.handed_over', 'outcome' => 'succeeded', 'metadata' => ['cash_session_id' => $locked->public_id, 'from_user_id' => $handover->from_user_id, 'to_user_id' => $toUser->id], 'occurred_at' => now()]);

            return ['handover' => $handover, 'session' => $locked->refresh(), 'replayed' => false];
        }, attempts: 3);
    }
}
