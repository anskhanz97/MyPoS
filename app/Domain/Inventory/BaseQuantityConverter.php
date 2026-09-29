<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Math\Decimal;
use App\Models\ProductVariant;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

final class BaseQuantityConverter
{
    public function convert(ProductVariant $variant, UnitOfMeasure $unit, string $quantity): string
    {
        $number = BigDecimal::of($quantity);
        if ($number->isZero()) {
            throw ValidationException::withMessages(['quantity' => 'Inventory quantity cannot be zero.']);
        }
        abort_unless($unit->merchant_id === $variant->merchant_id, 422, 'The unit does not belong to this merchant.');
        if ($unit->id === $variant->base_unit_id) {
            return Decimal::fixed($number, 6);
        }
        $conversion = UnitConversion::query()
            ->where('merchant_id', $variant->merchant_id)
            ->where('product_variant_id', $variant->id)
            ->where('from_unit_id', $unit->id)
            ->first();
        abort_unless($conversion instanceof UnitConversion, 422, 'No direct conversion to the variant base unit exists.');

        return Decimal::fixed($number->multipliedBy($conversion->factor_to_base), 6);
    }
}
