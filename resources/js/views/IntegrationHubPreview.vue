<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";

type Category = "all" | "communication" | "storage" | "payment";
type State = "active" | "draft" | "disabled";
type Integration = {
    name: string;
    provider: string;
    category: Exclude<Category, "all">;
    state: State;
    mode: string;
    mark: string;
    description: string;
    detail: string;
};

const category = ref<Category>("all");
const selected = ref<Integration | null>(null);
const testing = ref(false);
const testResult = ref("");
const search = ref("");
const integrations = ref<Integration[]>([
    {
        name: "Email",
        provider: "SMTP",
        category: "communication",
        state: "active",
        mode: "Live",
        mark: "@",
        description: "Receipts from your own mail server",
        detail: "receipts@northstar.pk",
    },
    {
        name: "WhatsApp",
        provider: "Meta Cloud API",
        category: "communication",
        state: "draft",
        mode: "Sandbox",
        mark: "W",
        description: "Approved receipt templates and delivery status",
        detail: "Template approval needed",
    },
    {
        name: "SMS",
        provider: "Twilio",
        category: "communication",
        state: "active",
        mode: "Sandbox",
        mark: "S",
        description: "Transactional receipt links with consent",
        detail: "Daily limit 250",
    },
    {
        name: "Google Drive",
        provider: "Google Picker",
        category: "storage",
        state: "active",
        mode: "Live",
        mark: "G",
        description: "Choose product images from Drive",
        detail: "Read-only picker",
    },
    {
        name: "OneDrive",
        provider: "Microsoft",
        category: "storage",
        state: "active",
        mode: "Sandbox",
        mark: "O",
        description: "Choose product images after sign-in",
        detail: "Credentials saved",
    },
    {
        name: "Stripe",
        provider: "Stripe Connect",
        category: "payment",
        state: "draft",
        mode: "Sandbox",
        mark: "⌁",
        description: "Card connector reserved for approved rollout",
        detail: "Checkout remains off",
    },
    {
        name: "Bank transfer",
        provider: "Manual verification",
        category: "payment",
        state: "active",
        mode: "Manual",
        mark: "B",
        description: "Bank account details at checkout",
        detail: "Proof checked by staff",
    },
    {
        name: "Raast",
        provider: "Manual verification",
        category: "payment",
        state: "active",
        mode: "Manual",
        mark: "R",
        description: "Raast alias for manual transfers",
        detail: "Alias ending 6916",
    },
    {
        name: "Easypaisa",
        provider: "Manual verification",
        category: "payment",
        state: "disabled",
        mode: "Manual",
        mark: "E",
        description: "Wallet destination at checkout",
        detail: "Not shown to cashiers",
    },
    {
        name: "JazzCash",
        provider: "Manual verification",
        category: "payment",
        state: "draft",
        mode: "Manual",
        mark: "J",
        description: "Wallet destination at checkout",
        detail: "Account title missing",
    },
]);

const filtered = computed(() =>
    integrations.value.filter(
        (item) =>
            (category.value === "all" || item.category === category.value) &&
            `${item.name} ${item.provider}`
                .toLowerCase()
                .includes(search.value.toLowerCase()),
    ),
);
const activeCount = computed(
    () => integrations.value.filter((item) => item.state === "active").length,
);

function open(item: Integration): void {
    selected.value = item;
    testResult.value = "";
}
function close(): void {
    selected.value = null;
    testing.value = false;
}
function save(): void {
    if (!selected.value) return;
    selected.value.state = "active";
    testResult.value =
        "Saved and activated. Secrets remain encrypted and hidden.";
}
function testConnection(): void {
    testing.value = true;
    testResult.value = "";
    window.setTimeout(() => {
        testing.value = false;
        testResult.value =
            selected.value?.mode === "Sandbox"
                ? "Sandbox ready. No external request or message was sent."
                : "Configuration check passed. No message or payment was sent.";
    }, 450);
}
</script>

