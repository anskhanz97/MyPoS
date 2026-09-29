<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Checkout\CartWorkflow;
use App\Domain\Checkout\Enums\CartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CartVersionRequest;
use App\Http\Requests\CreateCartRequest;
use App\Http\Requests\ReplaceCartRequest;
use App\Http\Requests\ScanCartRequest;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\CartLineExtra;
use App\Models\CartQuote;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleCreate, $store);
        $this->scope($merchant, $store, $register);
        $carts = Cart::query()->with(['register', 'location', 'customer', 'lines.extras'])->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('register_id', $register->id)->whereIn('status', [CartStatus::Active, CartStatus::Suspended])->latest('updated_at')->limit(100)->get();

        return response()->json(['data' => $carts->map($this->present(...))]);
    }

    public function store(CreateCartRequest $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer, CartWorkflow $workflow): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->scope($merchant, $store, $register);
        $location = (new InventoryLocation)->resolveRouteBinding((string) $request->validated('inventory_location_id'));
        abort_unless($location instanceof InventoryLocation, 422, 'Inventory location was not found.');

        return response()->json(['data' => $this->present($workflow->create($merchant, $store, $register, $location, $user))], 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleCreate, $store);
        $this->cartScope($merchant, $store, $register, $cart);

        return response()->json(['data' => $this->present($cart->load(['register', 'location', 'customer', 'lines.extras', 'quotes']))]);
    }

    public function replace(ReplaceCartRequest $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, CartWorkflow $workflow): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->cartScope($merchant, $store, $register, $cart);
        $data = $request->validated();
        $hasDiscount = ((float) ($data['manual_cart_discount'] ?? 0)) > 0;
        $lines = $data['lines'] ?? [];
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if (is_array($line) && ((float) ($line['manual_discount'] ?? 0)) > 0) {
                    $hasDiscount = true;
                    break;
                }
            }
        }
        if ($hasDiscount) {
            $authorizer->authorize($user, $merchant, Permission::DiscountOverride, $store);
        }

        return response()->json(['data' => $this->present($workflow->replace($cart, $user, $data))]);
    }

    public function scan(ScanCartRequest $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, CartWorkflow $workflow): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->cartScope($merchant, $store, $register, $cart);
        $data = $request->validated();

        return response()->json(['data' => $this->present($workflow->scan($merchant, $store, $cart, $user, (int) $data['expected_version'], (string) $data['code']))]);
    }

    public function suspend(CartVersionRequest $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, CartWorkflow $workflow): JsonResponse
    {
        return $this->transition($request, $merchant, $store, $register, $cart, $authorizer, $workflow, CartStatus::Suspended);
    }

    public function resume(CartVersionRequest $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, CartWorkflow $workflow): JsonResponse
    {
        return $this->transition($request, $merchant, $store, $register, $cart, $authorizer, $workflow, CartStatus::Active);
    }

    public function quote(CartVersionRequest $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, CartWorkflow $workflow): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->cartScope($merchant, $store, $register, $cart);
        $result = $workflow->quote($merchant, $store, $cart, $user, (int) $request->validated('expected_version'));

        return response()->json(['data' => $this->present($result['cart']), 'quote' => $this->quoteData($result['quote'])], 201);
    }

    private function transition(CartVersionRequest $request, Merchant $merchant, Store $store, Register $register, Cart $cart, RoleAuthorizer $authorizer, CartWorkflow $workflow, CartStatus $status): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->cartScope($merchant, $store, $register, $cart);

        return response()->json(['data' => $this->present($workflow->transition($cart, $user, (int) $request->validated('expected_version'), $status))]);
    }

    /** @return array<string,mixed> */
    private function present(Cart $cart): array
    {
        $cart->loadMissing(['register', 'location', 'customer', 'lines.variant', 'lines.unit', 'lines.batchSelection', 'lines.extras.option']);

        return ['id' => $cart->public_id, 'reference' => $cart->reference, 'state' => $cart->status->value, 'version' => $cart->version, 'pricing_version' => $cart->pricing_version, 'register_id' => $cart->register->public_id, 'inventory_location_id' => $cart->location->public_id, 'customer' => $cart->customer === null ? null : ['id' => $cart->customer->public_id, 'name' => $cart->customer->name], 'coupon_codes' => $cart->coupon_codes ?? [], 'manual_cart_discount' => $cart->manual_cart_discount, 'suspended_at' => $cart->suspended_at?->toAtomString(), 'lines' => $cart->lines->map(fn (CartLine $line): array => ['id' => $line->public_id, 'sequence' => $line->sequence, 'variant_id' => $line->variant->public_id, 'product_name' => $line->product_name_snapshot, 'variant_name' => $line->variant_name_snapshot, 'sku' => $line->sku_snapshot, 'unit_id' => $line->unit->public_id, 'unit_symbol' => $line->unit_symbol_snapshot, 'quantity' => $line->quantity_uom, 'quantity_base' => $line->quantity_base, 'manual_discount' => $line->manual_discount, 'scan_count' => $line->scan_count, 'batch_selection_id' => $line->batchSelection?->public_id, 'extras' => $line->extras->map(fn (CartLineExtra $extra): array => ['id' => $extra->option->public_id, 'group' => $extra->group_name_snapshot, 'name' => $extra->option_name_snapshot, 'price_delta' => $extra->unit_price_delta])->all()])->all()];
    }

    /** @return array<string,mixed> */
    private function quoteData(CartQuote $quote): array
    {
        return ['id' => $quote->public_id, 'quote_version' => $quote->quote_version, 'cart_version' => $quote->cart_version, 'pricing_version' => $quote->pricing_version, 'expires_at' => $quote->expires_at->toAtomString(), 'result' => $quote->payload];
    }

    private function scope(Merchant $merchant, Store $store, Register $register): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
    }

    private function cartScope(Merchant $merchant, Store $store, Register $register, Cart $cart): void
    {
        $this->scope($merchant, $store, $register);
        abort_unless($cart->merchant_id === $merchant->id && $cart->store_id === $store->id && $cart->register_id === $register->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
