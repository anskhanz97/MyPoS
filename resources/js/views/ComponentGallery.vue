<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import AppButton from "../components/AppButton.vue";
import AppDialog from "../components/AppDialog.vue";
import AppToast from "../components/AppToast.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";
import AuthAccessPreview from "./AuthAccessPreview.vue";
import CatalogPreview from "./CatalogPreview.vue";
import CashSessionPreview from "./CashSessionPreview.vue";
import CustomerPreview from "./CustomerPreview.vue";
import DataTransferPreview from "./DataTransferPreview.vue";
import FinancePreview from "./FinancePreview.vue";
import FiscalPreview from "./FiscalPreview.vue";
import InventoryPreview from "./InventoryPreview.vue";
import IntegrationHubPreview from "./IntegrationHubPreview.vue";
import LoyaltyPreview from "./LoyaltyPreview.vue";
import NotificationPreview from "./NotificationPreview.vue";
import PlatformSubscriptionPreview from "./PlatformSubscriptionPreview.vue";
import PayrollPreview from "./PayrollPreview.vue";
import PricingPreview from "./PricingPreview.vue";
import PublicLoyaltyPreview from "./PublicLoyaltyPreview.vue";
import PurchasingPreview from "./PurchasingPreview.vue";
import RegisterSettingsPreview from "./RegisterSettingsPreview.vue";
import ReportingPreview from "./ReportingPreview.vue";
import SaleReturnsPreview from "./SaleReturnsPreview.vue";
import TenantSetupPreview from "./TenantSetupPreview.vue";
import TransferCountPreview from "./TransferCountPreview.vue";
import WorkforcePreview from "./WorkforcePreview.vue";

const props = withDefaults(defineProps<{ publicView?: boolean }>(), {
    publicView: false,
});

const customerName = ref("Ayesha Khan");
const amount = ref("1250.00");
const quantity = ref("2.500000");
const activeTab = ref("Components");
const dialog = ref<InstanceType<typeof AppDialog> | null>(null);
const tabs = computed(() =>
    props.publicView
        ? ["Components", "States", "Shell notes"]
        : [
              "Components",
              "States",
              "Tenancy",
              "Access",
              "Platform",
              "Registers",
              "Catalog",
              "Pricing",
              "Customers",
              "Inventory",
              "Transfers & counts",
              "Purchasing",
              "Cash control",
              "Returns",
              "Loyalty",
              "Workforce",
              "Payroll",
              "Finance",
              "Reports",
              "Data transfer",
              "Notifications",
              "Integrations",
              "Fiscal",
              "Shell notes",
          ],
);

watch(
    () => props.publicView,
    () => {
        if (!tabs.value.includes(activeTab.value))
            activeTab.value = "Components";
    },
);

async function moveTab(event: KeyboardEvent, index: number): Promise<void> {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
    event.preventDefault();
    const last = tabs.value.length - 1;
    const next =
        event.key === "Home"
            ? 0
            : event.key === "End"
              ? last
              : event.key === "ArrowRight"
                ? (index + 1) % tabs.value.length
                : (index - 1 + tabs.value.length) % tabs.value.length;
    activeTab.value = tabs.value[next] ?? "Components";
    await nextTick();
    document.getElementById(`tab-${activeTab.value}`)?.focus();
}
</script>

<template>
    <div class="gallery">
        <header class="page-heading">
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

        <div class="tabs" role="tablist" aria-label="Gallery sections">
            <button
                v-for="tab in tabs"
                :id="`tab-${tab}`"
                :key="tab"
                role="tab"
                :aria-selected="activeTab === tab"
                :tabindex="activeTab === tab ? 0 : -1"
                @click="activeTab = tab"
                @keydown="moveTab($event, tabs.indexOf(tab))"
            >
                {{ tab }}
            </button>
        </div>

        <section
            v-if="!publicView"
            v-show="activeTab === 'Components'"
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

        <TenantSetupPreview
            v-if="!publicView"
            v-show="activeTab === 'Tenancy'"
        />

        <AuthAccessPreview v-if="!publicView" v-show="activeTab === 'Access'" />

        <PlatformSubscriptionPreview
            v-if="!publicView"
            v-show="activeTab === 'Platform'"
        />

        <RegisterSettingsPreview
            v-if="!publicView"
            v-show="activeTab === 'Registers'"
        />

        <CatalogPreview v-if="!publicView" v-show="activeTab === 'Catalog'" />

        <PricingPreview v-if="!publicView" v-show="activeTab === 'Pricing'" />

        <CustomerPreview
            v-if="!publicView"
            v-show="activeTab === 'Customers'"
        />

        <InventoryPreview
            v-if="!publicView"
            v-show="activeTab === 'Inventory'"
        />

        <TransferCountPreview
            v-if="!publicView"
            v-show="activeTab === 'Transfers & counts'"
        />

        <PurchasingPreview
            v-if="!publicView"
            v-show="activeTab === 'Purchasing'"
        />

        <CashSessionPreview
            v-if="!publicView"
            v-show="activeTab === 'Cash control'"
        />

        <SaleReturnsPreview
            v-if="!publicView"
            v-show="activeTab === 'Returns'"
        />

        <LoyaltyPreview v-if="!publicView" v-show="activeTab === 'Loyalty'" />

        <WorkforcePreview
            v-if="!publicView"
            v-show="activeTab === 'Workforce'"
        />

        <PayrollPreview v-if="!publicView" v-show="activeTab === 'Payroll'" />

        <FinancePreview v-if="!publicView" v-show="activeTab === 'Finance'" />

        <ReportingPreview v-if="!publicView" v-show="activeTab === 'Reports'" />

        <DataTransferPreview
            v-if="!publicView"
            v-show="activeTab === 'Data transfer'"
        />

        <NotificationPreview
            v-if="!publicView"
            v-show="activeTab === 'Notifications'"
        />

        <IntegrationHubPreview
            v-if="!publicView"
            v-show="activeTab === 'Integrations'"
        />

        <FiscalPreview v-if="!publicView" v-show="activeTab === 'Fiscal'" />

        <PublicLoyaltyPreview
            v-if="publicView"
            v-show="activeTab === 'Components'"
        />

        <section
            v-if="!publicView"
            v-show="activeTab === 'Components'"
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
            v-if="!publicView"
            v-show="activeTab === 'Components'"
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
            v-show="activeTab === 'States'"
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
            v-if="!publicView"
            v-show="activeTab === 'States'"
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
            v-show="activeTab === 'Shell notes'"
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
