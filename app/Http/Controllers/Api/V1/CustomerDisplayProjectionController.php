<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\CustomerDisplay\CustomerDisplayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublishCustomerDisplayProjectionRequest;
use App\Models\Cart;
use App\Models\CustomerDisplayPairing;
use App\Models\CustomerDisplaySnapshot;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDisplayProjectionController extends Controller
{
    public function store(PublishCustomerDisplayProjectionRequest $request, Merchant $merchant, Store $store, Register $register, CustomerDisplayPairing $pairing, RoleAuthorizer $authorizer, CustomerDisplayService $service): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id && $pairing->merchant_id === $merchant->id && $pairing->store_id === $store->id && $pairing->register_id === $register->id, 404);
        $data = $request->validated();
        $cart = isset($data['cart_id']) ? (new Cart)->resolveRouteBinding((string) $data['cart_id']) : null;
        $sale = isset($data['sale_id']) ? (new Sale)->resolveRouteBinding((string) $data['sale_id']) : null;
        $snapshot = $service->publish($pairing, (string) $data['state'], $cart instanceof Cart ? $cart : null, $sale instanceof Sale ? $sale : null);

        return response()->json(['data' => $this->present($snapshot)]);
    }

    public function show(Request $request, CustomerDisplayPairing $pairing, CustomerDisplayService $service): JsonResponse
    {
        $token = (string) $request->header('X-Display-Token');
        abort_if($token === '', 401, 'The display credential is required.');

        return response()->json(['data' => $this->present($service->read($pairing, $token), $pairing)]);
    }

    /** @return array<string, mixed> */
    private function present(CustomerDisplaySnapshot $snapshot, ?CustomerDisplayPairing $pairing = null): array
    {
        $owner = $pairing ?? CustomerDisplayPairing::query()->findOrFail($snapshot->customer_display_pairing_id);

        return ['pairing_id' => $owner->public_id, 'sequence' => $snapshot->sequence, 'state' => $snapshot->state, 'payload' => $snapshot->payload, 'clear_at' => $snapshot->clear_at?->toAtomString(), 'published_at' => $snapshot->published_at->toAtomString()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
