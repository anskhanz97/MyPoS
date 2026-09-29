<script setup lang="ts">
import type { Theme } from "../theme/themePreference";

defineProps<{ theme: Theme; nextTheme: Theme }>();
defineEmits<{ toggle: [] }>();
</script>

<template>
    <button
        class="theme-toggle"
        type="button"
        :aria-pressed="theme === 'dark'"
        @click="$emit('toggle')"
    >
        <span aria-hidden="true">{{ theme === "dark" ? "☾" : "☀" }}</span>
        <span>Use {{ nextTheme }} mode</span>
    </button>
</template>
