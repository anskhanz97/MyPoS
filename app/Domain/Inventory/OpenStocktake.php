<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\StocktakeState;
use App\Models\BatchStockBalance;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OpenStocktake
{
    public function __construct(private readonly InventoryScope $scope) {}

    public function handle(Merchant $merchant, Store $store, InventoryLocation $location, User $actor, ?string $notes): Stocktake
    {
        $this->scope->assertLocation($merchant, $store, $location);

        return DB::transaction(function () use ($merchant, $store, $location, $actor, $notes): Stocktake {
            // Lock aggregate projections first, matching the movement writer's lock order.
            // This makes the later movement ID a consistent baseline for every captured dimension.
            $allBalances = StockBalance::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('inventory_location_id', $location->id)
                ->with('variant.product')->orderBy('id')->lockForUpdate()->get();
            $batches = BatchStockBalance::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('inventory_location_id', $location->id)->where('quantity_base', '!=', 0)
                ->orderBy('id')->lockForUpdate()->get();
            $baselineMovementId = (int) StockMovement::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('inventory_location_id', $location->id)->max('id');
            $stocktake = Stocktake::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $store->id, 'inventory_location_id' => $location->id, 'opened_by_user_id' => $actor->id,
                'number' => 'CNT-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'state' => StocktakeState::Counting,
                'baseline_movement_id' => $baselineMovementId, 'opened_at' => now(), 'notes' => $notes,
            ]);
            $aggregate = $allBalances->filter(fn (StockBalance $balance): bool => ! $balance->variant->product->batch_tracking_enabled);
            foreach ($aggregate as $balance) {
                StocktakeLine::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'stocktake_id' => $stocktake->id,
                    'product_variant_id' => $balance->product_variant_id, 'baseline_quantity_base' => $balance->quantity_base,
                ]);
            }
            foreach ($batches as $balance) {
                StocktakeLine::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $store->id, 'stocktake_id' => $stocktake->id,
                    'product_variant_id' => $balance->product_variant_id, 'product_batch_id' => $balance->product_batch_id,
                    'baseline_quantity_base' => $balance->quantity_base,
                ]);
            }
            abort_if($stocktake->lines()->count() === 0, 409, 'No stock dimensions exist at this location to count.');
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.stocktake.opened', 'outcome' => 'succeeded', 'metadata' => ['stocktake_id' => $stocktake->public_id, 'baseline_movement_id' => $baselineMovementId], 'occurred_at' => now()]);

            return $stocktake->load(['location', 'lines.variant.product', 'lines.batch']);
        });
    }
}
