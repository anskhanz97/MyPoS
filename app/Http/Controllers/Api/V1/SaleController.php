<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Sales\FinalizeSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinalizeSaleRequest;
use App\Models\FiscalDocument;
use App\Models\Merchant;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineExtra;
use App\Models\SaleTender;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function store(FinalizeSaleRequest $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer, FinalizeSale $finalize): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        $this->scope($merchant, $store, $register);
        $data = $request->validated();
        $idempotency = (string) $data['idempotency_key'];
        unset($data['idempotency_key']);
        $result = $finalize->handle($merchant, $store, $register, $user, $idempotency, $data);

        return response()->json(['data' => $this->present($result['sale']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Register $register, Sale $sale, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleView, $store);
        $this->saleScope($merchant, $store, $register, $sale);

        return response()->json(['data' => $this->present($sale)]);
    }

    public function byClientTransaction(Request $request, Merchant $merchant, Store $store, Register $register, string $clientTransactionId, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleView, $store);
        $this->scope($merchant, $store, $register);
        abort_unless(preg_match('/^[0-9a-fA-F-]{36}$/', $clientTransactionId) === 1, 404);
        $sale = Sale::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('register_id', $register->id)->where('client_transaction_id', $clientTransactionId)->firstOrFail();

        return response()->json(['data' => $this->present($sale)]);
    }

    /** @return array<string,mixed> */
    private function present(Sale $sale): array
    {
        $sale->loadMissing(['lines.extras', 'tenders', 'receipt', 'register']);
        $fiscal = FiscalDocument::query()->where('merchant_id', $sale->merchant_id)->where('local_document_type', 'sale')->where('local_document_reference', $sale->public_id)->latest()->first();

        return ['id' => $sale->public_id, 'number' => $sale->number, 'state' => $sale->state, 'source' => $sale->source, 'client_transaction_id' => $sale->client_transaction_id, 'register_id' => $sale->register->public_id, 'business_date' => $sale->business_date->toDateString(), 'currency' => 'PKR', 'pricing_version' => $sale->pricing_version, 'totals' => ['subtotal' => $sale->subtotal, 'discount' => $sale->discount_total, 'net' => $sale->net_total, 'tax' => $sale->tax_total, 'gross' => $sale->total, 'cash_change' => $sale->cash_change], 'customer' => $sale->customer_snapshot, 'lines' => $sale->lines->map(fn (SaleLine $line) => ['id' => $line->public_id, 'description' => $line->product_name_snapshot, 'sku' => $line->sku_snapshot, 'quantity' => $line->quantity_uom, 'unit' => $line->unit_symbol_snapshot, 'unit_price' => $line->unit_price, 'discount' => $line->discount, 'net' => $line->net, 'tax' => $line->tax, 'gross' => $line->gross, 'allocations' => $line->allocations, 'extras' => $line->extras->map(fn (SaleLineExtra $extra) => ['name' => $extra->option_name_snapshot, 'price_delta' => $extra->unit_price_delta])->all()])->all(), 'tenders' => $sale->tenders->map(fn (SaleTender $tender) => ['id' => $tender->public_id, 'client_tender_id' => $tender->client_tender_id, 'type' => $tender->type, 'state' => $tender->state, 'amount' => $tender->amount, 'external_reference' => $tender->external_reference, 'confirmation_source' => $tender->confirmation_source])->all(), 'receipt' => ['id' => $sale->receipt->public_id, 'version' => $sale->receipt->version], 'fiscal_state' => $fiscal instanceof FiscalDocument ? $fiscal->state : 'not_required', 'fiscal_reference' => $fiscal instanceof FiscalDocument ? $fiscal->authority_reference : null, 'printing_state' => 'not_requested'];
    }

    private function scope(Merchant $merchant, Store $store, Register $register): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
    }

    private function saleScope(Merchant $merchant, Store $store, Register $register, Sale $sale): void
    {
        $this->scope($merchant, $store, $register);
        abort_unless($sale->merchant_id === $merchant->id && $sale->store_id === $store->id && $sale->register_id === $register->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
