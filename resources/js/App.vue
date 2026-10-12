<script setup lang="ts">
import { ref, watch } from "vue";
import AppShell, { type ShellKind } from "./components/AppShell.vue";
import { useThemePreference } from "./theme/themePreference";
import ComponentGallery from "./views/ComponentGallery.vue";
import CheckoutPreview from "./views/CheckoutPreview.vue";
import CustomerDisplayWindow from "./views/CustomerDisplayWindow.vue";

const shell = ref<ShellKind>("management");
const activeSection = ref("Tenancy");
const previousManagementSection = ref("Tenancy");
const themePreference = useThemePreference();
const currentTheme = themePreference.theme;
const nextTheme = themePreference.nextTheme;
const displayMode =
    new URL(window.location.href).searchParams.get("display") === "1";

if (displayMode) {
    themePreference.setScope("display");
}

watch(shell, (value) => {
    if (!displayMode) {
        themePreference.setScope(value === "public" ? "public" : "staff");
    }
});

function selectShell(value: ShellKind): void {
    shell.value = value;
    if (value === "checkout") {
        activeSection.value = "Checkout";
    } else if (value === "public") {
        activeSection.value = "Member profile";
    } else {
        activeSection.value = previousManagementSection.value;
    }
}

function selectSection(section: string): void {
    if (section === "Checkout") {
        selectShell("checkout");
        return;
    }
    if (section === "Member profile") {
        selectShell("public");
        return;
    }
    previousManagementSection.value = section;
    activeSection.value = section;
    shell.value = "management";
}
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
        :active-section="activeSection"
        @toggle-theme="themePreference.toggle"
        @select-section="selectSection"
    >
        <CheckoutPreview v-if="shell === 'checkout'" />
        <ComponentGallery
            v-else
            :public-view="shell === 'public'"
            :active-section="activeSection"
        />
    </AppShell>
</template>
