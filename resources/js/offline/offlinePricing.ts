import { decimal, fixed } from "../shared/decimal";

export function cashSettlement(
    total: string,
    received: string,
): { change: string; settled: boolean } {
    const due = decimal(total);
    const cash = decimal(received);
    const change = cash.greaterThan(due) ? cash.minus(due) : decimal("0");
    return {
        change: fixed(change, 2),
        settled: cash.minus(change).equals(due),
    };
}
