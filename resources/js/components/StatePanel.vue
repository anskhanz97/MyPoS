<script setup lang="ts">
import { computed } from "vue";
import AppIcon, { type AppIconName } from "./AppIcon.vue";
const props = defineProps<{
    state: "loading" | "empty" | "error";
    title: string;
    message: string;
    icon?: AppIconName;
}>();
const contextIcon = computed<AppIconName>(() => {
    if (props.icon) return props.icon;
    if (props.state === "error") return "alert";
    const context = `${props.title} ${props.message}`.toLowerCase();
    if (/stock|product|inventory|batch/.test(context)) return "products";
    if (/customer|member|loyalty/.test(context)) return "customers";
    if (/staff|payroll|attendance|team/.test(context)) return "team";
    if (/report|receipt|sale|document|invoice|transaction/.test(context))
        return "document";
    return "search";
});
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
        <span v-else class="state-panel__icon" aria-hidden="true"
            ><AppIcon :name="contextIcon"
        /></span>
        <div>
            <strong>{{ title }}</strong>
            <p>{{ message }}</p>
        </div>
    </section>
</template>
