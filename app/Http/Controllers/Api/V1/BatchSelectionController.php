<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\InventoryScope;
use App\Domain\Inventory\SelectSellableBatches;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBatchSelectionRequest;
use App\Models\BatchSelectionSnapshot;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BatchSelectionController extends Controller
{
    public function store(CreateBatchSelectionRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, InventoryScope $scope, SelectSellableBatches $selector): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $data = $request->validated();
        $location = (new InventoryLocation)->resolveRouteBinding((string) $data['location_id']);
        $variant = (new ProductVariant)->resolveRouteBinding((string) $data['variant_id']);
        abort_unless($location instanceof InventoryLocation && $variant instanceof ProductVariant, 422, 'A batch selection reference is invalid.');
        $scope->assertLocation($merchant, $store, $location);
        $scope->assertVariant($merchant, $store, $variant);
        $snapshot = $selector->handle($store, $location, $variant, (string) $data['quantity'], (string) $data['strategy'], $data['manual_allocations'] ?? [], $user);

        return response()->json(['data' => $this->serialize($snapshot)], 201);
    }

    /** @return array<string, mixed> */
    private function serialize(BatchSelectionSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->public_id,
            'strategy' => $snapshot->strategy,
            'requested_quantity_base' => $snapshot->requested_quantity_base,
            'business_date' => $snapshot->business_date->toDateString(),
            'content_hash' => $snapshot->content_hash,
            'expires_at' => $snapshot->expires_at->toAtomString(),
            'reserved' => false,
            'allocations' => $snapshot->lines->map(fn ($line): array => [
                'batch_id' => $line->batch->public_id,
                'batch_code' => $line->batch->code,
                'quantity_base' => $line->quantity_base,
                'expiry_date' => $line->expiry_date_snapshot->toDateString(),
            ])->all(),
        ];
    }
}
