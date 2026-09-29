<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Math\Decimal;
use App\Models\BatchStockBalance;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Store;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

final class BatchBalanceRebuilder
{
    /** @return list<array<string, int|string>> */
    public function inspect(Store $store): array
    {
        $ledger = $this->ledgerRows($store);
        $balances = BatchStockBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->get()->keyBy(fn (BatchStockBalance $balance): string => $this->key($balance->inventory_location_id, $balance->product_variant_id, $balance->product_batch_id));
        $result = [];
        foreach (array_unique([...array_keys($ledger), ...$balances->keys()->all()]) as $dimension) {
            $expected = $ledger[$dimension] ?? ['quantity' => '0.000000', 'count' => 0, 'last_id' => null];
            $balance = $balances->get($dimension);
            $projected = $balance instanceof BatchStockBalance ? $balance->quantity_base : '0.000000';
            $projectedCount = $balance instanceof BatchStockBalance ? $balance->movement_count : 0;
            if ($projected === $expected['quantity'] && $projectedCount === $expected['count']) {
                continue;
            }
            [$locationId, $variantId, $batchId] = array_map('intval', explode(':', $dimension));
            $batch = ProductBatch::query()->findOrFail($batchId);
            $result[] = [
                'location_internal_id' => $locationId, 'variant_internal_id' => $variantId,
                'batch_id' => $batch->public_id, 'batch_code' => $batch->code,
                'projected_quantity' => $projected, 'ledger_quantity' => $expected['quantity'],
                'projected_count' => $projectedCount, 'ledger_count' => $expected['count'],
            ];
        }

        return $result;
    }

    /** @return array{batch_discrepancies_before: int, batch_repaired: int} */
    public function rebuild(Store $store): array
    {
        $before = $this->inspect($store);
        $repaired = DB::transaction(function () use ($store): int {
            $ledger = $this->ledgerRows($store);
            $balances = BatchStockBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->lockForUpdate()->get()->keyBy(fn (BatchStockBalance $balance): string => $this->key($balance->inventory_location_id, $balance->product_variant_id, $balance->product_batch_id));
            $repairs = 0;
            foreach (array_unique([...array_keys($ledger), ...$balances->keys()->all()]) as $dimension) {
                [$locationId, $variantId, $batchId] = array_map('intval', explode(':', $dimension));
                $expected = $ledger[$dimension] ?? ['quantity' => '0.000000', 'count' => 0, 'last_id' => null];
                $balance = $balances->get($dimension);
                if (! $balance instanceof BatchStockBalance) {
                    BatchStockBalance::query()->create([
                        'merchant_id' => $store->merchant_id, 'store_id' => $store->id, 'inventory_location_id' => $locationId,
                        'product_variant_id' => $variantId, 'product_batch_id' => $batchId, 'quantity_base' => $expected['quantity'],
                        'movement_count' => $expected['count'], 'last_stock_movement_id' => $expected['last_id'], 'projection_version' => 1, 'last_reconciled_at' => now(),
                    ]);
                    $repairs++;

                    continue;
                }
                $changed = $balance->quantity_base !== $expected['quantity'] || $balance->movement_count !== $expected['count'] || $balance->last_stock_movement_id !== $expected['last_id'];
                $balance->update([
                    'quantity_base' => $expected['quantity'], 'movement_count' => $expected['count'], 'last_stock_movement_id' => $expected['last_id'],
                    'projection_version' => $balance->projection_version + ($changed ? 1 : 0), 'last_reconciled_at' => now(),
                ]);
                if ($changed) {
                    $repairs++;
                }
            }

            return $repairs;
        });

        return ['batch_discrepancies_before' => count($before), 'batch_repaired' => $repaired];
    }

    /** @return array<string, array{quantity: string, count: int, last_id: int|null}> */
    private function ledgerRows(Store $store): array
    {
        $rows = StockMovement::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->whereNotNull('product_batch_id')
            ->selectRaw('inventory_location_id, product_variant_id, product_batch_id, SUM(quantity_base) as ledger_quantity, COUNT(*) as ledger_count, MAX(id) as last_id')
            ->groupBy('inventory_location_id', 'product_variant_id', 'product_batch_id')->get();
        $result = [];
        foreach ($rows as $row) {
            $key = $this->key((int) $row->getAttribute('inventory_location_id'), (int) $row->getAttribute('product_variant_id'), (int) $row->getAttribute('product_batch_id'));
            $result[$key] = ['quantity' => Decimal::fixed(BigDecimal::of((string) $row->getAttribute('ledger_quantity')), 6), 'count' => (int) $row->getAttribute('ledger_count'), 'last_id' => $row->getAttribute('last_id') === null ? null : (int) $row->getAttribute('last_id')];
        }

        return $result;
    }

    private function key(int $locationId, int $variantId, int $batchId): string
    {
        return $locationId.':'.$variantId.':'.$batchId;
    }
}
