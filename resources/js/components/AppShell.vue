<script setup lang="ts">
import { computed, ref, watch } from "vue";
import type { Theme } from "../theme/themePreference";
import AppIcon, { type AppIconName } from "./AppIcon.vue";
import ThemeToggle from "./ThemeToggle.vue";

export type ShellKind = "management" | "checkout" | "public";

type NavigationGroup = {
    label: string;
    icon: AppIconName;
    description: string;
    items: string[];
};

const props = defineProps<{
    kind: ShellKind;
    theme: Theme;
    nextTheme: Theme;
    activeSection: string;
}>();

const emit = defineEmits<{
    toggleTheme: [];
    selectSection: [section: string];
    selectShell: [shell: ShellKind];
}>();

const navigationGroups: NavigationGroup[] = [
    {
        label: "Overview",
        icon: "overview",
        description: "Workspace and system state",
        items: ["Components", "States", "Shell notes"],
    },
    {
        label: "Setup",
        icon: "setup",
        description: "Merchant and access controls",
        items: ["Tenancy", "Access", "Platform", "Registers"],
    },
    {
        label: "Sell",
        icon: "sell",
        description: "Counter and after-sale work",
        items: ["Checkout", "Cash control", "Returns"],
    },
    {
        label: "Products",
        icon: "products",
        description: "Catalog and stock operations",
        items: [
            "Catalog",
            "Pricing",
            "Inventory",
            "Transfers & counts",
            "Purchasing",
        ],
    },
    {
        label: "Customers",
        icon: "customers",
        description: "Profiles and retention",
        items: ["Customers", "Loyalty", "Member profile"],
    },
    {
        label: "Team",
        icon: "team",
        description: "People and payroll",
        items: ["Workforce", "Payroll"],
    },
    {
        label: "Operations",
        icon: "operations",
        description: "Finance, reporting and services",
        items: [
            "Finance",
            "Reports",
            "Data transfer",
            "Notifications",
            "Integrations",
            "Fiscal",
        ],
    },
];
const shellOptions: ShellKind[] = ["management", "checkout", "public"];

const storedPanelState = localStorage.getItem("mypos.navigation.expanded");
const panelExpanded = ref(storedPanelState !== "false");
const mobileNavigationOpen = ref(false);
const selectedGroupLabel = ref(
    navigationGroups.find((group) => group.items.includes(props.activeSection))
        ?.label ?? "Overview",
);

const selectedGroup = computed(
    () =>
        navigationGroups.find(
            (group) => group.label === selectedGroupLabel.value,
        ) ?? navigationGroups[0]!,
);

watch(
    () => props.activeSection,
    (section) => {
        const group = navigationGroups.find((item) =>
            item.items.includes(section),
        );
        if (group) selectedGroupLabel.value = group.label;
    },
);

watch(panelExpanded, (expanded) => {
    localStorage.setItem("mypos.navigation.expanded", String(expanded));
});

function openGroup(group: NavigationGroup): void {
    selectedGroupLabel.value = group.label;
    panelExpanded.value = true;
}

function selectSection(section: string): void {
    emit("selectSection", section);
    mobileNavigationOpen.value = false;
}

function togglePanel(): void {
    panelExpanded.value = !panelExpanded.value;
}

function openMobileNavigation(): void {
    panelExpanded.value = true;
    mobileNavigationOpen.value = true;
}
</script>

