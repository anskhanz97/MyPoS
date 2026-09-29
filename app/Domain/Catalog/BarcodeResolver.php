<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Enums\BarcodeKind;
use App\Domain\Catalog\Enums\ScaleValueType;
use App\Models\Merchant;
use App\Models\ProductBarcode;
use App\Models\ScaleBarcodeFormat;
use App\Models\Store;
use Brick\Math\BigDecimal;

final class BarcodeResolver
{
    /** @return array{barcode: ProductBarcode, kind: string, quantity: string|null, embedded_price: string|null} */
    public function resolve(Merchant $merchant, Store $store, string $rawCode, CatalogScope $scope): array
    {
        abort_unless(preg_match('/^[0-9A-Za-z-]{1,64}$/', $rawCode) === 1, 422, 'The barcode contains unsupported characters.');
        $scopeStoreId = $scope->storeId($merchant, $store);
        $exact = ProductBarcode::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->where('code', $rawCode)->first();
        if ($exact !== null) {
            return ['barcode' => $exact, 'kind' => 'unit', 'quantity' => '1.000000', 'embedded_price' => null];
        }

        $formats = ScaleBarcodeFormat::query()->where('merchant_id', $merchant->id)
            ->where(fn ($query) => $scopeStoreId === null ? $query->whereNull('store_id') : $query->where('store_id', $scopeStoreId)->orWhereNull('store_id'))
            ->where('is_active', true)->orderByDesc('store_id')->get();
        foreach ($formats as $format) {
            if (strlen($rawCode) !== $format->total_length || ! str_starts_with($rawCode, $format->prefix) || ! ctype_digit($rawCode)) {
                continue;
            }
            if ($format->checksum === 'ean13' && ! $this->validEan13($rawCode)) {
                abort(422, 'The scale barcode checksum is invalid.');
            }
            $itemCode = substr($rawCode, $format->item_start, $format->item_length);
            $encodedValue = substr($rawCode, $format->value_start, $format->value_length);
            abort_unless($itemCode !== '' && $encodedValue !== '', 422, 'The scale barcode format is outside its configured bounds.');
            $barcode = ProductBarcode::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->where('kind', BarcodeKind::ScaleReference)->where('code', $itemCode)->first();
            abort_unless($barcode !== null, 404, 'No product matches the scale label item reference.');
            $value = BigDecimal::of($encodedValue)->withPointMovedLeft($format->value_decimal_places);

            return ['barcode' => $barcode, 'kind' => 'scale', 'quantity' => $format->value_type === ScaleValueType::Quantity ? $value->toScale(6)->__toString() : null, 'embedded_price' => $format->value_type === ScaleValueType::Price ? $value->toScale(2)->__toString() : null];
        }
        abort(404, 'No product matches the barcode.');
    }

    public function validEan13(string $code): bool
    {
        if (strlen($code) !== 13 || ! ctype_digit($code)) {
            return false;
        }
        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $code[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10 === (int) $code[12];
    }
}
