<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";

const pairingCode = ref<string | null>(null);
const pairingUsed = ref(false);
const promotionMode = ref<"manual" | "advanced">("manual");
const settingsVersion = ref(4);
const notice = ref("Store override follows merchant defaults · m4-s2");
const writer = ref<"this-tab" | "other-tab">("this-tab");
const pairingLabel = computed(() =>
    pairingUsed.value
        ? "Paired"
        : pairingCode.value
          ? "Expires in 09:42"
          : "Not issued",
);

function issuePairingCode(): void {
    pairingCode.value = "7KQ4N2PX";
    pairingUsed.value = false;
    notice.value = "Pairing code created for this register. It is shown once.";
}

function consumePairingCode(): void {
    pairingUsed.value = true;
    notice.value =
        "Counter browser paired. The one-time code can no longer be used.";
}

function publishSettings(): void {
    settingsVersion.value += 1;
    notice.value = `Store settings v${settingsVersion.value} published with an effective time and audit record.`;
}
</script>

<template>
    <div class="register-preview">
        <header class="page-heading register-heading">
            <div>
                <p class="kicker">Registers and devices · working preview</p>
                <h1>Know which counter can sell.</h1>
                <p>
                    Provision one browser safely, show its hardware truthfully,
                    and keep store settings versioned.
                </p>
            </div>
            <StatusBadge tone="success">Register active</StatusBadge>
        </header>
        <section
            class="gallery-section"
            aria-labelledby="register-identity-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="register-identity-title">Front counter · LAB-01</h2>
                    <p>
                        Lahore Test Store · browser enrollment required before
                        checkout
                    </p>
                </div>
                <StatusBadge tone="info">Fence 12</StatusBadge>
            </div>
            <div class="register-summary">
                <article>
                    <small>Scanner</small><strong>Keyboard wedge ready</strong
                    ><span>Configuration only</span>
                </article>
                <article>
                    <small>Receipt printer</small><strong>Unconfigured</strong
                    ><span>No connection is claimed</span>
                </article>
                <article>
                    <small>Cash drawer</small><strong>Unconfigured</strong
                    ><span>Requires printer helper</span>
                </article>
                <article>
                    <small>Customer display</small><strong>Off</strong
                    ><span>Optional second screen</span>
                </article>
            </div>
        </section>
        <section class="gallery-section" aria-labelledby="pairing-title">
            <div class="section-heading">
                <div>
                    <h2 id="pairing-title">Secure device pairing</h2>
                    <p>
                        Initial setup needs internet. The short code and device
                        key are each shown once.
                    </p>
                </div>
                <StatusBadge
                    :tone="
                        pairingUsed
                            ? 'success'
                            : pairingCode
                              ? 'warning'
                              : 'info'
                    "
                    >{{ pairingLabel }}</StatusBadge
                >
            </div>
            <div class="pairing-panel">
                <div class="pairing-code" aria-live="polite">
                    <small>One-time pairing code</small
                    ><strong>{{ pairingCode ?? "••••••••" }}</strong
                    ><span>{{
                        pairingCode
                            ? "Valid for this register only"
                            : "Issue only when the counter browser is ready"
                    }}</span>
                </div>
                <div class="pairing-actions">
                    <AppButton variant="primary" @click="issuePairingCode"
                        >Issue pairing code</AppButton
                    ><AppButton
                        :disabled="!pairingCode || pairingUsed"
                        @click="consumePairingCode"
                        >Complete simulated pairing</AppButton
                    >
                </div>
            </div>
        </section>
        <section class="gallery-section" aria-labelledby="coordination-title">
            <div class="section-heading">
                <div>
                    <h2 id="coordination-title">Active tab ownership</h2>
                    <p>
                        Only the elected writer may submit counter commands for
                        this device and fence.
                    </p>
                </div>
                <StatusBadge
                    :tone="writer === 'this-tab' ? 'success' : 'warning'"
                    >{{
                        writer === "this-tab"
                            ? "This tab is writer"
                            : "Read-only tab"
                    }}</StatusBadge
                >
            </div>
            <div class="writer-grid">
                <button
                    type="button"
                    :aria-pressed="writer === 'this-tab'"
                    @click="writer = 'this-tab'"
                >
                    <strong>Tab 9F2A</strong
                    ><span>Opened 09:14 · current browser</span>
                </button>
                <button
                    type="button"
                    :aria-pressed="writer === 'other-tab'"
                    @click="writer = 'other-tab'"
                >
                    <strong>Tab 6C11</strong
                    ><span>Opened 09:07 · same device</span>
                </button>
            </div>
            <p class="context-announcement" aria-live="polite">
                {{
                    writer === "this-tab"
                        ? "Writer lock held · device v3 · config m4-s2"
                        : "Checkout controls disabled here until ownership is taken over."
                }}
            </p>
        </section>
        <section class="gallery-section" aria-labelledby="settings-title">
            <div class="section-heading">
                <div>
                    <h2 id="settings-title">Store settings override</h2>
                    <p>
                        Merchant defaults remain underneath; promotion mode
                        never varies by register.
                    </p>
                </div>
                <StatusBadge>Store v{{ settingsVersion }}</StatusBadge>
            </div>
            <fieldset class="mode-grid settings-mode">
                <legend class="sr-only">Promotion mode</legend>
                <label
                    class="mode-card"
                    :class="{
                        'mode-card--selected': promotionMode === 'manual',
                    }"
                    ><input
                        v-model="promotionMode"
                        type="radio"
                        value="manual"
                        name="promotion-mode"
                    /><span
                        ><strong>Manual discounts</strong
                        ><small
                            >Staff applies authorized line or sale
                            discounts.</small
                        ></span
                    ></label
                >
                <label
                    class="mode-card"
                    :class="{
                        'mode-card--selected': promotionMode === 'advanced',
                    }"
                    ><input
                        v-model="promotionMode"
                        type="radio"
                        value="advanced"
                        name="promotion-mode"
                    /><span
                        ><strong>Advanced counter promotions</strong
                        ><small
                            >Scheduled prices, coupons, quantity rules and buy X
                            get Y.</small
                        ></span
                    ></label
                >
            </fieldset>
            <div class="settings-actions">
                <p class="context-announcement" aria-live="polite">
                    {{ notice }}
                </p>
                <AppButton variant="primary" @click="publishSettings"
                    >Publish next version</AppButton
                >
            </div>
        </section>
    </div>
</template>
