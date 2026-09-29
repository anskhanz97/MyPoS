<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
import {
    channelName,
    displayUrl,
    writeProjection,
    type DisplayProjection,
} from "../display/customerDisplayChannel";

const props = defineProps<{
    projection: Omit<DisplayProjection, "sequence">;
}>();

const enabled = ref(false);
const connection = ref<"off" | "waiting" | "connected" | "disconnected">("off");
const channelId = ref("");
const pairingCode = ref("");
let sequence = 0;
let channel: BroadcastChannel | null = null;
let displayWindow: Window | null = null;

function location() {
    return {
        channelId: channelId.value,
        registerId: props.projection.registerId,
    };
}

function publish(): void {
    if (!enabled.value || !channel) return;
    sequence += 1;
    const projection: DisplayProjection = { ...props.projection, sequence };
    writeProjection(localStorage, location(), projection);
    channel.postMessage({ type: "projection", projection });
}

function enable(): void {
    channelId.value = crypto.randomUUID();
    pairingCode.value =
        `${channelId.value.slice(0, 4)}-${channelId.value.slice(9, 13)}`.toUpperCase();
    channel = new BroadcastChannel(channelName(channelId.value));
    channel.onmessage = (event: MessageEvent<unknown>) => {
        const message = event.data as { type?: string; registerId?: string };
        if (
            message.type === "display-ready" &&
            message.registerId === props.projection.registerId
        ) {
            connection.value = "connected";
            publish();
        }
    };
    enabled.value = true;
    connection.value = "waiting";
    publish();
}

function disable(): void {
    if (channel) {
        sequence += 1;
        const projection: DisplayProjection = {
            schemaVersion: 1,
            registerId: props.projection.registerId,
            sequence,
            state: "idle",
            message: "Display disabled",
            lines: [],
            totals: null,
        };
        writeProjection(localStorage, location(), projection);
        channel.postMessage({ type: "projection", projection });
    }
    enabled.value = false;
    connection.value = "off";
    channel?.close();
    channel = null;
}

function openDisplay(): void {
    if (!enabled.value) return;
    displayWindow = window.open(
        displayUrl(location()),
        "mypos-customer-display",
        "popup,width=1100,height=760",
    );
    connection.value = displayWindow ? "waiting" : "disconnected";
}

function disconnect(): void {
    displayWindow?.close();
    displayWindow = null;
    connection.value = "disconnected";
}

watch(() => props.projection, publish, { deep: true });
onBeforeUnmount(() => channel?.close());
</script>

<template>
    <section class="display-control" aria-label="Customer display controls">
        <div>
            <p class="kicker">Optional second screen</p>
            <h2>Customer display</h2>
        </div>
        <div class="display-state">
            <StatusBadge
                :tone="
                    connection === 'connected'
                        ? 'success'
                        : connection === 'disconnected'
                          ? 'warning'
                          : 'neutral'
                "
                >{{ connection }}</StatusBadge
            >
            <span v-if="enabled"
                >Pairing {{ pairingCode }} · register LAB-01</span
            >
            <span v-else>Checkout works normally without a display.</span>
        </div>
        <div class="display-actions">
            <AppButton v-if="!enabled" variant="quiet" @click="enable"
                >Enable display</AppButton
            >
            <template v-else>
                <AppButton variant="primary" @click="openDisplay"
                    >Open customer display</AppButton
                >
                <AppButton
                    v-if="connection === 'connected'"
                    variant="quiet"
                    @click="disconnect"
                    >Simulate disconnect</AppButton
                >
                <AppButton variant="quiet" @click="disable"
                    >Disable & clear</AppButton
                >
            </template>
        </div>
    </section>
</template>

<style scoped>
.display-control {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 1rem;
    padding: 0.8rem 1rem;
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow);
}
.display-control h2 {
    margin: 0.1rem 0 0;
    font-size: 1rem;
}
.display-state {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-muted);
    font-size: 0.82rem;
}
.display-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
}
@media (max-width: 820px) {
    .display-control {
        grid-template-columns: 1fr;
        align-items: start;
    }
    .display-state {
        justify-content: flex-start;
    }
    .display-actions {
        justify-content: flex-start;
    }
}
</style>
