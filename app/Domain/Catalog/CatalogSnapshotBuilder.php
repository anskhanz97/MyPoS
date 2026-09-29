<?php

namespace App\Domain\Catalog;

use App\Models\ProductVariant;

final class CatalogSnapshotBuilder
{
    /** @return array{id: string, product_id: string, structure_version: int, sku: string, name: string, variant_name: string, attributes: array<string, string>, base_unit: array{code: string, symbol: string}, barcode_used: string|null} */
    public function variant(ProductVariant $variant, ?string $barcodeUsed = null): array
    {
        $variant->loadMissing(['product', 'baseUnit']);

        return [
            'id' => $variant->public_id,
            'product_id' => $variant->product->public_id,
            'structure_version' => $variant->product->structure_version,
            'sku' => $variant->sku,
            'name' => $variant->product->name,
            'variant_name' => $variant->name,
            'attributes' => $variant->attributes ?? [],
            'base_unit' => ['code' => $variant->baseUnit->code, 'symbol' => $variant->baseUnit->symbol],
            'barcode_used' => $barcodeUsed,
        ];
    }
}
