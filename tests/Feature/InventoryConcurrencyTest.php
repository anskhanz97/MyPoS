<?php

namespace Tests\Feature;

use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Inventory\PostStockMovement;
use App\Domain\Inventory\StockMovementCommand;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_parallel_movement_processes_preserve_every_effect_in_one_balance(): void
    {
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id]);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'store_id' => null]);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'store_id' => null, 'product_id' => $product->id, 'base_unit_id' => $unit->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable', 'is_default' => true]);
        $locationId = $location->public_id;
        $variantId = $variant->public_id;

        $tasks = [];
        foreach (range(1, 6) as $sequence) {
            $tasks[] = static function () use ($locationId, $variantId, $sequence): string {
                $resolvedLocation = (new InventoryLocation)->resolveRouteBinding($locationId);
                $resolvedVariant = (new ProductVariant)->resolveRouteBinding($variantId);
                if (! $resolvedLocation instanceof InventoryLocation || ! $resolvedVariant instanceof ProductVariant) {
                    throw new \RuntimeException('Inventory fixture could not be resolved.');
                }
                $result = app(PostStockMovement::class)->handle(new StockMovementCommand(
                    $resolvedLocation,
                    $resolvedVariant,
                    InventoryMovementKind::Adjustment,
                    '1.000000',
                    'concurrency_test',
                    'MOVE-'.$sequence,
                ));

                return $result['movement']->public_id;
            };
        }

        $results = Concurrency::run($tasks, timeout: 30);
        $this->assertCount(6, array_unique($results));
        $this->assertSame(6, StockMovement::query()->count());
        $balance = StockBalance::query()->sole();
        $this->assertSame('6.000000', $balance->quantity_base);
        $this->assertSame(6, $balance->movement_count);
        $this->assertSame(6, $balance->projection_version);
    }
}
