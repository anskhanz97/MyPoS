<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

const mode = ref<"manual" | "advanced">("advanced");
const version = ref(7);
const coupon = ref("SAVE5");
const published = ref(false);
const rules = computed(() =>
    mode.value === "advanced"
        ? [
              ["Scheduled price", "Tea tin · PKR 900.00", "01–07 Oct"],
              ["Quantity", "3+ oat biscuits · 10%", "Priority 20"],
              ["Coupon", "SAVE5 · PKR 5.00", "1 per customer"],
              ["Buy X get Y", "Buy 2 tea, get snack", "Priority 40"],
          ]
        : [],
);
</script>

<template>
    <div class="pricing-preview">
        <header class="page-heading pricing-heading">
            <div>
                <p class="kicker">Pricing · published snapshots</p>
                <h1>Every discount explains itself.</h1>
                <p>
                    Store overrides, effective times and exact allocations stay
                    visible before a manager publishes.
                </p>
            </div>
            <StatusBadge tone="info">m7-s2 · PKR</StatusBadge>
        </header>

        <section class="gallery-section" aria-labelledby="pricing-mode-title">
            <div class="section-heading">
                <div>
                    <h2 id="pricing-mode-title">Promotion mode</h2>
                    <p>Merchant default · Lahore Test Store override</p>
                </div>
                <StatusBadge
                    :tone="mode === 'advanced' ? 'success' : 'warning'"
                >
                    {{ mode === "advanced" ? "Advanced" : "Manual only" }}
                </StatusBadge>
            </div>
            <div class="pricing-mode-grid">
                <button
                    type="button"
                    :aria-pressed="mode === 'manual'"
                    @click="mode = 'manual'"
                >
                    <strong>Manual discounts</strong>
                    <span
                        >Standard prices plus authorized line/cart
                        discount.</span
                    >
                </button>
                <button
                    type="button"
                    :aria-pressed="mode === 'advanced'"
                    @click="mode = 'advanced'"
                >
                    <strong>Counter promotions</strong>
                    <span
                        >Schedules, coupons, quantity, mix-and-match and
                        buy-X-get-Y.</span
                    >
                </button>
            </div>
            <p class="context-announcement" role="status">
                Existing sales stay unchanged. Open carts must reprice when the
                effective version changes.
            </p>
        </section>

        <section class="gallery-section" aria-labelledby="rule-editor-title">
            <div class="section-heading">
                <div>
                    <h2 id="rule-editor-title">
                        Version {{ version + 1 }} draft
                    </h2>
                    <p>Effective 01 Oct 2026, 09:00 Asia/Karachi</p>
                </div>
                <AppButton variant="primary" @click="published = true">
                    {{ published ? "Published" : "Publish version" }}
                </AppButton>
            </div>
            <div v-if="rules.length" class="pricing-rule-list">
                <article v-for="rule in rules" :key="rule[0]">
                    <StatusBadge>{{ rule[0] }}</StatusBadge>
                    <strong>{{ rule[1] }}</strong>
                    <small>{{ rule[2] }}</small>
                </article>
            </div>
            <StatePanel
                v-else
                state="empty"
                title="Automatic rules retained, not applied"
                message="Switching to manual mode publishes a new version; earlier definitions remain in history."
            />
        </section>

        <section class="gallery-section" aria-labelledby="quote-title">
            <div class="section-heading">
                <div>
                    <h2 id="quote-title">Quote explanation</h2>
                    <p>Coupon evaluation does not consume a usage.</p>
                </div>
                <label class="field pricing-coupon"
                    ><span>Coupon</span><input v-model="coupon"
                /></label>
            </div>
            <div class="quote-explanation">
                <div><span>2 × Green tea</span><b>PKR 2,000.00</b></div>
                <div><span>Quantity promotion · 10%</span><b>−200.00</b></div>
                <div>
                    <span>Coupon {{ coupon || "—" }}</span
                    ><b>−5.00</b>
                </div>
                <div><span>Tax after discounts · 18%</span><b>323.10</b></div>
                <div class="quote-total">
                    <span>Quoted total</span><b>PKR 2,118.10</b>
                </div>
            </div>
        </section>
    </div>
</template>
