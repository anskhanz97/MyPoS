<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
const posted = ref(false);
const paid = ref(40000);
const net = 74000;
const outstanding = computed(() => net - paid.value);
</script>
<template>
    <section class="payroll-preview" aria-labelledby="payroll-title">
        <header class="payroll-head">
            <div>
                <p class="kicker">Payroll · September 2026</p>
                <h2 id="payroll-title">
                    Every rupee, explained before posting.
                </h2>
                <p>
                    Operational payroll from frozen attendance and pay terms.
                    Statutory amounts are entered or imported.
                </p>
            </div>
            <div class="payroll-total">
                <span>Net payroll</span><strong>PKR 74,000.00</strong
                ><StatusBadge :tone="posted ? 'success' : 'warning'">{{
                    posted ? "Posted" : "Draft review"
                }}</StatusBadge>
            </div>
        </header>
        <div class="metric-row">
            <article><span>Base pay</span><strong>75,000.00</strong></article>
            <article>
                <span>Allowances</span
                ><strong class="positive">+5,000.00</strong>
            </article>
            <article><span>Deductions</span><strong>−6,000.00</strong></article>
            <article>
                <span>Outstanding</span
                ><strong>{{
                    outstanding.toLocaleString("en-PK", {
                        minimumFractionDigits: 2,
                    })
                }}</strong>
            </article>
        </div>
        <article class="payroll-card">
            <div class="employee">
                <span class="pay-avatar">AK</span>
                <div>
                    <h3>Ayesha Khan</h3>
                    <p>PAY-001 · Monthly</p>
                </div>
                <strong>PKR 74,000.00</strong>
            </div>
            <div class="calculation">
                <div><span>Base salary</span><strong>75,000.00</strong></div>
                <div>
                    <span>Performance allowance</span
                    ><strong class="positive">+5,000.00</strong>
                </div>
                <div>
                    <span>Entered statutory</span><strong>−2,000.00</strong>
                </div>
                <div>
                    <span>Advance recovery</span><strong>−3,000.00</strong>
                </div>
                <div>
                    <span>Other deduction</span><strong>−1,000.00</strong>
                </div>
            </div>
            <p class="formula">
                Monthly base + allowances − entered/imported deductions. Half-up
                rounding to 2 decimal PKR.
            </p>
            <div class="pay-actions">
                <AppButton
                    variant="primary"
                    :disabled="posted"
                    @click="posted = true"
                    >{{
                        posted ? "Payroll posted" : "Approve & post"
                    }}</AppButton
                ><AppButton>Preview payslip</AppButton
                ><AppButton v-if="posted" @click="paid = 74000"
                    >Record remaining payout</AppButton
                >
            </div>
        </article>
        <div class="payroll-foot">
            <div>
                <p class="kicker">Recorded payout</p>
                <strong
                    >PKR
                    {{
                        paid.toLocaleString("en-PK", {
                            minimumFractionDigits: 2,
                        })
                    }}</strong
                >
                <p>
                    External reference BANK-REC-1. This is a record, not a bank
                    confirmation.
                </p>
            </div>
            <div>
                <p class="kicker">Input snapshot</p>
                <strong>Locked · SHA-256 verified</strong>
                <p>
                    Later attendance corrections create an explicit adjustment
                    in a later run.
                </p>
            </div>
        </div>
    </section>
</template>
<style scoped>
.payroll-preview {
    display: grid;
    gap: 1rem;
}
.payroll-head {
    display: flex;
    justify-content: space-between;
    gap: 2rem;
    padding: 1.5rem;
    border-radius: 1rem;
    background: linear-gradient(
        135deg,
        var(--surface),
        color-mix(in srgb, var(--chart-3) 10%, var(--surface))
    );
    border: 1px solid var(--border);
}
h2 {
    margin: 0.25rem 0;
    font-size: clamp(1.8rem, 4vw, 3rem);
}
.payroll-total {
    min-width: 220px;
    display: grid;
    gap: 0.5rem;
    align-content: center;
}
.payroll-total strong {
    font-size: 1.8rem;
}
.metric-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}
.metric-row article,
.payroll-card,
.payroll-foot > div {
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 0.8rem;
    background: var(--surface);
}
.metric-row span,
.calculation span,
.formula,
.employee p,
.payroll-foot p {
    color: var(--text-muted);
}
.metric-row strong {
    display: block;
    margin-top: 0.35rem;
    font-size: 1.25rem;
}
.positive {
    color: var(--success);
}
.employee {
    display: grid;
    grid-template-columns: 44px 1fr auto;
    align-items: center;
    gap: 0.75rem;
}
.employee h3,
.employee p {
    margin: 0.1rem;
}
.pay-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--success-surface);
    color: var(--success);
    font-weight: 800;
}
.calculation {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    margin: 1rem 0;
}
.calculation div {
    padding: 0.8rem;
    background: var(--surface-raised);
    border-radius: 0.5rem;
}
.calculation strong {
    display: block;
    margin-top: 0.3rem;
}
.formula {
    padding: 0.75rem;
    border-left: 3px solid var(--info);
}
.pay-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.payroll-foot {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.payroll-foot p {
    margin: 0.35rem 0;
}
@media (max-width: 760px) {
    .payroll-head {
        display: grid;
    }
    .metric-row {
        grid-template-columns: 1fr 1fr;
    }
    .calculation {
        grid-template-columns: 1fr;
    }
    .employee {
        grid-template-columns: 44px 1fr;
    }
    .employee > strong {
        grid-column: 1/-1;
    }
    .payroll-foot {
        grid-template-columns: 1fr;
    }
}
</style>
