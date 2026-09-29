import { describe, expect, it } from "vitest";
import { canonicalJson } from "../../resources/js/offline/canonical";
import { evaluateLease } from "../../resources/js/offline/lease";
import { cashSettlement } from "../../resources/js/offline/offlinePricing";

describe("offline runtime contracts", () => {
    it("canonicalizes nested command objects without reordering lists", () => {
        expect(canonicalJson({ z: 1, nested: { y: 2, a: 3 }, lines: [{ z: 4, a: 5 }] })).toBe('{"lines":[{"a":5,"z":4}],"nested":{"a":3,"y":2},"z":1}');
    });

    it("blocks expired leases and material clock rollback", () => {
        const lease = { issuedAt: "2026-09-29T08:00:00Z", expiresAt: "2026-10-02T08:00:00Z", lastTrustedServerTime: "2026-09-29T08:00:00Z" };
        expect(evaluateLease(lease, new Date("2026-09-30T08:00:00Z"))).toBe("valid");
        expect(evaluateLease(lease, new Date("2026-10-02T08:06:00Z"))).toBe("expired");
        expect(evaluateLease(lease, new Date("2026-09-29T07:54:00Z"))).toBe("clock_rollback");
    });

    it("settles cash using decimal arithmetic", () => {
        expect(cashSettlement("1290.92", "1300.00")).toEqual({ change: "9.08", settled: true });
        expect(cashSettlement("1290.92", "1200.00")).toEqual({ change: "0.00", settled: false });
    });
});
