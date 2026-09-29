<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type SessionState =
    "open" | "close_pending_sync" | "variance_review" | "closed";

const state = ref<SessionState>("open");
const paidIn = ref(50);
const paidOut = ref(20);
const counted = ref("");
const pendingOffline = ref(false);
const custody = ref("Ayesha Khan");
const announcement = ref("Drawer opened at 09:02 with PKR 10,000.00.");
const expected = computed(() => 10000 + paidIn.value - paidOut.value);
const variance = computed(() => Number(counted.value || 0) - expected.value);
const revealExpected = computed(() => state.value !== "open");

function addPaidIn(): void {
    paidIn.value += 500;
    announcement.value =
        "PKR 500.00 paid in by Ayesha Khan with reason recorded.";
}
function addPaidOut(): void {
    paidOut.value += 250;
    announcement.value = "PKR 250.00 paid out by manager with reason recorded.";
}
function handover(): void {
    custody.value =
        custody.value === "Ayesha Khan" ? "Bilal Ahmed" : "Ayesha Khan";
    announcement.value = `Drawer custody handed to ${custody.value}; the register session stayed open.`;
}
function submitCount(): void {
    if (counted.value === "") {
        announcement.value =
            "Enter the physical drawer count before submitting.";
        return;
    }
    if (pendingOffline.value) {
        state.value = "close_pending_sync";
        announcement.value =
            "Count recorded. One offline sale must sync before reconciliation.";
        return;
    }
    state.value = "variance_review";
    announcement.value =
        "Blind count submitted. Expected cash is now revealed for manager review.";
}
function approveClose(): void {
    state.value = "closed";
    announcement.value =
        "Variance reviewed with a reason. Drawer session closed at its cutoff.";
}
</script>

<template>
    <div class="cash-preview">
        <header class="page-heading cash-heading">
            <div>
                <p class="kicker">Register 01 · Lahore Test Store</p>
                <h1>Count what is there. Then compare.</h1>
                <p>
                    One shared drawer session, named custody, immutable movement
                    evidence, and a blind close before the expected amount
                    appears.
                </p>
            </div>
            <StatusBadge :tone="state === 'closed' ? 'neutral' : 'success'">
                {{ state.replaceAll("_", " ") }}
            </StatusBadge>
        </header>

        <div class="cash-grid">
            <section
                class="gallery-section session-card"
                aria-labelledby="session-title"
            >
                <div class="section-heading">
                    <div>
                        <p class="kicker">CS-7Y2K9P · 29 Sep 2026</p>
                        <h2 id="session-title">Counter drawer</h2>
                        <p>Opened 09:02 · current custodian {{ custody }}</p>
                    </div>
                    <AppButton
                        :disabled="state !== 'open'"
                        variant="quiet"
                        @click="handover"
                    >
                        Handover custody
                    </AppButton>
                </div>
                <dl class="cash-metrics">
                    <div>
                        <dt>Opening float</dt>
                        <dd>PKR 10,000.00</dd>
                    </div>
                    <div>
                        <dt>Paid in</dt>
                        <dd>PKR {{ paidIn.toFixed(2) }}</dd>
                    </div>
                    <div>
                        <dt>Paid out</dt>
                        <dd>PKR {{ paidOut.toFixed(2) }}</dd>
                    </div>
                    <div :class="{ concealed: !revealExpected }">
                        <dt>Expected cash</dt>
                        <dd>
                            {{
                                revealExpected
                                    ? `PKR ${expected.toFixed(2)}`
                                    : "Hidden until count"
                            }}
                        </dd>
                    </div>
                </dl>
                <div
                    class="movement-actions"
                    aria-label="Cash movement actions"
                >
                    <AppButton :disabled="state !== 'open'" @click="addPaidIn"
                        >Paid in</AppButton
                    >
                    <AppButton :disabled="state !== 'open'" @click="addPaidOut"
                        >Paid out</AppButton
                    >
                </div>
                <p
                    class="context-announcement"
                    role="status"
                    aria-live="polite"
                >
                    {{ announcement }}
                </p>
            </section>

            <section
                class="gallery-section close-card"
                aria-labelledby="count-title"
            >
                <div class="section-heading">
                    <div>
                        <p class="kicker">
                            Blind close · cutoff captured on submit
                        </p>
                        <h2 id="count-title">Physical count</h2>
                        <p>
                            Expected cash stays hidden while the cashier counts.
                        </p>
                    </div>
                    <span class="privacy-chip">Expected hidden</span>
                </div>
                <label class="count-field">
                    <span>Counted cash</span>
                    <span class="money-input"
                        ><b>PKR</b
                        ><input
                            v-model="counted"
                            inputmode="decimal"
                            placeholder="0.00"
                    /></span>
                    <small
                        >Use the denomination worksheet or enter the verified
                        total.</small
                    >
                </label>
                <label class="pending-check">
                    <input v-model="pendingOffline" type="checkbox" />
                    One offline cash sale is still pending sync
                </label>
                <AppButton
                    v-if="state === 'open' || state === 'close_pending_sync'"
                    variant="primary"
                    @click="submitCount"
                >
                    Submit blind count
                </AppButton>
                <div v-else class="review-box">
                    <div>
                        <span>Expected</span
                        ><strong>PKR {{ expected.toFixed(2) }}</strong>
                    </div>
                    <div>
                        <span>Counted</span
                        ><strong>PKR {{ Number(counted).toFixed(2) }}</strong>
                    </div>
                    <div>
                        <span>Variance</span
                        ><strong
                            :class="variance === 0 ? 'positive' : 'negative'"
                            >PKR {{ variance.toFixed(2) }}</strong
                        >
                    </div>
                    <label
                        >Manager reason<textarea
                            value="Count and movement evidence reviewed."
                        />
                    </label>
                    <AppButton
                        v-if="state !== 'closed'"
                        variant="primary"
                        @click="approveClose"
                        >Approve and close</AppButton
                    >
                </div>
            </section>
        </div>

        <section class="gallery-section" aria-labelledby="ledger-title">
            <div class="section-heading">
                <div>
                    <p class="kicker">Movement ledger</p>
                    <h2 id="ledger-title">Every amount has one source</h2>
                </div>
                <StatusBadge tone="info">Cash only</StatusBadge>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Staff</th>
                            <th>Source / reason</th>
                            <th class="numeric">Drawer effect</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>09:02</td>
                            <td>Ayesha Khan</td>
                            <td>Opening float · session source</td>
                            <td class="numeric positive">+ PKR 10,000.00</td>
                        </tr>
                        <tr>
                            <td>10:14</td>
                            <td>Ayesha Khan</td>
                            <td>Change replenishment · paid in</td>
                            <td class="numeric positive">
                                + PKR {{ paidIn.toFixed(2) }}
                            </td>
                        </tr>
                        <tr>
                            <td>11:26</td>
                            <td>Bilal Ahmed</td>
                            <td>Courier expense · approval recorded</td>
                            <td class="numeric negative">
                                − PKR {{ paidOut.toFixed(2) }}
                            </td>
                        </tr>
                        <tr>
                            <td>12:08</td>
                            <td>Ayesha Khan</td>
                            <td>Card sale · external tender</td>
                            <td class="numeric muted">PKR 0.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="state-strip" aria-label="Cash session supporting states">
            <StatePanel
                state="empty"
                title="No active session"
                message="Open this register with a verified float to begin."
            />
            <StatePanel
                state="loading"
                title="Loading ledger"
                message="Fetching the latest server-confirmed movement watermark."
            />
            <StatePanel
                state="error"
                title="Count needs attention"
                message="A physical count is required before expected cash is revealed."
            />
        </div>
    </div>
