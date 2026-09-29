<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Math\Decimal;
use App\Domain\Tenancy\Enums\StoreDataMode;
use App\Models\InventoryVariantMapping;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;
use Brick\Math\BigDecimal;

final class TransferVariantResolver
{
    /** @return array{destination: ProductVariant, factor: string, mapping: InventoryVariantMapping|null} */
    public function resolve(Merchant $merchant, Store $source, Store $destination, ProductVariant $sourceVariant): array
    {
        abort_unless($source->merchant_id === $merchant->id && $destination->merchant_id === $merchant->id && $source->id !== $destination->id, 422, 'Transfer stores are invalid.');
        if ($merchant->store_data_mode === StoreDataMode::Shared) {
            abort_unless($sourceVariant->merchant_id === $merchant->id && $sourceVariant->store_id === null, 422, 'The source product is outside the shared catalog.');

            return ['destination' => $sourceVariant, 'factor' => '1.000000', 'mapping' => null];
        }
        abort_unless($sourceVariant->merchant_id === $merchant->id && $sourceVariant->store_id === $source->id, 422, 'The source product is outside the source store catalog.');
        $mapping = InventoryVariantMapping::query()->where('merchant_id', $merchant->id)->where('source_store_id', $source->id)->where('destination_store_id', $destination->id)->where('source_product_variant_id', $sourceVariant->id)->where('is_active', true)->with('destinationVariant.product')->first();
        abort_unless($mapping instanceof InventoryVariantMapping && $mapping->destinationVariant->store_id === $destination->id, 409, 'An explicit active destination variant/UOM mapping is required.');

        return ['destination' => $mapping->destinationVariant, 'factor' => Decimal::fixed(BigDecimal::of($mapping->factor_to_destination_base), 6), 'mapping' => $mapping];
    }
}
