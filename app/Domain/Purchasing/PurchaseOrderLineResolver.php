<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\InventoryScope;
use App\Domain\Shared\Math\Decimal;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PurchaseOrderLineResolver
{
    public function __construct(private readonly InventoryScope $scope) {}

    /** @return array{variant: ProductVariant, unit: UnitOfMeasure, factor: string, quantity_uom: string, quantity_base: string, unit_cost: string} */
    public function resolve(Merchant $merchant, Store $store, string $variantId, string $unitId, string $quantity, string $unitCost): array
    {
        $variant = (new ProductVariant)->resolveRouteBinding($variantId);
        $unit = (new UnitOfMeasure)->resolveRouteBinding($unitId);
        abort_unless($variant instanceof ProductVariant && $unit instanceof UnitOfMeasure, 422, 'A purchase-order catalog reference is invalid.');
        $this->scope->assertVariant($merchant, $store, $variant);
        abort_unless($unit->merchant_id === $merchant->id && $unit->is_active, 422, 'The purchase unit is unavailable for this merchant.');
        $variant->loadMissing(['product', 'baseUnit']);
        if ($variant->base_unit_id === $unit->id) {
            $factor = '1.000000';
        } else {
            $conversion = UnitConversion::query()->where('merchant_id', $merchant->id)->where('product_variant_id', $variant->id)->where('from_unit_id', $unit->id)->first();
            if (! $conversion instanceof UnitConversion) {
                throw new ConflictHttpException('The selected purchase unit has no direct conversion to this variant base unit.');
            }
            $factor = $conversion->factor_to_base;
        }
        $quantityUom = Decimal::fixed(BigDecimal::of($quantity), 6);

        return [
            'variant' => $variant,
            'unit' => $unit,
            'factor' => $factor,
            'quantity_uom' => $quantityUom,
            'quantity_base' => Decimal::fixed(BigDecimal::of($quantityUom)->multipliedBy($factor), 6),
            'unit_cost' => Decimal::fixed(BigDecimal::of($unitCost), 6),
        ];
    }
}
