<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";

const orderState = ref<"draft" | "issued" | "cancelled">("draft");
const supplierArchived = ref(false);
const receivedCartons = ref(0);
const returnedPieces = ref(0);
const creditConfirmed = ref(false);
const supplierPayment = ref(0);
const announcement = ref("");
const remaining = computed(() =>
    orderState.value === "cancelled"
        ? "0.000000"
        : (24 - receivedCartons.value * 12).toFixed(6),
);
const valuationAverage = computed(() =>
    receivedCartons.value === 0 ? "100.000000" : "105.454545",
);
const supplierBalance = computed(
    () => 1500 - (creditConfirmed.value ? 375 : 0) - supplierPayment.value,
);
const supplierCreditLabel = computed(() => {
    if (!returnedPieces.value) return "PKR 0.00";
    return creditConfirmed.value
        ? "PKR 375.00 · confirmed"
        : "PKR 375.00 · expected";
});

function issueOrder(): void {
    orderState.value = "issued";
    announcement.value = "PO issued once. No stock movement was posted.";
}
function cancelOrder(): void {
    orderState.value = "cancelled";
    announcement.value =
        "Outstanding 24 pieces cancelled; received history preserved.";
}
function preparePdf(): void {
    announcement.value =
        "Print-ready A4 purchase order prepared in light styling.";
}
function postReceipt(): void {
    receivedCartons.value = 1;
    orderState.value = "issued";
    announcement.value =
        "GRN posted once: 12 pieces added, PO partially received, and valuation/outbox evidence recorded.";
}
function reverseReceipt(): void {
    receivedCartons.value = 0;
    announcement.value =
        "Latest receipt reversed with linked stock, valuation and supplier-charge entries.";
}
function postPurchaseReturn(): void {
    returnedPieces.value = 3;
    announcement.value =
        "Purchase return posted: 3 pieces removed at moving average; PKR 375.00 supplier credit awaits CN evidence.";
}
function confirmCredit(): void {
    creditConfirmed.value = true;
    announcement.value =
        "Supplier credit CN-900 confirmed. Physical return and financial evidence remain separately traceable.";
}
function recordSupplierPayment(): void {
    supplierPayment.value = 500;
    announcement.value =
        "PKR 500.00 payment recorded; PKR 400.00 allocated and PKR 100.00 remains unallocated. No bank transfer was initiated.";
}
</script>

