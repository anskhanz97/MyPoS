<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from "vue";
import { claimDropdown, releaseDropdown } from "../shared/dropdown";

defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        disabled?: boolean | undefined;
        select?: boolean;
        calendar?: boolean;
        align?: "start" | "end";
    }>(),
    { align: "start", disabled: false, select: false },
);
const emit = defineEmits<{ opened: []; keydown: [event: KeyboardEvent] }>();
const trigger = ref<HTMLButtonElement>();
const panel = ref<HTMLDivElement>();
const opened = ref(false);
const panelId = useId();
const identity = Symbol("dropdown");
const portalTarget = ref<HTMLElement | string>("body");

function position(): void {
    if (!trigger.value || !panel.value) return;
    const rect = trigger.value.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    const width = Math.min(
        Math.max(rect.width, props.calendar ? 304 : props.select ? 180 : 200),
        viewportWidth - 24,
    );
    const below = window.innerHeight - rect.bottom - 16;
    const above = rect.top - 16;
    const flip = below < (props.calendar ? 390 : 220) && above > below;
    const height = Math.min(
        props.calendar ? 420 : 320,
        Math.max(80, flip ? above : below),
    );
    Object.assign(panel.value.style, {
        width: `${width}px`,
        maxHeight: `${height}px`,
        left: `${Math.max(12, Math.min(props.align === "end" ? rect.right - width : rect.left, viewportWidth - width - 12))}px`,
        top: flip ? "auto" : `${rect.bottom + 6}px`,
        bottom: flip ? `${window.innerHeight - rect.top + 6}px` : "auto",
    });
}
function close(focus = false): void {
    releaseDropdown(identity);
    if (panel.value?.matches(":popover-open")) panel.value.hidePopover();
    if (focus) trigger.value?.focus();
}
async function open(): Promise<void> {
    if (props.disabled || !panel.value) return;
    if (!trigger.value?.closest("[popover]:popover-open")) {
        claimDropdown(identity, () => close());
    }
    position();
    panel.value.showPopover();
    await nextTick();
    emit("opened");
}
function toggle(): void {
    if (panel.value?.matches(":popover-open")) close();
    else void open();
}
function onToggle(event: Event): void {
    opened.value = (event as ToggleEvent).newState === "open";
    if (opened.value) {
        window.addEventListener("resize", position);
        window.addEventListener("scroll", followTrigger, true);
    } else {
        releaseDropdown(identity);
        window.removeEventListener("resize", position);
        window.removeEventListener("scroll", followTrigger, true);
    }
}
function followTrigger(event: Event): void {
    if (panel.value?.contains(event.target as Node)) return;
    const rect = trigger.value?.getBoundingClientRect();
    if (rect && (rect.bottom < 0 || rect.top > window.innerHeight)) close();
    else position();
}
function keys(event: KeyboardEvent): void {
    emit("keydown", event);
    if (event.defaultPrevented) return;
    if (event.key === "Escape" && panel.value?.matches(":popover-open")) {
        event.preventDefault();
        close(true);
    }
    if (
        !props.select &&
        !props.calendar &&
        ["ArrowDown", "ArrowUp"].includes(event.key)
    ) {
        event.preventDefault();
        void open().then(() =>
            panel.value
                ?.querySelector<HTMLButtonElement>("button:not(:disabled)")
                ?.focus(),
        );
    }
}
function menuKeys(event: KeyboardEvent): void {
    event.stopPropagation();
    if (props.calendar) {
        keys(event);
        if (event.key === "Tab") close(true);
        return;
    }
    if (event.key === "Tab") {
        close(true);
        return;
    }
    if (props.select) {
        keys(event);
        return;
    }
    const buttons = [
        ...(panel.value?.querySelectorAll<HTMLButtonElement>(
            "button:not(:disabled)",
        ) ?? []),
    ];
    const current = buttons.indexOf(
        document.activeElement as HTMLButtonElement,
    );
    if (event.key === "Escape") {
        event.preventDefault();
        close(true);
    }
    if (["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) {
        event.preventDefault();
        const index =
            event.key === "Home"
                ? 0
                : event.key === "End"
                  ? buttons.length - 1
                  : (current +
                        (event.key === "ArrowUp" ? -1 : 1) +
                        buttons.length) %
                    buttons.length;
        buttons[index]?.focus();
    }
}
onMounted(() => {
    portalTarget.value =
        trigger.value?.closest("[popover], dialog") ?? document.body;
});
onBeforeUnmount(() => {
    close();
    window.removeEventListener("resize", position);
    window.removeEventListener("scroll", followTrigger, true);
});
defineExpose({
    open,
    close,
    opened,
    trigger,
    isOpen: () => panel.value?.matches(":popover-open") ?? false,
});
</script>

<template>
    <span class="app-dropdown" :class="{ 'app-dropdown--select': select }">
        <button
            ref="trigger"
            v-bind="$attrs"
            type="button"
            class="app-dropdown__trigger"
            :disabled="disabled"
            :role="select ? 'combobox' : undefined"
            :aria-haspopup="calendar ? 'dialog' : select ? 'listbox' : 'menu'"
            :aria-expanded="opened"
            :aria-controls="panelId"
            @click="toggle"
            @keydown="keys"
        >
            <span class="app-dropdown__value"><slot name="trigger" /></span>
            <svg
                v-if="!calendar"
                aria-hidden="true"
                viewBox="0 0 16 16"
                width="14"
                height="14"
                fill="none"
            >
                <path
                    d="m4 6 4 4 4-4"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </button>
        <Teleport :to="portalTarget">
            <div
                :id="panelId"
                ref="panel"
                popover="auto"
                class="app-dropdown__panel"
                :class="{ 'app-date-picker__panel': calendar }"
                :role="calendar ? 'dialog' : select ? 'listbox' : 'menu'"
                :aria-label="String($attrs['aria-label'] ?? 'Options')"
                @toggle="onToggle"
                @keydown="menuKeys"
                @click="
                    (event) => {
                        if (
                            !calendar &&
                            (event.target as HTMLElement).closest(
                                'button:not(:disabled)',
                            )
                        )
                            close(true);
                    }
                "
            >
                <slot />
            </div>
        </Teleport>
    </span>
</template>
