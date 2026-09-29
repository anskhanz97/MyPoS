import { describe, expect, it } from "vitest";
import { useStoreContext } from "../../resources/js/tenancy/storeContext";

describe("store context", () => {
    it("clears store-specific transient state and rotates the cache namespace", () => {
        const context = useStoreContext("store-lhr");
        context.draftReference.value = "SALE-77";
        context.searchTerm.value = "tea";

        context.switchStore({
            id: "store-isb",
            name: "Islamabad Blue Area",
            code: "ISB-01",
        });

        expect(context.activeStoreId.value).toBe("store-isb");
        expect(context.draftReference.value).toBe("");
        expect(context.searchTerm.value).toBe("");
        expect(context.cacheGeneration.value).toBe(1);
        expect(context.announcement.value).toContain("Islamabad Blue Area");
    });

    it("preserves transient state when the selected store is already active", () => {
        const context = useStoreContext("store-lhr");
        context.draftReference.value = "SALE-77";

        context.switchStore({
            id: "store-lhr",
            name: "Lahore Flagship",
            code: "LHR-01",
        });

        expect(context.draftReference.value).toBe("SALE-77");
        expect(context.cacheGeneration.value).toBe(0);
    });
});
