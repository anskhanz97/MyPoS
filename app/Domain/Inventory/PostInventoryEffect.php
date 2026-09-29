<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\BundleStructure;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PostInventoryEffect
{
    public function __construct(private readonly BundleStructure $bundles, private readonly PostStockMovement $post) {}

    /** @return list<StockMovement> */
    public function handle(InventoryLocation $location, ProductVariant $variant, InventoryMovementKind $kind, string $quantityBase, string $sourceType, string $sourceReference, string $sourceLineReference, ?User $actor = null): array
    {
        return DB::transaction(function () use ($location, $variant, $kind, $quantityBase, $sourceType, $sourceReference, $sourceLineReference, $actor): array {
            $effects = $this->bundles->expand($variant, $quantityBase);
            $leaves = ProductVariant::query()->where('merchant_id', $variant->merchant_id)->whereIn('id', array_keys($effects))->get()->keyBy('id');
            $movements = [];
            foreach ($effects as $leafId => $leafQuantity) {
                $leaf = $leaves->get($leafId);
                abort_unless($leaf instanceof ProductVariant, 422, 'A bundle component is unavailable.');
                $result = $this->post->handle(new StockMovementCommand(
                    location: $location,
                    variant: $leaf,
                    kind: $kind,
                    quantityBase: $leafQuantity,
                    sourceType: $sourceType,
                    sourceReference: $sourceReference,
                    sourceLineReference: $sourceLineReference,
                    sourceEffect: 'variant:'.$leaf->public_id,
                    actor: $actor,
                ));
                $movements[] = $result['movement'];
            }

            return $movements;
        });
    }
}
