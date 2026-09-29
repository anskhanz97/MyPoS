import Decimal from "decimal.js";

Decimal.set({ precision: 40, rounding: Decimal.ROUND_HALF_UP });

export function decimal(value: string): Decimal {
    return new Decimal(value);
}

export function fixed(value: Decimal, scale: number): string {
    return value.toDecimalPlaces(scale, Decimal.ROUND_HALF_UP).toFixed(scale);
}

export function floorFixed(value: Decimal, scale: number): string {
    return value.toDecimalPlaces(scale, Decimal.ROUND_FLOOR).toFixed(scale);
}
