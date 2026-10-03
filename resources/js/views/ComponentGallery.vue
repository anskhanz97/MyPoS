<script setup lang="ts">
import {
    computed,
    defineAsyncComponent,
    nextTick,
    ref,
    watch,
    type Component,
} from "vue";
import AppButton from "../components/AppButton.vue";
import AppDialog from "../components/AppDialog.vue";
import AppToast from "../components/AppToast.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

const props = withDefaults(
    defineProps<{ publicView?: boolean; activeSection?: string }>(),
    {
        publicView: false,
        activeSection: "Components",
    },
);

const previewComponents: Record<string, Component> = {
    Tenancy: defineAsyncComponent(() => import("./TenantSetupPreview.vue")),
    Access: defineAsyncComponent(() => import("./AuthAccessPreview.vue")),
    Platform: defineAsyncComponent(
        () => import("./PlatformSubscriptionPreview.vue"),
    ),
    Registers: defineAsyncComponent(
        () => import("./RegisterSettingsPreview.vue"),
    ),
    Catalog: defineAsyncComponent(() => import("./CatalogPreview.vue")),
    Pricing: defineAsyncComponent(() => import("./PricingPreview.vue")),
    Customers: defineAsyncComponent(() => import("./CustomerPreview.vue")),
    Inventory: defineAsyncComponent(() => import("./InventoryPreview.vue")),
    "Transfers & counts": defineAsyncComponent(
        () => import("./TransferCountPreview.vue"),
    ),
    Purchasing: defineAsyncComponent(() => import("./PurchasingPreview.vue")),
    "Cash control": defineAsyncComponent(
        () => import("./CashSessionPreview.vue"),
    ),
    Returns: defineAsyncComponent(() => import("./SaleReturnsPreview.vue")),
    Loyalty: defineAsyncComponent(() => import("./LoyaltyPreview.vue")),
    Workforce: defineAsyncComponent(() => import("./WorkforcePreview.vue")),
    Payroll: defineAsyncComponent(() => import("./PayrollPreview.vue")),
    Finance: defineAsyncComponent(() => import("./FinancePreview.vue")),
    Reports: defineAsyncComponent(() => import("./ReportingPreview.vue")),
    "Data transfer": defineAsyncComponent(
        () => import("./DataTransferPreview.vue"),
    ),
    Notifications: defineAsyncComponent(
        () => import("./NotificationPreview.vue"),
    ),
    Integrations: defineAsyncComponent(
        () => import("./IntegrationHubPreview.vue"),
    ),
    Fiscal: defineAsyncComponent(() => import("./FiscalPreview.vue")),
    "Member profile": defineAsyncComponent(
        () => import("./PublicLoyaltyPreview.vue"),
    ),
};

const customerName = ref("Ayesha Khan");
const amount = ref("1250.00");
const quantity = ref("2.500000");
const publicTab = ref("Components");
const dialog = ref<InstanceType<typeof AppDialog> | null>(null);
const tabs = ["Components", "States", "Shell notes"];
const effectiveSection = computed(() =>
    props.publicView ? publicTab.value : props.activeSection,
);
const currentPreview = computed(() =>
    props.publicView
        ? effectiveSection.value === "Components"
            ? previewComponents["Member profile"]
            : undefined
        : previewComponents[effectiveSection.value],
);

watch(
    () => props.publicView,
    () => {
        if (!tabs.includes(publicTab.value)) {
            publicTab.value = "Components";
        }
    },
);

async function moveTab(event: KeyboardEvent, index: number): Promise<void> {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
    event.preventDefault();
    const last = tabs.length - 1;
    const next =
        event.key === "Home"
            ? 0
            : event.key === "End"
              ? last
              : event.key === "ArrowRight"
                ? (index + 1) % tabs.length
                : (index - 1 + tabs.length) % tabs.length;
    publicTab.value = tabs[next] ?? "Components";
    await nextTick();
    document.getElementById(`tab-${publicTab.value}`)?.focus();
}
</script>

