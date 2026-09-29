<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Purchasing\PostPurchaseReceipt;
use App\Domain\Purchasing\ReversePurchaseReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostPurchaseReceiptRequest;
use App\Http\Requests\ReversePurchaseReceiptRequest;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReceiptController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::PurchasingManage, $store);
        $this->assertOrderScope($purchaseOrder, $merchant, $store);
        $receipts = PurchaseReceipt::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('purchase_order_id', $purchaseOrder->id)->with($this->relations())->orderByDesc('id')->get();

        return response()->json(['data' => $receipts->map(fn (PurchaseReceipt $receipt): array => $this->serialize($receipt))]);
    }

    public function store(PostPurchaseReceiptRequest $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer, PostPurchaseReceipt $poster): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertOrderScope($purchaseOrder, $merchant, $store);
        $data = $request->validated();
        $location = (new InventoryLocation)->resolveRouteBinding((string) $data['location_id']);
        abort_unless($location instanceof InventoryLocation, 422, 'The inventory location is invalid.');
        $result = $poster->handle($merchant, $store, $purchaseOrder, $location, $user, (string) $data['idempotency_key'], (int) $data['expected_version'], (string) $data['received_at'], $data['supplier_reference'] ?? null, $data['notes'] ?? null, $data['lines']);

        return response()->json(['data' => $this->serialize($result['receipt']), 'purchase_order' => ['id' => $result['order']->public_id, 'state' => $result['order']->state->value, 'version' => $result['order']->version], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, PurchaseReceipt $purchaseReceipt, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::PurchasingManage, $store);
        $this->assertReceiptScope($purchaseReceipt, $merchant, $store);

        return response()->json(['data' => $this->serialize($purchaseReceipt)]);
    }

    public function reverse(ReversePurchaseReceiptRequest $request, Merchant $merchant, Store $store, PurchaseReceipt $purchaseReceipt, RoleAuthorizer $authorizer, ReversePurchaseReceipt $reverser): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertReceiptScope($purchaseReceipt, $merchant, $store);
        $result = $reverser->handle($purchaseReceipt, $user, (string) $request->validated('reason'));

        return response()->json(['data' => $this->serialize($result['receipt']), 'purchase_order' => ['id' => $result['order']->public_id, 'state' => $result['order']->state->value, 'version' => $result['order']->version], 'replayed' => $result['replayed']]);
    }

    /** @return array<string, mixed> */
    private function serialize(PurchaseReceipt $receipt): array
    {
        $receipt->loadMissing($this->relations());

        return [
            'id' => $receipt->public_id, 'number' => $receipt->number, 'status' => $receipt->status->value,
            'purchase_order' => ['id' => $receipt->purchaseOrder->public_id, 'number' => $receipt->purchaseOrder->number],
            'location' => ['id' => $receipt->location->public_id, 'code' => $receipt->location->code, 'name' => $receipt->location->name],
            'supplier_reference' => $receipt->supplier_reference, 'notes' => $receipt->notes, 'currency' => 'PKR',
            'received_at' => $receipt->received_at->toAtomString(), 'posted_at' => $receipt->posted_at->toAtomString(), 'valuation_timing' => $receipt->valuation_timing,
            'merchandise_total' => $receipt->merchandise_total, 'landed_cost_total' => $receipt->landed_cost_total, 'recoverable_tax_total' => $receipt->recoverable_tax_total, 'supplier_charge_total' => $receipt->supplier_charge_total,
            'reversed_at' => $receipt->reversed_at?->toAtomString(), 'reversal_reason' => $receipt->reversal_reason,
            'lines' => $receipt->lines->map(fn ($line): array => [
                'id' => $line->public_id, 'purchase_order_line_id' => $line->purchaseOrderLine->public_id, 'product_name' => $line->product_name_snapshot, 'sku' => $line->sku_snapshot,
                'quantity_uom' => $line->received_quantity_uom, 'unit_symbol' => $line->unit_symbol_snapshot, 'factor_to_base' => $line->factor_to_base, 'quantity_base' => $line->received_quantity_base,
                'unit_cost_uom' => $line->unit_cost_uom, 'unit_cost_base' => $line->unit_cost_base, 'landed_cost_total' => $line->landed_cost_total,
                'recoverable_tax_total' => $line->recoverable_tax_total, 'inventory_unit_cost_base' => $line->inventory_unit_cost_base, 'inventory_value' => $line->inventory_value,
                'batch' => $line->batch === null ? null : ['id' => $line->batch->public_id, 'code' => $line->batch->code, 'expiry_date' => $line->batch->expiry_date->toDateString()],
                'stock_movement_id' => $line->stockMovement->public_id,
                'valuation' => ['entry_id' => $line->costEntry->public_id, 'state' => $line->costEntry->cost_state->value, 'resulting_average' => $line->costEntry->resulting_average, 'resulting_quantity' => $line->costEntry->resulting_quantity, 'resolved_negative_quantity' => $line->costEntry->resolved_negative_quantity, 'cost_adjustment_value' => $line->costEntry->cost_adjustment_value],
            ])->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function assertOrderScope(PurchaseOrder $order, Merchant $merchant, Store $store): void
    {
        abort_unless($order->merchant_id === $merchant->id && $order->store_id === $store->id, 404);
    }

    private function assertReceiptScope(PurchaseReceipt $receipt, Merchant $merchant, Store $store): void
    {
        abort_unless($receipt->merchant_id === $merchant->id && $receipt->store_id === $store->id, 404);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['purchaseOrder', 'location', 'lines.purchaseOrderLine', 'lines.batch', 'lines.stockMovement', 'lines.costEntry'];
    }
}
