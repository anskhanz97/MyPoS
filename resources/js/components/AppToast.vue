<script setup lang="ts">
withDefaults(
    defineProps<{
        tone?: "success" | "error";
        title: string;
        message: string;
    }>(),
    { tone: "success" },
);
</script>

<template>
    <aside class="toast" :class="`toast--${tone}`" role="status">
        <strong>{{ title }}</strong
        ><span>{{ message }}</span>
    </aside>
</template>
