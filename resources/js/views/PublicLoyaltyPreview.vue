<script setup lang="ts">
import { nextTick, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type State = "form" | "loading" | "member" | "error" | "inactive";
const state = ref<State>("form");
const phone = ref("0300 1234567");
const name = ref("");
const marketing = ref(false);
const returning = ref(false);
const refreshes = ref(0);
const phoneInput = ref<HTMLInputElement | null>(null);

async function submit(): Promise<void> {
    state.value = "loading";
    await new Promise((resolve) => window.setTimeout(resolve, 220));
    if (phone.value.replace(/\D/g, "").length < 11) {
        state.value = "error";
        await nextTick(() => phoneInput.value?.focus());
        return;
    }
    state.value = "member";
}
async function refresh(): Promise<void> {
    state.value = "loading";
    await new Promise((resolve) => window.setTimeout(resolve, 160));
    refreshes.value += 1;
    returning.value = true;
    state.value = "member";
}
</script>

<template>
    <section class="public-loyalty" aria-labelledby="public-program-title">
        <div class="public-aura public-aura--one" aria-hidden="true"></div>
        <div class="public-aura public-aura--two" aria-hidden="true"></div>
        <div class="programme-mark" aria-hidden="true">EP</div>
        <p class="kicker">Sample Merchant · loyalty programme</p>
        <h2 id="public-program-title">Everyday Points</h2>
        <p class="programme-copy">
            Join once or check an existing balance with a Pakistan mobile
            number.
        </p>

        <StatePanel
            v-if="state === 'loading'"
            state="loading"
            title="Checking your membership"
            message="Only this programme is being searched…"
        />
        <StatePanel
            v-else-if="state === 'inactive'"
            state="error"
            title="Programme unavailable"
            message="This loyalty programme is not accepting joins or lookups right now."
        />

        <form
            v-else-if="state === 'form' || state === 'error'"
            class="join-form"
            @submit.prevent="submit"
        >
            <label
                ><span>Pakistan mobile <b>*</b></span
                ><input
                    ref="phoneInput"
                    v-model="phone"
                    inputmode="tel"
                    autocomplete="tel"
                    :aria-invalid="state === 'error'"
            /></label>
            <p v-if="state === 'error'" class="form-error" role="alert">
                Enter a complete Pakistan mobile number.
            </p>
            <label
                ><span>Name <small>optional</small></span
                ><input
                    v-model="name"
                    autocomplete="name"
                    placeholder="How should we greet you?"
            /></label>
            <label class="consent"
                ><input v-model="marketing" type="checkbox" /><span
                    >Send me optional programme news. Joining does not require
                    this consent.</span
                ></label
            >
            <AppButton variant="primary" type="submit"
                >Join or check points</AppButton
            >
            <p class="privacy-note">
                Anyone who knows this number can see this limited balance. This
                form does not verify identity and cannot redeem points.
            </p>
        </form>

        <div v-else class="member-result">
            <div class="member-result__top">
                <div>
                    <p class="kicker">
                        {{ returning ? "Welcome back" : "Membership ready" }}
                    </p>
                    <h3>A••••• K•••</h3>
                    <p>+92 ••• ••• 4567</p>
                </div>
                <StatusBadge tone="success">Active</StatusBadge>
            </div>
            <div class="public-balance">
                <span>Available points</span><strong>128.4000</strong
                ><small
                    >View only · redemption happens with staff at
                    checkout</small
                >
            </div>
            <div class="public-reward">
                <div>
                    <p class="kicker">Available reward</p>
                    <strong>Rs 90 checkout reward</strong
                    ><span>100.0000 points</span>
                </div>
                <StatusBadge>Ask staff</StatusBadge>
            </div>
            <AppButton variant="quiet" @click="refresh"
                >Refresh balance</AppButton
            >
            <small class="refresh-proof"
                >Read-only lookups: {{ refreshes }} · no duplicate enrollment or
                bonus</small
            >
        </div>

        <button
            class="inactive-preview"
            type="button"
            @click="state = state === 'inactive' ? 'form' : 'inactive'"
        >
            Preview
            {{ state === "inactive" ? "join form" : "inactive programme" }}
        </button>
    </section>
</template>

<style scoped>
.public-loyalty {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    max-width: 34rem;
    margin: 0 auto;
    padding: 2rem;
    border: 1px solid var(--border);
    border-radius: 1.5rem;
    background: linear-gradient(
        160deg,
        var(--surface),
        color-mix(in srgb, var(--accent) 7%, var(--surface))
    );
    box-shadow: 0 1.4rem 4rem color-mix(in srgb, var(--text) 10%, transparent);
    text-align: center;
}
.public-aura {
    position: absolute;
    z-index: -1;
    border-radius: 50%;
    filter: blur(1px);
    opacity: 0.55;
}
.public-aura--one {
    width: 14rem;
    height: 14rem;
    right: -7rem;
    top: -7rem;
    background: color-mix(in srgb, var(--accent) 28%, transparent);
}
.public-aura--two {
    width: 10rem;
    height: 10rem;
    left: -6rem;
    bottom: 4rem;
    background: color-mix(in srgb, var(--info) 18%, transparent);
}
.programme-mark {
    display: grid;
    place-items: center;
    width: 4.25rem;
    height: 4.25rem;
    margin: 0 auto 0.9rem;
    border-radius: 1.25rem;
    background: var(--accent);
    color: var(--accent-contrast);
    font-size: 1.35rem;
    font-weight: 900;
    box-shadow: 0 0.6rem 1.4rem
        color-mix(in srgb, var(--accent) 24%, transparent);
}
h2 {
    font-size: clamp(2rem, 7vw, 3rem);
    margin: 0.15rem 0;
}
.programme-copy {
    color: var(--muted);
    margin: 0.45rem auto 1.3rem;
    max-width: 28rem;
}
.join-form,
.member-result {
    display: grid;
    gap: 0.9rem;
    text-align: left;
    padding: 1.15rem;
    border: 1px solid var(--border);
    border-radius: 1rem;
    background: color-mix(in srgb, var(--canvas) 88%, transparent);
}
.join-form label:not(.consent) {
    display: grid;
    gap: 0.35rem;
    font-weight: 700;
}
.join-form label span {
    display: flex;
    justify-content: space-between;
}
.join-form small {
    color: var(--muted);
    font-weight: 400;
}
.join-form input:not([type="checkbox"]) {
    min-height: 3rem;
    padding: 0 0.8rem;
    border: 1px solid var(--border);
    border-radius: 0.65rem;
    background: var(--surface);
    color: var(--text);
}
.consent {
    display: flex;
    gap: 0.65rem;
    align-items: start;
    font-size: 0.88rem;
    color: var(--muted);
}
.consent input {
    margin-top: 0.2rem;
}
.privacy-note,
.refresh-proof {
    color: var(--muted);
    font-size: 0.78rem;
    line-height: 1.45;
}
.form-error {
    color: var(--danger-text);
    font-size: 0.82rem;
    margin: -0.55rem 0 0;
}
.member-result__top,
.public-reward {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 1rem;
}
.member-result h3 {
    font-size: 1.45rem;
    margin: 0.15rem 0;
}
.member-result p {
    margin: 0;
    color: var(--muted);
}
.public-balance {
    display: grid;
    gap: 0.2rem;
    padding: 1rem;
    border-radius: 0.85rem;
    background: var(--accent);
    color: var(--accent-contrast);
}
.public-balance strong {
    font-size: 2rem;
}
.public-balance small {
    opacity: 0.82;
}
.public-reward {
    padding: 0.9rem;
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    background: var(--surface);
}
.public-reward div {
    display: grid;
    gap: 0.25rem;
}
.inactive-preview {
    margin-top: 1rem;
    border: 0;
    background: none;
    color: var(--muted);
    text-decoration: underline;
    cursor: pointer;
}
@media (max-width: 520px) {
    .public-loyalty {
        padding: 1.2rem;
        border-radius: 1rem;
    }
    .member-result__top,
    .public-reward {
        align-items: flex-start;
    }
    .public-balance strong {
        font-size: 1.7rem;
    }
}
</style>
