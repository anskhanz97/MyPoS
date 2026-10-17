<?php

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\Math\Decimal;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\Register;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OpenCashSession
{
    /** @return array{session: CashSession, replayed: bool} */
    public function handle(Merchant $merchant, Store $store, Register $register, User $actor, string $idempotencyKey, string $openingAmount, string $openedAt): array
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
        abort_unless($register->status === 'active', 409, 'Only an active register can open a cash session.');
        $amount = BigDecimal::of($openingAmount)->toScale(2);
        $when = now()->parse($openedAt);
        abort_if($when->isAfter(now()->addMinutes(5)), 422, 'Opening time cannot be in the future.');
        $hash = hash('sha256', json_encode(['register' => $register->public_id, 'opening_amount' => Decimal::fixed($amount, 2), 'opened_at' => $when->toIso8601String()], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($merchant, $store, $register, $actor, $idempotencyKey, $amount, $when, $hash): array {
                Register::query()->lockForUpdate()->findOrFail($register->id);
                $existing = CashSession::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('open_idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing instanceof CashSession) {
                    if (! hash_equals($existing->open_payload_hash, $hash)) {
                        throw new ConflictHttpException('The cash-session idempotency key was reused with different data.');
                    }

                    return ['session' => $existing, 'replayed' => true];
                }
                if (CashSession::query()->where('register_id', $register->id)->whereNotNull('active_guard')->lockForUpdate()->exists()) {
                    throw new ConflictHttpException('This register already has an active cash session.');
                }
                $session = CashSession::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id,
                    'opened_by_user_id' => $actor->id, 'current_custodian_user_id' => $actor->id,
                    'number' => app(DocumentNumber::class)->next($merchant->id, 'CS', $when), 'status' => CashSessionStatus::Open,
                    'active_guard' => 1, 'open_idempotency_key' => $idempotencyKey, 'open_payload_hash' => $hash,
                    'business_date' => $when->toDateString(), 'opening_amount' => Decimal::fixed($amount, 2), 'opened_at' => $when,
                ]);
                CashMovement::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'cash_session_id' => $session->id,
                    'recorded_by_user_id' => $actor->id, 'kind' => CashMovementKind::OpeningFloat,
                    'signed_amount' => Decimal::fixed($amount, 2), 'reason' => 'Opening float', 'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $hash, 'source_type' => 'cash_session', 'source_public_id' => $session->public_id,
                    'source_key' => 'opening:'.$session->public_id, 'occurred_at' => $when,
                ]);
                OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'store_id' => $store->id, 'event_type' => 'cash.session.opened', 'aggregate_type' => 'cash_session', 'aggregate_id' => $session->public_id, 'payload' => ['cash_session_id' => $session->public_id, 'register_id' => $register->public_id, 'opened_by_user_id' => $actor->id, 'opening_amount' => $session->opening_amount, 'business_date' => $session->business_date->toDateString()], 'occurred_at' => now(), 'available_at' => now()]);
                SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'cash.session.opened', 'outcome' => 'succeeded', 'metadata' => ['cash_session_id' => $session->public_id, 'register_id' => $register->public_id], 'occurred_at' => now()]);

                return ['session' => $session, 'replayed' => false];
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                throw new ConflictHttpException('This register already has an active cash session.', $exception);
            }
            throw $exception;
        }
    }
}
