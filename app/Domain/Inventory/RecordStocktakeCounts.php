<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\StocktakeState;
use App\Domain\Shared\Math\Decimal;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RecordStocktakeCounts
{
    /** @param list<array{stocktake_line_id: string, counted_quantity_base: string}> $counts */
    public function handle(Stocktake $stocktake, int $expectedVersion, array $counts): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $expectedVersion, $counts): Stocktake {
            $locked = Stocktake::query()->with('lines')->lockForUpdate()->findOrFail($stocktake->id);
            if ($locked->state !== StocktakeState::Counting || $locked->version !== $expectedVersion) {
                throw new ConflictHttpException('The stocktake state or version changed before the count was saved.');
            }
            $cutoff = (int) StockMovement::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->store_id)->where('inventory_location_id', $locked->inventory_location_id)->max('id');
            $lineMap = $locked->lines->keyBy('public_id');
            foreach ($counts as $input) {
                $line = $lineMap->get($input['stocktake_line_id']);
                abort_unless($line instanceof StocktakeLine, 422, 'A stocktake line is invalid.');
                $interveningQuery = StockMovement::query()->where('merchant_id', $locked->merchant_id)->where('store_id', $locked->store_id)->where('inventory_location_id', $locked->inventory_location_id)->where('product_variant_id', $line->product_variant_id)->where('id', '>', $locked->baseline_movement_id)->where('id', '<=', $cutoff);
                $line->product_batch_id === null ? $interveningQuery->whereNull('product_batch_id') : $interveningQuery->where('product_batch_id', $line->product_batch_id);
                $intervening = BigDecimal::of((string) ($interveningQuery->sum('quantity_base') ?: '0'));
                $expectedAtCount = BigDecimal::of($line->baseline_quantity_base)->plus($intervening);
                $counted = BigDecimal::of(Decimal::fixed(BigDecimal::of($input['counted_quantity_base']), 6));
                $line->update([
                    'counted_quantity_base' => Decimal::fixed($counted, 6), 'count_cutoff_movement_id' => $cutoff,
                    'expected_at_count_quantity_base' => Decimal::fixed($expectedAtCount, 6),
                    'discrepancy_quantity_base' => Decimal::fixed($counted->minus($expectedAtCount), 6), 'counted_at' => now(),
                ]);
            }
            $locked->update(['version' => $locked->version + 1]);

            return $locked->refresh()->load(['location', 'lines.variant.product', 'lines.batch']);
        });
    }
}
