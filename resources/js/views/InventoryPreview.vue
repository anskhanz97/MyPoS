<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type StockRow = {
    sku: string;
    name: string;
    location: string;
    disposition: "Sellable" | "Quarantine" | "Unavailable";
    quantity: string;
    unit: string;
};

type BatchRow = {
    code: string;
    expiry: string;
    quantity: string;
    status: "Available" | "Expiring soon" | "Expired";
};

const rows: StockRow[] = [
    {
        sku: "TEA-250",
        name: "Green tea 250g",
        location: "MAIN",
        disposition: "Sellable",
        quantity: "48.000000",
        unit: "pc",
    },
    {
        sku: "NUT-014",
        name: "Loose almonds",
        location: "MAIN",
        disposition: "Sellable",
        quantity: "-1.250000",
        unit: "kg",
    },
    {
        sku: "BIS-012",
        name: "Oat biscuits",
        location: "QUAR",
        disposition: "Quarantine",
        quantity: "3.000000",
        unit: "pc",
    },
];

const batches: BatchRow[] = [
    {
        code: "LOT-2409-A",
        expiry: "29 Sep 2026",
        quantity: "8.000000",
        status: "Expired",
    },
    {
        code: "LOT-2410-B",
        expiry: "12 Oct 2026",
        quantity: "5.000000",
        status: "Expiring soon",
    },
    {
        code: "LOT-2411-C",
        expiry: "18 Nov 2026",
        quantity: "18.000000",
        status: "Available",
    },
];

const query = ref("");
const state = ref<"populated" | "empty" | "loading" | "error">("populated");
const adjustmentOpen = ref(false);
const adjustmentQuantity = ref("");
const adjustmentReason = ref("");
const attempted = ref(false);
const posted = ref(false);
const drift = ref(true);
const batchQuantity = ref("7");
const batchStrategy = ref<"fefo" | "manual">("fefo");
const batchProposed = ref(false);

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();
    return rows.filter((row) =>
        `${row.sku} ${row.name} ${row.location}`.toLowerCase().includes(term),
    );
});

function openAdjustment(): void {
    adjustmentOpen.value = true;
    attempted.value = false;
    posted.value = false;
}

function postAdjustment(): void {
    attempted.value = true;
    if (!adjustmentQuantity.value || !adjustmentReason.value.trim()) return;
    posted.value = true;
}

function proposeBatches(): void {
    batchProposed.value = Boolean(batchQuantity.value);
}
</script>

