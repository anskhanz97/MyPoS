<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Http\Controllers\Controller;
use App\Models\InventoryCostBalance;
use App\Models\InventoryCostEntry;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryValuationController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::InventoryView, $store);
        $data = $request->validate(['variant_id' => ['nullable', 'uuid'], 'pending_only' => ['nullable', 'boolean']]);
        $query = InventoryCostBalance::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->with('variant.product');
        if (! empty($data['variant_id'])) {
            $variant = (new ProductVariant)->resolveRouteBinding((string) $data['variant_id']);
            abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id, 422, 'The variant filter is invalid.');
            $query->where('product_variant_id', $variant->id);
        }
        if (($data['pending_only'] ?? false) === true) {
            $query->whereIn('cost_state', ['pending', 'provisional']);
        }
        $balances = $query->orderBy('product_variant_id')->get();

        return response()->json(['data' => $balances->map(function (InventoryCostBalance $balance): array {
            $entries = InventoryCostEntry::query()->where('inventory_cost_balance_id', $balance->id)->orderByDesc('id')->limit(50)->get();

            return [
                'id' => $balance->public_id, 'variant_id' => $balance->variant->public_id, 'product_name' => $balance->variant->product->name, 'sku' => $balance->variant->sku,
                'quantity_base' => $balance->quantity_base, 'average_unit_cost' => $balance->average_unit_cost, 'inventory_value' => $balance->inventory_value,
                'cost_state' => $balance->cost_state->value, 'pending_negative_quantity_base' => $balance->pending_negative_quantity_base, 'projection_version' => $balance->projection_version,
                'entries' => $entries->map(fn (InventoryCostEntry $entry): array => ['id' => $entry->public_id, 'kind' => $entry->entry_kind->value, 'quantity_delta' => $entry->quantity_delta, 'unit_cost' => $entry->unit_cost, 'value_delta' => $entry->value_delta, 'resulting_quantity' => $entry->resulting_quantity, 'resulting_average' => $entry->resulting_average, 'cost_state' => $entry->cost_state->value, 'resolved_negative_quantity' => $entry->resolved_negative_quantity, 'cost_adjustment_value' => $entry->cost_adjustment_value, 'valuation_timing' => $entry->valuation_timing, 'effective_at' => $entry->effective_at->toAtomString(), 'applied_at' => $entry->applied_at->toAtomString()])->all(),
            ];
        })]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