<template>
    <section class="integration-hub" aria-labelledby="integration-title">
        <header class="integration-hero">
            <div class="integration-hero__copy">
                <p class="kicker">Integration control room</p>
                <h2 id="integration-title">
                    Services belong to each merchant.
                </h2>
                <p>
                    Configure communication, file pickers and payment
                    destinations here. Credentials stay encrypted in MyPoS, so
                    routine setup never requires server environment edits.
                </p>
            </div>
            <div class="integration-score" aria-label="Integration health">
                <strong>{{ activeCount }}/{{ integrations.length }}</strong>
                <span>available to this merchant</span>
                <i><b></b> Provider traffic protected</i>
            </div>
        </header>

        <div class="integration-toolbar">
            <nav aria-label="Integration categories">
                <button
                    v-for="item in [
                        ['all', 'All services'],
                        ['communication', 'Messages'],
                        ['storage', 'Files'],
                        ['payment', 'Payments'],
                    ] as const"
                    :key="item[0]"
                    :class="{ selected: category === item[0] }"
                    @click="category = item[0]"
                >
                    {{ item[1] }}
                </button>
            </nav>
            <label
                ><span>Find a service</span
                ><input
                    v-model="search"
                    type="search"
                    placeholder="Search integrations"
            /></label>
        </div>

        <div class="integration-layout">
            <aside class="integration-readiness">
                <h3>Before going live</h3>
                <ol>
                    <li class="done">
                        <span>1</span>
                        <div>
                            <strong>Save configuration</strong
                            ><small
                                >Public fields and encrypted credentials</small
                            >
                        </div>
                    </li>
                    <li>
                        <span>2</span>
                        <div>
                            <strong>Run a safe check</strong
                            ><small>Tests credentials without sending</small>
                        </div>
                    </li>
                    <li>
                        <span>3</span>
                        <div>
                            <strong>Activate the channel</strong
                            ><small
                                >Queued work rechecks consent and limits</small
                            >
                        </div>
                    </li>
                </ol>
                <div class="integration-policy">
                    <strong>Safe by default</strong>
                    <p>
                        Messaging begins in sandbox. Marketing sends are
                        unavailable. Disabling a channel preserves its delivery
                        history.
                    </p>
                </div>
            </aside>

            <div class="integration-list" aria-live="polite">
                <div class="integration-list__heading">
                    <span>{{ filtered.length }} services</span
                    ><small>Owner-only configuration</small>
                </div>
                <article
                    v-for="item in filtered"
                    :key="item.name"
                    class="integration-row"
                >
                    <div class="integration-mark" :data-kind="item.category">
                        {{ item.mark }}
                    </div>
                    <div class="integration-name">
                        <strong>{{ item.name }}</strong
                        ><span>{{ item.provider }}</span>
                    </div>
                    <p>{{ item.description }}</p>
                    <div class="integration-detail">
                        <span>{{ item.detail }}</span
                        ><small>{{ item.mode }}</small>
                    </div>
                    <StatusBadge
                        :tone="
                            item.state === 'active'
                                ? 'success'
                                : item.state === 'draft'
                                  ? 'warning'
                                  : 'neutral'
                        "
                        >{{ item.state }}</StatusBadge
                    >
                    <button
                        class="integration-configure"
                        :aria-label="`Configure ${item.name}`"
                        @click="open(item)"
                    >
                        Configure
                    </button>
                </article>
                <div v-if="filtered.length === 0" class="integration-empty">
                    <strong>No matching services</strong
                    ><span>Clear the search or choose another category.</span>
                </div>
            </div>
        </div>

        <div v-if="selected" class="integration-scrim" @click.self="close">
            <section
                class="integration-drawer"
                role="dialog"
                aria-modal="true"
                :aria-label="`Configure ${selected.name}`"
            >
                <header>
                    <div
                        class="integration-mark"
                        :data-kind="selected.category"
                    >
                        {{ selected.mark }}
                    </div>
                    <div>
                        <p>{{ selected.provider }}</p>
                        <h3>{{ selected.name }}</h3>
                    </div>
                    <button
                        aria-label="Close integration settings"
                        @click="close"
                    >
                        ×
                    </button>
                </header>
                <div class="integration-drawer__body">
                    <div class="integration-mode">
                        <span>Connection mode</span>
                        <div>
                            <button
                                :class="{
                                    selected: selected.mode === 'Sandbox',
                                }"
                                @click="selected.mode = 'Sandbox'"
                            >
                                Sandbox</button
                            ><button
                                :class="{ selected: selected.mode === 'Live' }"
                                @click="selected.mode = 'Live'"
                            >
                                Live
                            </button>
                        </div>
                        <small
                            >Sandbox records realistic delivery states without
                            contacting a provider.</small
                        >
                    </div>
                    <label class="field"
                        ><span>Account or sender ID</span
                        ><input :value="selected.detail" /><small
                            >Shown only to authorized owners.</small
                        ></label
                    >
                    <label class="field"
                        ><span>API credential</span
                        ><input
                            type="password"
                            value="stored-secret-value"
                        /><small
                            >Leave unchanged to keep the saved encrypted
                            value.</small
                        ></label
                    >
                    <label
                        v-if="selected.category === 'communication'"
                        class="field"
                        ><span>Daily sending limit</span
                        ><input value="250" inputmode="numeric" /><small
                            >New sends stop when the merchant limit is
                            reached.</small
                        ></label
                    >
                    <div
                        v-if="selected.name === 'WhatsApp'"
                        class="integration-note"
                    >
                        <strong>Template approval</strong>
                        <p>
                            Create and approve the receipt template in WhatsApp
                            Manager, then save its exact name and language here.
                        </p>
                    </div>
                    <div
                        v-if="selected.category === 'payment'"
                        class="integration-note"
                    >
                        <strong>Payment boundary</strong>
                        <p>
                            Manual destinations show instructions and require
                            staff verification. Saving details does not create
                            an automatic gateway.
                        </p>
                    </div>
                    <p v-if="testResult" class="integration-result">
                        {{ testResult }}
                    </p>
                </div>
                <footer>
                    <AppButton
                        variant="quiet"
                        :busy="testing"
                        @click="testConnection"
                        >Check configuration</AppButton
                    ><AppButton variant="primary" @click="save"
                        >Save and activate</AppButton
                    >
                </footer>
            </section>
        </div>
    </section>
