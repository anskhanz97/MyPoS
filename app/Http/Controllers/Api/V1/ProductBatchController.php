<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\BatchExpiryPolicy;
use App\Domain\Inventory\InventoryScope;
use App\Domain\Shared\Math\Decimal;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductBatchRequest;
use App\Models\BatchStockBalance;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductBatchController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, InventoryScope $scope, BatchExpiryPolicy $expiryPolicy): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $data = $request->validate([
            'variant_id' => ['required', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:available,expiring_soon,expired,all'],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $variant = (new ProductVariant)->resolveRouteBinding((string) $data['variant_id']);
        abort_unless($variant instanceof ProductVariant, 422, 'The product variant is invalid.');
        $scope->assertVariant($merchant, $store, $variant);
        abort_unless($variant->product->batch_tracking_enabled, 409, 'Batch tracking is not enabled for this product.');
        $businessDate = $expiryPolicy->businessDate($store);
        $warningDays = (int) ($data['expiring_within_days'] ?? 30);
        $warningDate = $businessDate->addDays($warningDays);
        $location = null;
        if (! empty($data['location_id'])) {
            $resolvedLocation = (new InventoryLocation)->resolveRouteBinding((string) $data['location_id']);
            abort_unless($resolvedLocation instanceof InventoryLocation && $resolvedLocation->merchant_id === $merchant->id && $resolvedLocation->store_id === $store->id, 422, 'The inventory location is outside this store.');
            $location = $resolvedLocation;
        }
        $query = ProductBatch::query()->where('merchant_id', $merchant->id)->where('product_variant_id', $variant->id)->whereNull('archived_at')
            ->withSum(['balances as store_quantity' => fn ($balance) => $balance->where('store_id', $store->id)->when($location instanceof InventoryLocation, fn ($locationQuery) => $locationQuery->where('inventory_location_id', $location->id))], 'quantity_base');
        match ($data['status'] ?? 'all') {
            'available' => $query->whereDate('expiry_date', '>', $warningDate->toDateString()),
            'expiring_soon' => $query->whereDate('expiry_date', '>=', $businessDate->toDateString())->whereDate('expiry_date', '<=', $warningDate->toDateString()),
            'expired' => $query->whereDate('expiry_date', '<', $businessDate->toDateString()),
            default => null,
        };
        $paginator = $query->orderBy('expiry_date')->orderBy('code')->paginate((int) ($data['page_size'] ?? 25))->withQueryString();
        $items = $paginator->getCollection()->map(fn (ProductBatch $batch): array => $this->serialize($batch, $store, $expiryPolicy, $warningDays));

        return response()->json(['data' => $items, 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'business_date' => $businessDate->toDateString(), 'expiry_boundary' => 'sellable_through_expiry_date']]);
    }

    public function store(StoreProductBatchRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, InventoryScope $scope, BatchExpiryPolicy $expiryPolicy): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $data = $request->validated();
        $variant = (new ProductVariant)->resolveRouteBinding((string) $data['variant_id']);
        abort_unless($variant instanceof ProductVariant, 422, 'The product variant is invalid.');
        $scope->assertVariant($merchant, $store, $variant);
        abort_unless($variant->product->batch_tracking_enabled, 409, 'Enable batch tracking through the controlled allocation workflow first.');
        $code = trim((string) $data['code']);
        $existing = ProductBatch::query()->where('merchant_id', $merchant->id)->where('product_variant_id', $variant->id)->where('code', $code)->first();
        if ($existing instanceof ProductBatch) {
            if ($existing->expiry_date->toDateString() !== $data['expiry_date']) {
                throw new ConflictHttpException('This product batch code already exists with a different expiry date.');
            }

            return response()->json(['data' => $this->serialize($existing, $store, $expiryPolicy, 30), 'replayed' => true]);
        }
        $batch = ProductBatch::query()->create([
            'merchant_id' => $merchant->id,
            'product_variant_id' => $variant->id,
            'origin_store_id' => $store->id,
            'code' => $code,
            'expiry_date' => $data['expiry_date'],
            'received_on' => $data['received_on'] ?? null,
            'supplier_reference' => $data['supplier_reference'] ?? null,
        ]);
        SecurityAuditEvent::query()->create([
            'merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id,
            'event_code' => 'inventory.batch.created', 'outcome' => 'succeeded',
            'metadata' => ['batch_id' => $batch->public_id, 'variant_id' => $variant->public_id, 'code' => $batch->code, 'expiry_date' => $batch->expiry_date->toDateString()], 'occurred_at' => now(),
        ]);

        return response()->json(['data' => $this->serialize($batch, $store, $expiryPolicy, 30), 'replayed' => false], 201);
    }

    /** @return array<string, mixed> */
    private function serialize(ProductBatch $batch, Store $store, BatchExpiryPolicy $expiryPolicy, int $warningDays): array
    {
        $days = $expiryPolicy->daysUntilExpiry($batch, $store);
        $quantity = $batch->getAttribute('store_quantity');
        if ($quantity === null) {
            $quantity = BatchStockBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->where('product_batch_id', $batch->id)->sum('quantity_base');
        }

        return [
            'id' => $batch->public_id, 'code' => $batch->code, 'expiry_date' => $batch->expiry_date->toDateString(),
            'received_on' => $batch->received_on?->toDateString(), 'supplier_reference' => $batch->supplier_reference,
            'quantity_base' => Decimal::fixed(BigDecimal::of((string) $quantity), 6), 'days_until_expiry' => $days,
            'status' => $days < 0 ? 'expired' : ($days <= $warningDays ? 'expiring_soon' : 'available'),
        ];
    }
}
