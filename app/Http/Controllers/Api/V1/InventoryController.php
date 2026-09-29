<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function balances(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'location_id' => ['nullable', 'uuid'],
            'disposition' => ['nullable', 'in:sellable,unavailable,quarantine'],
            'negative_only' => ['nullable', 'boolean'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = StockBalance::query()
            ->where('merchant_id', $merchant->id)->where('store_id', $store->id)
            ->with(['location', 'variant.product', 'variant.baseUnit', 'lastMovement']);
        if (! empty($validated['location_id'])) {
            $location = (new InventoryLocation)->resolveRouteBinding((string) $validated['location_id']);
            abort_unless($location instanceof InventoryLocation && $location->merchant_id === $merchant->id && $location->store_id === $store->id, 422, 'The inventory location is outside this store.');
            $query->where('inventory_location_id', $location->id);
        }
        if (! empty($validated['disposition'])) {
            $query->whereHas('location', fn ($locationQuery) => $locationQuery->where('disposition', $validated['disposition']));
        }
        if ($validated['negative_only'] ?? false) {
            $query->where('quantity_base', '<', 0);
        }
        if (! empty($validated['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower((string) $validated['q'])).'%';
            $query->whereHas('variant', fn ($variantQuery) => $variantQuery->whereRaw('LOWER(sku) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term])->orWhereHas('product', fn ($productQuery) => $productQuery->whereRaw('LOWER(name) LIKE ?', [$term])));
        }
        $paginator = $query->orderBy('product_variant_id')->paginate((int) ($validated['page_size'] ?? 25))->withQueryString();
        $items = $paginator->getCollection()->map(fn (StockBalance $balance): array => [
            'id' => $balance->public_id,
            'location' => ['id' => $balance->location->public_id, 'code' => $balance->location->code, 'name' => $balance->location->name, 'disposition' => $balance->location->disposition->value],
            'variant' => ['id' => $balance->variant->public_id, 'sku' => $balance->variant->sku, 'name' => $balance->variant->name, 'product_name' => $balance->variant->product->name, 'base_unit' => $balance->variant->baseUnit->symbol],
            'quantity_base' => $balance->quantity_base,
            'is_negative' => BigDecimal::of($balance->quantity_base)->isNegative(),
            'movement_count' => $balance->movement_count,
            'projection_version' => $balance->projection_version,
            'last_movement_at' => $balance->lastMovement?->occurred_at->toAtomString(),
            'last_reconciled_at' => $balance->last_reconciled_at?->toAtomString(),
        ]);

        return response()->json(['data' => $items, 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'freshness_checked_at' => now()->toAtomString()]]);
    }

    public function movements(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $validated = $request->validate([
            'variant_id' => ['nullable', 'uuid'], 'location_id' => ['nullable', 'uuid'], 'source_type' => ['nullable', 'string', 'max:80'],
            'source_reference' => ['nullable', 'string', 'max:120'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = StockMovement::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->with(['location', 'variant.product', 'variant.baseUnit', 'batch', 'adjustmentReason', 'reversedMovement']);
        if (! empty($validated['variant_id'])) {
            $variant = (new ProductVariant)->resolveRouteBinding((string) $validated['variant_id']);
            abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id, 422, 'The product variant is outside this merchant.');
            $query->where('product_variant_id', $variant->id);
        }
        if (! empty($validated['location_id'])) {
            $location = (new InventoryLocation)->resolveRouteBinding((string) $validated['location_id']);
            abort_unless($location instanceof InventoryLocation && $location->merchant_id === $merchant->id && $location->store_id === $store->id, 422, 'The inventory location is outside this store.');
            $query->where('inventory_location_id', $location->id);
        }
        $query->when(! empty($validated['source_type']), fn ($sourceQuery) => $sourceQuery->where('source_type', $validated['source_type']))
            ->when(! empty($validated['source_reference']), fn ($sourceQuery) => $sourceQuery->where('source_reference', $validated['source_reference']));
        $paginator = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate((int) ($validated['page_size'] ?? 25))->withQueryString();

        return response()->json(['data' => StockMovementResource::collection($paginator->getCollection())->resolve($request), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }
}
