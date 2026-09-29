<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Devices\DeviceCredentialResolver;
use App\Domain\Offline\CanonicalPayload;
use App\Domain\Printing\CreateDrawerCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDrawerCommandRequest;
use App\Models\CashSession;
use App\Models\DeviceEnrollment;
use App\Models\DrawerCommand;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DrawerCommandController extends Controller
{
    public function store(CreateDrawerCommandRequest $request, Merchant $merchant, Store $store, Register $register, CashSession $cashSession, RoleAuthorizer $authorizer, CreateDrawerCommand $creator): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validated();
        $permission = match ($data['kind']) {
            'paid_out' => Permission::CashManage, 'no_sale_open', 'diagnostic' => Permission::DrawerNoSale, default => Permission::CashOperate
        };
        $authorizer->authorize($user, $merchant, $permission, $store);
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id && $cashSession->merchant_id === $merchant->id && $cashSession->store_id === $store->id && $cashSession->register_id === $register->id, 404);
        $result = $creator->handle($cashSession, $register, $user, (string) $data['idempotency_key'], (string) $data['kind'], (string) $data['reason']);

        return response()->json(['data' => $this->present($result['command']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function outcome(Request $request, DrawerCommand $drawerCommand, DeviceCredentialResolver $credentials, CanonicalPayload $canonical): JsonResponse
    {
        $data = $request->validate(['device_id' => ['required', 'uuid'], 'helper_event_id' => ['required', 'uuid'], 'outcome' => ['required', 'in:opened_reported,failed,outcome_unknown'], 'reported_at' => ['required', 'date']]);
        $device = (new DeviceEnrollment)->resolveRouteBinding((string) $data['device_id']);
        abort_unless($device instanceof DeviceEnrollment, 401);
        $credentials->resolve($device, $request->header('X-Device-Key'), true);
        abort_unless($device->merchant_id === $drawerCommand->merchant_id && $device->store_id === $drawerCommand->store_id && $device->register_id === $drawerCommand->register_id, 404);
        $outcomeHash = $canonical->hash($data);
        $replayed = DB::transaction(function () use ($drawerCommand, $data, $outcomeHash): bool {
            $locked = DrawerCommand::query()->lockForUpdate()->findOrFail($drawerCommand->id);
            if ($locked->helper_event_id !== null) {
                abort_unless($locked->helper_event_id === $data['helper_event_id'] && hash_equals((string) $locked->outcome_payload_hash, $outcomeHash), 409, 'The drawer outcome is already final.');

                return true;
            }
            abort_if($locked->expires_at->isPast() && $data['outcome'] === 'opened_reported', 409, 'The drawer authorization expired before helper acceptance.');
            $locked->forceFill(['helper_event_id' => $data['helper_event_id'], 'outcome_payload_hash' => $outcomeHash, 'state' => $data['outcome'], 'reported_at' => $data['reported_at']])->save();
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'event_type' => 'printing.drawer.outcome_recorded', 'aggregate_type' => 'drawer_command', 'aggregate_id' => $locked->public_id, 'payload' => ['drawer_command_id' => $locked->public_id, 'outcome' => $data['outcome']], 'occurred_at' => now(), 'available_at' => now()]);

            return false;
        });

        return response()->json(['data' => $this->present($drawerCommand->refresh()), 'replayed' => $replayed], $replayed ? 200 : 201);
    }

    /** @return array<string,mixed> */
    private function present(DrawerCommand $command): array
    {
        return ['id' => $command->public_id, 'kind' => $command->kind, 'state' => $command->state, 'reason' => $command->reason, 'signed_authorization' => $command->signed_authorization, 'expires_at' => $command->expires_at->toAtomString(), 'reported_at' => $command->reported_at?->toAtomString()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
