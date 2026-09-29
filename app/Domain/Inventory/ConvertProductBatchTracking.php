<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\CatalogScope;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Domain\Shared\Math\Decimal;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ConvertProductBatchTracking
{
    public function __construct(private readonly CatalogScope $catalogScope, private readonly PostStockMovement $post) {}

    /**
     * @param  list<array{location_id: string, variant_id: string, batch_code: string, expiry_date: string, quantity: string}>  $allocations
     * @return array{product: Product, replayed: bool, movements_posted: int}
     */
    public function handle(Merchant $merchant, Store $contextStore, Product $product, User $actor, string $idempotencyKey, int $expectedVersion, array $allocations): array
    {
        abort_unless($product->merchant_id === $merchant->id && $product->store_id === $this->catalogScope->storeId($merchant, $contextStore), 404);
        $normalized = array_map(fn (array $row): array => [
            'location_id' => $row['location_id'], 'variant_id' => $row['variant_id'], 'batch_code' => trim($row['batch_code']),
            'expiry_date' => $row['expiry_date'], 'quantity' => Decimal::fixed(BigDecimal::of($row['quantity']), 6),
        ], $allocations);
        usort($normalized, fn (array $left, array $right): int => [$left['location_id'], $left['variant_id'], $left['batch_code'], $left['expiry_date']] <=> [$right['location_id'], $right['variant_id'], $right['batch_code'], $right['expiry_date']]);
        $payloadHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $product, $actor, $idempotencyKey, $expectedVersion, $normalized, $payloadHash): array {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($locked->batch_tracking_enabled) {
                if ($locked->batch_conversion_key === $idempotencyKey && hash_equals((string) $locked->batch_conversion_hash, $payloadHash)) {
                    return ['product' => $locked, 'replayed' => true, 'movements_posted' => 0];
                }
                throw new ConflictHttpException('Batch tracking is already enabled with different conversion evidence.');
            }
            if ($locked->structure_version !== $expectedVersion) {
                throw new ConflictHttpException('The product structure changed; reload before allocating batches.');
            }

            $variants = ProductVariant::query()->where('merchant_id', $merchant->id)->where('product_id', $locked->id)->get()->keyBy('public_id');
            $balances = StockBalance::query()->where('merchant_id', $merchant->id)->whereIn('product_variant_id', $variants->pluck('id'))
                ->where('quantity_base', '!=', 0)->with(['location.store', 'variant.product'])->lockForUpdate()->get()->keyBy(fn (StockBalance $balance): string => $balance->inventory_location_id.':'.$balance->product_variant_id);
            foreach ($balances as $balance) {
                if (BigDecimal::of($balance->quantity_base)->isNegative()) {
                    throw new ConflictHttpException('Resolve negative stock before enabling batch tracking.');
                }
            }

            /** @var array<string, array{location: InventoryLocation, variant: ProductVariant, total: BigDecimal, batches: array<string, array{code: string, expiry_date: string, quantity: BigDecimal}>}> $groups */
            $groups = [];
            foreach ($normalized as $row) {
                $location = (new InventoryLocation)->resolveRouteBinding($row['location_id']);
                $variant = $variants->get($row['variant_id']);
                abort_unless($location instanceof InventoryLocation && $variant instanceof ProductVariant && $location->merchant_id === $merchant->id, 422, 'A batch allocation reference is outside this product or merchant.');
                $dimension = $location->id.':'.$variant->id;
                $batchKey = mb_strtolower($row['batch_code']);
                $groups[$dimension] ??= ['location' => $location, 'variant' => $variant, 'total' => BigDecimal::zero(), 'batches' => []];
                if (isset($groups[$dimension]['batches'][$batchKey]) && $groups[$dimension]['batches'][$batchKey]['expiry_date'] !== $row['expiry_date']) {
                    throw new ConflictHttpException('One batch code cannot have two expiry dates for the same product variant.');
                }
                $groups[$dimension]['batches'][$batchKey] ??= ['code' => $row['batch_code'], 'expiry_date' => $row['expiry_date'], 'quantity' => BigDecimal::zero()];
                $groups[$dimension]['batches'][$batchKey]['quantity'] = $groups[$dimension]['batches'][$batchKey]['quantity']->plus($row['quantity']);
                $groups[$dimension]['total'] = $groups[$dimension]['total']->plus($row['quantity']);
            }

            if (count($groups) !== $balances->count()) {
                throw new ConflictHttpException('Allocate every non-zero store/location/variant balance exactly once.');
            }
            foreach ($groups as $dimension => $group) {
                $balance = $balances->get($dimension);
                if (! $balance instanceof StockBalance || ! BigDecimal::of($balance->quantity_base)->isEqualTo($group['total'])) {
                    throw new ConflictHttpException('Batch allocations must exactly equal each current stock balance.');
                }
            }

            $movements = 0;
            foreach ($groups as $dimension => $group) {
                $balance = $balances->get($dimension);
                abort_unless($balance instanceof StockBalance, 500);
                $this->post->handle(new StockMovementCommand(
                    location: $group['location'], variant: $group['variant'], kind: InventoryMovementKind::BatchAllocation,
                    quantityBase: Decimal::fixed(BigDecimal::of($balance->quantity_base)->negated(), 6), sourceType: 'batch_tracking_conversion',
                    sourceReference: $idempotencyKey, sourceLineReference: $dimension, sourceEffect: 'unallocated_release', actor: $actor,
                    reasonText: 'Convert existing stock into identified batches',
                ));
                $movements++;
                foreach ($group['batches'] as $batchInput) {
                    $batch = ProductBatch::query()->where('merchant_id', $merchant->id)->where('product_variant_id', $group['variant']->id)->where('code', $batchInput['code'])->first();
                    if ($batch instanceof ProductBatch && $batch->expiry_date->toDateString() !== $batchInput['expiry_date']) {
                        throw new ConflictHttpException('A batch code already exists with a different expiry date.');
                    }
                    $batch ??= ProductBatch::query()->create([
                        'merchant_id' => $merchant->id, 'product_variant_id' => $group['variant']->id, 'origin_store_id' => $group['location']->store_id,
                        'code' => $batchInput['code'], 'expiry_date' => $batchInput['expiry_date'],
                    ]);
                    $this->post->handle(new StockMovementCommand(
                        location: $group['location'], variant: $group['variant'], batch: $batch, kind: InventoryMovementKind::BatchAllocation,
                        quantityBase: Decimal::fixed($batchInput['quantity'], 6), sourceType: 'batch_tracking_conversion',
                        sourceReference: $idempotencyKey, sourceLineReference: $dimension, sourceEffect: 'batch:'.$batch->public_id, actor: $actor,
                        reasonText: 'Allocate existing stock to batch '.$batch->code,
                    ));
                    $movements++;
                }
            }

            $locked->update(['batch_tracking_enabled' => true, 'batch_conversion_key' => $idempotencyKey, 'batch_conversion_hash' => $payloadHash, 'structure_version' => $locked->structure_version + 1]);
            SecurityAuditEvent::query()->create([
                'merchant_id' => $merchant->id, 'actor_user_id' => $actor->id, 'event_code' => 'inventory.batch_tracking.enabled', 'outcome' => 'succeeded',
                'metadata' => ['product_id' => $locked->public_id, 'conversion_key' => $idempotencyKey, 'allocated_dimensions' => count($groups), 'movements_posted' => $movements], 'occurred_at' => now(),
            ]);

            return ['product' => $locked->refresh(), 'replayed' => false, 'movements_posted' => $movements];
        });
    }
}
