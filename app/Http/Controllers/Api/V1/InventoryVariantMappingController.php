<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Shared\Math\Decimal;
use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryVariantMappingRequest;
use App\Models\InventoryVariantMapping;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;

class InventoryVariantMappingController extends Controller
{
    public function store(StoreInventoryVariantMappingRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        abort_unless($merchant->store_data_mode === StoreDataMode::Independent, 409, 'Shared catalogs do not need variant mappings.');
        $data = $request->validated();
        $destination = (new Store)->resolveRouteBinding((string) $data['destination_store_id']);
        $sourceVariant = (new ProductVariant)->resolveRouteBinding((string) $data['source_variant_id']);
        $destinationVariant = (new ProductVariant)->resolveRouteBinding((string) $data['destination_variant_id']);
        abort_unless($destination instanceof Store && $destination->merchant_id === $merchant->id && $destination->id !== $store->id, 422, 'The destination store is invalid.');
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $destination);
        abort_unless($sourceVariant instanceof ProductVariant && $destinationVariant instanceof ProductVariant && $sourceVariant->merchant_id === $merchant->id && $sourceVariant->store_id === $store->id && $destinationVariant->merchant_id === $merchant->id && $destinationVariant->store_id === $destination->id, 422, 'Mapped variants must belong to their declared independent stores.');
        abort_unless($sourceVariant->product->batch_tracking_enabled === $destinationVariant->product->batch_tracking_enabled, 409, 'Mapped variants must use compatible batch tracking.');
        $mapping = InventoryVariantMapping::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'source_store_id' => $store->id, 'destination_store_id' => $destination->id, 'source_product_variant_id' => $sourceVariant->id],
            ['destination_product_variant_id' => $destinationVariant->id, 'factor_to_destination_base' => Decimal::fixed(BigDecimal::of((string) $data['factor_to_destination_base']), 6), 'is_active' => true],
        );
        SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id, 'event_code' => 'inventory.transfer_mapping.saved', 'outcome' => 'succeeded', 'metadata' => ['mapping_id' => $mapping->public_id, 'destination_store_id' => $destination->public_id], 'occurred_at' => now()]);

        return response()->json(['data' => ['id' => $mapping->public_id, 'source_variant_id' => $sourceVariant->public_id, 'destination_variant_id' => $destinationVariant->public_id, 'factor_to_destination_base' => $mapping->factor_to_destination_base]], $mapping->wasRecentlyCreated ? 201 : 200);
    }
}
