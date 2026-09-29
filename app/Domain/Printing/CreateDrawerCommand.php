<?php

namespace App\Domain\Printing;

use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Offline\CanonicalPayload;
use App\Models\CashSession;
use App\Models\DrawerCommand;
use App\Models\OutboxEvent;
use App\Models\Register;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CreateDrawerCommand
{
    public function __construct(private readonly CanonicalPayload $canonical, private readonly PrintAuthorizationSigner $signer) {}

    /** @return array{command:DrawerCommand,replayed:bool} */
    public function handle(CashSession $session, Register $register, User $actor, string $idempotencyKey, string $kind, string $reason): array
    {
        $input = ['cash_session_id' => $session->public_id, 'register_id' => $register->public_id, 'kind' => $kind, 'reason' => $reason];
        $hash = $this->canonical->hash($input);

        return DB::transaction(function () use ($session, $register, $actor, $idempotencyKey, $kind, $reason, $hash): array {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $existing = DrawerCommand::query()->where('merchant_id', $locked->merchant_id)->where('register_id', $register->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof DrawerCommand) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The drawer idempotency key was reused with different data.');
                }

                return ['command' => $existing, 'replayed' => true];
            }
            abort_unless($locked->register_id === $register->id && $locked->status === CashSessionStatus::Open, 409, 'The drawer requires the active cash session.');
            abort_unless(($register->capabilities['cash_drawer'] ?? false) === true, 409, 'This register has no configured cash drawer capability.');
            $register->loadMissing('store.merchant');
            $commandId = (string) Str::uuid7();
            $expires = now()->addMinutes(2);
            $payload = ['kind' => $kind, 'drawer_command_id' => $commandId, 'merchant_id' => $register->store->merchant->public_id, 'store_id' => $register->store->public_id, 'register_id' => $register->public_id, 'fencing_epoch' => $register->fencing_epoch, 'cash_session_id' => $locked->public_id, 'expires_at' => $expires->toAtomString(), 'nonce' => (string) Str::uuid()];
            $command = DrawerCommand::query()->create(['public_id' => $commandId, 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'register_id' => $register->id, 'cash_session_id' => $locked->id, 'requested_by_user_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'kind' => $kind, 'reason' => $reason, 'state' => 'authorized', 'signed_authorization' => $this->signer->sign($payload), 'expires_at' => $expires]);
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'printing.drawer.authorized', 'aggregate_type' => 'drawer_command', 'aggregate_id' => $command->public_id, 'payload' => ['drawer_command_id' => $command->public_id, 'register_id' => $register->public_id, 'kind' => $kind], 'occurred_at' => now(), 'available_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'printing.drawer.authorized', 'outcome' => 'succeeded', 'metadata' => ['drawer_command_id' => $command->public_id, 'kind' => $kind, 'reason' => $reason], 'occurred_at' => now()]);

            return ['command' => $command, 'replayed' => false];
        }, attempts: 3);
    }
}
