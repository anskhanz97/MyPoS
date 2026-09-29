<?php

namespace App\Http\Resources;

use App\Models\BundleComponent;
use App\Models\ExtraGroup;
use App\Models\ExtraOption;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImage;
use App\Models\ProductStoreAvailability;
use App\Models\ProductVariant;
use App\Models\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class CatalogProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id, 'scope' => $this->store_id === null ? 'merchant' : 'store', 'type' => $this->type->value,
            'name' => $this->name, 'description' => $this->description, 'tax_classification_reference' => $this->tax_classification_reference,
            'batch_tracking_enabled' => $this->batch_tracking_enabled, 'structure_version' => $this->structure_version,
            'archived_at' => $this->archived_at?->toAtomString(),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->public_id, 'sku' => $variant->sku, 'name' => $variant->name, 'attributes' => $variant->attributes ?? [],
                'is_weighted' => $variant->is_weighted, 'is_default' => $variant->is_default, 'archived_at' => $variant->archived_at?->toAtomString(),
                'base_unit' => ['id' => $variant->baseUnit->public_id, 'code' => $variant->baseUnit->code, 'name' => $variant->baseUnit->name, 'symbol' => $variant->baseUnit->symbol, 'decimal_places' => $variant->baseUnit->decimal_places],
                'barcodes' => $variant->barcodes->map(fn (ProductBarcode $barcode): array => ['id' => $barcode->public_id, 'code' => $barcode->code, 'kind' => $barcode->kind->value, 'is_primary' => $barcode->is_primary])->values(),
                'conversions' => $variant->conversions->map(fn (UnitConversion $conversion): array => ['id' => $conversion->public_id, 'unit' => ['id' => $conversion->fromUnit->public_id, 'code' => $conversion->fromUnit->code, 'symbol' => $conversion->fromUnit->symbol], 'factor_to_base' => $conversion->factor_to_base])->values(),
                'bundle_components' => $variant->bundleComponents->map(fn (BundleComponent $component): array => ['variant_id' => $component->componentVariant->public_id, 'sku' => $component->componentVariant->sku, 'product_name' => $component->componentVariant->product->name, 'quantity_base' => $component->quantity_base])->values(),
            ])->values()),
            'extra_groups' => $this->whenLoaded('extraGroups', fn () => $this->extraGroups->map(fn (ExtraGroup $group): array => [
                'id' => $group->public_id, 'name' => $group->name, 'minimum_selected' => $group->minimum_selected, 'maximum_selected' => $group->maximum_selected,
                'options' => $group->options->map(fn (ExtraOption $option): array => ['id' => $option->public_id, 'name' => $option->name, 'price_delta' => $option->price_delta, 'stock_variant_id' => $option->stockVariant?->public_id, 'stock_quantity_base' => $option->stock_quantity_base, 'is_active' => $option->is_active])->values(),
            ])->values()),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (ProductImage $image): array => ['id' => $image->public_id, 'mime_type' => $image->mime_type, 'size_bytes' => $image->size_bytes, 'alt_text' => $image->alt_text])->values()),
            'store_availability' => $this->whenLoaded('storeAvailabilities', fn () => $this->storeAvailabilities->map(fn (ProductStoreAvailability $availability): array => ['store_id' => $availability->store->public_id, 'is_available' => $availability->is_available])->values()),
        ];
    }
}
