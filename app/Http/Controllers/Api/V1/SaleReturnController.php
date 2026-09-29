<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Sales\ConfirmExternalReturnRefund;
use App\Domain\Sales\PostSaleExchange;
use App\Domain\Sales\PostSaleReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmSaleReturnTenderRequest;
use App\Http\Requests\StoreSaleExchangeRequest;
use App\Http\Requests\StoreSaleReturnRequest;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleExchange;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\SaleReturnTender;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, Register $register, Sale $sale, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleView, $store);
        $this->scope($merchant, $store, $register, $sale);
        $returns = SaleReturn::query()->where('sale_id', $sale->id)->latest('id')->get()->map(fn (SaleReturn $return) => $this->present($return));

        return response()->json(['data' => $returns]);
    }

    public function store(StoreSaleReturnRequest $request, Merchant $merchant, Store $store, Register $register, Sale $sale, RoleAuthorizer $authorizer, PostSaleReturn $post): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::ReturnCreate, $store);
        $this->scope($merchant, $store, $register, $sale);
        $data = $request->validated();
        $key = (string) $data['idempotency_key'];
        unset($data['idempotency_key']);
        $result = $post->handle($merchant, $store, $register, $sale, $user, $key, $data, $request->header('X-Approval-Token'));

        return response()->json(['data' => $this->present($result['return']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Register $register, SaleReturn $saleReturn, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleView, $store);
        $this->returnScope($merchant, $store, $register, $saleReturn);

        return response()->json(['data' => $this->present($saleReturn)]);
    }

    public function confirm(ConfirmSaleReturnTenderRequest $request, Merchant $merchant, Store $store, Register $register, SaleReturn $saleReturn, SaleReturnTender $tender, RoleAuthorizer $authorizer, ConfirmExternalReturnRefund $confirm): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::ReturnCreate, $store);
        $this->returnScope($merchant, $store, $register, $saleReturn);
        abort_unless($tender->sale_return_id === $saleReturn->id, 404);
        $data = $request->validated();
        $result = $confirm->handle($saleReturn, $tender, $user, $data['external_reference'], $data['confirmation_source']);

        return response()->json(['data' => $this->present($result['return']), 'replayed' => $result['replayed']]);
    }

    public function exchange(StoreSaleExchangeRequest $request, Merchant $merchant, Store $store, Register $register, Sale $sale, RoleAuthorizer $authorizer, PostSaleExchange $post): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::ReturnCreate, $store);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->scope($merchant, $store, $register, $sale);
        $data = $request->validated();
        $key = (string) $data['idempotency_key'];
        unset($data['idempotency_key']);
        $result = $post->handle($merchant, $store, $register, $sale, $user, $key, $data, $request->header('X-Approval-Token'));

        return response()->json(['data' => $this->presentExchange($result['exchange']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    /** @return array<string,mixed> */
    private function present(SaleReturn $return): array
    {
        $return->loadMissing(['sale', 'register', 'lines.saleLine', 'tenders', 'exchange.newSale']);

        return ['id' => $return->public_id, 'number' => $return->number, 'state' => $return->state, 'original_sale' => ['id' => $return->sale->public_id, 'number' => $return->sale->number], 'register_id' => $return->register->public_id, 'business_date' => $return->business_date->toDateString(), 'reason' => $return->reason, 'late_exception' => $return->late_exception, 'late_reason' => $return->late_reason, 'policy_window_days' => $return->policy_window_days, 'totals' => ['subtotal' => $return->subtotal, 'discount' => $return->discount_total, 'net' => $return->net_total, 'tax' => $return->tax_total, 'gross' => $return->total], 'lines' => $return->lines->map(fn (SaleReturnLine $line) => ['id' => $line->public_id, 'sale_line_id' => $line->saleLine->public_id, 'description' => $line->saleLine->product_name_snapshot, 'sku' => $line->saleLine->sku_snapshot, 'quantity' => $line->quantity_uom, 'cumulative_quantity' => $line->cumulative_quantity_uom, 'disposition' => $line->disposition, 'discount' => $line->discount, 'net' => $line->net, 'tax' => $line->tax, 'gross' => $line->gross])->all(), 'refund_tenders' => $return->tenders->map(fn (SaleReturnTender $tender) => ['id' => $tender->public_id, 'type' => $tender->type, 'state' => $tender->state, 'amount' => $tender->amount, 'external_reference' => $tender->external_reference, 'confirmation_source' => $tender->confirmation_source])->all(), 'receipt' => $return->receipt_payload, 'exchange' => $return->exchange === null ? null : ['id' => $return->exchange->public_id, 'number' => $return->exchange->number, 'new_sale_id' => $return->exchange->newSale->public_id]];
    }

    /** @return array<string,mixed> */
    private function presentExchange(SaleExchange $exchange): array
    {
        $exchange->loadMissing(['saleReturn', 'newSale']);

        return ['id' => $exchange->public_id, 'number' => $exchange->number, 'return' => $this->present($exchange->saleReturn), 'new_sale' => ['id' => $exchange->newSale->public_id, 'number' => $exchange->newSale->number, 'total' => $exchange->newSale->total], 'difference' => ['direction' => $exchange->difference_direction, 'amount' => $exchange->difference_amount], 'posted_at' => $exchange->posted_at->toAtomString()];
    }

    private function scope(Merchant $merchant, Store $store, Register $register, Sale $sale): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id && $sale->merchant_id === $merchant->id && $sale->store_id === $store->id, 404);
    }

    private function returnScope(Merchant $merchant, Store $store, Register $register, SaleReturn $return): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id && $return->merchant_id === $merchant->id && $return->store_id === $store->id && $return->register_id === $register->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
