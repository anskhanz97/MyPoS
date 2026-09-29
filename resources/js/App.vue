<script setup lang="ts">
import { ref, watch } from "vue";
import AppShell, { type ShellKind } from "./components/AppShell.vue";
import { useThemePreference } from "./theme/themePreference";
import ComponentGallery from "./views/ComponentGallery.vue";
import CheckoutPreview from "./views/CheckoutPreview.vue";
import CustomerDisplayWindow from "./views/CustomerDisplayWindow.vue";

const shell = ref<ShellKind>("management");
const shells: ShellKind[] = ["management", "checkout", "public"];
const themePreference = useThemePreference();
const currentTheme = themePreference.theme;
const nextTheme = themePreference.nextTheme;
const displayMode =
    new URL(window.location.href).searchParams.get("display") === "1";

if (displayMode) themePreference.setScope("display");

watch(shell, (value) => {
    if (!displayMode)
        themePreference.setScope(value === "public" ? "public" : "staff");
});
</script>

<template>
    <CustomerDisplayWindow
        v-if="displayMode"
        :theme="currentTheme"
        :next-theme="nextTheme"
        @toggle-theme="themePreference.toggle"
    />
    <AppShell
        v-else
        :kind="shell"
        :theme="currentTheme"
        :next-theme="nextTheme"
        @toggle-theme="themePreference.toggle"
    >
        <div class="shell-picker" aria-label="Preview shell">
            <span>Preview shell</span>
            <button
                v-for="kind in shells"
                :key="kind"
                type="button"
                :aria-pressed="shell === kind"
                @click="shell = kind"
            >
                {{ kind }}
            </button>
        </div>
        <CheckoutPreview v-if="shell === 'checkout'" />
        <ComponentGallery v-else :public-view="shell === 'public'" />
    </AppShell>
</template>
