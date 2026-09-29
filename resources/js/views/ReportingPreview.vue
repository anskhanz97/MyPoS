<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";

const saved = ref(false);
const selected = ref("Gross sales");
const metrics = [
    ["Gross sales", "PKR 428,750", 92],
    ["Returns", "PKR 12,480", 18],
    ["Cost of goods", "PKR 241,620", 58],
    ["Gross margin", "PKR 174,650", 42],
] as const;
</script>

<template>
    <section class="report-preview">
        <header>
            <div>
                <p class="kicker">Standard reports</p>
                <h2>Every total leads back to its source.</h2>
                <p>September 01–30 · All stores · Asia/Karachi</p>
            </div>
            <div class="actions">
                <StatusBadge tone="success">Live ledger</StatusBadge>
                <AppButton @click="saved = true">Save this filter</AppButton>
            </div>
        </header>
        <div v-if="saved" class="saved" role="status">
            “Month end” saved for your account only.
        </div>
        <div class="metric-grid">
            <button
                v-for="metric in metrics"
                :key="metric[0]"
                :class="{ selected: selected === metric[0] }"
                @click="selected = metric[0]"
            >
                <span>{{ metric[0] }}</span
                ><strong>{{ metric[1] }}</strong>
                <i><b :style="{ width: `${metric[2]}%` }" /></i>
            </button>
        </div>
        <div class="report-grid">
            <article>
                <div class="title">
                    <div>
                        <p class="kicker">Daily movement</p>
                        <h3>Sales after returns</h3>
                    </div>
                    <span>PKR</span>
                </div>
                <div class="chart" aria-label="Sales chart">
                    <i
                        v-for="height in [42, 65, 54, 82, 71, 96, 74]"
                        :key="height"
                        :style="{ height: `${height}%` }"
                    />
                </div>
                <div class="axis"><span>Mon</span><span>Sun</span></div>
            </article>
            <article>
                <div class="title">
                    <div>
                        <p class="kicker">Drill-down</p>
                        <h3>{{ selected }}</h3>
                    </div>
                    <AppButton variant="quiet">Export CSV</AppButton>
                </div>
                <button class="row">
                    <span
                        >INV-02481<br /><small>Sep 29 · Main store</small></span
                    ><strong>PKR 8,450</strong>
                </button>
                <button class="row">
                    <span>INV-02480<br /><small>Sep 29 · Clifton</small></span
                    ><strong>PKR 6,220</strong>
                </button>
                <button class="row">
                    <span
                        >INV-02479<br /><small>Sep 28 · Main store</small></span
                    ><strong>PKR 4,980</strong>
                </button>
            </article>
        </div>
        <div class="definition">
            <strong>Definition</strong>
            <span
                >Returns use their own business date. Provisional cost
                adjustments remain visible until resolved.</span
            >
        </div>
    </section>
</template>

<style scoped>
.report-preview {
    display: grid;
    gap: 1rem;
}
.report-preview > header,
.report-grid article,
.definition {
    border: 1px solid var(--border);
    border-radius: 0.9rem;
    background: var(--surface);
    padding: 1.25rem;
}
.report-preview > header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    background: linear-gradient(
        135deg,
        var(--surface),
        color-mix(in srgb, var(--accent) 9%, var(--surface))
    );
}
h2 {
    margin: 0.2rem 0;
    font-size: clamp(1.8rem, 4vw, 3rem);
}
.actions,
.title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.saved {
    padding: 0.7rem 1rem;
    border-left: 4px solid var(--success);
    background: var(--success-surface);
}
.metric-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}
.metric-grid button {
    text-align: left;
    display: grid;
    gap: 0.45rem;
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    background: var(--surface);
    color: var(--text);
    padding: 1rem;
}
.metric-grid button.selected {
    outline: 3px solid color-mix(in srgb, var(--accent) 25%, transparent);
    border-color: var(--accent);
}
.metric-grid strong {
    font-size: 1.25rem;
}
.metric-grid i {
    height: 5px;
    border-radius: 5px;
    background: var(--surface-muted);
    overflow: hidden;
}
.metric-grid b {
    display: block;
    height: 100%;
    background: var(--accent);
}
.report-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 1rem;
}
.title h3 {
    margin: 0.2rem 0;
}
.chart {
    height: 180px;
    display: flex;
    gap: 0.65rem;
    align-items: end;
    padding-top: 1rem;
    border-bottom: 1px solid var(--border);
}
.chart i {
    flex: 1;
    min-width: 16px;
    border-radius: 0.35rem 0.35rem 0 0;
    background: linear-gradient(var(--accent), var(--chart-2));
}
.axis,
.row,
.definition {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.axis {
    color: var(--text-muted);
    padding-top: 0.35rem;
}
.row {
    width: 100%;
    text-align: left;
    align-items: center;
    padding: 0.8rem 0;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: transparent;
    color: var(--text);
}
.row small,
.definition span {
    color: var(--text-muted);
}
@media (max-width: 760px) {
    .report-preview > header,
    .actions,
    .definition {
        align-items: start;
        flex-direction: column;
    }
    .metric-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .report-grid {
        grid-template-columns: 1fr;
    }
}
</style>
