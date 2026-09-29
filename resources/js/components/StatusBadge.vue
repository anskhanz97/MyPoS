<script setup lang="ts">
withDefaults(
    defineProps<{
        tone?: "neutral" | "success" | "warning" | "danger" | "info";
    }>(),
    { tone: "neutral" },
);
</script>

<template>
    <span class="status-badge" :class="`status-badge--${tone}`"
        ><span class="status-badge__mark" aria-hidden="true"></span><slot
    /></span>
</template>
