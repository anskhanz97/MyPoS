<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from "vue";
import StatePanel from "../components/StatePanel.vue";
const props = withDefaults(
    defineProps<{ publicView?: boolean; activeSection?: string }>(),
    { publicView: false, activeSection: "Tenancy" },
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

const currentPreview = computed(
    () =>
        previewComponents[
            props.publicView ? "Member profile" : props.activeSection
        ],
);
</script>
<template>
    <div class="gallery">
        <Suspense v-if="currentPreview">
            <component :is="currentPreview" />
            <template #fallback
                ><StatePanel
                    state="loading"
                    title="Opening workspace"
                    message="The selected tools are being prepared."
            /></template>
        </Suspense>
    </div>
</template>