<template>
    <div
        class="app-shell"
        :class="[
            `app-shell--${kind}`,
            { 'app-shell--nav-collapsed': !panelExpanded },
        ]"
    >
        <aside
            v-if="kind !== 'public'"
            id="application-navigation"
            class="application-navigation"
            :class="{
                'application-navigation--mobile-open': mobileNavigationOpen,
            }"
            aria-label="Application navigation"
        >
            <div class="nav-rail">
                <a class="brand" href="/" aria-label="MyPoS home">
                    <span class="brand__mark">M</span>
                    <span class="sr-only">MyPoS</span>
                </a>

                <nav class="nav-rail__groups" aria-label="Navigation groups">
                    <button
                        v-for="group in navigationGroups"
                        :key="group.label"
                        type="button"
                        :class="{
                            'is-active': selectedGroup.label === group.label,
                        }"
                        :aria-label="`Open ${group.label} navigation`"
                        :aria-pressed="selectedGroup.label === group.label"
                        :title="group.label"
                        @click="openGroup(group)"
                    >
                        <AppIcon :name="group.icon" />
                        <span>{{ group.label }}</span>
                    </button>
                </nav>

                <div class="nav-rail__status" title="Online simulation">
                    <i class="signal signal--online"></i>
                    <span class="sr-only">Online simulation</span>
                </div>
            </div>

            <div class="nav-panel">
                <div class="nav-panel__header">
                    <div>
                        <strong>{{ selectedGroup.label }}</strong>
                        <span>{{ selectedGroup.description }}</span>
                    </div>
                    <button
                        type="button"
                        class="nav-collapse"
                        aria-label="Collapse navigation"
                        @click="togglePanel"
                    >
                        <AppIcon name="collapse" />
                    </button>
                </div>

                <button type="button" class="merchant-context">
                    <span class="merchant-context__mark">SM</span>
                    <span>
                        <strong>Sample Merchant</strong>
                        <small>Lahore Test Store · LAB-01</small>
                    </span>
                    <span aria-hidden="true">⌄</span>
                </button>

                <nav
                    class="nav-panel__items"
                    :aria-label="`${selectedGroup.label} pages`"
                >
                    <button
                        v-for="item in selectedGroup.items"
                        :key="item"
                        type="button"
                        :class="{ 'is-active': activeSection === item }"
                        :aria-current="
                            activeSection === item ? 'page' : undefined
                        "
                        @click="selectSection(item)"
                    >
                        <span>{{ item }}</span>
                        <span aria-hidden="true">›</span>
                    </button>
                </nav>

                <div class="nav-panel__footer">
                    <span
                        ><i class="signal signal--online"></i>Online
                        simulation</span
                    >
                    <span><b>0</b> pending</span>
                </div>
            </div>
        </aside>

        <button
            v-if="kind !== 'public' && mobileNavigationOpen"
            type="button"
            class="navigation-scrim"
            aria-label="Close navigation"
            @click="mobileNavigationOpen = false"
        ></button>

        <header class="topbar">
            <div class="topbar__identity">
                <button
                    v-if="kind !== 'public'"
                    type="button"
                    class="mobile-menu"
                    aria-label="Open navigation"
                    aria-controls="application-navigation"
                    :aria-expanded="mobileNavigationOpen"
                    @click="openMobileNavigation"
                >
                    <AppIcon name="menu" />
                </button>
                <div>
                    <span class="topbar__scope">
                        {{
                            kind === "public"
                                ? "Member preview"
                                : selectedGroup.label
                        }}
                        <span aria-hidden="true">/</span>
                        {{ activeSection }}
                    </span>
                    <strong>
                        {{
                            kind === "public"
                                ? "Sample Merchant rewards"
                                : kind === "checkout"
                                  ? "Checkout workspace"
                                  : "Management workspace"
                        }}
                    </strong>
                </div>
            </div>

            <div class="topbar__actions">
                <button type="button" class="topbar-action" aria-label="Search">
                    <AppIcon name="search" />
                </button>
                <button
                    type="button"
                    class="topbar-action"
                    aria-label="Notifications"
                >
                    <AppIcon name="bell" />
                    <span class="notification-dot"></span>
                </button>
                <div class="shell-switcher" aria-label="Preview shell">
                    <button
                        v-for="shell in shellOptions"
                        :key="shell"
                        type="button"
                        :aria-pressed="kind === shell"
                        @click="$emit('selectShell', shell)"
                    >
                        {{ shell }}
                    </button>
                </div>
                <ThemeToggle
                    :theme="theme"
                    :next-theme="nextTheme"
                    @toggle="$emit('toggleTheme')"
                />
                <span class="user-avatar" title="Ayesha Khan">AK</span>
            </div>
        </header>

        <main id="workspace" class="workspace" tabindex="-1"><slot /></main>
    </div>
</template>
