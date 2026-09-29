<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Exceptions\InvalidBundleStructure;
use App\Models\BundleComponent;
use App\Models\ProductVariant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

final class BundleStructure
{
    /** @param list<int> $componentIds */
    public function assertAcyclic(int $merchantId, int $bundleVariantId, array $componentIds): void
    {
        foreach ($componentIds as $componentId) {
            if ($componentId === $bundleVariantId || $this->reaches($merchantId, $componentId, $bundleVariantId, [])) {
                throw new InvalidBundleStructure('A bundle cannot contain itself directly or through another bundle.');
            }
        }
    }

    /** @return array<int, string> variant database ID to base quantity */
    public function expand(ProductVariant $variant, string $quantity = '1.000000'): array
    {
        return $this->expandVariant($variant->merchant_id, $variant->id, BigDecimal::of($quantity), []);
    }

    /**
     * @param  list<int>  $visited
     * @return array<int, string>
     */
    private function expandVariant(int $merchantId, int $variantId, BigDecimal $multiplier, array $visited): array
    {
        if (in_array($variantId, $visited, true)) {
            throw new InvalidBundleStructure('A recursive bundle was detected.');
        }
        /** @var Collection<int, BundleComponent> $components */
        $components = BundleComponent::query()->where('merchant_id', $merchantId)->where('bundle_variant_id', $variantId)->get();
        if ($components->isEmpty()) {
            return [$variantId => $multiplier->toScale(6)->__toString()];
        }
        $expanded = [];
        foreach ($components as $component) {
            $child = $this->expandVariant($merchantId, $component->component_variant_id, $multiplier->multipliedBy($component->quantity_base), [...$visited, $variantId]);
            foreach ($child as $leafId => $leafQuantity) {
                $expanded[$leafId] = BigDecimal::of($expanded[$leafId] ?? '0')->plus($leafQuantity)->toScale(6)->__toString();
            }
        }

        return $expanded;
    }

    /** @param list<int> $visited */
    private function reaches(int $merchantId, int $fromVariantId, int $targetVariantId, array $visited): bool
    {
        if (in_array($fromVariantId, $visited, true)) {
            return false;
        }
        $children = BundleComponent::query()->where('merchant_id', $merchantId)->where('bundle_variant_id', $fromVariantId)->pluck('component_variant_id');
        foreach ($children as $childId) {
            if ($childId === $targetVariantId || $this->reaches($merchantId, (int) $childId, $targetVariantId, [...$visited, $fromVariantId])) {
                return true;
            }
        }

        return false;
    }
}
