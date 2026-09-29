<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Actions\ConsumeApproval;
use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\BaseQuantityConverter;
use App\Domain\Inventory\Enums\AdjustmentDirection;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\InventoryScope;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreInventoryAdjustmentRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, InventoryScope $scope, BaseQuantityConverter $converter, PostStockMovement $post, ConsumeApproval $approvals): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $data = $request->validated();
        $location = (new InventoryLocation)->resolveRouteBinding((string) $data['location_id']);
        $variant = (new ProductVariant)->resolveRouteBinding((string) $data['variant_id']);
        $batch = null;
        if (isset($data['batch_id'])) {
            $resolvedBatch = (new ProductBatch)->resolveRouteBinding((string) $data['batch_id']);
            abort_unless($resolvedBatch instanceof ProductBatch, 422, 'The product batch is invalid.');
            $batch = $resolvedBatch;
        }
        $unit = (new UnitOfMeasure)->resolveRouteBinding((string) $data['unit_id']);
        $reason = (new InventoryAdjustmentReason)->resolveRouteBinding((string) $data['reason_id']);
        abort_unless($location instanceof InventoryLocation && $variant instanceof ProductVariant && $unit instanceof UnitOfMeasure && $reason instanceof InventoryAdjustmentReason, 422, 'An inventory adjustment reference is invalid.');
        $scope->assertLocation($merchant, $store, $location);
        $scope->assertVariant($merchant, $store, $variant);
        abort_unless($reason->merchant_id === $merchant->id && $reason->is_active, 422, 'The adjustment reason is unavailable.');
        $quantity = $converter->convert($variant, $unit, (string) $data['quantity']);
        $isIncrease = BigDecimal::of($quantity)->isPositive();
        if (($reason->direction === AdjustmentDirection::Increase && ! $isIncrease) || ($reason->direction === AdjustmentDirection::Decrease && $isIncrease)) {
            throw ValidationException::withMessages(['quantity' => 'The quantity direction does not match the selected adjustment reason.']);
        }
        $beforeCreate = $reason->requires_approval
            ? fn () => $approvals->handle((string) $request->header('X-Approval-Token', ''), $merchant->id, $store->id, Permission::InventoryAdjustApprove, 'inventory_adjustment', (string) $data['idempotency_key'])
            : null;
        $result = $post->handle(new StockMovementCommand(
            location: $location,
            variant: $variant,
            batch: $batch,
            kind: InventoryMovementKind::Adjustment,
            quantityBase: $quantity,
            sourceType: 'manual_adjustment',
            sourceReference: (string) $data['idempotency_key'],
            actor: $user,
            adjustmentReason: $reason,
            reasonText: (string) $data['reason_text'],
        ), $beforeCreate);
        if (! $result['replayed']) {
            SecurityAuditEvent::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id,
                'event_code' => 'inventory.adjustment.posted', 'outcome' => 'succeeded',
                'metadata' => ['movement_id' => $result['movement']->public_id, 'variant_id' => $variant->public_id, 'location_id' => $location->public_id, 'reason_code' => $reason->code, 'quantity_base' => $quantity, 'negative_balance' => BigDecimal::of($result['balance']->quantity_base)->isNegative()], 'occurred_at' => now(),
            ]);
        }

        return response()->json(['data' => [
            'movement' => (new StockMovementResource($result['movement']))->resolve($request),
            'balance' => ['quantity_base' => $result['balance']->quantity_base, 'is_negative' => BigDecimal::of($result['balance']->quantity_base)->isNegative(), 'projection_version' => $result['balance']->projection_version],
            'replayed' => $result['replayed'],
        ]], $result['replayed'] ? 200 : 201);
    }
}
