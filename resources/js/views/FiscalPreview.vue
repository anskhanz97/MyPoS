<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
const reconciled = ref(false);
</script>
<template>
    <section class="fiscal-preview">
        <header>
            <div>
                <p class="kicker">Fiscal readiness</p>
                <h2>Local sale and authority outcome stay distinct.</h2>
                <p>FBR DI · v1.12 · sandbox evidence only</p>
            </div>
            <StatusBadge tone="warning">Production blocked</StatusBadge>
        </header>
        <div class="gate">
            <strong>Launch gate</strong
            ><span
                >Licensed-integrator onboarding, merchant credentials, approved
                mappings and written outage procedure are still required.</span
            >
        </div>
        <div class="fiscal-grid">
            <article>
                <div class="title">
                    <div>
                        <p class="kicker">Submission evidence</p>
                        <h3>Recent documents</h3>
                    </div>
                    <StatusBadge tone="info">Immutable payloads</StatusBadge>
                </div>
                <div class="document">
                    <span class="mark ok">✓</span>
                    <div>
                        <strong>SALE-20260927-00007</strong
                        ><small>Accepted · FBR-INV-1</small>
                    </div>
                    <span>12:42</span>
                </div>
                <div class="document">
                    <span class="mark no">×</span>
                    <div>
                        <strong>SALE-20260927-00006</strong
                        ><small>Rejected · mapping 0042</small>
                    </div>
                    <AppButton variant="quiet">Review</AppButton>
                </div>
                <div class="document">
                    <span class="mark maybe">?</span>
                    <div>
                        <strong>SALE-20260927-00005</strong
                        ><small>{{
                            reconciled
                                ? "Accepted · reconciled"
                                : "Unknown · do not retry"
                        }}</small>
                    </div>
                    <AppButton
                        v-if="!reconciled"
                        variant="quiet"
                        @click="reconciled = true"
                        >Record lookup evidence</AppButton
                    >
                </div>
            </article>
            <article>
                <p class="kicker">Authority matrix</p>
                <h3>Activation status</h3>
                <div class="authority">
                    <strong>FBR DI</strong
                    ><StatusBadge tone="warning">Sandbox coded</StatusBadge
                    ><small>v1.12 mapping and fixed host</small>
                </div>
                <div class="authority">
                    <strong>KPRA</strong><StatusBadge>Blocked</StatusBadge
                    ><small>Conflicting official field shapes</small>
                </div>
                <div class="authority">
                    <strong>PRA · SRB · BRA · ICT</strong
                    ><StatusBadge>Blocked</StatusBadge
                    ><small
                        >Current canonical onboarding contracts not
                        proven</small
                    >
                </div>
            </article>
        </div>
        <div class="rule">
            <strong>Unknown is a real state</strong
            ><span
                >A timeout after send never generates a new invoice number. An
                official lookup or licensed-integrator confirmation must resolve
                it.</span
            >
        </div>
    </section>
</template>
<style scoped>
.fiscal-preview {
    display: grid;
    gap: 1rem;
}
.fiscal-preview > header,
article,
.gate,
.rule {
    padding: 1.25rem;
    border: 1px solid var(--border);
    border-radius: 0.9rem;
    background: var(--surface);
}
.fiscal-preview > header,
.title,
.gate,
.rule {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
h2 {
    margin: 0.2rem 0;
    font-size: clamp(1.8rem, 4vw, 3rem);
}
.gate {
    border-left: 4px solid var(--warning);
}
.gate span,
.rule span,
small {
    color: var(--text-muted);
}
.fiscal-grid {
    display: grid;
    grid-template-columns: 1.25fr 0.75fr;
    gap: 1rem;
}
.title h3,
article h3 {
    margin: 0.2rem 0;
}
.document {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    gap: 0.8rem;
    align-items: center;
    padding: 0.9rem 0;
    border-bottom: 1px solid var(--border);
}
.document small {
    display: block;
}
.mark {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    font-weight: 800;
}
.mark.ok {
    background: var(--success-surface);
    color: var(--success);
}
.mark.no {
    background: var(--danger-surface);
    color: var(--danger);
}
.mark.maybe {
    background: var(--warning-surface);
}
.authority {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.4rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid var(--border);
}
.authority small {
    grid-column: 1/-1;
}
@media (max-width: 760px) {
    .fiscal-preview > header,
    .title,
    .gate,
    .rule {
        align-items: start;
        flex-direction: column;
    }
    .fiscal-grid {
        grid-template-columns: 1fr;
    }
    .document {
        grid-template-columns: 34px 1fr;
    }
    .document > :last-child {
        grid-column: 2;
    }
}
</style>
