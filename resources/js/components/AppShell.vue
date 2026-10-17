<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AppDropdown from "./AppDropdown.vue";
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
}>();

const navigationGroups: NavigationGroup[] = [
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

const storedPanelState = localStorage.getItem("mypos.navigation.expanded");
const panelExpanded = ref(storedPanelState !== "false");
const mobileNavigationOpen = ref(false);

const logoutBusy = ref(false);
const accountMessage = ref("");
const selectedGroupLabel = ref(
    navigationGroups.find((group) => group.items.includes(props.activeSection))
        ?.label ?? "Setup",
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
    if (window.matchMedia("(max-width: 1023px)").matches) {
        mobileNavigationOpen.value = false;
        return;
    }
    panelExpanded.value = !panelExpanded.value;
}

function openMobileNavigation(): void {
    panelExpanded.value = true;
    mobileNavigationOpen.value = true;
}
async function logout(): Promise<void> {
    logoutBusy.value = true;
    accountMessage.value = "";
    try {
        const csrfToken = document.cookie
            .split("; ")
            .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
            ?.slice(11);
        const response = await fetch("/api/v1/auth/logout", {
            method: "DELETE",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                ...(csrfToken
                    ? { "X-XSRF-TOKEN": decodeURIComponent(csrfToken) }
                    : {}),
            },
        });
        if (response.ok || response.status === 401) {
            selectSection("Access");
        } else {
            accountMessage.value = "Unable to log out. Please try again.";
        }
    } catch {
        accountMessage.value = "Unable to log out. Please try again.";
    } finally {
        logoutBusy.value = false;
    }
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

                <div class="sidebar-tools">
                    <button
                        type="button"
                        class="sidebar-tool"
                        aria-label="Notifications"
                        @click="selectSection('Notifications')"
                    >
                        <AppIcon name="bell" />
                    </button>
                    <ThemeToggle
                        :theme="theme"
                        :next-theme="nextTheme"
                        @toggle="$emit('toggleTheme')"
                    />
                    <AppDropdown
                        class="sidebar-tool"
                        aria-label="Profile and logout"
                    >
                        <template #trigger
                            ><span class="user-avatar">AK</span></template
                        >
                        <strong>Ayesha Khan</strong>
                        <button
                            type="button"
                            role="menuitem"
                            @click="selectSection('Access')"
                        >
                            Profile
                        </button>
                        <button
                            type="button"
                            role="menuitem"
                            :disabled="logoutBusy"
                            @click="logout"
                        >
                            {{ logoutBusy ? "Logging out..." : "Log out" }}
                        </button>
                        <p v-if="accountMessage" role="alert">
                            {{ accountMessage }}
                        </p>
                    </AppDropdown>
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
            </div>
            <button
                type="button"
                class="sidebar-edge-toggle"
                :aria-label="
                    panelExpanded ? 'Collapse navigation' : 'Expand navigation'
                "
                :aria-expanded="panelExpanded"
                aria-controls="application-navigation"
                @click="togglePanel"
            >
                <AppIcon name="collapse" />
            </button>
        </aside>

        <button
            v-if="mobileNavigationOpen"
            type="button"
            class="navigation-scrim"
            aria-label="Close navigation"
            @click="mobileNavigationOpen = false"
        ></button>

        <button
            type="button"
            class="mobile-menu sidebar-mobile-trigger"
            aria-label="Open navigation"
            aria-controls="application-navigation"
            :aria-expanded="mobileNavigationOpen"
            @click="openMobileNavigation"
        >
            <AppIcon name="menu" />
        </button>

        <main id="workspace" class="workspace" tabindex="-1"><slot /></main>
    </div>
</template>
