import { describe, expect, it } from "vitest";
import { readTheme, staffThemeKey, writeTheme } from "../../resources/js/theme/themePreference";

function memoryStorage(): Pick<Storage, "getItem" | "setItem"> {
    const values = new Map<string, string>();
    return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
}

describe("theme preference", () => {
    it("uses staff choice before the register fallback", () => {
        const storage = memoryStorage();
        storage.setItem("mypos.theme.register", "dark");
        storage.setItem(staffThemeKey("staff-1"), "light");
        expect(readTheme(storage, "staff", "staff-1")).toBe("light");
    });

    it("keeps public preference independent from staff and register", () => {
        const storage = memoryStorage();
        writeTheme(storage, "dark", "staff", "staff-1");
        expect(readTheme(storage, "public")).toBe("light");
        writeTheme(storage, "dark", "public");
        expect(readTheme(storage, "public")).toBe("dark");
        expect(readTheme(storage, "staff", "staff-1")).toBe("dark");
    });
});
