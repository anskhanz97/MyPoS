<script setup lang="ts">
import { ref } from "vue";
import AppButton from "./AppButton.vue";

const dialog = ref<HTMLDialogElement | null>(null);
function open(): void {
    dialog.value?.showModal();
}
function close(): void {
    dialog.value?.close();
}
defineExpose({ open, close });
</script>

<template>
    <dialog ref="dialog" class="app-dialog" @click.self="close">
        <form method="dialog" class="app-dialog__content">
            <header><slot name="title" /></header>
            <div class="app-dialog__body"><slot /></div>
            <footer>
                <AppButton variant="quiet" @click="close">Cancel</AppButton
                ><slot name="action" />
            </footer>
        </form>
    </dialog>
</template>
