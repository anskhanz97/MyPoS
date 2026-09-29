<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type Status = "trial" | "active" | "grace" | "restricted" | "cancelled";
const status = ref<Status>("grace");
const reference = ref("BANK-0091");
const paymentRecorded = ref(false);
const reason = ref("Renewal overdue after manual review.");
const notice = ref("Grace period ends 06 Oct 2026.");
const canSell = computed(() =>
    ["trial", "active", "grace"].includes(status.value),
);

function recordPayment(): void {
    if (!reference.value.trim()) {
        notice.value = "Enter the external payment reference.";
        return;
    }
    paymentRecorded.value = true;
    status.value = "active";
    notice.value =
        "Payment recorded once. Service renewed through 29 Oct 2026.";
}

function restrict(): void {
    if (reason.value.trim().length < 3) {
        notice.value =
            "Enter an audit reason before restricting new operations.";
        return;
    }
    status.value = "restricted";
    notice.value =
        "New selling restricted. Export, support and pending offline submission remain available.";
}
</script>

<template>
    <div class="platform-preview">
        <header class="page-heading">
            <div>
                <p class="kicker">Platform administration · working preview</p>
                <h1>Renew service without mixing billing and sales.</h1>
                <p>
                    Platform records stay separate from merchant tenders, while
                    owners receive a clear read-only renewal status.
                </p>
            </div>
            <StatusBadge
                :tone="
                    status === 'active'
                        ? 'success'
                        : status === 'restricted'
                          ? 'danger'
                          : 'warning'
                "
                >{{ status }}</StatusBadge
            >
        </header>

        <section
            class="gallery-section"
            aria-labelledby="platform-merchant-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="platform-merchant-title">Merchant subscription</h2>
                    <p>Karachi Corner · independent stores · Complete plan</p>
                </div>
                <StatusBadge tone="info">Platform operator</StatusBadge>
            </div>
            <div class="subscription-grid">
                <article>
                    <small>Renewal</small><strong>29 Sep 2026</strong
                    ><span>PKR 5,000.00 / 30 days</span>
                </article>
                <article>
                    <small>New operations</small
                    ><strong>{{ canSell ? "Allowed" : "Restricted" }}</strong
                    ><span>Server-authoritative entitlement</span>
                </article>
                <article>
                    <small>Recovery access</small
                    ><strong>Always available</strong
                    ><span>Pending offline · export · support</span>
                </article>
            </div>
            <p class="context-announcement" aria-live="polite">{{ notice }}</p>
        </section>

        <section class="gallery-section" aria-labelledby="manual-payment-title">
            <div class="section-heading">
                <div>
                    <h2 id="manual-payment-title">Record manual payment</h2>
                    <p>
                        This records evidence only; it does not acquire or
                        verify money.
                    </p>
                </div>
                <StatusBadge :tone="paymentRecorded ? 'success' : 'info'">{{
                    paymentRecorded ? "Recorded" : "Awaiting record"
                }}</StatusBadge>
            </div>
            <div class="access-form">
                <label class="field"
                    ><span>Reference</span><input v-model="reference"
                /></label>
                <label class="field"
                    ><span>Amount</span
                    ><span class="input-affix"
                        ><b>PKR</b
                        ><input value="5000.00" inputmode="decimal" /></span
                ></label>
                <AppButton
                    variant="primary"
                    :disabled="paymentRecorded"
                    @click="recordPayment"
                    >{{
                        paymentRecorded
                            ? "Payment already recorded"
                            : "Record and renew"
                    }}</AppButton
                >
            </div>
        </section>

        <section class="gallery-section" aria-labelledby="restriction-title">
            <div class="section-heading">
                <div>
                    <h2 id="restriction-title">Controlled restriction</h2>
                    <p>
                        Reasoned lifecycle changes preserve recovery routes and
                        merchant records.
                    </p>
                </div>
            </div>
            <div class="access-form">
                <label class="field"
                    ><span>Audit reason</span><input v-model="reason"
                /></label>
                <AppButton variant="danger" @click="restrict"
                    >Restrict new operations</AppButton
                >
            </div>
            <nav
                class="permission-nav"
                aria-label="Capabilities retained during restriction"
            >
                <span>Submit pending offline sales</span
                ><span>Export merchant data</span><span>Contact support</span>
            </nav>
        </section>

        <section class="gallery-section" aria-labelledby="owner-view-title">
            <div class="section-heading">
                <div>
                    <h2 id="owner-view-title">Owner renewal view</h2>
                    <p>
                        Read-only status, renewal date and action guidance
                        without platform controls.
                    </p>
                </div>
                <StatusBadge tone="warning">7 days grace</StatusBadge>
            </div>
            <div class="review-panel">
                <dl>
                    <div>
                        <dt>Plan</dt>
                        <dd>Complete</dd>
                    </div>
                    <div>
                        <dt>Current period</dt>
                        <dd>Through 29 Sep 2026</dd>
                    </div>
                    <div>
                        <dt>Renewal action</dt>
                        <dd>Contact MyPoS administration</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section
            class="gallery-section"
            aria-labelledby="platform-states-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="platform-states-title">Platform states</h2>
                    <p>
                        Operational responses do not reveal another merchant or
                        any production secret.
                    </p>
                </div>
            </div>
            <div class="state-grid">
                <StatePanel
                    state="loading"
                    title="Loading subscriptions"
                    message="Checking the platform ledger."
                /><StatePanel
                    state="empty"
                    title="No plan assigned"
                    message="Assign an active plan before activation."
                /><StatePanel
                    state="error"
                    title="Duplicate reference"
                    message="Review the existing payment instead of recording it again."
                />
            </div>
        </section>
    </div>
</template>