</template>

<style scoped>
.cash-preview {
    display: grid;
    gap: 1.15rem;
}
.cash-heading {
    background: linear-gradient(
        135deg,
        var(--surface) 0%,
        var(--info-surface) 100%
    );
}
.cash-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(19rem, 0.75fr);
    gap: 1rem;
}
.session-card,
.close-card {
    margin: 0;
}
.cash-metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.7rem;
    margin: 1rem 0;
}
.cash-metrics div {
    padding: 0.85rem;
    border: 1px solid var(--border);
    border-radius: 0.7rem;
    background: var(--surface-raised);
}
.cash-metrics dt,
.review-box span {
    color: var(--text-muted);
    font-size: 0.78rem;
}
.cash-metrics dd {
    margin: 0.25rem 0 0;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
}
.cash-metrics .concealed {
    border-style: dashed;
}
.movement-actions {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
}
.privacy-chip {
    padding: 0.35rem 0.55rem;
    border: 1px dashed var(--border-strong);
    border-radius: 999px;
    color: var(--text-muted);
    font-size: 0.78rem;
}
.count-field {
    display: grid;
    gap: 0.4rem;
    margin: 1rem 0;
    font-weight: 650;
}
.count-field small {
    color: var(--text-muted);
    font-weight: 400;
}
.money-input {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    border: 1px solid var(--border-strong);
    border-radius: 0.65rem;
    background: var(--input);
    overflow: hidden;
}
.money-input b {
    padding: 0.8rem;
    color: var(--text-muted);
    border-right: 1px solid var(--border);
}
.money-input input {
    min-width: 0;
    padding: 0.8rem;
    border: 0;
    background: transparent;
    color: var(--text);
}
.pending-check {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    margin: 0.8rem 0 1rem;
    color: var(--text-muted);
}
.review-box {
    display: grid;
    gap: 0.65rem;
    padding: 0.85rem;
    border-radius: 0.75rem;
    background: var(--surface-raised);
}
.review-box > div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.review-box label {
    display: grid;
    gap: 0.35rem;
    color: var(--text-muted);
    font-size: 0.82rem;
}
.review-box textarea {
    min-height: 4.5rem;
    padding: 0.65rem;
    border: 1px solid var(--border);
    border-radius: 0.55rem;
    background: var(--input);
    color: var(--text);
    resize: vertical;
}
.positive {
    color: var(--success);
}
.negative {
    color: var(--danger);
}
.muted {
    color: var(--text-muted);
}
.state-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
@media (max-width: 900px) {
    .cash-grid {
        grid-template-columns: 1fr;
    }
    .cash-metrics {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 560px) {
    .cash-metrics,
    .state-strip {
        grid-template-columns: 1fr;
    }
}
</style>
