<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";

type PrintState =
    | "not_requested"
    | "accepted_by_helper"
    | "printed_reported"
    | "failed"
    | "outcome_unknown";

defineProps<{ total: number }>();
const printState = ref<PrintState>("not_requested");
const helper = ref<"ready" | "unavailable" | "paper_out">("ready");
const reason = ref("");
const copyCount = ref(0);
const drawerState = ref<
    "not_requested" | "opened_reported" | "outcome_unknown"
>("not_requested");
const busy = ref(false);

const tone = computed(() => {
    if (printState.value === "printed_reported") return "success";
    if (printState.value === "failed") return "danger";
    if (printState.value === "accepted_by_helper") return "info";
    if (printState.value === "outcome_unknown") return "warning";
    return "neutral";
});
const label = computed(() => printState.value.replaceAll("_", " "));

function requestPrint(copy: "original" | "reprint"): void {
    if (copy === "reprint" && reason.value.trim().length < 5) return;
    busy.value = true;
    window.setTimeout(() => {
        busy.value = false;
        copyCount.value += 1;
        if (helper.value === "unavailable")
            printState.value = "outcome_unknown";
        else if (helper.value === "paper_out") printState.value = "failed";
        else {
            printState.value = "accepted_by_helper";
            drawerState.value =
                copy === "original" ? "opened_reported" : drawerState.value;
            window.setTimeout(
                () => (printState.value = "printed_reported"),
                180,
            );
        }
    }, 180);
}
</script>

<template>
    <section class="print-operations" aria-labelledby="print-heading">
        <div class="print-heading">
            <div>
                <p class="kicker">Local helper · signed protocol v1</p>
                <h2 id="print-heading">Receipt & drawer</h2>
            </div>
            <div class="finality-lock">
                <StatusBadge tone="success">sale posted</StatusBadge>
                <span>Print outcomes cannot change the sale.</span>
            </div>
        </div>

        <div class="print-grid">
            <article class="receipt-slip" aria-label="Frozen receipt preview">
                <div class="receipt-brand">MY POS · COUNTER 01</div>
                <p>SALE-01J8K7M42F</p>
                <div><span>Karak tea × 2</span><strong>PKR 360.00</strong></div>
                <div>
                    <span>Classic burger + extras</span
                    ><strong>PKR 770.00</strong>
                </div>
                <hr />
                <div class="receipt-total">
                    <span>TOTAL</span
                    ><strong>PKR {{ total.toFixed(2) }}</strong>
                </div>
                <p class="receipt-foot">Frozen snapshot · 80 mm · UTF-8</p>
            </article>

            <div class="print-console">
                <div class="helper-status">
                    <div>
                        <span>Helper</span>
                        <strong>{{ helper.replaceAll("_", " ") }}</strong>
                    </div>
                    <label>
                        Simulate helper
                        <select v-model="helper">
                            <option value="ready">Ready</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="paper_out">Paper out</option>
                        </select>
                    </label>
                </div>
                <div class="operation-state" role="status" aria-live="polite">
                    <StatusBadge :tone="tone">{{ label }}</StatusBadge>
                    <p v-if="printState === 'failed'">
                        Paper out. The sale is still posted; refill paper and
                        retry this job.
                    </p>
                    <p v-else-if="printState === 'outcome_unknown'">
                        Helper outcome is unknown. Check the printer before
                        creating a marked copy.
                    </p>
                    <p v-else-if="printState === 'printed_reported'">
                        Helper reported printing. This is physical-device
                        evidence, not sale finality.
                    </p>
                    <p v-else>Ready to create one signed original print job.</p>
                </div>
                <div class="print-actions">
                    <AppButton
                        variant="primary"
                        :disabled="busy || copyCount > 0"
                        @click="requestPrint('original')"
                        >{{ busy ? "Sending…" : "Print original" }}</AppButton
                    >
                    <AppButton
                        v-if="
                            printState === 'failed' ||
                            printState === 'outcome_unknown'
                        "
                        variant="quiet"
                        :disabled="busy"
                        @click="
                            requestPrint(
                                copyCount === 0 ? 'original' : 'reprint',
                            )
                        "
                        >Retry safely</AppButton
                    >
                </div>
                <label class="reprint-reason">
                    Reprint reason
                    <input
                        v-model="reason"
                        placeholder="Required for marked copy"
                    />
                </label>
                <AppButton
                    variant="quiet"
                    :disabled="busy || reason.trim().length < 5"
                    @click="requestPrint('reprint')"
                    >Print marked copy</AppButton
                >
                <dl class="hardware-evidence">
                    <div>
                        <dt>Copies requested</dt>
                        <dd>{{ copyCount }}</dd>
                    </div>
                    <div>
                        <dt>Drawer</dt>
                        <dd>{{ drawerState.replaceAll("_", " ") }}</dd>
                    </div>
                    <div>
                        <dt>Signature</dt>
                        <dd>Ed25519 · register scoped</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>

<style scoped>
.print-operations {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow);
}
.print-heading,
.finality-lock,
.helper-status,
.hardware-evidence div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}
.print-heading h2 {
    margin: 0.15rem 0 0;
}
.finality-lock {
    color: var(--text-muted);
    font-size: 0.84rem;
}
.print-grid {
    display: grid;
    grid-template-columns: minmax(15rem, 0.45fr) minmax(20rem, 1fr);
    gap: 1rem;
    align-items: start;
}
.receipt-slip {
    width: min(100%, 22rem);
    justify-self: center;
    padding: 1.5rem 1.25rem;
    color: #111;
    background: #fffef8;
    border: 1px solid #d9d4c5;
    box-shadow: 0 0.5rem 1.5rem rgb(0 0 0 / 12%);
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
}
.receipt-brand {
    text-align: center;
    font-weight: 850;
}
.receipt-slip > div:not(.receipt-brand) {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.receipt-slip hr {
    border: 0;
    border-top: 1px dashed #555;
}
.receipt-total {
    font-size: 1.05rem;
}
.receipt-foot {
    margin-bottom: 0;
    text-align: center;
    font-size: 0.72rem;
}
.print-console {
    display: grid;
    gap: 0.85rem;
}
.helper-status,
.operation-state,
.hardware-evidence {
    padding: 0.85rem;
    border: 1px solid var(--border);
    background: var(--surface-raised);
}
.helper-status > div {
    display: grid;
    gap: 0.2rem;
}
.helper-status span,
.helper-status label,
.hardware-evidence dt {
    color: var(--text-muted);
    font-size: 0.75rem;
}
.helper-status label {
    display: grid;
    gap: 0.3rem;
}
.helper-status select,
.reprint-reason input {
    border: 1px solid var(--border-strong);
    background: var(--input);
    color: var(--text);
    padding: 0.55rem;
}
.operation-state {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.operation-state p {
    margin: 0;
}
.print-actions {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.reprint-reason {
    display: grid;
    gap: 0.35rem;
    font-weight: 700;
    font-size: 0.8rem;
}
.hardware-evidence {
    display: grid;
    gap: 0.55rem;
    margin: 0;
}
.hardware-evidence dd {
    margin: 0;
    font-weight: 700;
    text-transform: capitalize;
}
@media (max-width: 760px) {
    .print-grid {
        grid-template-columns: 1fr;
    }
    .print-heading {
        align-items: flex-start;
    }
    .finality-lock {
        align-items: flex-end;
        flex-direction: column;
    }
}
</style>
