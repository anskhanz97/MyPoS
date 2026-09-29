<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type ViewState = "ready" | "loading" | "empty" | "error";
const state = ref<ViewState>("ready");
const points = ref(128.4);
const reserved = ref(false);
const returned = ref(false);
const message = ref("Member found across this merchant's stores.");
const available = computed(() => points.value - (reserved.value ? 100 : 0));

function findMember(): void {
    state.value = "loading";
    window.setTimeout(() => {
        state.value = "ready";
        message.value = "Ayesha's merchant-wide balance is ready.";
    }, 220);
}
function reserve(): void {
    reserved.value = !reserved.value;
    message.value = reserved.value
        ? "100.0000 points reserved for this online cart."
        : "Reward reservation released; the balance is available again.";
}
function previewReturn(): void {
    if (returned.value) return;
    returned.value = true;
    points.value = -12.35;
    reserved.value = false;
    message.value =
        "Return reversed points already spent. Later earnings repay the negative balance.";
}
</script>

<template>
    <div class="loyalty-preview">
        <header class="page-heading loyalty-heading">
            <div>
                <p class="kicker">Loyalty · merchant-wide programme</p>
                <h1>Points follow the member, not the branch.</h1>
                <p>
                    Cashiers can find and redeem online. Managers publish
                    earning rules, rewards and review the append-only ledger.
                </p>
            </div>
            <StatusBadge :tone="points < 0 ? 'warning' : 'success'">
                {{ points < 0 ? "Repayment in progress" : "Programme active" }}
            </StatusBadge>
        </header>

        <section class="member-search" aria-label="Loyalty member lookup">
            <label
                ><span>Pakistan mobile</span
                ><input value="0300 1234567" autocomplete="tel"
            /></label>
            <AppButton
                :busy="state === 'loading'"
                variant="primary"
                @click="findMember"
                >Find member</AppButton
            >
            <button class="state-link" type="button" @click="state = 'empty'">
                Empty state
            </button>
            <button class="state-link" type="button" @click="state = 'error'">
                Error state
            </button>
        </section>

        <StatePanel
            v-if="state === 'loading'"
            state="loading"
            title="Finding member"
            message="Searching only this merchant's loyalty programme…"
        />
        <StatePanel
            v-else-if="state === 'empty'"
            state="empty"
            title="No loyalty member"
            message="Managers can create a member, then explicitly link the local customer profile."
        />
        <StatePanel
            v-else-if="state === 'error'"
            state="error"
            title="Lookup unavailable"
            message="Redemption stays blocked. The current sale can continue without a reward."
        />

        <div v-else class="loyalty-grid">
            <main>
                <section class="member-card">
                    <div class="member-identity">
                        <span class="avatar" aria-hidden="true">AK</span>
                        <div>
                            <p class="kicker">Member since 2025</p>
                            <h2>Ayesha Khan</h2>
                            <p>+92 300 1234567 · 2 linked store profiles</p>
                        </div>
                    </div>
                    <dl class="point-stats">
                        <div>
                            <dt>Ledger balance</dt>
                            <dd>{{ points.toFixed(4) }}</dd>
                        </div>
                        <div>
                            <dt>Reserved</dt>
                            <dd>{{ reserved ? "100.0000" : "0.0000" }}</dd>
                        </div>
                        <div>
                            <dt>Available</dt>
                            <dd>{{ available.toFixed(4) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="ledger-card" aria-labelledby="ledger-title">
                    <div class="section-heading">
                        <div>
                            <p class="kicker">Rebuildable projection</p>
                            <h2 id="ledger-title">Points ledger</h2>
                        </div>
                        <StatusBadge>4 decimals</StatusBadge>
                    </div>
                    <div class="ledger-row">
                        <div>
                            <strong>Earn · SALE-WA83K</strong
                            ><span>Gulberg · eligible net PKR 100.00</span>
                        </div>
                        <b class="positive">+10.0000</b>
                    </div>
                    <div class="ledger-row">
                        <div>
                            <strong>Redeem · SALE-QP19M</strong
                            ><span>DHA · Rs 90 reward</span>
                        </div>
                        <b>−10.0000</b>
                    </div>
                    <div v-if="returned" class="ledger-row ledger-row--warning">
                        <div>
                            <strong>Return reversal · RET-3J8N</strong
                            ><span
                                >Original earned allocation reversed exactly
                                once</span
                            >
                        </div>
                        <b>−12.3500</b>
                    </div>
                    <div class="ledger-row">
                        <div>
                            <strong>Earn · offline accepted</strong
                            ><span>Posted only after server acceptance</span>
                        </div>
                        <b class="positive">+1.0000</b>
                    </div>
                </section>
            </main>

            <aside class="reward-panel">
                <p class="kicker">Online checkout</p>
                <h2>Rs 90 reward</h2>
                <p>
                    Costs <strong>100.0000 points</strong>. The reservation
                    lasts five minutes and is committed with the sale.
                </p>
                <AppButton
                    :variant="reserved ? 'quiet' : 'primary'"
                    :disabled="points < 100"
                    @click="reserve"
                    >{{
                        reserved ? "Release reward" : "Reserve reward"
                    }}</AppButton
                >
                <div class="rule-note">
                    <strong>Earn rule</strong
                    ><span>0.10000000 points per eligible PKR</span
                    ><small>Expiry: 365 days · floor to 4 decimals</small>
                </div>
                <AppButton variant="quiet" @click="previewReturn"
                    >Preview spent-points return</AppButton
                >
            </aside>
        </div>
        <p class="sr-only" aria-live="polite">{{ message }}</p>
    </div>
</template>

<style scoped>
.loyalty-preview {
    display: grid;
    gap: 1rem;
}
.loyalty-heading {
    padding: 1.25rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: linear-gradient(
        135deg,
        var(--surface),
        color-mix(in srgb, var(--accent) 8%, var(--surface))
    );
}
.member-search {
    display: flex;
    align-items: end;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 0.85rem;
    background: var(--surface);
}
.member-search label {
    display: grid;
    gap: 0.35rem;
    flex: 1;
    max-width: 26rem;
}
.member-search input {
    min-height: 2.75rem;
    border: 1px solid var(--border);
    border-radius: 0.55rem;
    padding: 0 0.75rem;
    background: var(--canvas);
    color: var(--text);
}
.state-link {
    border: 0;
    background: none;
    color: var(--accent);
    text-decoration: underline;
    cursor: pointer;
    padding: 0.7rem;
}
.loyalty-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(17rem, 0.7fr);
    gap: 1rem;
}
.loyalty-grid main {
    display: grid;
    gap: 1rem;
}
.member-card,
.ledger-card,
.reward-panel {
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: var(--surface);
    padding: 1.15rem;
}
.member-identity {
    display: flex;
    gap: 0.85rem;
    align-items: center;
}
.member-identity h2,
.reward-panel h2 {
    margin: 0.15rem 0;
}
.avatar {
    display: grid;
    place-items: center;
    width: 3rem;
    height: 3rem;
    border-radius: 0.8rem;
    background: var(--accent);
    color: var(--accent-contrast);
    font-weight: 800;
}
.point-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
    margin: 1rem 0 0;
}
.point-stats div,
.rule-note {
    padding: 0.8rem;
    border-radius: 0.7rem;
    background: var(--canvas);
}
.point-stats dt {
    font-size: 0.76rem;
    color: var(--muted);
}
.point-stats dd {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0.2rem 0 0;
}
.ledger-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 0;
    border-top: 1px solid var(--border);
}
.ledger-row div {
    display: grid;
    gap: 0.15rem;
}
.ledger-row span,
.reward-panel p,
.rule-note span,
.rule-note small {
    color: var(--muted);
}
.ledger-row--warning {
    color: var(--warning-text);
}
.positive {
    color: var(--success-text);
}
.reward-panel {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    align-self: start;
}
.rule-note {
    display: grid;
    gap: 0.3rem;
    margin-top: 0.35rem;
}
@media (max-width: 760px) {
    .member-search {
        align-items: stretch;
        flex-direction: column;
    }
    .member-search label {
        max-width: none;
    }
    .loyalty-grid {
        grid-template-columns: 1fr;
    }
    .point-stats {
        grid-template-columns: 1fr;
    }
    .ledger-row {
        align-items: start;
    }
    .loyalty-heading {
        padding: 1rem;
    }
}
</style>
