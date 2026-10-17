import { describe, expect, it, vi } from "vitest";
import {
    claimDropdown,
    releaseDropdown,
} from "../../resources/js/shared/dropdown";

describe("shared dropdown ownership", () => {
    it("closes the previous menu and ignores a late close from that menu", () => {
        const first = Symbol("first");
        const second = Symbol("second");
        const third = Symbol("third");
        const closeFirst = vi.fn();
        const closeSecond = vi.fn();
        claimDropdown(first, closeFirst);
        claimDropdown(second, closeSecond);
        expect(closeFirst).toHaveBeenCalledOnce();
        releaseDropdown(first);
        claimDropdown(third, vi.fn());
        expect(closeSecond).toHaveBeenCalledOnce();
        releaseDropdown(third);
    });
});
