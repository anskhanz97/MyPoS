<script setup lang="ts">
defineProps<{
    state: "loading" | "empty" | "error";
    title: string;
    message: string;
}>();
</script>

<template>
    <section
        class="state-panel"
        :class="`state-panel--${state}`"
        :aria-live="state === 'loading' ? 'polite' : undefined"
    >
        <div
            v-if="state === 'loading'"
            class="skeleton-stack"
            aria-hidden="true"
        >
            <i></i><i></i><i></i>
        </div>
        <span v-else class="state-panel__icon" aria-hidden="true">{{
            state === "error" ? "!" : "○"
        }}</span>
        <div>
            <strong>{{ title }}</strong>
            <p>{{ message }}</p>
        </div>
    </section>
</template>
