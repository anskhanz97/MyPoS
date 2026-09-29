<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Purchasing\CancelPurchaseOrder;
use App\Domain\Purchasing\CreatePurchaseOrder;
use App\Domain\Purchasing\IssuePurchaseOrder;
use App\Domain\Purchasing\PurchaseOrderPdf;
use App\Domain\Purchasing\UpdatePurchaseOrder;
use App\Domain\Shared\Math\Decimal;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPurchaseOrderRequest;
use App\Http\Requests\IssuePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Merchant;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $data = $request->validate(['state' => ['nullable', 'in:draft,issued,partially_received,received,cancelled'], 'supplier_id' => ['nullable', 'uuid'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = PurchaseOrder::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->with($this->relations());
        $query->when(! empty($data['state']), fn ($stateQuery) => $stateQuery->where('state', $data['state']));
        if (! empty($data['supplier_id'])) {
            $supplier = (new Supplier)->resolveRouteBinding((string) $data['supplier_id']);
            abort_unless($supplier instanceof Supplier && $supplier->merchant_id === $merchant->id, 422, 'The supplier filter is invalid.');
            $query->where('supplier_id', $supplier->id);
        }
        $paginator = $query->orderByDesc('id')->paginate((int) ($data['page_size'] ?? 25))->withQueryString();

        return response()->json(['data' => $paginator->getCollection()->map(fn (PurchaseOrder $order): array => $this->serialize($order)), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }

    public function store(StorePurchaseOrderRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CreatePurchaseOrder $creator): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $data = $request->validated();
        $supplier = (new Supplier)->resolveRouteBinding((string) $data['supplier_id']);
        abort_unless($supplier instanceof Supplier, 422, 'The supplier is invalid.');
        $order = $creator->handle($merchant, $store, $supplier, $user, (string) $data['order_date'], $data['expected_date'] ?? null, $data['source_reference'] ?? null, $data['notes'] ?? null, $data['lines']);

        return response()->json(['data' => $this->serialize($order)], 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($purchaseOrder, $merchant, $store);

        return response()->json(['data' => $this->serialize($purchaseOrder)]);
    }

    public function update(UpdatePurchaseOrderRequest $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer, UpdatePurchaseOrder $updater): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($purchaseOrder, $merchant, $store);
        $data = $request->validated();
        $supplier = (new Supplier)->resolveRouteBinding((string) $data['supplier_id']);
        abort_unless($supplier instanceof Supplier, 422, 'The supplier is invalid.');
        $order = $updater->handle($purchaseOrder, $merchant, $store, $supplier, $user, (int) $data['expected_version'], (string) $data['order_date'], $data['expected_date'] ?? null, $data['source_reference'] ?? null, $data['notes'] ?? null, $data['amendment_reason'] ?? null, $data['lines']);

        return response()->json(['data' => $this->serialize($order)]);
    }

    public function issue(IssuePurchaseOrderRequest $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer, IssuePurchaseOrder $issuer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($purchaseOrder, $merchant, $store);
        $data = $request->validated();
        $result = $issuer->handle($purchaseOrder, $user, (string) $data['idempotency_key'], (int) $data['expected_version']);

        return response()->json(['data' => $this->serialize($result['order']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function cancel(CancelPurchaseOrderRequest $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer, CancelPurchaseOrder $canceller): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($purchaseOrder, $merchant, $store);
        $data = $request->validated();

        return response()->json(['data' => $this->serialize($canceller->handle($purchaseOrder, $user, (int) $data['expected_version'], (string) $data['reason']))]);
    }

    public function pdf(Request $request, Merchant $merchant, Store $store, PurchaseOrder $purchaseOrder, RoleAuthorizer $authorizer, PurchaseOrderPdf $pdf): Response
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($purchaseOrder, $merchant, $store);

        return response($pdf->render($purchaseOrder), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$purchaseOrder->number.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function assertScope(PurchaseOrder $order, Merchant $merchant, Store $store): void
    {
        abort_unless($order->merchant_id === $merchant->id && $order->store_id === $store->id, 404);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['supplier', 'store.merchant', 'lines.variant.product', 'lines.purchaseUnit', 'history'];
    }

    /** @return array<string, mixed> */
    private function serialize(PurchaseOrder $order): array
    {
        $order->loadMissing($this->relations());
        $total = BigDecimal::zero();
        $lines = $order->lines->map(function ($line) use (&$total): array {
            $lineTotal = BigDecimal::of($line->ordered_quantity_uom)->multipliedBy($line->expected_unit_cost);
            $total = $total->plus($lineTotal);
            $remaining = BigDecimal::of($line->ordered_quantity_base)->minus($line->received_quantity_base)->minus($line->cancelled_quantity_base);

            return [
                'id' => $line->public_id, 'variant_id' => $line->variant->public_id, 'product_name' => $line->product_name_snapshot, 'sku' => $line->sku_snapshot,
                'purchase_unit_id' => $line->purchaseUnit->public_id, 'unit_symbol' => $line->unit_symbol_snapshot,
                'ordered_quantity_uom' => $line->ordered_quantity_uom, 'factor_to_base' => $line->factor_to_base, 'ordered_quantity_base' => $line->ordered_quantity_base,
                'received_quantity_base' => $line->received_quantity_base, 'returned_quantity_base' => $line->returned_quantity_base,
                'cancelled_quantity_base' => $line->cancelled_quantity_base, 'remaining_quantity_base' => Decimal::fixed($remaining->isPositive() ? $remaining : BigDecimal::zero(), 6),
                'expected_unit_cost' => $line->expected_unit_cost, 'expected_line_total' => Decimal::fixed($lineTotal, 6), 'tax_reference' => $line->tax_reference,
            ];
        });

        return [
            'id' => $order->public_id, 'number' => $order->number, 'state' => $order->state->value, 'version' => $order->version,
            'store' => ['id' => $order->store->public_id, 'code' => $order->store->code, 'name' => $order->store->name],
            'supplier' => ['id' => $order->supplier->public_id, 'code' => $order->supplier->code, 'name' => $order->supplier->name, 'archived_at' => $order->supplier->archived_at?->toAtomString()],
            'order_date' => $order->order_date->toDateString(), 'expected_date' => $order->expected_date?->toDateString(), 'source_reference' => $order->source_reference,
            'notes' => $order->notes, 'issued_at' => $order->issued_at?->toAtomString(), 'cancelled_at' => $order->cancelled_at?->toAtomString(), 'cancellation_reason' => $order->cancellation_reason,
            'currency' => 'PKR', 'expected_total' => Decimal::fixed($total, 6), 'supplier_balance_state' => 'operational_ledger_available',
            'lines' => $lines->all(),
            'history' => $order->history->map(fn ($entry): array => ['id' => $entry->public_id, 'action' => $entry->action, 'from_state' => $entry->from_state, 'to_state' => $entry->to_state, 'version' => $entry->version, 'reason' => $entry->reason, 'occurred_at' => $entry->occurred_at->toAtomString()])->all(),
        ];
    }
}
