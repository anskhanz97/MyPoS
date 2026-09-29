<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: "primary" | "secondary" | "quiet" | "danger";
        disabled?: boolean;
        busy?: boolean;
    }>(),
    {
        variant: "secondary",
        disabled: false,
        busy: false,
    },
);
</script>

<template>
    <button
        class="app-button"
        :class="`app-button--${variant}`"
        type="button"
        :disabled="disabled || busy"
        :aria-busy="busy"
    >
        <span v-if="busy" class="spinner" aria-hidden="true"></span>
        <slot />
    </button>
</template>
