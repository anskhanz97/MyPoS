<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Purchasing\Enums\SupplierCreditStatus;
use App\Domain\Purchasing\PostPurchaseReturn;
use App\Domain\Purchasing\ReversePurchaseReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostPurchaseReturnRequest;
use App\Http\Requests\ReversePurchaseReceiptRequest;
use App\Models\Merchant;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, PurchaseReceipt $purchaseReceipt, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::PurchasingManage, $store);
        $this->assertReceiptScope($purchaseReceipt, $merchant, $store);
        $returns = PurchaseReturn::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('purchase_receipt_id', $purchaseReceipt->id)->with($this->relations())->orderByDesc('id')->get();

        return response()->json(['data' => $returns->map(fn (PurchaseReturn $return): array => $this->serialize($return))]);
    }

    public function store(PostPurchaseReturnRequest $request, Merchant $merchant, Store $store, PurchaseReceipt $purchaseReceipt, RoleAuthorizer $authorizer, PostPurchaseReturn $poster): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertReceiptScope($purchaseReceipt, $merchant, $store);
        $data = $request->validated();
        $result = $poster->handle($merchant, $store, $purchaseReceipt, $user, (string) $data['idempotency_key'], (string) $data['returned_at'], (string) $data['reason'], SupplierCreditStatus::from((string) $data['credit_status']), $data['supplier_credit_reference'] ?? null, $data['lines']);

        return response()->json(['data' => $this->serialize($result['return']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, PurchaseReturn $purchaseReturn, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::PurchasingManage, $store);
        $this->assertReturnScope($purchaseReturn, $merchant, $store);

        return response()->json(['data' => $this->serialize($purchaseReturn)]);
    }

    public function reverse(ReversePurchaseReceiptRequest $request, Merchant $merchant, Store $store, PurchaseReturn $purchaseReturn, RoleAuthorizer $authorizer, ReversePurchaseReturn $reverser): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertReturnScope($purchaseReturn, $merchant, $store);
        $result = $reverser->handle($purchaseReturn, $user, (string) $request->validated('reason'));

        return response()->json(['data' => $this->serialize($result['return']), 'replayed' => $result['replayed']]);
    }

    /** @return array<string, mixed> */
    private function serialize(PurchaseReturn $return): array
    {
        $return->loadMissing($this->relations());

        return [
            'id' => $return->public_id, 'number' => $return->number, 'status' => $return->status->value, 'reason' => $return->reason,
            'receipt' => ['id' => $return->receipt->public_id, 'number' => $return->receipt->number], 'supplier' => ['id' => $return->supplier->public_id, 'name' => $return->supplier->name],
            'location' => ['id' => $return->location->public_id, 'code' => $return->location->code], 'returned_at' => $return->returned_at->toAtomString(),
            'credit' => ['id' => $return->credit?->public_id, 'status' => $return->credit_status->value, 'amount' => $return->supplier_credit_total, 'supplier_reference' => $return->supplier_credit_reference],
            'reversed_at' => $return->reversed_at?->toAtomString(), 'reversal_reason' => $return->reversal_reason,
            'lines' => $return->lines->map(fn ($line): array => ['id' => $line->public_id, 'purchase_receipt_line_id' => $line->receiptLine->public_id, 'product_name' => $line->product_name_snapshot, 'sku' => $line->sku_snapshot, 'quantity_uom' => $line->returned_quantity_uom, 'unit_symbol' => $line->unit_symbol_snapshot, 'quantity_base' => $line->returned_quantity_base, 'supplier_credit_amount' => $line->supplier_credit_amount, 'valuation_unit_cost' => $line->valuation_unit_cost, 'batch' => $line->batch === null ? null : ['id' => $line->batch->public_id, 'code' => $line->batch->code], 'stock_movement_id' => $line->stockMovement->public_id, 'cost_entry_id' => $line->costEntry->public_id])->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function assertReceiptScope(PurchaseReceipt $receipt, Merchant $merchant, Store $store): void
    {
        abort_unless($receipt->merchant_id === $merchant->id && $receipt->store_id === $store->id, 404);
    }

    private function assertReturnScope(PurchaseReturn $return, Merchant $merchant, Store $store): void
    {
        abort_unless($return->merchant_id === $merchant->id && $return->store_id === $store->id, 404);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['receipt', 'location', 'supplier', 'credit', 'lines.receiptLine', 'lines.batch', 'lines.stockMovement', 'lines.costEntry'];
    }
}
