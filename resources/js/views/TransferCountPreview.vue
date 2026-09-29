<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";

const received = ref("2.000000");
const transferState = ref<"partially_received" | "received">(
    "partially_received",
);
const count = ref("47.000000");
const stocktakeState = ref<"counting" | "review" | "posted">("counting");

const inTransit = computed(() =>
    transferState.value === "received" ? "0.000000" : "4.000000",
);

function closeShort(): void {
    received.value = "5.000000";
    transferState.value = "received";
}

function advanceCount(): void {
    stocktakeState.value =
        stocktakeState.value === "counting" ? "review" : "posted";
}
</script>

<template>
    <div class="transfer-count-preview">
        <header class="page-heading transfer-count-heading">
            <div>
                <p class="kicker">Inventory operations · Lahore to Karachi</p>
                <h1>Move stock without losing the trail.</h1>
                <p>
                    Dispatch and receipt are separate ledger events. Counts
                    preserve sales made while staff are counting.
                </p>
            </div>
            <StatusBadge tone="info">Online operation</StatusBadge>
        </header>

        <section class="gallery-section" aria-labelledby="transfer-title">
            <div class="section-heading">
                <div>
                    <p class="kicker">TR-29A7C1 · version 3</p>
                    <h2 id="transfer-title">Partially received transfer</h2>
                    <p>
                        Source stock left at dispatch. Destination stock appears
                        only when a receipt is posted.
                    </p>
                </div>
                <StatusBadge
                    :tone="transferState === 'received' ? 'success' : 'warning'"
                    >{{
                        transferState === "received"
                            ? "Received · short resolved"
                            : "Partially received"
                    }}</StatusBadge
                >
            </div>
            <div class="operation-route" aria-label="Transfer route">
                <article>
                    <small>From</small><strong>Lahore Test Store</strong
                    ><span>MAIN · dispatched 6 cartons</span>
                </article>
                <span aria-hidden="true">→</span>
                <article>
                    <small>To</small><strong>Karachi Clifton</strong
                    ><span>RECEIVING · 1 carton = 12 pieces</span>
                </article>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Mapped product</th>
                            <th>Batch</th>
                            <th class="numeric">Expected</th>
                            <th class="numeric">Received</th>
                            <th class="numeric">In transit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>Tea case → Tea pieces</strong
                                ><small class="inventory-sku"
                                    >CASE-TEA → TEA-250 · explicit
                                    mapping</small
                                >
                            </td>
                            <td>
                                LOT-2410-B<small class="inventory-sku"
                                    >Expires 12 Oct 2026</small
                                >
                            </td>
                            <td class="numeric">6.000000 ctn</td>
                            <td class="numeric">{{ received }} ctn</td>
                            <td class="numeric">{{ inTransit }} ctn</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="operation-resolution">
                <div>
                    <strong>Verified shortage resolution</strong>
                    <p>
                        Receive 3 more and close 1 carton short. The missing
                        quantity stays on the transfer as an audited
                        discrepancy.
                    </p>
                </div>
                <AppButton
                    v-if="transferState !== 'received'"
                    variant="primary"
                    @click="closeShort"
                    >Receive 3 and close short</AppButton
                >
            </div>
        </section>

        <section class="gallery-section" aria-labelledby="count-title">
            <div class="section-heading">
                <div>
                    <p class="kicker">CNT-14F9 · live counting</p>
                    <h2 id="count-title">
                        Count against the movement timeline.
                    </h2>
                    <p>
                        The expected quantity is reconstructed at the moment
                        each count was captured.
                    </p>
                </div>
                <StatusBadge
                    :tone="stocktakeState === 'posted' ? 'success' : 'info'"
                    >{{ stocktakeState }}</StatusBadge
                >
            </div>
            <div class="count-workspace">
                <div class="count-timeline">
                    <div>
                        <span>1</span>
                        <p><strong>Baseline</strong>50.000000 pc</p>
                    </div>
                    <div>
                        <span>2</span>
                        <p><strong>Sale during count</strong>−2.000000 pc</p>
                    </div>
                    <div>
                        <span>3</span>
                        <p><strong>Expected at count</strong>48.000000 pc</p>
                    </div>
                    <div>
                        <span>4</span>
                        <p><strong>Later sale preserved</strong>−1.000000 pc</p>
                    </div>
                </div>
                <div class="count-review">
                    <label class="field"
                        ><span>Physical count</span
                        ><span class="input-affix input-affix--suffix"
                            ><input
                                v-model="count"
                                inputmode="decimal"
                                :readonly="stocktakeState !== 'counting'"
                            /><b>pc</b></span
                        ><small>Captured after movement #2081</small></label
                    >
                    <dl>
                        <div>
                            <dt>Expected at count</dt>
                            <dd>48.000000</dd>
                        </div>
                        <div>
                            <dt>Discrepancy</dt>
                            <dd>−1.000000</dd>
                        </div>
                        <div>
                            <dt>Current before post</dt>
                            <dd>47.000000</dd>
                        </div>
                        <div>
                            <dt>Result after post</dt>
                            <dd>46.000000</dd>
                        </div>
                    </dl>
                    <p class="context-announcement">
                        The count posts a −1 movement. It does not replace the
                        live balance with 47.
                    </p>
                    <AppButton
                        v-if="stocktakeState !== 'posted'"
                        variant="primary"
                        @click="advanceCount"
                        >{{
                            stocktakeState === "counting"
                                ? "Send to review"
                                : "Post count adjustment"
                        }}</AppButton
                    >
                </div>
            </div>
        </section>
    </div>
</template>
