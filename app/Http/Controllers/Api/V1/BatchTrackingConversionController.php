<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\ConvertProductBatchTracking;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertProductToBatchTrackingRequest;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BatchTrackingConversionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ConvertProductToBatchTrackingRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, ConvertProductBatchTracking $converter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $data = $request->validated();
        $product = (new Product)->resolveRouteBinding((string) $data['product_id']);
        abort_unless($product instanceof Product, 422, 'The product is invalid.');
        $authorizedStores = [$store->id => true];
        foreach ($data['allocations'] as $allocation) {
            $location = (new InventoryLocation)->resolveRouteBinding((string) $allocation['location_id']);
            abort_unless($location instanceof InventoryLocation && $location->merchant_id === $merchant->id, 422, 'A batch allocation location is invalid.');
            if (! isset($authorizedStores[$location->store_id])) {
                $allocationStore = Store::query()->where('merchant_id', $merchant->id)->findOrFail($location->store_id);
                $authorizer->authorize($user, $merchant, Permission::InventoryManage, $allocationStore);
                $authorizedStores[$location->store_id] = true;
            }
        }
        $result = $converter->handle($merchant, $store, $product, $user, (string) $data['idempotency_key'], (int) $data['expected_structure_version'], $data['allocations']);

        return response()->json(['data' => [
            'product_id' => $result['product']->public_id,
            'batch_tracking_enabled' => $result['product']->batch_tracking_enabled,
            'structure_version' => $result['product']->structure_version,
            'movements_posted' => $result['movements_posted'],
            'replayed' => $result['replayed'],
        ]], $result['replayed'] ? 200 : 201);
    }
}
