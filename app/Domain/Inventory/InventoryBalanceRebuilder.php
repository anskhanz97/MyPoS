<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Math\Decimal;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

final class InventoryBalanceRebuilder
{
    /**
     * @return list<array{location_id: string, location_code: string, variant_id: string, sku: string, projected_quantity: string, ledger_quantity: string, projected_count: int, ledger_count: int, discrepancy: string}>
     */
    public function inspect(Store $store): array
    {
        $ledger = $this->ledgerRows($store);
        $balances = StockBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->get()->keyBy(fn (StockBalance $balance): string => $this->key($balance->inventory_location_id, $balance->product_variant_id));
        $dimensions = array_unique([...array_keys($ledger), ...$balances->keys()->all()]);
        $locations = InventoryLocation::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->get()->keyBy('id');
        $variants = ProductVariant::query()->where('merchant_id', $store->merchant_id)->whereIn('id', array_map(fn (string $key): int => (int) explode(':', $key)[1], $dimensions))->get()->keyBy('id');
        $result = [];
        foreach ($dimensions as $dimension) {
            [$locationId, $variantId] = array_map('intval', explode(':', $dimension));
            $expected = $ledger[$dimension] ?? ['quantity' => '0.000000', 'count' => 0, 'last_id' => null];
            $balance = $balances->get($dimension);
            $projected = $balance instanceof StockBalance ? $balance->quantity_base : '0.000000';
            $projectedCount = $balance instanceof StockBalance ? $balance->movement_count : 0;
            $difference = Decimal::fixed(BigDecimal::of($projected)->minus($expected['quantity']), 6);
            if ($difference === '0.000000' && $projectedCount === $expected['count']) {
                continue;
            }
            $location = $locations->get($locationId);
            $variant = $variants->get($variantId);
            if (! $location instanceof InventoryLocation || ! $variant instanceof ProductVariant) {
                continue;
            }
            $result[] = [
                'location_id' => $location->public_id,
                'location_code' => $location->code,
                'variant_id' => $variant->public_id,
                'sku' => $variant->sku,
                'projected_quantity' => $projected,
                'ledger_quantity' => $expected['quantity'],
                'projected_count' => $projectedCount,
                'ledger_count' => $expected['count'],
                'discrepancy' => $difference,
            ];
        }

        return $result;
    }

    /** @return array{discrepancies_before: int, repaired: int} */
    public function rebuild(Store $store): array
    {
        $before = $this->inspect($store);
        $repaired = DB::transaction(function () use ($store): int {
            $ledger = $this->ledgerRows($store);
            $balances = StockBalance::query()->where('merchant_id', $store->merchant_id)->where('store_id', $store->id)->lockForUpdate()->get()->keyBy(fn (StockBalance $balance): string => $this->key($balance->inventory_location_id, $balance->product_variant_id));
            $dimensions = array_unique([...array_keys($ledger), ...$balances->keys()->all()]);
            $repairs = 0;
            foreach ($dimensions as $dimension) {
                [$locationId, $variantId] = array_map('intval', explode(':', $dimension));
                $expected = $ledger[$dimension] ?? ['quantity' => '0.000000', 'count' => 0, 'last_id' => null];
                $balance = $balances->get($dimension);
                if (! $balance instanceof StockBalance) {
                    StockBalance::query()->create([
                        'merchant_id' => $store->merchant_id,
                        'store_id' => $store->id,
                        'inventory_location_id' => $locationId,
                        'product_variant_id' => $variantId,
                        'quantity_base' => $expected['quantity'],
                        'movement_count' => $expected['count'],
                        'last_stock_movement_id' => $expected['last_id'],
                        'projection_version' => 1,
                        'last_reconciled_at' => now(),
                    ]);
                    $repairs++;

                    continue;
                }
                $changed = $balance->quantity_base !== $expected['quantity'] || $balance->movement_count !== $expected['count'] || $balance->last_stock_movement_id !== $expected['last_id'];
                $balance->update([
                    'quantity_base' => $expected['quantity'],
                    'movement_count' => $expected['count'],
                    'last_stock_movement_id' => $expected['last_id'],
                    'projection_version' => $balance->projection_version + ($changed ? 1 : 0),
                    'last_reconciled_at' => now(),
                ]);
                if ($changed) {
                    $repairs++;
                }
            }

            return $repairs;
        });

        return ['discrepancies_before' => count($before), 'repaired' => $repaired];
    }

    /** @return array<string, array{quantity: string, count: int, last_id: int|null}> */
    private function ledgerRows(Store $store): array
    {
        $rows = StockMovement::query()
            ->where('merchant_id', $store->merchant_id)
            ->where('store_id', $store->id)
            ->selectRaw('inventory_location_id, product_variant_id, SUM(quantity_base) as ledger_quantity, COUNT(*) as ledger_count, MAX(id) as last_id')
            ->groupBy('inventory_location_id', 'product_variant_id')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $locationId = (int) $row->getAttribute('inventory_location_id');
            $variantId = (int) $row->getAttribute('product_variant_id');
            $result[$this->key($locationId, $variantId)] = [
                'quantity' => Decimal::fixed(BigDecimal::of((string) $row->getAttribute('ledger_quantity')), 6),
                'count' => (int) $row->getAttribute('ledger_count'),
                'last_id' => $row->getAttribute('last_id') === null ? null : (int) $row->getAttribute('last_id'),
            ];
        }

        return $result;
    }

    private function key(int $locationId, int $variantId): string
    {
        return $locationId.':'.$variantId;
    }
}
