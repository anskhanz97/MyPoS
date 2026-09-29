<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import ThemeToggle from "../components/ThemeToggle.vue";
import {
    channelName,
    parseDisplayLocation,
    readProjection,
    type DisplayProjection,
} from "../display/customerDisplayChannel";
import type { Theme } from "../theme/themePreference";

defineProps<{ theme: Theme; nextTheme: Theme }>();
defineEmits<{ toggleTheme: [] }>();

const location = parseDisplayLocation(new URL(window.location.href));
const connection = ref<"connected" | "waiting" | "invalid">(
    location ? "waiting" : "invalid",
);
const projection = ref<DisplayProjection | null>(
    location ? readProjection(localStorage, location) : null,
);
let channel: BroadcastChannel | null = null;
let clearTimer: number | null = null;

function accept(value: DisplayProjection): void {
    if (!location || value.registerId !== location.registerId) return;
    if (projection.value && value.sequence <= projection.value.sequence) return;
    projection.value = value;
    connection.value = "connected";
    if (clearTimer !== null) window.clearTimeout(clearTimer);
    if (value.state === "completed") {
        clearTimer = window.setTimeout(() => {
            projection.value = {
                schemaVersion: 1,
                registerId: location.registerId,
                sequence: value.sequence + 1,
                state: "idle",
                message: "Welcome",
                lines: [],
                totals: null,
            };
        }, 30_000);
    }
}

onMounted(() => {
    if (!location) return;
    channel = new BroadcastChannel(channelName(location.channelId));
    channel.onmessage = (event: MessageEvent<unknown>) => {
        const value = event.data as {
            type?: string;
            projection?: DisplayProjection;
        };
        if (value.type === "projection" && value.projection)
            accept(value.projection);
    };
    channel.postMessage({
        type: "display-ready",
        registerId: location.registerId,
    });
    if (projection.value) connection.value = "connected";
});
onBeforeUnmount(() => {
    channel?.close();
    if (clearTimer !== null) window.clearTimeout(clearTimer);
});
</script>

<template>
    <main class="customer-display" aria-label="Customer display">
        <header>
            <div><span class="display-mark">M</span><strong>MyPoS</strong></div>
            <div class="display-tools">
                <span class="connection" :data-state="connection">{{
                    connection
                }}</span>
                <ThemeToggle
                    :theme="theme"
                    :next-theme="nextTheme"
                    @toggle="$emit('toggleTheme')"
                />
            </div>
        </header>
        <section v-if="connection === 'invalid'" class="display-message">
            <p class="eyebrow">Pairing required</p>
            <h1>This display link is invalid.</h1>
            <p>Return to the register and create a new display session.</p>
        </section>
        <section
            v-else-if="!projection || projection.state === 'idle'"
            class="display-message"
        >
            <p class="eyebrow">Lahore Test Store</p>
            <h1>{{ projection?.message ?? "Waiting for register…" }}</h1>
            <p>Your order will appear here.</p>
        </section>
        <section
            v-else-if="projection.state === 'completed'"
            class="display-message completed"
        >
            <p class="eyebrow">Payment complete</p>
            <h1>{{ projection.message }}</h1>
            <strong>PKR {{ projection.totals?.gross }}</strong>
            <p>This screen clears automatically for the next customer.</p>
        </section>
        <section
            v-else-if="projection.state !== 'cart'"
            class="display-message"
        >
            <p class="eyebrow">Counter update</p>
            <h1>{{ projection.message }}</h1>
            <p>Basket details are hidden while this order is not active.</p>
        </section>
        <section v-else class="display-order">
            <div class="display-title">
                <p class="eyebrow">Live order</p>
                <h1>{{ projection.message }}</h1>
            </div>
            <div class="display-lines">
                <article v-for="line in projection.lines" :key="line.name">
                    <div>
                        <strong>{{ line.name }}</strong
                        ><span>{{ line.quantity }} {{ line.unit }}</span>
                    </div>
                    <div class="line-price">
                        <strong>PKR {{ line.gross }}</strong
                        ><span v-if="line.discount !== '0.00'"
                            >Saved PKR {{ line.discount }}</span
                        >
                    </div>
                    <p v-if="line.extras.length">
                        {{ line.extras.join(" · ") }}
                    </p>
                </article>
            </div>
            <aside class="display-totals">
                <div>
                    <span>Subtotal</span
                    ><strong>PKR {{ projection.totals?.subtotal }}</strong>
                </div>
                <div class="discount">
                    <span>Discount</span
                    ><strong>− PKR {{ projection.totals?.discount }}</strong>
                </div>
                <div>
                    <span>Tax</span
                    ><strong>PKR {{ projection.totals?.tax }}</strong>
                </div>
                <div class="grand">
                    <span>Total</span
                    ><strong>PKR {{ projection.totals?.gross }}</strong>
                </div>
            </aside>
        </section>
    </main>
</template>

<style scoped>
.customer-display {
    min-height: 100vh;
    padding: clamp(1.25rem, 3vw, 3rem);
    background: var(--canvas);
    color: var(--text);
}
.customer-display header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.customer-display header > div {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.display-mark {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.35rem;
    background: var(--accent);
    color: var(--accent-contrast);
    font-weight: 900;
}
.display-tools {
    display: flex;
    gap: 1rem;
}
.connection {
    text-transform: capitalize;
    color: var(--text-muted);
}
.connection[data-state="connected"] {
    color: var(--success);
}
.display-message {
    min-height: 70vh;
    display: grid;
    place-content: center;
    text-align: center;
    gap: 0.8rem;
}
.display-message h1 {
    max-width: 16ch;
    margin: 0;
    font-size: clamp(3rem, 8vw, 7rem);
    line-height: 0.95;
}
.display-message strong {
    font-size: clamp(2rem, 5vw, 4rem);
    color: var(--accent);
}
.eyebrow {
    margin: 0;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-weight: 800;
}
.display-order {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(22rem, 0.6fr);
    gap: clamp(2rem, 5vw, 5rem);
    padding-top: clamp(2rem, 4vw, 4rem);
}
.display-title {
    grid-column: 1/-1;
}
.display-title h1 {
    margin: 0.25rem 0 0;
    font-size: clamp(2.5rem, 5vw, 5rem);
}
.display-lines {
    display: grid;
    gap: 1rem;
}
.display-lines article {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.5rem 2rem;
    padding: 1.25rem 0;
    border-bottom: 1px solid var(--border);
}
.display-lines article > div {
    display: grid;
    gap: 0.3rem;
}
.display-lines article strong {
    font-size: clamp(1.2rem, 2.5vw, 2rem);
}
.display-lines article span,
.display-lines article p {
    color: var(--text-muted);
}
.display-lines article p {
    grid-column: 1/-1;
    margin: 0;
}
.line-price {
    text-align: right;
}
.display-totals {
    align-self: start;
    display: grid;
    gap: 1rem;
    padding: 1.5rem;
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow);
}
.display-totals div {
    display: flex;
    justify-content: space-between;
    gap: 2rem;
    font-size: 1.2rem;
}
.display-totals .discount {
    color: var(--success);
}
.display-totals .grand {
    padding-top: 1rem;
    border-top: 2px solid var(--text);
    font-size: clamp(1.6rem, 3vw, 2.5rem);
}
@media (max-width: 760px) {
    .display-order {
        grid-template-columns: 1fr;
    }
    .display-title {
        grid-column: auto;
    }
    .display-tools .connection {
        display: none;
    }
}
</style>
