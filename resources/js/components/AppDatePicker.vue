<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useAttrs, useId } from "vue";
import AppDropdown from "./AppDropdown.vue";
import AppSelect from "./AppSelect.vue";

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    modelValue?: string | undefined;
    min?: string | undefined;
    max?: string | undefined;
    disabled?: boolean | undefined;
    required?: boolean | undefined;
}>();
const emit = defineEmits<{
    "update:modelValue": [value: string];
    change: [event: Event];
}>();
const attrs = useAttrs();
const root = ref<HTMLSpanElement>();
const input = ref<HTMLInputElement>();
const dropdown = ref<InstanceType<typeof AppDropdown>>();
const label = ref("Date");
const calendarId = useId();
const focused = ref(iso(new Date()));
const month = ref(startOfMonth(new Date()));
const heading = computed(() =>
    month.value.toLocaleDateString("en-GB", { month: "long", year: "numeric" }),
);
const months = Array.from({ length: 12 }, (_, index) =>
    new Date(2024, index, 1).toLocaleDateString("en-GB", { month: "long" }),
);
const years = computed(() => {
    const first = props.min
        ? parse(props.min).getFullYear()
        : Math.min(1900, month.value.getFullYear());
    const last = props.max
        ? parse(props.max).getFullYear()
        : Math.max(new Date().getFullYear() + 20, month.value.getFullYear());
    return Array.from(
        { length: Math.max(0, last - first + 1) },
        (_, index) => last - index,
    );
});
const selectedMonth = computed({
    get: () => month.value.getMonth(),
    set: (value: string | number) =>
        jump(month.value.getFullYear(), Number(value)),
});
const selectedYear = computed({
    get: () => month.value.getFullYear(),
    set: (value: string | number) =>
        jump(Number(value), month.value.getMonth()),
});
function jump(year: number, monthIndex: number): void {
    const lastDay = new Date(year, monthIndex + 1, 0).getDate();
    const target = new Date(
        year,
        monthIndex,
        Math.min(parse(focused.value).getDate(), lastDay),
        12,
    );
    focused.value = clamp(iso(target));
    month.value = startOfMonth(parse(focused.value));
}
function monthOutsideLimits(monthIndex: number): boolean {
    const year = month.value.getFullYear();
    return Boolean(
        (props.min && iso(new Date(year, monthIndex + 1, 0, 12)) < props.min) ||
        (props.max && iso(new Date(year, monthIndex, 1, 12)) > props.max),
    );
}
const weeks = computed(() => {
    const start = new Date(month.value);
    start.setDate(1 - ((start.getDay() + 6) % 7));
    return Array.from({ length: 6 }, (_, week) =>
        Array.from({ length: 7 }, (_, day) => {
            const date = new Date(start);
            date.setDate(start.getDate() + week * 7 + day);
            return {
                key: iso(date),
                date,
                label: date.toLocaleDateString("en-GB", {
                    weekday: "long",
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                }),
            };
        }),
    );
});
function iso(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}
function parse(value: string): Date {
    return new Date(`${value}T12:00:00`);
}
function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1, 12);
}
function unavailable(value: string): boolean {
    return Boolean(
        (props.min && value < props.min) || (props.max && value > props.max),
    );
}
function clamp(value: string): string {
    return props.min && value < props.min
        ? props.min
        : props.max && value > props.max
          ? props.max
          : value;
}
function focusDay(): void {
    void nextTick(() =>
        document.getElementById(`${calendarId}-${focused.value}`)?.focus(),
    );
}
function opened(): void {
    focused.value = clamp(props.modelValue || iso(new Date()));
    month.value = startOfMonth(parse(focused.value));
    focusDay();
}
function moveMonth(amount: number): void {
    const next = new Date(month.value);
    next.setMonth(next.getMonth() + amount);
    month.value = next;
    focused.value = clamp(iso(next));
}
function monthUnavailable(amount: number): boolean {
    const edge = new Date(month.value);
    edge.setMonth(edge.getMonth() + (amount < 0 ? 0 : 1), amount < 0 ? 0 : 1);
    return unavailable(iso(edge));
}
function choose(value: string): void {
    if ((value && unavailable(value)) || !input.value) return;
    input.value.value = value;
    input.value.dispatchEvent(new Event("input", { bubbles: true }));
    input.value.dispatchEvent(new Event("change", { bubbles: true }));
    dropdown.value?.close();
    input.value.focus();
}
function keys(event: KeyboardEvent): void {
    if (
        event.target === dropdown.value?.trigger &&
        ["ArrowDown", "ArrowUp"].includes(event.key)
    ) {
        event.preventDefault();
        if (dropdown.value?.isOpen()) focusDay();
        else void dropdown.value?.open();
        return;
    }
    if (!dropdown.value?.isOpen()) return;
    if (!(event.target as HTMLElement).hasAttribute("data-day")) return;
    const date = parse(focused.value);
    const steps: Record<string, number> = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -7,
        ArrowDown: 7,
    };
    const step = steps[event.key];
    if (step !== undefined) date.setDate(date.getDate() + step);
    else if (event.key === "Home")
        date.setDate(date.getDate() - ((date.getDay() + 6) % 7));
    else if (event.key === "End")
        date.setDate(date.getDate() + 6 - ((date.getDay() + 6) % 7));
    else if (event.key === "PageUp" || event.key === "PageDown") {
        const day = date.getDate();
        date.setDate(1);
        date.setMonth(date.getMonth() + (event.key === "PageUp" ? -1 : 1));
        const last = new Date(
            date.getFullYear(),
            date.getMonth() + 1,
            0,
        ).getDate();
        date.setDate(Math.min(day, last));
    } else return;
    event.preventDefault();
    focused.value = clamp(iso(date));
    month.value = startOfMonth(parse(focused.value));
    focusDay();
}
onMounted(() => {
    const parent = root.value?.closest("label");
    if (parent) {
        const copy = parent.cloneNode(true) as HTMLElement;
        copy.querySelectorAll(".app-date-picker, small").forEach((element) =>
            element.remove(),
        );
        label.value = copy.textContent?.trim() || "Date";
    } else label.value = String(attrs["aria-label"] ?? "Date");
});
</script>

