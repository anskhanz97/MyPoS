<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Models\StockMovement;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReverseStockMovement
{
    public function __construct(private readonly PostStockMovement $post) {}

    public function handle(StockMovement $movement, string $sourceReference, string $reason, ?User $actor = null): StockMovement
    {
        return DB::transaction(function () use ($movement, $sourceReference, $reason, $actor): StockMovement {
            $locked = StockMovement::query()->with(['location.store.merchant', 'variant.product', 'batch'])->lockForUpdate()->findOrFail($movement->id);
            if ($locked->reversal()->exists()) {
                throw new ConflictHttpException('This movement already has a reversal.');
            }
            $result = $this->post->handle(new StockMovementCommand(
                location: $locked->location,
                variant: $locked->variant,
                batch: $locked->batch,
                kind: InventoryMovementKind::Correction,
                quantityBase: (string) BigDecimal::of($locked->quantity_base)->negated(),
                sourceType: 'movement_reversal',
                sourceReference: $sourceReference,
                sourceLineReference: $locked->public_id,
                actor: $actor,
                reverses: $locked,
                reasonText: $reason,
                unitCost: $locked->unit_cost,
                costState: $locked->cost_state,
            ));

            return $result['movement'];
        });
    }
}
