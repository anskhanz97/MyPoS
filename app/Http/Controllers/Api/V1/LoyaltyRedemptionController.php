<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Loyalty\LoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\LoyaltyRedemptionReservation;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyRedemptionController extends Controller
{
    public function store(Request $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, LoyaltyService $loyalty): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        abort_unless($store->merchant_id === $merchant->id && $register->store_id === $store->id && $cart->register_id === $register->id && $cart->merchant_id === $merchant->id, 404);
        $data = $request->validate(['reward_id' => ['required', 'uuid'], 'expected_cart_version' => ['required', 'integer', 'min:1']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) === 1, 422, 'A UUID Idempotency-Key header is required.');
        $result = $loyalty->reserve($merchant, $store, $cart, $user, $key, $data['reward_id'], $data['expected_cart_version']);

        return response()->json(['data' => $this->data($result['reservation']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function destroy(Request $request, Merchant $merchant, Store $store, Register $register, Cart $cart, LoyaltyRedemptionReservation $reservation, RoleAuthorizer $authorizer, LoyaltyService $loyalty): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        abort_unless($reservation->merchant_id === $merchant->id && $reservation->store_id === $store->id && $reservation->cart_id === $cart->id && $cart->register_id === $register->id, 404);
        $loyalty->releaseCart($cart);

        return response()->json(['data' => $this->data($reservation->refresh())]);
    }

    /** @return array<string,mixed> */
    private function data(LoyaltyRedemptionReservation $reservation): array
    {
        return ['id' => $reservation->public_id, 'state' => $reservation->state, 'points' => $reservation->points, 'discount_amount' => $reservation->discount_amount, 'expires_at' => $reservation->expires_at->toAtomString()];
    }
}