<template>
    <div class="inventory-preview">
        <header class="page-heading inventory-heading">
            <div>
                <p class="kicker">Inventory ledger · Lahore Test Store</p>
                <h1>Every stock change leaves a trace.</h1>
                <p>
                    Balances are projections of signed base-unit movements.
                    Corrections append a reversal instead of changing history.
                </p>
            </div>
            <StatusBadge :tone="drift ? 'warning' : 'success'">{{
                drift ? "1 projection discrepancy" : "Ledger reconciled"
            }}</StatusBadge>
        </header>

        <section class="inventory-summary" aria-label="Stock summary">
            <article>
                <small>Sellable quantity</small><strong>46.750000</strong
                ><span>Across 2 tracked variants</span>
            </article>
            <article class="inventory-summary--warning">
                <small>Negative balances</small><strong>1</strong
                ><span>Warning permitted; review required</span>
            </article>
            <article>
                <small>Quarantine</small><strong>3.000000</strong
                ><span>Unavailable for checkout</span>
            </article>
            <article>
                <small>Freshness</small><strong>Just now</strong
                ><span>Movement #1048 projected</span>
            </article>
        </section>

        <section class="gallery-section" aria-labelledby="batch-title">
            <div class="section-heading">
                <div>
                    <p class="kicker">
                        Batch tracking · store date 29 Sep 2026
                    </p>
                    <h2 id="batch-title">
                        Sell the earliest safe stock first.
                    </h2>
                    <p>
                        The printed expiry date remains sellable through that
                        local day. “Expiring soon” is a warning; “Expired” is a
                        hard checkout block.
                    </p>
                </div>
                <StatusBadge tone="warning"
                    >1 expired batch blocked</StatusBadge
                >
            </div>
            <div class="batch-workspace">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>Expiry</th>
                                <th class="numeric">Sellable quantity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="batch in batches" :key="batch.code">
                                <td>
                                    <strong>{{ batch.code }}</strong
                                    ><small class="inventory-sku"
                                        >Green tea 250g</small
                                    >
                                </td>
                                <td>{{ batch.expiry }}</td>
                                <td class="numeric">{{ batch.quantity }} pc</td>
                                <td>
                                    <StatusBadge
                                        :tone="
                                            batch.status === 'Expired'
                                                ? 'danger'
                                                : batch.status ===
                                                    'Expiring soon'
                                                  ? 'warning'
                                                  : 'success'
                                        "
                                        >{{ batch.status }}</StatusBadge
                                    >
                                    <small
                                        v-if="batch.status === 'Expired'"
                                        class="batch-block-note"
                                        >Unavailable for sale</small
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <form class="batch-selection" @submit.prevent="proposeBatches">
                    <div>
                        <p class="kicker">Non-reserving selection snapshot</p>
                        <h3>Allocate 7 pieces</h3>
                        <p>
                            Checkout revalidates the batch facts before posting
                            stock.
                        </p>
                    </div>
                    <label class="field"
                        ><span>Quantity</span
                        ><input
                            v-model="batchQuantity"
                            inputmode="decimal"
                            aria-label="Batch quantity"
                    /></label>
                    <label class="field"
                        ><span>Selection strategy</span
                        ><select
                            v-model="batchStrategy"
                            aria-label="Selection strategy"
                        >
                            <option value="fefo">Earliest expiry first</option>
                            <option value="manual">Manual batches</option>
                        </select></label
                    >
                    <AppButton variant="primary" @click="proposeBatches"
                        >Propose safe batches</AppButton
                    >
                    <div
                        v-if="batchProposed"
                        class="batch-proposal"
                        role="status"
                    >
                        <strong>FEFO proposal ready</strong>
                        <span>LOT-2410-B · 5 pc</span>
                        <span>LOT-2411-C · 2 pc</span>
                        <small
                            >Expires in 15 minutes · stock not reserved</small
                        >
                    </div>
                </form>
            </div>
        </section>

        <section class="gallery-section" aria-labelledby="stock-on-hand-title">
            <div class="section-heading">
                <div>
                    <h2 id="stock-on-hand-title">Stock on hand</h2>
                    <p>Store and location scope are resolved by the server.</p>
                </div>
                <AppButton variant="primary" @click="openAdjustment"
                    >New adjustment</AppButton
                >
            </div>
            <div class="inventory-toolbar">
                <label class="field"
                    ><span>Search product or SKU</span
                    ><input v-model="query" autocomplete="off"
                /></label>
                <label class="field"
                    ><span>Preview state</span
                    ><select v-model="state">
                        <option value="populated">Populated</option>
                        <option value="empty">Empty</option>
                        <option value="loading">Loading</option>
                        <option value="error">Error</option>
                    </select></label
                >
                <AppButton variant="quiet" @click="query = ''"
                    >Clear filters</AppButton
                >
            </div>
            <StatePanel
                v-if="state === 'loading'"
                state="loading"
                title="Loading balances"
                message="The current store projection is being read."
            />
            <StatePanel
                v-else-if="state === 'error'"
                state="error"
                title="Balances could not load"
                message="The ledger is unchanged. Retry the read when connectivity returns."
            />
            <StatePanel
                v-else-if="state === 'empty' || filtered.length === 0"
                state="empty"
                title="No stock matches"
                message="Clear filters or post an authorized opening adjustment."
            />
            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Location</th>
                            <th>Disposition</th>
                            <th class="numeric">Base quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in filtered"
                            :key="`${row.sku}-${row.location}`"
                        >
                            <td>
                                <strong>{{ row.name }}</strong
                                ><small class="inventory-sku">{{
                                    row.sku
                                }}</small>
                            </td>
                            <td>{{ row.location }}</td>
                            <td>{{ row.disposition }}</td>
                            <td class="numeric">
                                {{ row.quantity }} {{ row.unit }}
                            </td>
                            <td>
                                <StatusBadge
                                    :tone="
                                        row.quantity.startsWith('-')
                                            ? 'warning'
                                            : row.disposition === 'Sellable'
                                              ? 'success'
                                              : 'info'
                                    "
                                    >{{
                                        row.quantity.startsWith("-")
                                            ? "Negative"
                                            : row.disposition
                                    }}</StatusBadge
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="inventory-lower-grid">
            <article
                class="gallery-section movement-card"
                aria-labelledby="movement-history-title"
            >
                <div class="section-heading">
                    <div>
                        <h2 id="movement-history-title">Movement history</h2>
                        <p>
                            Source references support drill-down and safe
                            retries.
                        </p>
                    </div>
                </div>
                <ol class="movement-list">
                    <li>
                        <span class="movement-quantity movement-quantity--in"
                            >+24.000000 pc</span
                        >
                        <div>
                            <strong>Verified count</strong
                            ><small>Manual adjustment · ADJ-1048 · MAIN</small>
                        </div>
                    </li>
                    <li>
                        <span class="movement-quantity movement-quantity--out"
                            >−2.000000 pc</span
                        >
                        <div>
                            <strong>Bundle component issue</strong
                            ><small>Sale · SALE-887 / LINE-2 · MAIN</small>
                        </div>
                    </li>
                    <li>
                        <span class="movement-quantity movement-quantity--in"
                            >+3.000000 pc</span
                        >
                        <div>
                            <strong>Return to quarantine</strong
                            ><small>Return · RET-107 · QUAR</small>
                        </div>
                    </li>
                </ol>
            </article>
            <article
                class="gallery-section reconciliation-card"
                aria-labelledby="reconciliation-title"
            >
                <div class="section-heading">
                    <div>
                        <h2 id="reconciliation-title">
                            Projection reconciliation
                        </h2>
                        <p>
                            A discrepancy is shown before an explicit rebuild.
                        </p>
                    </div>
                </div>
                <div v-if="drift" class="reconciliation-diff">
                    <span>Projected</span><strong>47.750000</strong
                    ><span>Ledger</span><strong>46.750000</strong
                    ><span>Difference</span><strong>+1.000000</strong>
                </div>
                <StatePanel
                    v-else
                    state="empty"
                    title="Projection matches ledger"
                    message="The last explicit reconciliation found no remaining difference."
                />
                <AppButton v-if="drift" @click="drift = false"
                    >Confirm and rebuild projection</AppButton
                >
            </article>
        </section>

        <section
            v-if="adjustmentOpen"
            class="gallery-section"
            aria-labelledby="adjustment-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="adjustment-title">Post stock adjustment</h2>
                    <p>
                        Enter a signed quantity. The selected unit converts
                        directly to the base unit.
                    </p>
                </div>
                <StatusBadge tone="info">Manager action</StatusBadge>
            </div>
            <div class="adjustment-form">
                <label class="field"
                    ><span>Product</span
                    ><select>
                        <option>Green tea 250g · TEA-250</option></select
                    ><small>Base unit: piece</small></label
                >
                <label class="field"
                    ><span>Location</span
                    ><select>
                        <option>Main sellable stock · MAIN</option></select
                    ><small>Sellable disposition</small></label
                >
                <label
                    class="field"
                    :class="{
                        'field--error': attempted && !adjustmentQuantity,
                    }"
                    ><span>Signed quantity</span
                    ><span class="input-affix input-affix--suffix"
                        ><input
                            v-model="adjustmentQuantity"
                            inputmode="decimal"
                        /><b>carton</b></span
                    ><small>{{
                        attempted && !adjustmentQuantity
                            ? "Enter a non-zero quantity."
                            : "1 carton = 24 pieces"
                    }}</small></label
                >
                <label
                    class="field"
                    :class="{
                        'field--error': attempted && !adjustmentReason.trim(),
                    }"
                    ><span>Reason details</span
                    ><input v-model="adjustmentReason" /><small>{{
                        attempted && !adjustmentReason.trim()
                            ? "Explain why stock changes."
                            : "Stored with the append-only movement."
                    }}</small></label
                >
            </div>
            <div class="adjustment-actions">
                <p v-if="posted" class="context-announcement" role="status">
                    Adjustment posted once. New balance: 70.750000 pc.
                </p>
                <AppButton variant="primary" @click="postAdjustment"
                    >Post adjustment</AppButton
                >
            </div>
        </section>
    </div>
</template>
