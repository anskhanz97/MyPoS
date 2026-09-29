<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryDisposition;
use App\Domain\Shared\Math\Decimal;
use App\Models\BatchSelectionLine;
use App\Models\BatchSelectionSnapshot;
use App\Models\BatchStockBalance;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SelectSellableBatches
{
    public function __construct(private readonly BatchExpiryPolicy $expiryPolicy) {}

    /**
     * @param  list<array{batch_id: string, quantity: string}>  $manualAllocations
     */
    public function handle(Store $store, InventoryLocation $location, ProductVariant $variant, string $quantity, string $strategy, array $manualAllocations, User $actor): BatchSelectionSnapshot
    {
        abort_unless($location->store_id === $store->id && $location->merchant_id === $store->merchant_id, 422, 'The inventory location is outside this store.');
        abort_unless($location->disposition === InventoryDisposition::Sellable, 409, 'Batch sale selection requires a sellable location.');
        abort_unless($variant->merchant_id === $store->merchant_id && $variant->product->batch_tracking_enabled, 409, 'Batch tracking is not enabled for this product.');
        $requested = Decimal::fixed(BigDecimal::of($quantity), 6);
        $businessDate = $this->expiryPolicy->businessDate($store);

        return DB::transaction(function () use ($store, $location, $variant, $requested, $strategy, $manualAllocations, $actor, $businessDate): BatchSelectionSnapshot {
            $available = BatchStockBalance::query()
                ->where('merchant_id', $store->merchant_id)
                ->where('store_id', $store->id)
                ->where('inventory_location_id', $location->id)
                ->where('product_variant_id', $variant->id)
                ->where('quantity_base', '>', 0)
                ->whereHas('batch', fn ($query) => $query->whereNull('archived_at')->whereDate('expiry_date', '>=', $businessDate->toDateString()))
                ->with('batch')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (BatchStockBalance $balance): string => $balance->batch->public_id);

            $allocations = [];
            if ($strategy === 'manual') {
                $remaining = BigDecimal::of($requested);
                foreach ($manualAllocations as $input) {
                    $balance = $available->get($input['batch_id']);
                    abort_unless($balance instanceof BatchStockBalance, 409, 'A manually selected batch is unavailable, expired or outside this store/product.');
                    $selected = BigDecimal::of(Decimal::fixed(BigDecimal::of($input['quantity']), 6));
                    if ($selected->isGreaterThan(BigDecimal::of($balance->quantity_base))) {
                        throw new ConflictHttpException('A manually selected batch no longer has the requested quantity.');
                    }
                    $allocations[] = ['balance' => $balance, 'quantity' => Decimal::fixed($selected, 6)];
                    $remaining = $remaining->minus($selected);
                }
                if (! $remaining->isZero()) {
                    throw new ConflictHttpException('Manual batch quantities must equal the requested quantity exactly.');
                }
            } else {
                $remaining = BigDecimal::of($requested);
                $ordered = $available->sortBy(fn (BatchStockBalance $balance): string => $balance->batch->expiry_date->format('Y-m-d').'|'.$balance->batch->code.'|'.str_pad((string) $balance->batch->id, 20, '0', STR_PAD_LEFT));
                foreach ($ordered as $balance) {
                    if ($remaining->isZero()) {
                        break;
                    }
                    $selected = BigDecimal::of($balance->quantity_base)->isLessThan($remaining) ? BigDecimal::of($balance->quantity_base) : $remaining;
                    $allocations[] = ['balance' => $balance, 'quantity' => Decimal::fixed($selected, 6)];
                    $remaining = $remaining->minus($selected);
                }
                if (! $remaining->isZero()) {
                    throw new ConflictHttpException('Non-expired batch stock does not cover the requested quantity; unallocated shortage is blocked.');
                }
            }

            $content = array_map(fn (array $allocation): array => [
                'batch_id' => $allocation['balance']->batch->public_id,
                'quantity' => $allocation['quantity'],
                'expiry_date' => $allocation['balance']->batch->expiry_date->toDateString(),
                'balance_version' => $allocation['balance']->projection_version,
            ], $allocations);
            $snapshot = BatchSelectionSnapshot::query()->create([
                'merchant_id' => $store->merchant_id,
                'store_id' => $store->id,
                'inventory_location_id' => $location->id,
                'product_variant_id' => $variant->id,
                'actor_user_id' => $actor->id,
                'strategy' => $strategy,
                'requested_quantity_base' => $requested,
                'business_date' => $businessDate,
                'content_hash' => hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)),
                'expires_at' => now()->addMinutes(15),
            ]);
            foreach ($allocations as $sequence => $allocation) {
                BatchSelectionLine::query()->create([
                    'merchant_id' => $store->merchant_id,
                    'store_id' => $store->id,
                    'batch_selection_snapshot_id' => $snapshot->id,
                    'product_batch_id' => $allocation['balance']->product_batch_id,
                    'quantity_base' => $allocation['quantity'],
                    'expiry_date_snapshot' => $allocation['balance']->batch->expiry_date,
                    'sequence' => $sequence + 1,
                ]);
            }

            return $snapshot->load('lines.batch');
        });
    }
}
