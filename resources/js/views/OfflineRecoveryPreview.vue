<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import AppIcon from "../components/AppIcon.vue";
import StatusBadge from "../components/StatusBadge.vue";
import { canonicalHash } from "../offline/canonical";
import { serverReachable } from "../offline/connectivity";
import {
    commitOfflineSale,
    exportUnresolvedEvidence,
    listOfflineSales,
    requestDurableStorage,
    updateOfflineSale,
} from "../offline/offlineStore";
import type { OfflineOutboxCommand, OfflineSale } from "../offline/types";
import { OfflineWriterLease } from "../offline/writerLease";

const writer = new OfflineWriterLease("mypos:demo-register:writer");
const sales = ref<OfflineSale[]>([]);
const writerActive = ref(false);
const persistence = ref<"checking" | "durable" | "best-effort">("checking");
const reachability = ref<"checking" | "online" | "offline">("checking");
const message = ref("Loading durable offline evidence…");
const busy = ref(false);

const counts = computed(() => ({
    queued: sales.value.filter(
        (sale) => sale.state === "queued" || sale.state === "submitting",
    ).length,
    accepted: sales.value.filter((sale) => sale.state === "accepted").length,
    conflict: sales.value.filter((sale) => sale.state === "conflict").length,
}));

async function refresh(): Promise<void> {
    sales.value = await listOfflineSales();
}

async function queueSale(): Promise<void> {
    if (!writerActive.value || busy.value) return;
    busy.value = true;
    const commandId = crypto.randomUUID();
    const payload = {
        total: "1290.92",
        tender: "cash",
        snapshotVersion: "offline-v1-demo",
        fencingEpoch: 1,
    };
    const sale: OfflineSale = {
        commandId,
        clientTransactionId: crypto.randomUUID(),
        committedAtLocal: new Date().toISOString(),
        snapshotVersion: "offline-v1-demo",
        fencingEpoch: 1,
        total: "1290.92",
        currency: "PKR",
        state: "queued",
        receipt: {
            number: `OFF-${commandId.slice(0, 8).toUpperCase()}`,
            lines: [
                {
                    name: "Karak tea + basket",
                    quantity: "1.000",
                    gross: "1290.92",
                },
            ],
        },
    };
    const command: OfflineOutboxCommand = {
        commandId,
        commandType: "finalize_cash_sale",
        payloadHash: await canonicalHash(payload),
        payload,
        state: "queued",
        attemptCount: 0,
        nextAttemptAt: new Date().toISOString(),
    };
    try {
        await commitOfflineSale(sale, command);
        await refresh();
        message.value =
            "Cash sale and outbox command committed together. It is safe to show the receipt.";
    } catch {
        message.value =
            "Storage failed. No completed sale was shown and the basket remains available.";
    } finally {
        busy.value = false;
    }
}

function simulateQuotaFailure(): void {
    message.value =
        "Storage quota simulation blocked completion before local commit. No receipt or sale was created.";
}

async function acceptOldest(): Promise<void> {
    const pending = sales.value.find(
        (sale) => sale.state === "queued" || sale.state === "submitting",
    );
    if (!pending) return;
    await updateOfflineSale(pending.commandId, "accepted");
    await refresh();
    message.value =
        "The server acknowledged the original command ID. Retry cannot post it twice.";
}

async function conflictOldest(): Promise<void> {
    const pending = sales.value.find(
        (sale) => sale.state === "queued" || sale.state === "submitting",
    );
    if (!pending) return;
    await updateOfflineSale(pending.commandId, "conflict", {
        code: "pricing_changed",
        message: "Authoritative pricing changed; manager review is required.",
    });
    await refresh();
    message.value =
        "Conflict evidence is preserved for controlled reconciliation.";
}

