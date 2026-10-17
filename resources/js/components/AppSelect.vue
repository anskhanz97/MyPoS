<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    onUpdated,
    ref,
    useAttrs,
    useId,
} from "vue";
import AppDropdown from "./AppDropdown.vue";

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    modelValue?: string | number | null | undefined;
    disabled?: boolean | undefined;
}>();
const emit = defineEmits<{
    "update:modelValue": [value: string | number];
    change: [event: Event];
}>();
const attrs = useAttrs();
const nativeName = computed(() =>
    typeof attrs.name === "string" ? attrs.name : undefined,
);
const root = ref<HTMLSpanElement>();
const native = ref<HTMLSelectElement>();
const dropdown = ref<InstanceType<typeof AppDropdown>>();
const options = ref<{ label: string; disabled: boolean; value: string }[]>([]);
const selected = ref(-1);
const active = ref(-1);
const label = ref("");
const invalid = ref(false);
const errorId = useId();
const localValue = ref<string | number>();
let observer: MutationObserver | undefined;
let search = "";
let searchedAt = 0;
const value = computed({
    get: () =>
        props.modelValue === undefined ? localValue.value : props.modelValue,
    set: (value) => {
        if (value !== null && value !== undefined) {
            localValue.value = value;
            emit("update:modelValue", value);
        }
    },
});

function sync(): void {
    if (!native.value) return;
    const next = [...native.value.options].map((option) => ({
        label: option.label,
        value: option.value,
        disabled: option.disabled,
    }));
    if (JSON.stringify(next) !== JSON.stringify(options.value))
        options.value = next;
    selected.value = native.value.selectedIndex;
    const parentLabel = root.value?.closest("label");
    if (parentLabel) {
        const copy = parentLabel.cloneNode(true) as HTMLElement;
        copy.querySelectorAll(".app-select, small, input, button").forEach(
            (element) => element.remove(),
        );
        label.value = copy.textContent?.trim() ?? "";
    }
}
function choose(index: number): void {
    if (!native.value || options.value[index]?.disabled) return;
    native.value.selectedIndex = index;
    native.value.dispatchEvent(new Event("change", { bubbles: true }));
    sync();
    dropdown.value?.close(true);
}
function focusActive(): void {
    void nextTick(() =>
        document
            .getElementById(
                `${dropdown.value?.trigger?.getAttribute("aria-controls")}-option-${active.value}`,
            )
            ?.focus(),
    );
}
function opened(): void {
    active.value =
        selected.value >= 0
            ? selected.value
            : options.value.findIndex((option) => !option.disabled);
    focusActive();
}
function keys(event: KeyboardEvent): void {
    if (["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) {
        event.preventDefault();
        if (!dropdown.value?.isOpen()) {
            void dropdown.value?.open();
            return;
        }
        const enabled = options.value
            .map((option, index) => (option.disabled ? -1 : index))
            .filter((index) => index >= 0);
        const current = enabled.indexOf(active.value);
        active.value =
            event.key === "Home"
                ? enabled[0]!
                : event.key === "End"
                  ? enabled.at(-1)!
                  : enabled[
                        (current +
                            (event.key === "ArrowUp" ? -1 : 1) +
                            enabled.length) %
                            enabled.length
                    ]!;
        focusActive();
    } else if (event.key === "Escape" && dropdown.value?.isOpen()) {
        event.preventDefault();
        dropdown.value?.close(true);
    } else if (event.key === "Tab") {
        dropdown.value?.close(true);
    } else if (
        event.key.length === 1 &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey &&
        event.key !== " "
    ) {
        event.preventDefault();
        search = Date.now() - searchedAt > 700 ? event.key : search + event.key;
        searchedAt = Date.now();
        const index = options.value.findIndex(
            (option) =>
                !option.disabled &&
                option.label
                    .toLocaleLowerCase()
                    .startsWith(search.toLocaleLowerCase()),
        );
        if (index >= 0) {
            if (!dropdown.value?.isOpen()) choose(index);
            else {
                active.value = index;
                focusActive();
            }
        }
    }
}
onMounted(() => {
    if (
        native.value &&
        props.modelValue === undefined &&
        native.value.options.length
    )
        value.value = native.value.options[0]!.value;
    sync();
    observer = new MutationObserver(sync);
    if (native.value)
        observer.observe(native.value, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
        });
});
onUpdated(sync);
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <span ref="root" class="app-select">
        <AppDropdown
            ref="dropdown"
            v-bind="attrs"
            :aria-label="attrs['aria-label'] ?? (label || undefined)"
            select
            :disabled="disabled"
            :aria-invalid="invalid || undefined"
            :aria-describedby="invalid ? errorId : attrs['aria-describedby']"
            @opened="opened"
            @keydown="keys"
        >
            <template #trigger>{{
                options[selected]?.label ??
                options[0]?.label ??
                "Select an option"
            }}</template>
            <button
                v-for="(option, index) in options"
                :id="`${dropdown?.trigger?.getAttribute('aria-controls')}-option-${index}`"
                :key="`${option.value}-${index}`"
                type="button"
                role="option"
                :aria-selected="index === selected"
                :disabled="option.disabled"
                :tabindex="index === active ? 0 : -1"
                @click="choose(index)"
            >
                <span>{{ option.label }}</span
                ><svg
                    v-if="index === selected"
                    aria-hidden="true"
                    viewBox="0 0 16 16"
                    width="16"
                    height="16"
                    fill="none"
                >
                    <path
                        d="m3 8 3 3 7-7"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
        </AppDropdown>
        <select
            ref="native"
            v-model="value"
            hidden
            tabindex="-1"
            aria-hidden="true"
            :name="nativeName"
            :required="
                Boolean(
                    attrs.required !== undefined && attrs.required !== false,
                )
            "
            :disabled="disabled"
            @change="
                (event) => {
                    invalid = false;
                    sync();
                    emit('change', event);
                }
            "
            @invalid.prevent="
                invalid = true;
                dropdown?.trigger?.focus();
            "
        >
            <slot />
        </select>
        <span
            v-if="invalid"
            :id="errorId"
            class="app-select__error"
            role="alert"
            >Choose an option to continue.</span
        >
    </span>
</template>
