<script setup lang="ts">
import { computed, nextTick, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type ViewState = "ready" | "empty" | "loading" | "error";
const viewState = ref<ViewState>("ready");
const quantity = ref("1.000000");
const disposition = ref("restock");
const refundMethod = ref("cash");
const externalReference = ref("");
const late = ref(false);
const approved = ref(false);
const posted = ref(false);
const pending = ref(false);
const announcement = ref("");
const search = ref("SALE-7K29F4");
const quantityInput = ref<HTMLInputElement | null>(null);

const refund = computed(() =>
    quantity.value === "0.500000" ? "59.00" : "118.00",
);
const invalidQuantity = computed(
    () => Number(quantity.value) <= 0 || Number(quantity.value) > 1,
);
const canPost = computed(
    () =>
        !invalidQuantity.value &&
        (!late.value || approved.value) &&
        (refundMethod.value === "cash" ||
            externalReference.value.trim() !== ""),
);

async function lookup(): Promise<void> {
    viewState.value = "loading";
    await new Promise((resolve) => window.setTimeout(resolve, 240));
    viewState.value = search.value.trim() ? "ready" : "empty";
    if (viewState.value === "ready")
        await nextTick(() => quantityInput.value?.focus());
}
function approve(): void {
    approved.value = true;
    announcement.value =
        "Manager approval bound to this late return for one use.";
}
function post(): void {
    if (!canPost.value) return;
    posted.value = true;
    pending.value =
        refundMethod.value !== "cash" && externalReference.value === "PENDING";
    announcement.value = pending.value
        ? "Return recorded. External refund still needs provider evidence."
        : `Return posted. PKR ${refund.value} was refunded and the original sale entitlement was reduced.`;
}
</script>

<template>
    <div class="returns-preview">
        <header class="page-heading returns-heading">
            <div>
                <p class="kicker">Returns & exchanges · Lahore Test Store</p>
                <h1>Start with the original sale.</h1>
                <p>
                    Quantities, discounts and tax come from the receipt. Every
                    refund keeps its drawer or external confirmation trail.
                </p>
            </div>
            <StatusBadge
                :tone="posted ? (pending ? 'warning' : 'success') : 'info'"
            >
                {{
                    posted
                        ? pending
                            ? "Refund pending"
                            : "Return posted"
                        : "7-day policy"
                }}
            </StatusBadge>
        </header>

        <section class="return-lookup" aria-labelledby="lookup-title">
            <div>
                <p class="kicker">Original transaction</p>
                <h2 id="lookup-title">Find a paid sale</h2>
            </div>
            <label>
                Receipt or sale number
                <input
                    v-model="search"
                    type="search"
                    autocomplete="off"
                    @keydown.enter="lookup"
                />
            </label>
            <AppButton
                variant="primary"
                :busy="viewState === 'loading'"
                @click="lookup"
                >Find sale</AppButton
            >
        </section>

        <StatePanel
            v-if="viewState === 'empty'"
            state="empty"
            title="No sale selected"
            message="Scan a receipt barcode or enter its sale number to begin."
        />
        <StatePanel
            v-else-if="viewState === 'error'"
            state="error"
            title="Sale lookup failed"
            message="The return draft is unchanged. Check connectivity and retry."
        />
        <StatePanel
            v-else-if="viewState === 'loading'"
            state="loading"
            title="Loading original sale"
            message="Checking returnable quantities and previous refunds…"
        />

        <div v-else class="return-layout">
            <main class="return-main">
                <section class="sale-summary" aria-labelledby="sale-title">
                    <div class="section-heading">
                        <div>
                            <p class="kicker">
                                SALE-7K29F4 · 27 Sep 2026, 14:32
                            </p>
                            <h2 id="sale-title">Original sale · PKR 236.00</h2>
                            <p>Counter 01 · Cashier Hira · paid by cash</p>
                        </div>
                        <StatusBadge tone="success">Returnable</StatusBadge>
                    </div>
                    <div class="policy-strip">
                        <strong>{{
                            late
                                ? "Outside ordinary window"
                                : "2 days since sale"
                        }}</strong>
                        <span>{{
                            late
                                ? "Manager approval and reason required"
                                : "5 days remain under current policy"
                        }}</span>
                        <button
                            type="button"
                            @click="
                                late = !late;
                                approved = false;
                            "
                        >
                            Preview {{ late ? "ordinary" : "late" }} case
                        </button>
                    </div>
                </section>

                <section class="line-card" aria-labelledby="line-title">
                    <div class="line-product">
                        <span class="line-index">01</span>
                        <div>
                            <h3 id="line-title">Assam Breakfast Tea</h3>
                            <p>
                                TEA-1 · sold 2.000000 pc · 1.000000 already
                                returned
                            </p>
                        </div>
                        <strong>PKR 118.00</strong>
                    </div>
                    <div class="line-controls">
                        <label
                            >Return quantity<input
                                ref="quantityInput"
                                v-model="quantity"
                                inputmode="decimal"
                                :aria-invalid="invalidQuantity"
                        /></label>
                        <label
                            >Stock disposition<select v-model="disposition">
                                <option value="restock">
                                    Restock to original location
                                </option>
                                <option value="damaged">
                                    Damaged · no restock
                                </option>
                                <option value="expired">
                                    Expired · no restock
                                </option>
                                <option value="discard">
                                    Discard · no restock
                                </option>
                            </select></label
                        >
                    </div>
                    <p v-if="invalidQuantity" class="field-error" role="alert">
                        Enter up to the remaining 1.000000 pc.
                    </p>
                    <dl class="allocation-grid">
                        <div>
                            <dt>Original price</dt>
                            <dd>PKR 100.00</dd>
                        </div>
                        <div>
                            <dt>Discount share</dt>
                            <dd>PKR 0.00</dd>
                        </div>
                        <div>
                            <dt>Original tax</dt>
                            <dd>PKR 18.00</dd>
                        </div>
                        <div>
                            <dt>Refund entitlement</dt>
                            <dd>PKR {{ refund }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="late"
                    class="approval-card"
                    aria-labelledby="approval-title"
                >
                    <div>
                        <p class="kicker">Deadline exception</p>
                        <h2 id="approval-title">Manager approval</h2>
                        <p>
                            Reason: sealed goods verified after the ordinary
                            return window.
                        </p>
                    </div>
                    <StatusBadge :tone="approved ? 'success' : 'warning'">{{
                        approved ? "Approved once" : "Approval needed"
                    }}</StatusBadge>
                    <AppButton :disabled="approved" @click="approve">{{
                        approved ? "Approval captured" : "Approve this return"
                    }}</AppButton>
                </section>
            </main>

            <aside class="refund-panel" aria-labelledby="refund-title">
                <div>
                    <p class="kicker">Settlement</p>
                    <h2 id="refund-title">Refund PKR {{ refund }}</h2>
                </div>
                <label
                    >Refund method<select v-model="refundMethod">
                        <option value="cash">Cash · active drawer</option>
                        <option value="external_card">External card</option>
                        <option value="external_wallet">External wallet</option>
                        <option value="bank_transfer">Bank transfer</option>
                    </select></label
                >
                <label v-if="refundMethod !== 'cash'"
                    >Actual confirmation reference<input
                        v-model="externalReference"
                        placeholder="Reference, or PENDING"
                    /><small
                        >Use PENDING only while the provider action is
                        unfinished.</small
                    ></label
                >
                <div class="refund-math">
                    <span>Original line entitlement</span
                    ><strong>PKR {{ refund }}</strong
                    ><span>Refund tenders</span><strong>PKR {{ refund }}</strong
                    ><span class="grand">Difference</span
                    ><strong class="grand">PKR 0.00</strong>
                </div>
                <label class="reason-field"
                    >Return reason<textarea rows="3">
Customer changed their mind; packaging checked at counter.</textarea>
                </label>
                <AppButton
                    variant="primary"
                    :disabled="!canPost || posted"
                    @click="post"
                    >{{ posted ? "Return recorded" : "Post return" }}</AppButton
                >
                <p class="settlement-note">
                    Cash posts a negative movement in the current session.
                    External methods need real confirmation evidence.
                </p>
            </aside>
        </div>

        <section
            v-if="posted"
            class="return-receipt"
            aria-labelledby="receipt-title"
        >
            <div>
                <p class="kicker">Linked document</p>
                <h2 id="receipt-title">RET-P9X2LM</h2>
                <p>
                    Original SALE-7K29F4 · {{ quantity }} pc · {{ disposition }}
                </p>
            </div>
            <div>
                <span>Refund state</span
                ><strong>{{
                    pending ? "External action pending" : "Completed"
                }}</strong>
            </div>
            <div>
                <span>Refund total</span><strong>PKR {{ refund }}</strong>
            </div>
            <AppButton>Print return receipt</AppButton>
        </section>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>
    </div>
</template>

<style scoped>
.returns-preview {
    display: grid;
    gap: 1.25rem;
}
.returns-heading {
    background: linear-gradient(
        125deg,
        var(--surface) 45%,
        var(--info-surface)
    );
}
.return-lookup {
    display: grid;
    grid-template-columns: minmax(13rem, 1fr) minmax(16rem, 1.4fr) auto;
    gap: 1rem;
    align-items: end;
    padding: 1.1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.9rem;
    box-shadow: var(--shadow);
}
label {
    display: grid;
    gap: 0.42rem;
    color: var(--text);
    font-size: 0.82rem;
    font-weight: 700;
}
input,
select,
textarea {
    width: 100%;
    padding: 0.72rem 0.8rem;
    color: var(--text);
    background: var(--input);
    border: 1px solid var(--border-strong);
    border-radius: 0.55rem;
}
small {
    color: var(--text-muted);
    font-weight: 400;
    line-height: 1.35;
}
.return-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 23rem);
    gap: 1.25rem;
    align-items: start;
}
.return-main {
    display: grid;
    gap: 1rem;
}
.sale-summary,
.line-card,
.approval-card,
.refund-panel,
.return-receipt {
    padding: 1.25rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.9rem;
    box-shadow: var(--shadow);
}
.section-heading,
.line-product,
.approval-card,
.return-receipt {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
}
.policy-strip {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.8rem;
    align-items: center;
    margin-top: 1rem;
    padding: 0.75rem;
    background: var(--info-surface);
    border-radius: 0.6rem;
}
.policy-strip span {
    color: var(--text-muted);
    font-size: 0.85rem;
}
.policy-strip button {
    color: var(--info);
    background: transparent;
    border: 0;
    text-decoration: underline;
    cursor: pointer;
}
.line-card {
    display: grid;
    gap: 1rem;
}
.line-product {
    display: grid;
    grid-template-columns: auto 1fr auto;
}
.line-product h3 {
    margin: 0 0 0.25rem;
}
.line-index {
    display: grid;
    place-items: center;
    width: 2.4rem;
    height: 2.4rem;
    color: var(--accent-contrast);
    background: var(--accent);
    border-radius: 0.55rem;
    font-weight: 800;
}
.line-controls {
    display: grid;
    grid-template-columns: 1fr 1.7fr;
    gap: 1rem;
}
.field-error {
    color: var(--danger);
    font-size: 0.85rem;
}
.allocation-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.6rem;
    margin: 0;
}
.allocation-grid div {
    padding: 0.7rem;
    background: var(--surface-raised);
    border: 1px solid var(--border);
    border-radius: 0.55rem;
}
dt {
    color: var(--text-muted);
    font-size: 0.73rem;
}
dd {
    margin: 0.25rem 0 0;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
}
.approval-card {
    border-left: 0.3rem solid var(--warning);
}
.refund-panel {
    position: sticky;
    top: 1rem;
    display: grid;
    gap: 1rem;
}
.refund-panel h2 {
    font-size: 1.7rem;
}
.refund-math {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.65rem;
    padding-block: 1rem;
    border-block: 1px solid var(--border);
    font-variant-numeric: tabular-nums;
}
.refund-math .grand {
    padding-top: 0.65rem;
    border-top: 1px solid var(--border);
}
.settlement-note {
    font-size: 0.78rem;
}
.return-receipt {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
}
.return-receipt > div:not(:first-child) {
    display: grid;
    gap: 0.2rem;
}
.return-receipt span {
    color: var(--text-muted);
    font-size: 0.75rem;
}
@media (max-width: 900px) {
    .return-layout {
        grid-template-columns: 1fr;
    }
    .refund-panel {
        position: static;
    }
    .return-receipt {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 650px) {
    .return-lookup,
    .line-controls,
    .allocation-grid {
        grid-template-columns: 1fr;
    }
    .policy-strip {
        grid-template-columns: 1fr;
    }
    .section-heading,
    .approval-card {
        align-items: flex-start;
        flex-direction: column;
    }
    .return-receipt {
        grid-template-columns: 1fr;
    }
}
</style>
