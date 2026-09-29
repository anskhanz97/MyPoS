import type { OfflineLease } from "./types";

export type LeaseState = "valid" | "expired" | "clock_rollback";

export function evaluateLease(
    lease: OfflineLease,
    now: Date,
    toleranceMs = 5 * 60_000,
): LeaseState {
    const current = now.getTime();
    if (current + toleranceMs < Date.parse(lease.lastTrustedServerTime))
        return "clock_rollback";
    return current <= Date.parse(lease.expiresAt) + toleranceMs
        ? "valid"
        : "expired";
}
