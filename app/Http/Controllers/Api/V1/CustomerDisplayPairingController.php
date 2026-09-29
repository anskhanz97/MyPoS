<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\CustomerDisplay\CustomerDisplayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumeCustomerDisplayPairingRequest;
use App\Http\Requests\CreateCustomerDisplayPairingRequest;
use App\Models\CustomerDisplayPairing;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDisplayPairingController extends Controller
{
    public function store(CreateCustomerDisplayPairingRequest $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer, CustomerDisplayService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->scope($merchant, $store, $register);
        $result = $service->issue($register, $user);

        return response()->json(['data' => ['pairing_id' => $result['pairing']->public_id, 'register_id' => $register->public_id, 'pairing_code' => $result['code'], 'channel_id' => $result['pairing']->channel_id, 'expires_at' => $result['pairing']->pairing_expires_at->toAtomString()]], 201);
    }

    public function consume(ConsumeCustomerDisplayPairingRequest $request, CustomerDisplayService $service): JsonResponse
    {
        $register = (new Register)->resolveRouteBinding((string) $request->validated('register_id'));
        abort_unless($register instanceof Register, 404);
        $result = $service->consume((string) $request->validated('pairing_code'), $register);

        return response()->json(['data' => ['pairing_id' => $result['pairing']->public_id, 'register_id' => $register->public_id, 'channel_id' => $result['pairing']->channel_id, 'display_token' => $result['token'], 'session_expires_at' => $result['pairing']->session_expires_at?->toAtomString()]], 201);
    }

    public function destroy(Request $request, Merchant $merchant, Store $store, Register $register, CustomerDisplayPairing $pairing, RoleAuthorizer $authorizer, CustomerDisplayService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->scope($merchant, $store, $register);
        abort_unless($pairing->merchant_id === $merchant->id && $pairing->store_id === $store->id && $pairing->register_id === $register->id, 404);
        $service->revoke($pairing, $user);

        return response()->json(['data' => ['id' => $pairing->public_id, 'status' => 'revoked']]);
    }

    private function scope(Merchant $merchant, Store $store, Register $register): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
