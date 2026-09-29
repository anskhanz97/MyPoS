<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
const corrected = ref(false);
</script>
<template>
    <section class="finance-preview">
        <header>
            <div>
                <p class="kicker">Operational finance</p>
                <h2>Cash out once. Reconcile every source.</h2>
                <p>
                    Expenses, supplier payments and payroll payouts keep their
                    own provenance.
                </p>
            </div>
            <div class="settle">
                <span>September settlement</span><strong>PKR 186,420</strong
                ><StatusBadge tone="warning">1 exception</StatusBadge>
            </div>
        </header>
        <div class="finance-grid">
            <article>
                <div class="title">
                    <div>
                        <p class="kicker">Expenses</p>
                        <h3>Recent records</h3>
                    </div>
                    <AppButton>New expense</AppButton>
                </div>
                <div class="expense">
                    <span class="icon">⚡</span>
                    <div>
                        <strong>Electricity vendor</strong
                        ><small>Utilities · Cash · BILL-1</small>
                    </div>
                    <strong :class="{ strike: corrected }">PKR 1,200</strong>
                </div>
                <div v-if="corrected" class="correction">
                    Correction recorded · original evidence retained
                </div>
                <div class="expense">
                    <span class="icon">▣</span>
                    <div>
                        <strong>Office supplies</strong
                        ><small>General · External recorded</small>
                    </div>
                    <strong>PKR 3,850</strong>
                </div>
                <AppButton
                    v-if="!corrected"
                    variant="danger"
                    @click="corrected = true"
                    >Correct selected record</AppButton
                >
            </article>
            <article>
                <div class="title">
                    <div>
                        <p class="kicker">Tender settlements</p>
                        <h3>Manual card statement</h3>
                    </div>
                    <StatusBadge tone="info">User-entered</StatusBadge>
                </div>
                <div class="match"><span>Matched</span><strong>12</strong></div>
                <div class="match warning">
                    <span>Fee difference</span><strong>PKR 240</strong>
                </div>
                <div class="match danger">
                    <span>Unmatched reference</span><strong>UNKNOWN-1</strong>
                </div>
                <p class="note">
                    A matched line is operational reconciliation, not
                    cryptographic bank confirmation.
                </p>
            </article>
        </div>
        <div class="scope-note">
            <strong>No double counting</strong
            ><span
                >Supplier and payroll payouts remain in their owning ledgers.
                Reports classify their cash effect without copying them into
                expense totals.</span
            >
        </div>
    </section>
</template>
<style scoped>
.finance-preview {
    display: grid;
    gap: 1rem;
}
.finance-preview > header {
    display: flex;
    justify-content: space-between;
    gap: 2rem;
    padding: 1.5rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: linear-gradient(
        135deg,
        var(--surface),
        color-mix(in srgb, var(--chart-2) 10%, var(--surface))
    );
}
h2 {
    margin: 0.25rem 0;
    font-size: clamp(1.8rem, 4vw, 3rem);
}
.settle {
    display: grid;
    gap: 0.4rem;
    min-width: 220px;
    align-content: center;
}
.settle strong {
    font-size: 1.6rem;
}
.finance-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 1rem;
}
.finance-grid article,
.scope-note {
    padding: 1.2rem;
    border: 1px solid var(--border);
    border-radius: 0.8rem;
    background: var(--surface);
}
.title {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.title h3 {
    margin: 0.2rem 0;
}
.expense {
    display: grid;
    grid-template-columns: 38px 1fr auto;
    gap: 0.75rem;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}
.expense small {
    display: block;
    color: var(--text-muted);
}
.icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 0.5rem;
    background: var(--warning-surface);
}
.strike {
    text-decoration: line-through;
    color: var(--text-muted);
}
.correction {
    padding: 0.6rem;
    background: var(--success-surface);
    color: var(--success);
}
.match {
    display: flex;
    justify-content: space-between;
    padding: 0.9rem;
    margin: 0.5rem 0;
    border-radius: 0.5rem;
    background: var(--success-surface);
}
.match.warning {
    background: var(--warning-surface);
}
.match.danger {
    background: var(--danger-surface);
}
.note {
    color: var(--text-muted);
}
.scope-note {
    display: flex;
    gap: 1rem;
}
.scope-note span {
    color: var(--text-muted);
}
@media (max-width: 760px) {
    .finance-preview > header,
    .finance-grid {
        display: grid;
        grid-template-columns: 1fr;
    }
    .scope-note {
        display: grid;
    }
    .expense {
        grid-template-columns: 38px 1fr;
    }
    .expense > strong {
        grid-column: 2;
    }
}
</style>
