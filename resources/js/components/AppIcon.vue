<script setup lang="ts">
export type AppIconName =
    | "overview"
    | "setup"
    | "sell"
    | "products"
    | "customers"
    | "team"
    | "operations"
    | "menu"
    | "collapse"
    | "search"
    | "bell";

defineProps<{ name: AppIconName }>();
</script>

<template>
    <svg
        class="app-icon"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="name === 'overview'">
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
        </template>
        <template v-else-if="name === 'setup'">
            <circle cx="12" cy="12" r="3" />
            <path
                d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"
            />
        </template>
        <template v-else-if="name === 'sell'">
            <path d="M4 6h16l-1.5 9h-13Z" />
            <path d="M8 6V4h8v2M8 19h.01M16 19h.01" />
        </template>
        <template v-else-if="name === 'products'">
            <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z" />
            <path d="m4.5 7.7 7.5 4.2 7.5-4.2M12 12v9" />
        </template>
        <template v-else-if="name === 'customers'">
            <circle cx="9" cy="8" r="3" />
            <path
                d="M3.5 19a5.5 5.5 0 0 1 11 0M16 8.5a2.5 2.5 0 0 1 0 5M17 16a4 4 0 0 1 3.5 3"
            />
        </template>
        <template v-else-if="name === 'team'">
            <path
                d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
            />
            <path d="M2.5 20a5.5 5.5 0 0 1 11 0M10.5 20a5.5 5.5 0 0 1 11 0" />
        </template>
        <template v-else-if="name === 'operations'">
            <path d="M4 5h16M4 12h16M4 19h16" />
            <circle cx="8" cy="5" r="2" fill="currentColor" stroke="none" />
            <circle cx="16" cy="12" r="2" fill="currentColor" stroke="none" />
            <circle cx="10" cy="19" r="2" fill="currentColor" stroke="none" />
        </template>
        <template v-else-if="name === 'menu'">
            <path d="M4 6h16M4 12h16M4 18h16" />
        </template>
        <template v-else-if="name === 'collapse'">
            <path d="m14 6-6 6 6 6" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </template>
        <template v-else>
            <path
                d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"
            />
        </template>
    </svg>
</template>
