import { describe, expect, it } from "vitest";
import {
    displayUrl,
    parseDisplayLocation,
    readProjection,
    storageKey,
    writeProjection,
    type DisplayProjection,
} from "../../resources/js/display/customerDisplayChannel";

class MemoryStorage {
    private values = new Map<string, string>();
    getItem(key: string): string | null {
        return this.values.get(key) ?? null;
    }
    setItem(key: string, value: string): void {
        this.values.set(key, value);
    }
}

const location = { channelId: "channel-1", registerId: "LAB-01" };
const projection: DisplayProjection = {
    schemaVersion: 1,
    registerId: "LAB-01",
    sequence: 2,
    state: "cart",
    message: "Your order",
    lines: [
        {
            name: "Karak tea",
            quantity: "2",
            unit: "each",
            gross: "360.00",
            discount: "36.00",
            extras: [],
        },
    ],
    totals: {
        subtotal: "360.00",
        discount: "36.00",
        tax: "58.32",
        gross: "382.32",
    },
};

describe("same-device customer display channel", () => {
    it("round-trips only a register-scoped projection", () => {
        const storage = new MemoryStorage();
        writeProjection(storage, location, projection);
        expect(readProjection(storage, location)).toEqual(projection);
        expect(() =>
            writeProjection(storage, location, {
                ...projection,
                registerId: "OTHER",
            }),
        ).toThrow("another register");
    });

    it("rejects malformed or cross-register stored content", () => {
        const storage = new MemoryStorage();
        storage.setItem(
            storageKey(location.channelId),
            JSON.stringify({ ...projection, registerId: "OTHER" }),
        );
        expect(readProjection(storage, location)).toBeNull();
        storage.setItem(storageKey(location.channelId), "not-json");
        expect(readProjection(storage, location)).toBeNull();
    });

    it("keeps the random channel and register in the URL fragment", () => {
        const url = displayUrl(location);
        expect(url).toContain("?display=1#");
        expect(parseDisplayLocation(new URL(url, "https://pos.test"))).toEqual(
            location,
        );
    });
});
