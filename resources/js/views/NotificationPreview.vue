<script setup lang="ts">
import { ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
const read = ref(false);
</script>
<template>
    <section class="notification-preview">
        <header>
            <div>
                <p class="kicker">Notifications</p>
                <h2>Operational signals without the noise.</h2>
                <p>3 unread · Main store · delivery transport configured</p>
            </div>
            <StatusBadge tone="success">Email enabled</StatusBadge>
        </header>
        <div class="notification-grid">
            <article class="alerts">
                <div class="title">
                    <div>
                        <p class="kicker">In-app alerts</p>
                        <h3>Needs attention</h3>
                    </div>
                    <AppButton variant="quiet">Preferences</AppButton>
                </div>
                <button :class="['alert', { read }]" @click="read = true">
                    <span class="symbol">!</span
                    ><span
                        ><strong>Batch expires in 12 days</strong
                        ><small>Tea · B-2026-14 · Main store</small></span
                    ><StatusBadge tone="warning">Inventory</StatusBadge></button
                ><button class="alert">
                    <span class="symbol">↻</span
                    ><span
                        ><strong>Offline sale needs review</strong
                        ><small
                            >Conflict retained · no data was discarded</small
                        ></span
                    ><StatusBadge tone="danger">Sync</StatusBadge></button
                ><button class="alert">
                    <span class="symbol">×</span
                    ><span
                        ><strong>Receipt email failed</strong
                        ><small
                            >Configuration error · safe to retry</small
                        ></span
                    ><StatusBadge tone="danger">Email</StatusBadge>
                </button>
            </article>
            <article class="delivery">
                <p class="kicker">Receipt delivery</p>
                <h3>INV-02481</h3>
                <div class="recipient">
                    <span>To</span><strong>a•••••@example.com</strong>
                </div>
                <ol>
                    <li class="done">
                        <b>1</b
                        ><span
                            ><strong>Queued</strong
                            ><small>Sale remained complete</small></span
                        >
                    </li>
                    <li class="done">
                        <b>2</b
                        ><span
                            ><strong>Provider accepted</strong
                            ><small
                                >Attempt 2 · PDF snapshot attached</small
                            ></span
                        >
                    </li>
                    <li>
                        <b>3</b
                        ><span
                            ><strong>Read status</strong
                            ><small>Not claimed or tracked</small></span
                        >
                    </li>
                </ol>
                <AppButton>Resend immutable receipt</AppButton>
            </article>
        </div>
        <div class="privacy">
            <strong>Private by scope</strong
            ><span
                >Payroll alerts reveal no pay amounts. Transactional receipts
                remain separate from marketing consent. SMS and WhatsApp are not
                active.</span
            >
        </div>
    </section>
</template>
<style scoped>
.notification-preview {
    display: grid;
    gap: 1rem;
}
.notification-preview > header,
article,
.privacy {
    padding: 1.25rem;
    border: 1px solid var(--border);
    border-radius: 0.9rem;
    background: var(--surface);
}
.notification-preview > header,
.title,
.privacy {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
h2 {
    margin: 0.2rem 0;
    font-size: clamp(1.8rem, 4vw, 3rem);
}
.notification-grid {
    display: grid;
    grid-template-columns: 1.25fr 0.75fr;
    gap: 1rem;
}
.title h3,
.delivery h3 {
    margin: 0.2rem 0;
}
.alert {
    width: 100%;
    display: grid;
    grid-template-columns: 34px 1fr auto;
    gap: 0.75rem;
    align-items: center;
    text-align: left;
    padding: 1rem 0;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: transparent;
    color: var(--text);
}
.alert small,
.privacy span,
.recipient span,
li small {
    display: block;
    color: var(--text-muted);
}
.alert.read {
    opacity: 0.58;
}
.symbol {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--warning-surface);
    font-weight: 800;
}
.recipient {
    padding: 0.8rem;
    background: var(--surface-muted);
    border-radius: 0.5rem;
}
.delivery ol {
    list-style: none;
    padding: 0;
}
.delivery li {
    display: flex;
    gap: 0.75rem;
    padding: 0.7rem 0;
}
.delivery li b {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--surface-muted);
}
.delivery li.done b {
    background: var(--success-surface);
    color: var(--success);
}
@media (max-width: 760px) {
    .notification-preview > header,
    .title,
    .privacy {
        align-items: start;
        flex-direction: column;
    }
    .notification-grid {
        grid-template-columns: 1fr;
    }
    .alert {
        grid-template-columns: 34px 1fr;
    }
    .alert > :last-child {
        grid-column: 2;
    }
}
</style>