<template>
    <span ref="root" class="app-date-picker">
        <input
            ref="input"
            v-bind="$attrs"
            :aria-label="String($attrs['aria-label'] ?? label)"
            type="date"
            :value="modelValue"
            :min="min"
            :max="max"
            :required="required"
            :disabled="disabled"
            @click.prevent="
                input?.focus();
                dropdown?.open();
            "
            @input="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
            @change="emit('change', $event)"
            @keydown.alt.down.prevent="dropdown?.open()"
        />
        <AppDropdown
            ref="dropdown"
            calendar
            :disabled="disabled"
            :aria-label="`${label} calendar`"
            @opened="opened"
            @keydown="keys"
        >
            <template #trigger>
                <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    width="19"
                    height="19"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <rect x="3" y="5" width="18" height="16" rx="3" />
                    <path
                        d="M7 3v4m10-4v4M3 11h18m-12 4h.01M12 15h.01M16 15h.01"
                    />
                </svg>
            </template>
            <div class="app-date-picker__header">
                <button
                    type="button"
                    aria-label="Previous month"
                    :disabled="monthUnavailable(-1)"
                    @click="moveMonth(-1)"
                >
                    <svg
                        aria-hidden="true"
                        width="16"
                        height="16"
                        viewBox="0 0 16 16"
                        fill="none"
                    >
                        <path
                            d="m10 4-4 4 4 4"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>
                <span :id="calendarId" class="sr-only" aria-live="polite">{{
                    heading
                }}</span>
                <div class="app-date-picker__selectors">
                    <AppSelect v-model="selectedMonth" aria-label="Month">
                        <option
                            v-for="(name, index) in months"
                            :key="name"
                            :value="index"
                            :disabled="monthOutsideLimits(index)"
                        >
                            {{ name }}
                        </option>
                    </AppSelect>
                    <AppSelect v-model="selectedYear" aria-label="Year">
                        <option v-for="year in years" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </AppSelect>
                </div>
                <button
                    type="button"
                    aria-label="Next month"
                    :disabled="monthUnavailable(1)"
                    @click="moveMonth(1)"
                >
                    <svg
                        aria-hidden="true"
                        width="16"
                        height="16"
                        viewBox="0 0 16 16"
                        fill="none"
                    >
                        <path
                            d="m6 4 4 4-4 4"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>
            </div>
            <table
                class="app-date-picker__grid"
                role="grid"
                :aria-labelledby="calendarId"
            >
                <thead>
                    <tr>
                        <th
                            v-for="day in [
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                                'Sun',
                            ]"
                            :key="day"
                            scope="col"
                        >
                            {{ day }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(week, index) in weeks" :key="index">
                        <td
                            v-for="day in week"
                            :key="day.key"
                            :aria-selected="day.key === modelValue"
                        >
                            <button
                                :id="`${calendarId}-${day.key}`"
                                type="button"
                                data-day
                                :aria-label="day.label"
                                :aria-current="
                                    day.key === iso(new Date())
                                        ? 'date'
                                        : undefined
                                "
                                :class="{
                                    'is-selected': day.key === modelValue,
                                    'is-outside':
                                        day.date.getMonth() !==
                                        month.getMonth(),
                                }"
                                :tabindex="day.key === focused ? 0 : -1"
                                :disabled="unavailable(day.key)"
                                @click="choose(day.key)"
                                @focus="focused = day.key"
                            >
                                {{ day.date.getDate() }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="app-date-picker__footer">
                <button
                    type="button"
                    :disabled="unavailable(iso(new Date()))"
                    @click="choose(iso(new Date()))"
                >
                    Today</button
                ><button v-if="!required" type="button" @click="choose('')">
                    Clear
                </button>
            </div>
        </AppDropdown>
    </span>
</template>