async function exportEvidence(): Promise<void> {
    const json = await exportUnresolvedEvidence();
    const blob = new Blob([json], { type: "application/json" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `mypos-unresolved-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
    URL.revokeObjectURL(link.href);
    message.value =
        "Unresolved evidence exported without device credentials or staff PIN data.";
}

onMounted(async () => {
    writerActive.value = await writer.acquire();
    const storage = await requestDurableStorage();
    persistence.value = storage.persisted ? "durable" : "best-effort";
    reachability.value = (await serverReachable()) ? "online" : "offline";
    await refresh();
    message.value = writerActive.value
        ? "This tab owns offline writes."
        : "Another tab owns offline writes. This tab is safely read-only.";
});
onBeforeUnmount(() => writer.release());
</script>

<template>
    <details
        class="offline-recovery"
        role="region"
        aria-labelledby="offline-recovery-title"
    >
        <summary class="offline-heading">
            <div>
                <p class="kicker">Outage mode · cash only</p>
                <h2 id="offline-recovery-title">Durable offline queue</h2>
            </div>
            <div class="offline-badges">
                <StatusBadge
                    :tone="reachability === 'online' ? 'success' : 'warning'"
                    >{{ reachability }}</StatusBadge
                ><StatusBadge :tone="writerActive ? 'info' : 'warning'">{{
                    writerActive ? "writer tab" : "read-only tab"
                }}</StatusBadge>
            </div>
            <AppIcon name="collapse" class="outage-chevron" />
        </summary>
        <div class="offline-policy">
            <div>
                <span>Register lease</span><strong>71h 42m remaining</strong>
            </div>
            <div>
                <span>Browser storage</span><strong>{{ persistence }}</strong>
            </div>
            <div><span>Offline payment</span><strong>Cash only</strong></div>
            <div>
                <span>Unavailable offline</span
                ><strong>External tenders · loyalty redemption</strong>
            </div>
        </div>
        <div class="offline-counts" aria-label="Offline queue counts">
            <div>
                <strong>{{ counts.queued }}</strong
                ><span>Pending</span>
            </div>
            <div>
                <strong>{{ counts.accepted }}</strong
                ><span>Accepted</span>
            </div>
            <div>
                <strong>{{ counts.conflict }}</strong
                ><span>Needs review</span>
            </div>
        </div>
        <div class="offline-actions">
            <button
                type="button"
                :disabled="!writerActive || busy"
                @click="queueSale"
            >
                Queue offline cash sale
            </button>
            <button
                type="button"
                :disabled="!counts.queued"
                @click="acceptOldest"
            >
                Simulate lost response + retry
            </button>
            <button
                type="button"
                :disabled="!counts.queued"
                @click="conflictOldest"
            >
                Simulate reconciliation conflict
            </button>
            <button type="button" @click="simulateQuotaFailure">
                Simulate disk full
            </button>
            <button
                type="button"
                :disabled="!counts.queued && !counts.conflict"
                @click="exportEvidence"
            >
                Export unresolved evidence
            </button>
        </div>
        <p class="offline-message" aria-live="polite">{{ message }}</p>
        <ol v-if="sales.length" class="offline-ledger">
            <li v-for="sale in sales" :key="sale.commandId">
                <span
                    ><strong>{{ sale.receipt.number }}</strong
                    ><small
                        >{{
                            new Date(sale.committedAtLocal).toLocaleString()
                        }}
                        · {{ sale.commandId.slice(0, 8) }}</small
                    ></span
                ><b>PKR {{ sale.total }}</b
                ><StatusBadge
                    :tone="
                        sale.state === 'accepted'
                            ? 'success'
                            : sale.state === 'conflict'
                              ? 'danger'
                              : 'warning'
                    "
                    >{{ sale.state }}</StatusBadge
                ><small v-if="sale.conflict">{{ sale.conflict.message }}</small>
            </li>
        </ol>
        <div v-else class="offline-empty">
            <strong>No offline sales on this device</strong
            ><span
                >Queued and conflicted evidence remains here across reloads,
                logout and application updates.</span
            >
        </div>
    </details>
</template>

<style scoped>
.offline-heading {
    cursor: pointer;
    list-style: none;
}
.offline-heading::-webkit-details-marker {
    display: none;
}
.offline-heading .outage-chevron {
    transform: rotate(-90deg);
    margin-left: 0.5rem;
}
.offline-recovery[open] .outage-chevron {
    transform: rotate(90deg);
}
.offline-recovery:not([open]) {
    padding-block: 0.9rem;
}
</style>