<template>
    <div class="gallery">
        <header
            v-if="
                ['Components', 'States', 'Shell notes'].includes(
                    effectiveSection,
                )
            "
            class="page-heading"
        >
            <div>
                <p class="kicker">Design system reference</p>
                <h1>
                    {{
                        publicView
                            ? "Member access, limited data."
                            : "Fast controls, clear state."
                    }}
                </h1>
                <p>
                    {{
                        publicView
                            ? "Public components reveal only the member information needed for the current task. Values below are synthetic."
                            : "Shared building blocks for management and checkout views. Values below are synthetic."
                    }}
                </p>
            </div>
            <StatusBadge tone="info">Foundation preview</StatusBadge>
        </header>

        <div
            v-if="publicView"
            class="tabs"
            role="tablist"
            aria-label="Gallery sections"
        >
            <button
                v-for="tab in tabs"
                :id="`tab-${tab}`"
                :key="tab"
                role="tab"
                :aria-selected="publicTab === tab"
                :tabindex="publicTab === tab ? 0 : -1"
                @click="publicTab = tab"
                @keydown="moveTab($event, tabs.indexOf(tab))"
            >
                {{ tab }}
            </button>
        </div>

        <section
            v-if="!publicView && effectiveSection === 'Components'"
            class="gallery-section"
            aria-labelledby="actions-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="actions-title">Actions and status</h2>
                    <p>Priority remains legible without relying on color.</p>
                </div>
            </div>
            <div class="component-row">
                <AppButton variant="primary">Save changes</AppButton
                ><AppButton>Review sale</AppButton
                ><AppButton variant="quiet">Clear filters</AppButton
                ><AppButton variant="danger">Void draft</AppButton
                ><AppButton busy>Saving</AppButton
                ><AppButton disabled>Unavailable</AppButton>
            </div>
            <div class="component-row">
                <StatusBadge tone="success">Paid</StatusBadge
                ><StatusBadge tone="warning">Needs review</StatusBadge
                ><StatusBadge tone="danger">Blocked</StatusBadge
                ><StatusBadge tone="info">Sync pending</StatusBadge
                ><StatusBadge>Draft</StatusBadge>
            </div>
        </section>

        <Suspense v-if="currentPreview">
            <component :is="currentPreview" />
            <template #fallback>
                <StatePanel
                    state="loading"
                    title="Opening workspace"
                    message="The selected tools are being prepared."
                />
            </template>
        </Suspense>

        <section
            v-if="!publicView && effectiveSection === 'Components'"
            class="gallery-section"
            aria-labelledby="fields-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="fields-title">Fields and validation</h2>
                    <p>
                        {{
                            publicView
                                ? "Public lookup uses the minimum fields needed for this merchant."
                                : "Money and quantity remain decimal strings from input to API."
                        }}
                    </p>
                </div>
            </div>
            <div class="field-grid">
                <label class="field"
                    ><span>Customer name</span
                    ><input v-model="customerName" autocomplete="off" /><small
                        >Optional for counter sales.</small
                    ></label
                >
                <label class="field"
                    ><span>Sale amount</span
                    ><span class="input-affix"
                        ><b>PKR</b
                        ><input v-model="amount" inputmode="decimal" /></span
                    ><small>Two decimal places.</small></label
                >
                <label class="field"
                    ><span>Quantity</span
                    ><span class="input-affix input-affix--suffix"
                        ><input v-model="quantity" inputmode="decimal" /><b
                            >kg</b
                        ></span
                    ><small>Up to six stored decimals.</small></label
                >
                <label class="field field--error"
                    ><span>Reference</span
                    ><input
                        value=""
                        aria-invalid="true"
                        aria-describedby="reference-error"
                    /><small id="reference-error"
                        >Enter a reference before saving.</small
                    ></label
                >
            </div>
        </section>

        <section
            v-if="!publicView && effectiveSection === 'Components'"
            class="gallery-section"
            aria-labelledby="table-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="table-title">Table and filters</h2>
                    <p>Numeric columns align for quick comparison.</p>
                </div>
                <div class="filter-row">
                    <label>Search <input value="Sample" /></label
                    ><AppButton variant="quiet">Reset</AppButton>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Status</th>
                            <th class="numeric">Quantity</th>
                            <th class="numeric">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sample green tea</td>
                            <td>
                                <StatusBadge tone="success"
                                    >Available</StatusBadge
                                >
                            </td>
                            <td class="numeric">12.000000</td>
                            <td class="numeric">PKR 1,250.00</td>
                        </tr>
                        <tr>
                            <td>Sample oat biscuits</td>
                            <td>
                                <StatusBadge tone="warning"
                                    >Low stock</StatusBadge
                                >
                            </td>
                            <td class="numeric">3.000000</td>
                            <td class="numeric">PKR 480.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="effectiveSection === 'States'"
            class="gallery-section"
            aria-labelledby="states-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="states-title">System states</h2>
                    <p>
                        Each state explains what is happening or what to do
                        next.
                    </p>
                </div>
            </div>
            <div class="state-grid">
                <StatePanel
                    state="loading"
                    title="Loading sales"
                    message="Recent activity is being prepared."
                /><StatePanel
                    state="empty"
                    title="No saved filters"
                    message="Save a filter after choosing the columns you need."
                /><StatePanel
                    state="error"
                    title="Report could not load"
                    message="Check the connection and try again."
                />
            </div>
        </section>

        <section
            v-if="!publicView && effectiveSection === 'States'"
            class="gallery-section"
            aria-labelledby="feedback-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="feedback-title">Feedback and approval</h2>
                    <p>Confirmations name the effect before it happens.</p>
                </div>
            </div>
            <div class="feedback-grid">
                <AppToast
                    title="Draft saved"
                    message="The sale remains editable."
                /><AppToast
                    tone="error"
                    title="Sync paused"
                    message="Pending work is safe on this register."
                /><AppButton @click="dialog?.open()"
                    >Open approval prompt</AppButton
                >
            </div>
        </section>

        <section
            v-if="effectiveSection === 'Shell notes'"
            class="gallery-section"
            aria-labelledby="shell-notes-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="shell-notes-title">Shell responsibilities</h2>
                    <p>
                        Context and display preference stay visible without
                        exposing unavailable modules.
                    </p>
                </div>
            </div>
            <div class="state-grid">
                <StatePanel
                    state="empty"
                    title="Management"
                    message="Merchant, store and authorized navigation frame back-office work."
                /><StatePanel
                    state="empty"
                    title="Checkout"
                    message="Register, connectivity and pending work remain visible during a sale."
                /><StatePanel
                    state="empty"
                    title="Public"
                    message="Member pages omit staff controls and use an independent theme preference."
                />
            </div>
        </section>

        <AppDialog v-if="!publicView" ref="dialog"
            ><template #title><h2>Approve exceptional action?</h2></template>
            <p>
                This preview demonstrates a focused approval prompt. No business
                action will run.
            </p>
            <template #action
                ><AppButton variant="primary" @click="dialog?.close()"
                    >Approve preview</AppButton
                ></template
            ></AppDialog
        >
    </div>
</template>
