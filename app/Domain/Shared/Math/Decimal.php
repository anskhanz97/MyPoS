<?php

namespace App\Domain\Shared\Math;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Decimal
{
    public const int CalculatorScale = 20;

    public static function of(string $value): BigDecimal
    {
        return BigDecimal::of($value);
    }

    /** @param int<0, max> $scale */
    public static function fixed(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }

    /** @param int<0, max> $scale */
    public static function floorFixed(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::Floor);
    }
}
