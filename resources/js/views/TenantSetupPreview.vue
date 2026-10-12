<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
import { type StoreOption, useStoreContext } from "../tenancy/storeContext";

type DataMode = "shared" | "independent";
type SetupStep = "choose" | "review" | "locked";

const stores: StoreOption[] = [
    { id: "store-lhr", name: "Lahore Flagship", code: "LHR-01" },
    { id: "store-isb", name: "Islamabad Blue Area", code: "ISB-01" },
];
const mode = ref<DataMode>("shared");
const step = ref<SetupStep>("choose");
const reviewConfirmed = ref(false);
const validationMessage = ref("");
const storeContext = useStoreContext(stores[0]?.id ?? "");
const activeStore = computed(
    () =>
        stores.find((store) => store.id === storeContext.activeStoreId.value) ??
        stores[0],
);

function openReview(): void {
    validationMessage.value = "";
    step.value = "review";
}

function activate(): void {
    if (!reviewConfirmed.value) {
        validationMessage.value =
            "Confirm that you understand this choice cannot be changed after activation.";
        return;
    }

    validationMessage.value = "";
    step.value = "locked";
}
</script>

<template>
    <div class="tenancy-preview">
        <header class="page-heading tenancy-heading">
            <div>
                <p class="kicker">Merchant setup · working preview</p>
                <h1>Choose how stores own daily records.</h1>
                <p>
                    This selection controls products and operational customers.
                    Loyalty identity remains merchant-wide in either mode.
                </p>
            </div>
            <StatusBadge :tone="step === 'locked' ? 'success' : 'warning'">
                {{ step === "locked" ? "Mode locked" : "Setup in progress" }}
            </StatusBadge>
        </header>

        <section class="gallery-section" aria-labelledby="ownership-title">
            <div class="section-heading">
                <div>
                    <h2 id="ownership-title">Operational data ownership</h2>
                    <p>Select once, review the effect, then activate.</p>
                </div>
                <span class="step-count"
                    >{{ step === "choose" ? "1" : "2" }} / 2</span
                >
            </div>

            <fieldset v-if="step === 'choose'" class="mode-grid">
                <legend class="sr-only">Store data mode</legend>
                <label
                    class="mode-card"
                    :class="{ 'mode-card--selected': mode === 'shared' }"
                >
                    <input v-model="mode" type="radio" value="shared" />
                    <span
                        ><strong>Shared records</strong
                        ><small
                            >One product and customer record can be used across
                            the merchant. Each store still keeps its own stock
                            and price overrides.</small
                        ></span
                    >
                </label>
                <label
                    class="mode-card"
                    :class="{ 'mode-card--selected': mode === 'independent' }"
                >
                    <input v-model="mode" type="radio" value="independent" />
                    <span
                        ><strong>Independent records</strong
                        ><small
                            >Each store owns separate products and operational
                            customers, even when the SKU, name or phone number
                            matches.</small
                        ></span
                    >
                </label>
                <div class="mode-note">
                    <strong>Always merchant-wide</strong>
                    <p>
                        Loyalty uses one identity with explicit links to local
                        customer records. Sales history is not merged.
                    </p>
                </div>
                <AppButton variant="primary" @click="openReview"
                    >Continue to review</AppButton
                >
            </fieldset>

            <div v-else class="review-panel">
                <dl>
                    <div>
                        <dt>Selected mode</dt>
                        <dd>
                            {{
                                mode === "shared"
                                    ? "Shared records"
                                    : "Independent records"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>Initial store</dt>
                        <dd>Lahore Flagship · LHR-01</dd>
                    </div>
                    <div>
                        <dt>Timezone</dt>
                        <dd>Asia/Karachi</dd>
                    </div>
                    <div>
                        <dt>Currency</dt>
                        <dd>PKR</dd>
                    </div>
                </dl>
                <p class="lock-warning">
                    <strong>Permanent after activation.</strong> A later
                    conversion requires a separately designed migration; there
                    is no live mode-switch endpoint.
                </p>
                <label v-if="step !== 'locked'" class="confirm-row">
                    <input v-model="reviewConfirmed" type="checkbox" />
                    <span
                        >I reviewed the ownership rules and want to activate
                        this merchant.</span
                    >
                </label>
                <p v-if="validationMessage" class="form-error" role="alert">
                    {{ validationMessage }}
                </p>
                <div v-if="step !== 'locked'" class="review-actions">
                    <AppButton variant="quiet" @click="step = 'choose'"
                        >Back</AppButton
                    >
                    <AppButton variant="primary" @click="activate"
                        >Activate and lock mode</AppButton
                    >
                </div>
                <StatusBadge v-else tone="success"
                    >Activated · selection locked</StatusBadge
                >
            </div>
        </section>

        <section class="gallery-section" aria-labelledby="stores-title">
            <div class="section-heading">
                <div>
                    <h2 id="stores-title">Authorized store context</h2>
                    <p>
                        Changing store clears incompatible drafts, filters and
                        store cache namespaces.
                    </p>
                </div>
                <StatusBadge tone="info">{{ activeStore?.code }}</StatusBadge>
            </div>
            <div class="store-workspace">
                <div class="store-list" aria-label="Authorized stores">
                    <button
                        v-for="store in stores"
                        :key="store.id"
                        type="button"
                        :aria-pressed="
                            store.id === storeContext.activeStoreId.value
                        "
                        @click="storeContext.switchStore(store)"
                    >
                        <span>{{ store.name }}</span
                        ><small>{{ store.code }}</small>
                    </button>
                </div>
                <div class="store-draft">
                    <label class="field"
                        ><span>Transient sale reference</span
                        ><input
                            v-model="storeContext.draftReference.value"
                        /><small
                            >Enter a value, then switch stores to verify it
                            clears.</small
                        ></label
                    >
                    <label class="field"
                        ><span>Store search</span
                        ><input v-model="storeContext.searchTerm.value" /><small
                            >Cache generation
                            {{ storeContext.cacheGeneration.value }}</small
                        ></label
                    >
                </div>
            </div>
            <p class="context-announcement" aria-live="polite">
                {{ storeContext.announcement.value }}
            </p>
        </section>
    </div>
</template>