<template>
    <div class="purchasing-preview">
        <header class="page-heading purchasing-heading">
            <div>
                <p class="kicker">Purchasing · Lahore Test Store</p>
                <h1>Plan the order. Receive it separately.</h1>
                <p>
                    Supplier records and purchase orders preserve expectations;
                    stock changes only through a later goods receipt.
                </p>
            </div>
            <StatusBadge tone="info">PKR planning</StatusBadge>
        </header>

        <div class="purchasing-layout">
            <section class="gallery-section" aria-labelledby="supplier-title">
                <div class="section-heading">
                    <div>
                        <p class="kicker">Supplier directory</p>
                        <h2 id="supplier-title">Karachi Tea Traders</h2>
                        <p>SUP-001 · merchant-wide purchasing record</p>
                    </div>
                    <StatusBadge
                        :tone="supplierArchived ? 'neutral' : 'success'"
                    >
                        {{ supplierArchived ? "Archived" : "Active" }}
                    </StatusBadge>
                </div>
                <dl class="supplier-details">
                    <div>
                        <dt>Contact</dt>
                        <dd>Sana Ahmed</dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd>021-5550123</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>orders@example.test</dd>
                    </div>
                    <div>
                        <dt>Tax reference</dt>
                        <dd>NTN-12345</dd>
                    </div>
                </dl>
                <p class="context-announcement">
                    Supplier balance: PKR {{ supplierBalance.toFixed(2) }} ·
                    store scope
                </p>
                <AppButton
                    v-if="!supplierArchived"
                    variant="quiet"
                    @click="supplierArchived = true"
                    >Archive supplier</AppButton
                >
            </section>

            <section class="gallery-section" aria-labelledby="po-title">
                <div class="section-heading">
                    <div>
                        <p class="kicker">PO-29A7C1 · version 2</p>
                        <h2 id="po-title">Expected order</h2>
                        <p>29 Sep 2026 · expected 03 Oct 2026</p>
                    </div>
                    <StatusBadge
                        :tone="
                            orderState === 'cancelled'
                                ? 'danger'
                                : orderState === 'issued'
                                  ? 'success'
                                  : 'warning'
                        "
                        >{{ orderState }}</StatusBadge
                    >
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="numeric">Ordered</th>
                                <th class="numeric">Base quantity</th>
                                <th class="numeric">Remaining</th>
                                <th class="numeric">Expected total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Green tea 250g</strong>
                                    <small class="inventory-sku"
                                        >TEA-250 · 1 carton = 12 pieces</small
                                    >
                                </td>
                                <td class="numeric">2.000000 ctn</td>
                                <td class="numeric">24.000000 pc</td>
                                <td class="numeric">{{ remaining }} pc</td>
                                <td class="numeric">PKR 2,500.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="purchase-summary">
                    <div>
                        <small>Expected order total</small>
                        <strong>PKR 2,500.00</strong>
                    </div>
                    <div class="purchase-actions">
                        <AppButton variant="quiet" @click="preparePdf"
                            >Prepare PDF</AppButton
                        >
                        <AppButton
                            v-if="orderState === 'draft'"
                            variant="primary"
                            @click="issueOrder"
                            >Issue purchase order</AppButton
                        >
                        <AppButton
                            v-else-if="orderState === 'issued'"
                            variant="danger"
                            @click="cancelOrder"
                            >Cancel outstanding</AppButton
                        >
                    </div>
                </div>
                <p
                    v-if="announcement"
                    class="context-announcement"
                    role="status"
                >
                    {{ announcement }}
                </p>
                <div class="po-history" aria-label="Purchase order history">
                    <span>1</span>
                    <p><strong>Created</strong>29 Sep · Sana Ahmed</p>
                    <span>2</span>
                    <p>
                        <strong>Issued</strong>Expected values frozen for
                        supplier PDF
                    </p>
                </div>
            </section>

            <section
                class="gallery-section receipt-workbench"
                aria-labelledby="receipt-title"
            >
                <div class="section-heading">
                    <div>
                        <p class="kicker">Goods receipt · online command</p>
                        <h2 id="receipt-title">Receive against PO-29A7C1</h2>
                        <p>
                            Actual quantities and costs post stock, valuation,
                            supplier charge evidence and an outbox event
                            together.
                        </p>
                    </div>
                    <StatusBadge
                        :tone="receivedCartons ? 'success' : 'warning'"
                    >
                        {{ receivedCartons ? "Partially received" : "Ready" }}
                    </StatusBadge>
                </div>
                <div class="receipt-context-grid">
                    <label>
                        Inventory location
                        <select aria-label="Inventory location">
                            <option>Main sellable stock</option>
                            <option>Quarantine</option>
                        </select>
                    </label>
                    <label>
                        Supplier reference
                        <input
                            value="INV-1001"
                            aria-label="Supplier reference"
                        />
                    </label>
                    <label>
                        Received at
                        <input
                            value="29 Sep 2026 · 10:30 PKT"
                            aria-label="Received at"
                        />
                    </label>
                </div>
                <div class="table-wrap">
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="numeric">Receive</th>
                                <th class="numeric">Actual unit cost</th>
                                <th class="numeric">Landed cost</th>
                                <th class="numeric">Recoverable tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Green tea 250g</strong>
                                    <small class="inventory-sku"
                                        >No batch required · 12 pc per
                                        carton</small
                                    >
                                </td>
                                <td class="numeric">1.000000 ctn</td>
                                <td class="numeric">PKR 1,200.000000</td>
                                <td class="numeric">PKR 120.000000</td>
                                <td class="numeric">PKR 180.000000</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="valuation-strip" aria-label="Valuation result">
                    <div>
                        <small>Before</small>
                        <strong>10 pc @ PKR 100.000000</strong>
                    </div>
                    <div>
                        <small>Receipt value</small>
                        <strong>12 pc @ PKR 110.000000</strong>
                    </div>
                    <div>
                        <small>Moving average</small>
                        <strong>PKR {{ valuationAverage }}</strong>
                    </div>
                    <div>
                        <small>Supplier charge</small>
                        <strong>PKR 1,500.00</strong>
                    </div>
                </div>
                <div class="receipt-command-bar">
                    <p>
                        Recoverable tax is excluded from stock value. Posting a
                        backdated document keeps the current valuation sequence.
                    </p>
                    <AppButton
                        v-if="!receivedCartons"
                        variant="primary"
                        :disabled="orderState !== 'issued'"
                        @click="postReceipt"
                        >Post partial receipt</AppButton
                    >
                    <AppButton
                        v-else
                        variant="danger"
                        :disabled="!!returnedPieces"
                        @click="reverseReceipt"
                        >Reverse latest receipt</AppButton
                    >
                </div>
            </section>

            <section
                class="gallery-section supplier-ledger-workbench"
                aria-labelledby="supplier-ledger-title"
            >
                <div class="section-heading">
                    <div>
                        <p class="kicker">Returns · credits · payments</p>
                        <h2 id="supplier-ledger-title">
                            Supplier balance workbench
                        </h2>
                        <p>
                            Physical goods, supplier documents and recorded
                            settlements stay as separate linked evidence.
                        </p>
                    </div>
                    <StatusBadge
                        :tone="supplierBalance ? 'warning' : 'success'"
                    >
                        PKR {{ supplierBalance.toFixed(2) }} due
                    </StatusBadge>
                </div>
                <div class="supplier-ledger-summary">
                    <div>
                        <small>Posted charge</small>
                        <strong>PKR 1,500.00</strong>
                    </div>
                    <div>
                        <small>Supplier credit</small>
                        <strong>{{ supplierCreditLabel }}</strong>
                    </div>
                    <div>
                        <small>Recorded payments</small>
                        <strong>PKR {{ supplierPayment.toFixed(2) }}</strong>
                    </div>
                    <div>
                        <small>Unallocated</small>
                        <strong
                            >PKR
                            {{ supplierPayment ? "100.00" : "0.00" }}</strong
                        >
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="supplier-ledger-table">
                        <thead>
                            <tr>
                                <th>Evidence</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th class="numeric">Balance effect</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Goods receipt charge</td>
                                <td>INV-1001</td>
                                <td>Posted</td>
                                <td class="numeric">PKR 1,500.00</td>
                            </tr>
                            <tr v-if="returnedPieces">
                                <td>Purchase return · 3.000000 pc</td>
                                <td>
                                    {{
                                        creditConfirmed
                                            ? "CN-900"
                                            : "Awaiting CN"
                                    }}
                                </td>
                                <td>
                                    {{
                                        creditConfirmed
                                            ? "Confirmed"
                                            : "Expected"
                                    }}
                                </td>
                                <td class="numeric">
                                    {{
                                        creditConfirmed
                                            ? "−PKR 375.00"
                                            : "Pending"
                                    }}
                                </td>
                            </tr>
                            <tr v-if="supplierPayment">
                                <td>Recorded bank transfer</td>
                                <td>BANK-7788</td>
                                <td>PKR 400 allocated</td>
                                <td class="numeric">−PKR 500.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="supplier-command-bar">
                    <p>
                        Payment records describe external evidence; they do not
                        send money. Store and merchant-wide statements use the
                        same immutable entries.
                    </p>
                    <div class="purchase-actions">
                        <AppButton
                            variant="quiet"
                            :disabled="!receivedCartons || !!returnedPieces"
                            @click="postPurchaseReturn"
                            >Post 3-piece return</AppButton
                        >
                        <AppButton
                            variant="quiet"
                            :disabled="!returnedPieces || creditConfirmed"
                            @click="confirmCredit"
                            >Confirm CN-900</AppButton
                        >
                        <AppButton
                            variant="primary"
                            :disabled="!creditConfirmed || !!supplierPayment"
                            @click="recordSupplierPayment"
                            >Record supplier payment</AppButton
                        >
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
