<?php

namespace App\Domain\Offline;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\FixedRolePermissions;
use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\PostCashMovement;
use App\Domain\Checkout\CartWorkflow;
use App\Domain\Pricing\PricingVersionResolver;
use App\Domain\Sales\FinalizeSale;
use App\Models\CashSession;
use App\Models\DeviceEnrollment;
use App\Models\InventoryLocation;
use App\Models\MerchantMembership;
use App\Models\OfflineBootstrapSnapshot;
use App\Models\OfflineCommand;
use App\Models\OutboxEvent;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class IngestOfflineCommands
{
    public function __construct(
        private readonly CanonicalPayload $canonical,
        private readonly FixedRolePermissions $permissions,
        private readonly PricingVersionResolver $pricing,
        private readonly CartWorkflow $carts,
        private readonly FinalizeSale $sales,
        private readonly PostCashMovement $cash,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $commands
     * @return list<array<string, mixed>>
     */
    public function handle(DeviceEnrollment $device, array $commands): array
    {
        return array_map(fn (array $command): array => $this->ingest($device, $command), $commands);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function ingest(DeviceEnrollment $device, array $input): array
    {
        $actualHash = $this->canonical->hash($input['payload']);
        if (! hash_equals((string) $input['payload_hash'], $actualHash)) {
            return $this->unrecordedConflict((string) $input['command_id'], 'payload_hash_mismatch', 'The offline command payload did not match its recorded fingerprint.');
        }

        $record = DB::transaction(function () use ($device, $input): OfflineCommand {
            DeviceEnrollment::query()->lockForUpdate()->findOrFail($device->id);
            $existing = OfflineCommand::query()->where('merchant_id', $device->merchant_id)->where('command_id', $input['command_id'])->lockForUpdate()->first();
            if ($existing instanceof OfflineCommand) {
                if ($existing->device_enrollment_id !== $device->id || $existing->command_type !== $input['command_type'] || ! hash_equals($existing->payload_hash, (string) $input['payload_hash'])) {
                    $existing->setAttribute('identity_conflict', true);

                    return $existing;
                }
                $existing->forceFill(['attempt_count' => $existing->attempt_count + 1])->save();

                return $existing;
            }

            $session = (new CashSession)->resolveRouteBinding((string) $input['payload']['register_session_id']);

            return OfflineCommand::query()->create([
                'merchant_id' => $device->merchant_id,
                'store_id' => $device->store_id,
                'register_id' => $device->register_id,
                'device_enrollment_id' => $device->id,
                'cash_session_id' => $session instanceof CashSession && $session->merchant_id === $device->merchant_id && $session->store_id === $device->store_id && $session->register_id === $device->register_id ? $session->id : null,
                'command_id' => $input['command_id'],
                'command_type' => $input['command_type'],
                'schema_version' => $input['schema_version'],
                'committed_at_local' => $input['committed_at_local'],
                'snapshot_version' => $input['snapshot_version'],
                'fencing_epoch' => $input['fencing_epoch'],
                'payload_hash' => $input['payload_hash'],
                'payload' => $input['payload'],
                'state' => 'pending',
                'received_at' => now(),
            ]);
        }, attempts: 3);

        if ($record->getAttribute('identity_conflict') === true) {
            return $this->unrecordedConflict((string) $input['command_id'], 'command_identity_reused', 'The command identifier was already used with different data.');
        }
        if ($record->state !== 'pending') {
            return $this->present($record, true);
        }

        $this->refreshPendingCount($record);
        try {
            $resource = $this->process($device, $record);
            $record->forceFill(['state' => 'acknowledged', 'resource_type' => $resource['type'], 'resource_public_id' => $resource['id'], 'processed_at' => now(), 'conflict_code' => null, 'safe_message' => null])->save();
            $this->event($record, 'offline.command.acknowledged');
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
            $this->conflict($record, 'command_rejected', $this->safeMessage($response->getStatusCode(), $response->getContent()));
        } catch (HttpExceptionInterface $exception) {
            $this->conflict($record, $this->conflictCode($exception->getStatusCode()), $exception->getMessage() !== '' ? $exception->getMessage() : 'The offline command needs reconciliation.');
        } catch (ModelNotFoundException) {
            $this->conflict($record, 'record_not_found', 'A referenced offline record is no longer available.');
        } catch (Throwable $exception) {
            $this->refreshPendingCount($record);
            throw $exception;
        }
        $this->refreshPendingCount($record);

        return $this->present($record->refresh(), false);
    }

    /** @return array{type:string,id:string} */
    private function process(DeviceEnrollment $device, OfflineCommand $command): array
    {
        $snapshot = OfflineBootstrapSnapshot::query()->where('device_enrollment_id', $device->id)->where('snapshot_version', $command->snapshot_version)->first();
        abort_unless($snapshot instanceof OfflineBootstrapSnapshot && $snapshot->schema_version === $command->schema_version, 409, 'The offline snapshot is unknown or incompatible.');
        abort_unless(hash_equals($snapshot->content_hash, $this->canonical->hash($snapshot->payload)), 409, 'The offline snapshot evidence is invalid.');
        abort_unless($command->committed_at_local->betweenIncluded($snapshot->issued_at->subMinutes(5), $snapshot->expires_at->addMinutes(5)), 409, 'The offline sale was committed outside its authorized lease.');
        abort_unless($command->fencing_epoch === $device->fencing_epoch && $command->fencing_epoch === (int) data_get($snapshot->payload, 'device.fencing_epoch'), 409, 'The register ownership lease changed before synchronization.');
        abort_if($device->revoked_at !== null && $command->committed_at_local->isAfter($device->revoked_at->addMinutes(5)), 409, 'The offline command was committed after this device was retired.');
        abort_if($device->lease_expires_at !== null && $command->committed_at_local->isAfter($device->lease_expires_at->addMinutes(5)), 409, 'The offline command was committed after the device lease expired.');
        $payload = $command->payload;
        $membership = (new MerchantMembership)->resolveRouteBinding((string) $payload['membership_id']);
        abort_unless($membership instanceof MerchantMembership && $membership->merchant_id === $device->merchant_id, 403, 'The offline staff authorization is no longer valid.');
        $membership->loadMissing('user');
        $staffEvidence = null;
        $staffRows = $snapshot->payload['staff'] ?? null;
        if (is_array($staffRows)) {
            foreach ($staffRows as $staff) {
                if (is_array($staff) && ($staff['membership_id'] ?? null) === $membership->public_id) {
                    $staffEvidence = $staff;
                    break;
                }
            }
        }
        abort_unless(is_array($staffEvidence) && is_string($staffEvidence['auth_expires_at'] ?? null) && $command->committed_at_local->lessThanOrEqualTo(CarbonImmutable::parse($staffEvidence['auth_expires_at'])->addMinutes(5)), 403, 'The cached staff authorization expired before this command.');
        abort_unless(($staffEvidence['role'] ?? null) === $membership->role->value && ($staffEvidence['pin_changed_at'] ?? null) === $membership->pin_changed_at?->toAtomString(), 403, 'The staff authorization changed after this offline snapshot.');
        abort_unless($membership->status->value === 'active' && $membership->user->is_active && $membership->storeAssignments()->where('store_id', $device->store_id)->exists(), 403, 'The offline staff authorization is no longer valid.');
        $session = (new CashSession)->resolveRouteBinding((string) $payload['register_session_id']);
        abort_unless($session instanceof CashSession && $session->merchant_id === $device->merchant_id && $session->store_id === $device->store_id && $session->register_id === $device->register_id, 422, 'The offline cash session is outside this register.');

        return match ($command->command_type) {
            'finalize_cash_sale' => $this->finalizeSale($device, $command, $snapshot, $membership, $session, $staffEvidence),
            'cash_movement' => $this->cashMovement($command, $membership, $session, $staffEvidence),
            default => abort(422, 'The offline command type is unsupported.'),
        };
    }

    /**
     * @param  array<string, mixed>  $staffEvidence
     * @return array{type:string,id:string}
     */
    private function finalizeSale(DeviceEnrollment $device, OfflineCommand $command, OfflineBootstrapSnapshot $snapshot, MerchantMembership $membership, CashSession $session, array $staffEvidence): array
    {
        abort_unless($this->authorized($membership, $staffEvidence, Permission::SaleCreate), 403, 'This staff role cannot create sales.');
        $payload = $command->payload;
        abort_unless(is_array($payload['cart'] ?? null) && is_array($payload['cart']['lines'] ?? null) && count($payload['cart']['lines']) > 0, 422, 'The offline cart is empty.');
        abort_unless(isset($payload['inventory_location_id'], $payload['client_transaction_id'], $payload['cash_tender_id'], $payload['cash_received'], $payload['cash_change'], $payload['expected_total']), 422, 'The offline cash sale is incomplete.');
        $existing = Sale::query()->where('merchant_id', $device->merchant_id)->where('idempotency_key', $command->command_id)->first();
        if ($existing instanceof Sale) {
            abort_unless($existing->client_transaction_id === $payload['client_transaction_id'] && $existing->source === 'offline', 409, 'The offline sale identity resolved to different evidence.');

            return ['type' => 'sale', 'id' => $existing->public_id];
        }
        $merchant = $device->merchant;
        $store = $device->store;
        $register = $device->register;
        $currentPricing = $this->pricing->current($merchant, $store, CarbonImmutable::now());
        abort_unless(hash_equals((string) data_get($snapshot->payload, 'pricing.version'), $currentPricing['version']), 409, 'Pricing changed after this offline snapshot. Review the sale before posting.');
        $location = (new InventoryLocation)->resolveRouteBinding((string) $payload['inventory_location_id']);
        abort_unless($location instanceof InventoryLocation && $location->merchant_id === $device->merchant_id && $location->store_id === $device->store_id, 422, 'The offline inventory location is outside this store.');

        return DB::transaction(function () use ($merchant, $store, $register, $location, $membership, $payload, $command, $session): array {
            $cart = $this->carts->create($merchant, $store, $register, $location, $membership->user);
            $cartInput = $payload['cart'];
            $cartInput['expected_version'] = 1;
            $cart = $this->carts->replace($cart, $membership->user, $cartInput);
            $quoted = $this->carts->quote($merchant, $store, $cart, $membership->user, $cart->version);
            abort_unless((string) data_get($quoted['quote']->payload, 'totals.gross') === (string) $payload['expected_total'], 409, 'The authoritative total differs from the offline total. Review the sale before posting.');
            $result = $this->sales->handle($merchant, $store, $register, $membership->user, $command->command_id, [
                'client_transaction_id' => $payload['client_transaction_id'],
                'quote_id' => $quoted['quote']->public_id,
                'quote_version' => $quoted['quote']->quote_version,
                'register_session_id' => $session->public_id,
                'tenders' => [['tender_id' => $payload['cash_tender_id'], 'type' => 'cash', 'amount' => $payload['cash_received'], 'confirmation_source' => 'not_applicable']],
                'cash_change' => $payload['cash_change'],
            ], 'offline');

            return ['type' => 'sale', 'id' => $result['sale']->public_id];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $staffEvidence
     * @return array{type:string,id:string}
     */
    private function cashMovement(OfflineCommand $command, MerchantMembership $membership, CashSession $session, array $staffEvidence): array
    {
        $payload = $command->payload;
        abort_unless(isset($payload['kind'], $payload['amount'], $payload['reason'], $payload['occurred_at']), 422, 'The offline cash movement is incomplete.');
        $kind = CashMovementKind::tryFrom((string) $payload['kind']);
        abort_unless($kind instanceof CashMovementKind && in_array($kind, [CashMovementKind::PaidIn, CashMovementKind::PaidOut, CashMovementKind::NoSaleOpen], true), 422, 'The offline cash movement kind is unsupported.');
        $permission = match ($kind) {
            CashMovementKind::PaidOut => Permission::CashManage,
            CashMovementKind::NoSaleOpen => Permission::DrawerNoSale,
            default => Permission::CashOperate,
        };
        abort_unless($this->authorized($membership, $staffEvidence, $permission), 403, 'This staff role cannot post the requested cash movement.');
        $result = $this->cash->handle($session, $membership->user, $command->command_id, $kind, (string) $payload['amount'], (string) $payload['reason'], (string) $payload['occurred_at'], null, null, true);

        return ['type' => 'cash_movement', 'id' => $result['movement']->public_id];
    }

    private function conflict(OfflineCommand $command, string $code, string $message): void
    {
        $command->forceFill(['state' => 'conflict', 'conflict_code' => $code, 'safe_message' => Str::limit($message, 500, ''), 'processed_at' => now()])->save();
        $this->event($command, 'offline.command.conflicted');
    }

    private function event(OfflineCommand $command, string $type): void
    {
        OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $command->merchant_id, 'store_id' => $command->store_id, 'event_type' => $type, 'aggregate_type' => 'offline_command', 'aggregate_id' => $command->public_id, 'payload' => ['command_id' => $command->command_id, 'state' => $command->state, 'resource_type' => $command->resource_type, 'resource_id' => $command->resource_public_id, 'conflict_code' => $command->conflict_code], 'occurred_at' => now(), 'available_at' => now()]);
    }

    private function refreshPendingCount(OfflineCommand $command): void
    {
        if ($command->cash_session_id === null) {
            return;
        }
        $count = OfflineCommand::query()->where('cash_session_id', $command->cash_session_id)->where('command_type', 'finalize_cash_sale')->whereIn('state', ['pending', 'conflict'])->count();
        CashSession::query()->whereKey($command->cash_session_id)->update(['pending_offline_sale_count' => $count]);
    }

    /** @return array<string,mixed> */
    private function present(OfflineCommand $command, bool $replayed): array
    {
        return ['command_id' => $command->command_id, 'state' => $command->state, 'replayed' => $replayed, 'attempt_count' => $command->attempt_count, 'resource' => $command->resource_type === null ? null : ['type' => $command->resource_type, 'id' => $command->resource_public_id], 'conflict' => $command->conflict_code === null ? null : ['code' => $command->conflict_code, 'message' => $command->safe_message]];
    }

    /** @return array<string,mixed> */
    private function unrecordedConflict(string $commandId, string $code, string $message): array
    {
        return ['command_id' => $commandId, 'state' => 'conflict', 'replayed' => false, 'attempt_count' => 0, 'resource' => null, 'conflict' => ['code' => $code, 'message' => $message]];
    }

    private function conflictCode(int $status): string
    {
        return match ($status) {
            403 => 'authorization_expired',
            404 => 'record_not_found',
            409 => 'state_conflict',
            default => 'validation_failed',
        };
    }

    private function safeMessage(int $status, string|false $content): string
    {
        $decoded = is_string($content) ? json_decode($content, true) : null;

        return is_array($decoded) && is_string($decoded['message'] ?? null) ? $decoded['message'] : "The offline command was rejected ({$status}).";
    }

    /** @param array<string, mixed> $staffEvidence */
    private function authorized(MerchantMembership $membership, array $staffEvidence, Permission $permission): bool
    {
        $cached = $staffEvidence['permissions'] ?? [];

        return is_array($cached) && in_array($permission->value, $cached, true) && $this->permissions->allows($membership->role, $permission);
    }
}