</template>

<style scoped>
.integration-hub {
    display: grid;
    gap: 1rem;
}
.integration-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 16rem;
    gap: 1.5rem;
    padding: clamp(1.2rem, 3vw, 2.2rem);
    border: 1px solid var(--border);
    border-radius: 1.2rem;
    background: var(--surface-raised);
    box-shadow: var(--shadow);
}
.integration-hero h2 {
    max-width: 18ch;
    margin: 0.25rem 0 0.65rem;
    font-size: clamp(1.8rem, 4vw, 3.2rem);
    line-height: 0.98;
    letter-spacing: -0.05em;
}
.integration-hero__copy > p:last-child {
    max-width: 66ch;
    color: var(--text-muted);
}
.integration-score {
    display: grid;
    align-content: center;
    gap: 0.35rem;
    padding: 1.2rem;
    border-left: 5px solid var(--accent);
    background: var(--canvas);
}
.integration-score strong {
    font-size: 2.4rem;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.integration-score span,
.integration-score i {
    color: var(--text-muted);
    font-size: 0.78rem;
    font-style: normal;
}
.integration-score i {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.6rem;
}
.integration-score i b {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 0 4px var(--success-surface);
}
.integration-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}
.integration-toolbar nav {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.integration-toolbar button,
.integration-configure,
.integration-mode button {
    min-height: 2.3rem;
    padding: 0.45rem 0.75rem;
    border: 1px solid transparent;
    border-radius: 0.6rem;
    color: var(--text-muted);
    background: transparent;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}
.integration-toolbar button.selected,
.integration-mode button.selected {
    color: var(--accent-contrast);
    background: var(--accent);
}
.integration-toolbar label {
    display: grid;
    gap: 0.25rem;
    min-width: 14rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}
.integration-toolbar input,
.integration-drawer input {
    min-height: 2.55rem;
    padding: 0.55rem 0.7rem;
    border: 1px solid var(--border-strong);
    border-radius: 0.65rem;
    color: var(--text);
    background: var(--input);
    font: inherit;
}
.integration-layout {
    display: grid;
    grid-template-columns: 17rem minmax(0, 1fr);
    gap: 1rem;
    align-items: start;
}
.integration-readiness {
    position: sticky;
    top: 1rem;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: var(--surface);
}
.integration-readiness h3 {
    margin: 0.1rem 0 1rem;
}
.integration-readiness ol {
    display: grid;
    gap: 0.85rem;
    margin: 0;
    padding: 0;
    list-style: none;
}
.integration-readiness li {
    display: grid;
    grid-template-columns: 1.8rem 1fr;
    gap: 0.65rem;
    align-items: start;
}
.integration-readiness li > span {
    display: grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    border: 1px solid var(--border-strong);
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 800;
}
.integration-readiness li.done > span {
    color: var(--accent-contrast);
    border-color: var(--success);
    background: var(--success);
}
.integration-readiness li div {
    display: grid;
    gap: 0.15rem;
}
.integration-readiness small,
.integration-policy p {
    color: var(--text-muted);
    line-height: 1.45;
}
.integration-policy {
    margin-top: 1.1rem;
    padding: 0.85rem;
    border-left: 3px solid var(--info);
    background: var(--info-surface);
}
.integration-policy p {
    margin: 0.3rem 0 0;
    font-size: 0.78rem;
}
.integration-list {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: var(--surface);
}
.integration-list__heading {
    display: flex;
    justify-content: space-between;
    padding: 0.8rem 1rem;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    font-size: 0.78rem;
}
.integration-row {
    display: grid;
    grid-template-columns: 2.5rem 8.5rem minmax(12rem, 1fr) 9rem auto auto;
    gap: 0.85rem;
    align-items: center;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border);
}
.integration-row:last-child {
    border-bottom: 0;
}
.integration-row:hover {
    background: var(--canvas);
}
.integration-mark {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.7rem;
    color: #fff;
    background: #315a77;
    font-size: 1rem;
    font-weight: 900;
}
.integration-mark[data-kind="communication"] {
    background: #9a3d58;
}
.integration-mark[data-kind="payment"] {
    color: var(--accent-contrast);
    background: var(--accent);
}
.integration-name,
.integration-detail {
    display: grid;
    gap: 0.15rem;
}
.integration-name span,
.integration-detail small,
.integration-row > p {
    color: var(--text-muted);
    font-size: 0.78rem;
}
.integration-row > p {
    margin: 0;
}
.integration-configure {
    color: var(--text);
    border-color: var(--border-strong);
    background: var(--input);
}
.integration-empty {
    display: grid;
    gap: 0.25rem;
    padding: 3rem 1rem;
    text-align: center;
}
.integration-empty span {
    color: var(--text-muted);
}
.integration-scrim {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    justify-content: flex-end;
    background: rgb(0 0 0 / 0.48);
}
.integration-drawer {
    display: grid;
    grid-template-rows: auto 1fr auto;
    width: min(31rem, 100%);
    height: 100%;
    color: var(--text);
    background: var(--surface-raised);
    box-shadow: -1rem 0 3rem rgb(0 0 0 / 0.2);
}
.integration-drawer header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.8rem;
    align-items: center;
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--border);
}
.integration-drawer header p,
.integration-drawer header h3 {
    margin: 0;
}
.integration-drawer header p {
    color: var(--text-muted);
    font-size: 0.75rem;
}
.integration-drawer header button {
    border: 0;
    color: var(--text);
    background: transparent;
    font-size: 1.8rem;
    cursor: pointer;
}
.integration-drawer__body {
    overflow: auto;
    display: grid;
    align-content: start;
    gap: 1rem;
    padding: 1.2rem;
}
.integration-drawer footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.65rem;
    padding: 1rem 1.2rem;
    border-top: 1px solid var(--border);
}
.integration-mode {
    display: grid;
    gap: 0.5rem;
}
.integration-mode > div {
    display: grid;
    grid-template-columns: 1fr 1fr;
    padding: 0.25rem;
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    background: var(--canvas);
}
.integration-mode small {
    color: var(--text-muted);
}
.integration-note,
.integration-result {
    padding: 0.85rem;
    border-left: 3px solid var(--warning);
    background: var(--warning-surface);
}
.integration-note p,
.integration-result {
    margin: 0.3rem 0 0;
    color: var(--text-muted);
    font-size: 0.82rem;
    line-height: 1.5;
}
.integration-result {
    color: var(--text);
    border-color: var(--success);
    background: var(--success-surface);
}
@media (max-width: 1050px) {
    .integration-layout {
        grid-template-columns: 1fr;
    }
    .integration-readiness {
        position: static;
    }
    .integration-row {
        grid-template-columns: 2.5rem 8rem 1fr auto auto;
    }
    .integration-detail {
        display: none;
    }
}
@media (max-width: 720px) {
    .integration-hero {
        grid-template-columns: 1fr;
    }
    .integration-toolbar {
        align-items: stretch;
        flex-direction: column;
    }
    .integration-toolbar label {
        min-width: 0;
    }
    .integration-row {
        grid-template-columns: 2.5rem 1fr auto;
    }
    .integration-row > p,
    .integration-row > .status-badge {
        grid-column: 2 / -1;
    }
    .integration-configure {
        grid-column: 2 / -1;
    }
    .integration-name {
        align-self: center;
    }
    .integration-drawer footer {
        flex-direction: column;
    }
}
@media (prefers-reduced-motion: no-preference) {
    .integration-drawer {
        animation: drawer-in 0.18s ease-out;
    }
    @keyframes drawer-in {
        from {
            transform: translateX(2rem);
            opacity: 0.7;
        }
    }
}
</style>
