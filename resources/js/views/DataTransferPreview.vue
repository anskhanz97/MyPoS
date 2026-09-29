<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
const committed = ref(false);
</script>
<template>
    <section class="transfer-preview">
        <header>
            <div>
                <p class="kicker">Data transfer</p>
                <h2>Preview first. Commit once.</h2>
                <p>Catalog import · schema v1 · Main store</p>
            </div>
            <StatusBadge :tone="committed ? 'success' : 'warning'">{{
                committed ? "Committed" : "2 rows need review"
            }}</StatusBadge>
        </header>
        <div class="steps" aria-label="Import progress">
            <strong>1 Upload</strong><strong>2 Validate</strong
            ><strong :class="{ active: !committed }">3 Review</strong
            ><strong :class="{ active: committed }">4 Complete</strong>
        </div>
        <div class="transfer-grid">
            <article>
                <div class="title">
                    <div>
                        <p class="kicker">Row outcomes</p>
                        <h3>catalog-september.csv</h3>
                    </div>
                    <AppButton variant="quiet">Download errors</AppButton>
                </div>
                <div class="row good">
                    <span>2</span><strong>GREEN-TEA</strong
                    ><small>Valid · Unicode preserved</small>
                </div>
                <div class="row bad">
                    <span>3</span><strong>TEA-1</strong
                    ><small>Duplicate SKU in this store scope</small>
                </div>
                <div class="row bad">
                    <span>4</span><strong>SPICE-4</strong
                    ><small>Unknown unit “PACKS”</small>
                </div>
            </article>
            <article class="summary">
                <p class="kicker">Dry-run summary</p>
                <h3>No operational records changed</h3>
                <dl>
                    <div>
                        <dt>Total rows</dt>
                        <dd>3</dd>
                    </div>
                    <div>
                        <dt>Valid</dt>
                        <dd>1</dd>
                    </div>
                    <div>
                        <dt>Invalid</dt>
                        <dd>2</dd>
                    </div>
                </dl>
                <p>
                    Fix every financial or stock row before commit. Opening
                    stock posts explicit quantity and cost ledger entries.
                </p>
                <AppButton :disabled="!committed"
                    >Commit validated rows</AppButton
                ><AppButton
                    v-if="!committed"
                    variant="quiet"
                    @click="committed = true"
                    >Use corrected file simulation</AppButton
                >
            </article>
        </div>
        <div class="exports">
            <div>
                <strong>Customer export</strong
                ><span>Ready · expires in 7 days</span>
            </div>
            <div>
                <strong>Sales report export</strong
                ><span>Queued · permission recheck pending</span>
            </div>
            <AppButton>Download ready CSV</AppButton>
        </div>
    </section>
</template>
<style scoped>
.transfer-preview {
    display: grid;
    gap: 1rem;
}
.transfer-preview > header,
article,
.exports {
    padding: 1.25rem;
    border: 1px solid var(--border);
    border-radius: 0.9rem;
    background: var(--surface);
}
.transfer-preview > header,
.title,
.exports {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
h2 {
    margin: 0.2rem 0;
    font-size: clamp(1.8rem, 4vw, 3rem);
}
.steps {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2px;
    border-radius: 0.6rem;
    overflow: hidden;
}
.steps strong {
    padding: 0.75rem;
    background: var(--surface-muted);
    color: var(--text-muted);
    text-align: center;
}
.steps .active {
    background: var(--info-surface);
    color: var(--text);
}
.transfer-grid {
    display: grid;
    grid-template-columns: 1.3fr 0.8fr;
    gap: 1rem;
}
.title h3,
.summary h3 {
    margin: 0.2rem 0;
}
.row {
    display: grid;
    grid-template-columns: 34px 1fr 1.2fr;
    gap: 0.75rem;
    padding: 0.8rem 0;
    border-bottom: 1px solid var(--border);
}
.row span {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--surface-muted);
}
.row small {
    color: var(--text-muted);
}
.row.bad small {
    color: var(--danger);
}
.summary {
    display: grid;
    align-content: start;
    gap: 0.8rem;
}
dl {
    margin: 0;
}
dl div {
    display: flex;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--border);
}
dd {
    font-weight: 700;
}
.exports > div {
    display: grid;
}
.exports span,
.summary p {
    color: var(--text-muted);
}
@media (max-width: 760px) {
    .transfer-preview > header,
    .title,
    .exports {
        align-items: start;
        flex-direction: column;
    }
    .transfer-grid {
        grid-template-columns: 1fr;
    }
    .steps {
        grid-template-columns: repeat(2, 1fr);
    }
    .row {
        grid-template-columns: 34px 1fr;
    }
    .row small {
        grid-column: 2;
    }
}
</style>
