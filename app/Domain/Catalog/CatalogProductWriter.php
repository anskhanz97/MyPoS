<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Enums\ProductType;
use App\Models\BundleComponent;
use App\Models\ExtraGroup;
use App\Models\ExtraOption;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductStoreAvailability;
use App\Models\ProductVariant;
use App\Models\SecurityAuditEvent;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CatalogProductWriter
{
    public function __construct(private readonly CatalogScope $scope, private readonly BundleStructure $bundles) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, Merchant $merchant, Store $store, array $data): Product
    {
        $scopeStoreId = $this->scope->storeId($merchant, $store);

        return DB::transaction(function () use ($actor, $merchant, $store, $scopeStoreId, $data): Product {
            $variantsData = $data['variants'];
            abort_unless(is_array($variantsData), 422);
            $skus = array_map(fn (array $variant): string => mb_strtoupper(trim((string) $variant['sku'])), $variantsData);
            abort_if(count($skus) !== count(array_unique($skus)), 422, 'Variant SKUs must be unique within the product.');
            abort_if(ProductVariant::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->whereIn('sku', $skus)->exists(), 422, 'A SKU already exists in this catalog.');
            $explicitDefaults = array_filter($variantsData, fn (array $variant): bool => ($variant['is_default'] ?? false) === true);
            abort_if(count($explicitDefaults) > 1, 422, 'A product must have exactly one default variant.');
            $hasExplicitDefault = $explicitDefaults !== [];
            $type = ProductType::from((string) $data['type']);
            $product = Product::query()->create([
                'merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'type' => $type,
                'name' => $data['name'], 'description' => $data['description'] ?? null,
                'tax_classification_reference' => $data['tax_classification_reference'] ?? null,
                'batch_tracking_enabled' => $data['batch_tracking_enabled'] ?? false, 'structure_version' => 1,
            ]);
            $createdVariants = [];
            $seenBarcodes = [];
            foreach ($variantsData as $index => $variantData) {
                $unit = $this->unit($merchant, (string) $variantData['base_unit_id']);
                $components = $variantData['bundle_components'] ?? [];
                abort_if($type === ProductType::Bundle && $components === [], 422, 'Every bundle variant requires at least one finished-product component.');
                abort_if($type !== ProductType::Bundle && $components !== [], 422, 'Only bundle products can define bundle components.');
                $variant = ProductVariant::query()->create([
                    'merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'product_id' => $product->id, 'base_unit_id' => $unit->id,
                    'sku' => $skus[$index], 'name' => $variantData['name'], 'attributes' => $variantData['attributes'] ?? null,
                    'is_weighted' => $type === ProductType::Weighted || ($variantData['is_weighted'] ?? false),
                    'is_default' => $hasExplicitDefault ? ($variantData['is_default'] ?? false) : $index === 0,
                ]);
                $createdVariants[] = $variant;
                foreach ($variantData['barcodes'] ?? [] as $barcodeData) {
                    $code = (string) $barcodeData['code'];
                    abort_if(in_array($code, $seenBarcodes, true), 422, 'Barcodes must be unique within the product.');
                    abort_if(ProductBarcode::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->where('code', $code)->exists(), 422, 'A barcode already exists in this catalog.');
                    $seenBarcodes[] = $code;
                    ProductBarcode::query()->create(['merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'product_variant_id' => $variant->id, 'code' => $code, 'kind' => $barcodeData['kind'], 'is_primary' => $barcodeData['is_primary'] ?? false]);
                }
                foreach ($variantData['conversions'] ?? [] as $conversionData) {
                    $fromUnit = $this->unit($merchant, (string) $conversionData['unit_id']);
                    abort_if($fromUnit->id === $unit->id, 422, 'A conversion unit must differ from the base unit.');
                    UnitConversion::query()->create(['merchant_id' => $merchant->id, 'product_variant_id' => $variant->id, 'from_unit_id' => $fromUnit->id, 'factor_to_base' => $conversionData['factor_to_base']]);
                }
            }
            foreach ($variantsData as $index => $variantData) {
                $variant = $createdVariants[$index];
                $componentIds = [];
                foreach ($variantData['bundle_components'] ?? [] as $componentData) {
                    $component = $this->variant($merchant, $scopeStoreId, (string) $componentData['variant_id']);
                    $componentIds[] = $component->id;
                    BundleComponent::query()->create(['merchant_id' => $merchant->id, 'bundle_variant_id' => $variant->id, 'component_variant_id' => $component->id, 'quantity_base' => $componentData['quantity_base']]);
                }
                $this->bundles->assertAcyclic($merchant->id, $variant->id, $componentIds);
            }
            foreach ($data['extra_groups'] ?? [] as $groupIndex => $groupData) {
                abort_if((int) $groupData['minimum_selected'] > (int) $groupData['maximum_selected'], 422, 'Extra minimum cannot exceed maximum.');
                $group = ExtraGroup::query()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'name' => $groupData['name'], 'minimum_selected' => $groupData['minimum_selected'], 'maximum_selected' => $groupData['maximum_selected'], 'sort_order' => $groupIndex]);
                foreach ($groupData['options'] as $optionIndex => $optionData) {
                    $stockVariant = isset($optionData['stock_variant_id']) ? $this->variant($merchant, $scopeStoreId, (string) $optionData['stock_variant_id']) : null;
                    abort_if(($stockVariant === null) !== empty($optionData['stock_quantity_base']), 422, 'An extra stock variant and quantity must be supplied together.');
                    ExtraOption::query()->create(['merchant_id' => $merchant->id, 'extra_group_id' => $group->id, 'name' => $optionData['name'], 'price_delta' => $optionData['price_delta'], 'stock_variant_id' => $stockVariant?->id, 'stock_quantity_base' => $optionData['stock_quantity_base'] ?? null, 'sort_order' => $optionIndex]);
                }
            }
            $this->syncAvailability($merchant, $product, $scopeStoreId, $data['available_store_ids'] ?? null);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'catalog.product.created', 'outcome' => 'succeeded', 'metadata' => ['product_id' => $product->public_id, 'structure_version' => 1], 'occurred_at' => now()]);

            return $product->load($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Merchant $merchant, Store $store, Product $product, array $data): Product
    {
        $scopeStoreId = $this->scope->storeId($merchant, $store);
        abort_unless($product->merchant_id === $merchant->id && $product->store_id === $scopeStoreId, 404);

        return DB::transaction(function () use ($actor, $merchant, $store, $scopeStoreId, $product, $data): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($locked->structure_version === (int) $data['expected_structure_version'], 409, 'The catalog structure changed. Reload before saving.');
            if (! $locked->batch_tracking_enabled && ($data['batch_tracking_enabled'] ?? false)) {
                abort_unless((bool) ($data['batch_conversion_confirmed'] ?? false), 409, 'Confirm the existing-stock batch conversion plan before enabling batch tracking.');
                abort_if(StockBalance::query()->where('merchant_id', $merchant->id)->whereIn('product_variant_id', $locked->variants()->pluck('id'))->where('quantity_base', '!=', 0)->exists(), 409, 'Existing stock must be allocated through the batch conversion endpoint before enabling tracking.');
            }
            $changes = Arr::only($data, ['name', 'description', 'tax_classification_reference', 'batch_tracking_enabled']);
            if (array_key_exists('archived', $data)) {
                $changes['archived_at'] = $data['archived'] ? now() : null;
            }
            $changes['structure_version'] = $locked->structure_version + 1;
            $locked->update($changes);
            if (array_key_exists('available_store_ids', $data)) {
                $this->syncAvailability($merchant, $locked, $scopeStoreId, $data['available_store_ids']);
            }
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'catalog.product.updated', 'outcome' => 'succeeded', 'metadata' => ['product_id' => $locked->public_id, 'structure_version' => $locked->structure_version, 'changed_keys' => array_keys($changes)], 'occurred_at' => now()]);

            return $locked->refresh()->load($this->relations());
        });
    }

    /** @param array<int, array{variant_id: string, quantity_base: string}> $components */
    public function replaceBundle(User $actor, Merchant $merchant, Store $store, ProductVariant $bundleVariant, int $expectedVersion, array $components): Product
    {
        $scopeStoreId = $this->scope->storeId($merchant, $store);
        abort_unless($bundleVariant->merchant_id === $merchant->id && $bundleVariant->store_id === $scopeStoreId, 404);

        return DB::transaction(function () use ($actor, $merchant, $store, $scopeStoreId, $bundleVariant, $expectedVersion, $components): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($bundleVariant->product_id);
            abort_unless($product->type === ProductType::Bundle, 409, 'Only bundle products have components.');
            abort_unless($product->structure_version === $expectedVersion, 409, 'The catalog structure changed. Reload before saving.');
            $resolved = [];
            foreach ($components as $componentData) {
                $component = $this->variant($merchant, $scopeStoreId, $componentData['variant_id']);
                $resolved[] = ['variant' => $component, 'quantity_base' => $componentData['quantity_base']];
            }
            $this->bundles->assertAcyclic($merchant->id, $bundleVariant->id, array_map(fn (array $row): int => $row['variant']->id, $resolved));
            BundleComponent::query()->where('bundle_variant_id', $bundleVariant->id)->delete();
            foreach ($resolved as $row) {
                BundleComponent::query()->create(['merchant_id' => $merchant->id, 'bundle_variant_id' => $bundleVariant->id, 'component_variant_id' => $row['variant']->id, 'quantity_base' => $row['quantity_base']]);
            }
            $product->increment('structure_version');
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'catalog.bundle.updated', 'outcome' => 'succeeded', 'metadata' => ['product_id' => $product->public_id, 'variant_id' => $bundleVariant->public_id, 'structure_version' => $product->structure_version], 'occurred_at' => now()]);

            return $product->refresh()->load($this->relations());
        });
    }

    /** @return list<string> */
    public function relations(): array
    {
        return ['variants.baseUnit', 'variants.barcodes', 'variants.conversions.fromUnit', 'variants.bundleComponents.componentVariant.product', 'extraGroups.options.stockVariant', 'images', 'storeAvailabilities.store'];
    }

    private function unit(Merchant $merchant, string $publicId): UnitOfMeasure
    {
        $unit = (new UnitOfMeasure)->resolveRouteBinding($publicId);
        abort_unless($unit instanceof UnitOfMeasure && $unit->merchant_id === $merchant->id && $unit->is_active, 422, 'The unit is not available to this merchant.');

        return $unit;
    }

    private function variant(Merchant $merchant, ?int $storeId, string $publicId): ProductVariant
    {
        $variant = (new ProductVariant)->resolveRouteBinding($publicId);
        abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id && $variant->store_id === $storeId && $variant->archived_at === null, 422, 'The component variant is outside this catalog.');

        return $variant;
    }

    /** @param array<mixed>|null $storePublicIds */
    private function syncAvailability(Merchant $merchant, Product $product, ?int $scopeStoreId, ?array $storePublicIds): void
    {
        if ($scopeStoreId !== null) {
            abort_if($storePublicIds !== null && $storePublicIds !== [], 422, 'Independent-store products do not use shared-catalog availability overrides.');

            return;
        }
        $ids = [];
        foreach ($storePublicIds ?? [] as $publicId) {
            $store = (new Store)->resolveRouteBinding((string) $publicId);
            abort_unless($store instanceof Store && $store->merchant_id === $merchant->id, 422, 'A selected availability store is outside this merchant.');
            $ids[] = $store->id;
        }
        $allStoreIds = Store::query()->where('merchant_id', $merchant->id)->pluck('id');
        ProductStoreAvailability::query()->where('product_id', $product->id)->delete();
        foreach ($allStoreIds as $storeId) {
            ProductStoreAvailability::query()->create(['merchant_id' => $merchant->id, 'store_id' => $storeId, 'product_id' => $product->id, 'is_available' => $storePublicIds === null || in_array($storeId, $ids, true)]);
        }
    }
}
