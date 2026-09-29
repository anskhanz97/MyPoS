import { computed, ref, type ComputedRef, type Ref } from "vue";

export type Theme = "light" | "dark";
export type ThemeScope = "staff" | "public" | "display";

export interface ThemePreference {
    theme: Ref<Theme>;
    nextTheme: ComputedRef<Theme>;
    scope: Ref<ThemeScope>;
    setScope: (scope: ThemeScope, staffId?: string) => void;
    setTheme: (theme: Theme) => void;
    toggle: () => void;
}

const registerKey = "mypos.theme.register";
const publicKey = "mypos.theme.public";
const displayKey = "mypos.theme.display";

export function staffThemeKey(staffId = "anonymous"): string {
    return `mypos.theme.staff:${staffId}`;
}

function isTheme(value: string | null): value is Theme {
    return value === "light" || value === "dark";
}

export function readTheme(
    storage: Pick<Storage, "getItem">,
    scope: ThemeScope,
    staffId = "anonymous",
): Theme {
    const preferred = storage.getItem(
        scope === "public"
            ? publicKey
            : scope === "display"
              ? displayKey
              : staffThemeKey(staffId),
    );
    if (isTheme(preferred)) return preferred;
    if (scope === "staff") {
        const register = storage.getItem(registerKey);
        if (isTheme(register)) return register;
    }
    return "light";
}

export function writeTheme(
    storage: Pick<Storage, "setItem">,
    theme: Theme,
    scope: ThemeScope,
    staffId = "anonymous",
): void {
    storage.setItem(
        scope === "public"
            ? publicKey
            : scope === "display"
              ? displayKey
              : staffThemeKey(staffId),
        theme,
    );
    if (scope === "staff") storage.setItem(registerKey, theme);
}

export function useThemePreference(): ThemePreference {
    const scope = ref<ThemeScope>("staff");
    const staffId = ref("anonymous");
    const initial = readTheme(localStorage, scope.value, staffId.value);
    document.documentElement.dataset.theme = initial;
    const theme = ref<Theme>(initial);
    const nextTheme = computed<Theme>(() =>
        theme.value === "light" ? "dark" : "light",
    );

    function apply(value: Theme): void {
        theme.value = value;
        document.documentElement.dataset.theme = value;
    }

    function setTheme(value: Theme): void {
        apply(value);
        writeTheme(localStorage, value, scope.value, staffId.value);
    }

    function setScope(value: ThemeScope, nextStaffId = "anonymous"): void {
        scope.value = value;
        staffId.value = nextStaffId;
        apply(readTheme(localStorage, value, nextStaffId));
    }

    return {
        theme,
        nextTheme,
        scope,
        setScope,
        setTheme,
        toggle: () => setTheme(nextTheme.value),
    };
}
