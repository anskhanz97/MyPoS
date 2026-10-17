<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import AppIcon from "../components/AppIcon.vue";
import AppButton from "../components/AppButton.vue";
import StatusBadge from "../components/StatusBadge.vue";
import OfflineRecoveryPreview from "./OfflineRecoveryPreview.vue";
import PrintOperationsPreview from "./PrintOperationsPreview.vue";
import DisplayControlPreview from "./DisplayControlPreview.vue";
import type { DisplayProjection } from "../display/customerDisplayChannel";

type Product = {
    sku: string;
    name: string;
    detail: string;
    price: number;
    tone: string;
};
type Line = Product & { quantity: number; extras?: string; warning?: string };

const products: Product[] = [
    {
        sku: "TEA-1",
        name: "Karak tea",
        detail: "Cup · tax 18%",
        price: 180,
        tone: "saffron",
    },
    {
        sku: "BRG-2",
        name: "Classic burger",
        detail: "Regular · choose extras",
        price: 650,
        tone: "berry",
    },
    {
        sku: "APL-KG",
        name: "Red apples",
        detail: "Weighted · per kg",
        price: 420,
        tone: "leaf",
    },
    {
        sku: "WTR-1",
        name: "Mineral water",
        detail: "500 ml",
        price: 90,
        tone: "sky",
    },
];
const lines = ref<Line[]>([
    {
        sku: "TEA-1",
        name: "Karak tea",
        detail: "Cup · tax 18%",
        price: 180,
        tone: "saffron",
        quantity: 2,
    },
    {
        sku: "BRG-2",
        name: "Classic burger",
        detail: "Regular · choose extras",
        price: 650,
        tone: "berry",
        quantity: 1,
        extras: "Cheese + Jalapeño · + PKR 120",
    },
]);
const checkoutControls = ref<HTMLDialogElement | null>(null);
async function openCheckoutControls(): Promise<void> {
    checkoutControls.value?.showModal();
    await nextTick();
    scanInput.value?.focus();
}
const scanCode = ref("");
const scanInput = ref<HTMLInputElement>();
const query = ref("");
const state = ref<"active" | "suspended">("active");
const connection = ref<"online" | "retrying">("online");
const announcement = ref("Authoritative quote m1-s0 · cart version 7");
const customer = ref("Walk-in customer");
const saleStage = ref<"basket" | "payment" | "posted">("basket");
const cashTender = ref(0);
const externalTender = ref(0);
const externalReference = ref("");
const paymentBusy = ref(false);

const subtotal = computed(
    () =>
        lines.value.reduce((sum, line) => sum + line.price * line.quantity, 0) +
        (lines.value.some((line) => line.sku === "BRG-2") ? 120 : 0),
);
const discount = computed(() =>
    lines.value.some((line) => line.sku === "TEA-1" && line.quantity >= 2)
        ? 36
        : 0,
);
const tax = computed(
    () => Math.round((subtotal.value - discount.value) * 0.18 * 100) / 100,
);
const total = computed(() => subtotal.value - discount.value + tax.value);
const cashChange = computed(() =>
    Math.max(0, cashTender.value + externalTender.value - total.value),
);
const netSettlement = computed(
    () => cashTender.value + externalTender.value - cashChange.value,
);
const paymentValid = computed(
    () =>
        Math.abs(netSettlement.value - total.value) < 0.001 &&
        cashChange.value <= cashTender.value &&
        (externalTender.value === 0 || externalReference.value.trim() !== ""),
);
const filtered = computed(() =>
    products.filter((product) =>
        `${product.name} ${product.sku}`
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
const displayProjection = computed<Omit<DisplayProjection, "sequence">>(() => {
    if (saleStage.value === "posted") {
        return {
            schemaVersion: 1,
            registerId: "LAB-01",
            state: "completed",
            message: "Thank you. Payment complete.",
            lines: [],
            totals: {
                subtotal: subtotal.value.toFixed(2),
                discount: discount.value.toFixed(2),
                tax: tax.value.toFixed(2),
                gross: total.value.toFixed(2),
            },
        };
    }
    if (state.value === "suspended") {
        return {
            schemaVersion: 1,
            registerId: "LAB-01",
            state: "suspended",
            message: "This sale is paused.",
            lines: [],
            totals: null,
        };
    }
    return {
        schemaVersion: 1,
        registerId: "LAB-01",
        state: "cart",
        message: "Your order",
        lines: lines.value.map((line) => ({
            name: line.name,
            quantity: line.quantity.toFixed(3).replace(/\.000$/, ""),
            unit: line.sku === "APL-KG" ? "kg" : "each",
            gross: (
                line.price * line.quantity +
                (line.sku === "BRG-2" ? 120 : 0)
            ).toFixed(2),
            discount: line.sku === "TEA-1" ? discount.value.toFixed(2) : "0.00",
            extras: line.extras ? [line.extras] : [],
        })),
        totals: {
            subtotal: subtotal.value.toFixed(2),
            discount: discount.value.toFixed(2),
            tax: tax.value.toFixed(2),
            gross: total.value.toFixed(2),
        },
    };
});

function add(product: Product, quantity = 1): void {
    if (state.value === "suspended") return;
    if (product.sku === "BRG-2") {
        announcement.value =
            "Variant and extras confirmed: regular, cheese and jalapeño.";
    }
    const existing = lines.value.find((line) => line.sku === product.sku);
    if (existing)
        existing.quantity =
            Math.round((existing.quantity + quantity) * 1000) / 1000;
    else {
        const line: Line = { ...product, quantity };
        if (product.sku === "BRG-2")
            line.extras = "Cheese + Jalapeño · + PKR 120";
        lines.value.push(line);
    }
    announcement.value = `${product.name} added. Cart version advanced; quote refresh pending.`;
}
function scan(): void {
    const code = scanCode.value.trim();
    if (code === "2901234012508") add(products[2]!, 1.25);
    else if (code === "89010001") add(products[0]!);
    else if (code === "EXPIRED01")
        announcement.value =
            "Blocked: batch B-2401 expired on 28 Sep 2026. Select eligible stock.";
    else announcement.value = "No active product matches that barcode.";
    scanCode.value = "";
    void nextTick(() => scanInput.value?.focus());
}
function toggleSuspend(): void {
    state.value = state.value === "active" ? "suspended" : "active";
    announcement.value =
        state.value === "suspended"
            ? "Cart suspended without reserving stock."
            : "Cart resumed and availability will be checked again.";
}
function retry(): void {
    connection.value = connection.value === "online" ? "retrying" : "online";
    announcement.value =
        connection.value === "retrying"
            ? "Network interrupted. Cart is preserved; retry remains visible."
            : "Connection restored. Server quote confirmed.";
}
function openPayment(): void {
    saleStage.value = "payment";
    cashTender.value = Math.ceil(total.value / 100) * 100;
    externalTender.value = 0;
    externalReference.value = "";
    announcement.value =
        "Payment opened against quote m1-s0. The sale is not posted yet.";
}
function useSplitTender(): void {
    externalTender.value = 500;
    cashTender.value = 1000;
    externalReference.value = "EASYP-STAFF-9421";
    announcement.value =
        "External payment recorded from staff declaration; provider verification is not claimed.";
}
function finalizeSale(): void {
    if (!paymentValid.value) {
        announcement.value =
            "Payment must settle the exact total. External tenders also need a staff reference.";
        return;
    }
    paymentBusy.value = true;
    window.setTimeout(() => {
        paymentBusy.value = false;
        saleStage.value = "posted";
        state.value = "suspended";
        announcement.value =
            "Sale SALE-20260927-00001 posted once. Receipt data is ready; printing was not requested.";
    }, 250);
}
function keyboard(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;
    if (
        event.key === "/" &&
        !["INPUT", "TEXTAREA", "SELECT"].includes(target?.tagName ?? "")
    ) {
        event.preventDefault();
        void openCheckoutControls();
    }
}
onMounted(() => window.addEventListener("keydown", keyboard));
onBeforeUnmount(() => window.removeEventListener("keydown", keyboard));
</script>

<template>
    <div class="checkout-preview">
        <dialog
            ref="checkoutControls"
            class="checkout-controls-modal app-dialog"
            aria-labelledby="checkout-controls-title"
            @click.self="checkoutControls?.close()"
        >
            <div class="checkout-controls-content">
                <div class="checkout-controls-heading">
                    <h2 id="checkout-controls-title">Checkout controls</h2>
                    <AppButton
                        variant="quiet"
                        @click="checkoutControls?.close()"
                        >Close</AppButton
                    >
                </div>
                <div class="checkout-command">
                    <div>
                        <p class="kicker">Counter sale · CART-8K2M41</p>
                        <h1>Checkout</h1>
                    </div>
                    <label class="scanner-field">
                        <span>Scan barcode <kbd>/</kbd></span>
                        <input
                            ref="scanInput"
                            v-model="scanCode"
                            autocomplete="off"
                            placeholder="Scan or type code"
                            @keyup.enter="scan"
                        />
                    </label>
                    <label class="customer-field">
                        <span>Customer</span>
                        <AppSelect v-model="customer">
                            <option>Walk-in customer</option>
                            <option>Sana Malik · Gold</option>
                        </AppSelect>
                    </label>
                    <button
                        class="staff-chip"
                        type="button"
                        @click="
                            announcement =
                                'Staff changed to Bilal; this cart stayed open.'
                        "
                    >
                        <span>AK</span>Ayesha Khan
                    </button>
                </div>
                <DisplayControlPreview :projection="displayProjection" />
            </div>
        </dialog>

        <div
            v-if="connection === 'retrying'"
            class="network-banner"
            role="alert"
        >
            <strong>Reconnecting</strong
            ><span>Cart changes are preserved. Server quote is pending.</span
            ><button type="button" @click="retry">Retry now</button>
        </div>

        <div class="checkout-layout">
            <section class="product-panel" aria-labelledby="products-title">
                <div class="checkout-section-heading">
                    <div>
                        <p class="kicker">Fast keys</p>
                        <h2 id="products-title">Popular at this counter</h2>
                    </div>
                    <label class="product-search"
                        ><span class="sr-only">Search products</span
                        ><input
                            v-model="query"
                            placeholder="Search products or SKU"
                    /></label>
                    <button
                        type="button"
                        class="checkout-controls-trigger"
                        aria-label="Open checkout controls"
                        title="Scanner, customer and display"
                        @click="openCheckoutControls"
                    >
                        <AppIcon name="setup" />
                    </button>
                </div>
                <div class="category-row" aria-label="Product categories">
                    <button type="button" class="selected">Popular</button
                    ><button type="button">Drinks</button
                    ><button type="button">Food</button
                    ><button type="button">Weighted</button>
                </div>
                <div class="product-grid">
                    <button
                        v-for="product in filtered"
                        :key="product.sku"
                        type="button"
                        class="product-tile"
                        :data-tone="product.tone"
                        :disabled="state === 'suspended'"
                        @click="add(product)"
                    >
                        <span class="product-monogram">{{
                            product.name.slice(0, 2).toUpperCase()
                        }}</span
                        ><strong>{{ product.name }}</strong
                        ><small>{{ product.detail }}</small
                        ><b>PKR {{ product.price.toFixed(2) }}</b>
                    </button>
                </div>
                <div class="scan-examples">
                    <span>Hardware simulation</span
                    ><button
                        type="button"
                        @click="
                            scanCode = '89010001';
                            scan();
                        "
                    >
                        Scan tea</button
                    ><button
                        type="button"
                        @click="
                            scanCode = '2901234012508';
                            scan();
                        "
                    >
                        Scan 1.250 kg</button
                    ><button
                        type="button"
                        @click="
                            scanCode = 'EXPIRED01';
                            scan();
                        "
                    >
                        Try expired batch
                    </button>
                </div>
            </section>

            <aside class="basket" aria-labelledby="basket-title">
                <div class="basket-heading">
                    <div>
                        <p class="kicker">Version 7 · {{ customer }}</p>
                        <h2 id="basket-title">Current basket</h2>
                    </div>
                    <StatusBadge
                        :tone="state === 'active' ? 'success' : 'warning'"
                        >{{ state }}</StatusBadge
                    >
                </div>
                <ol v-if="lines.length" class="basket-lines">
                    <li v-for="line in lines" :key="line.sku">
                        <div class="line-main">
                            <strong>{{ line.name }}</strong
                            ><small>{{ line.sku }} · {{ line.detail }}</small
                            ><small v-if="line.extras" class="line-extra">{{
                                line.extras
                            }}</small>
                        </div>
                        <div class="quantity-control">
                            <button
                                type="button"
                                :aria-label="`Reduce ${line.name}`"
                                @click="
                                    line.quantity = Math.max(
                                        0.001,
                                        line.quantity - 1,
                                    )
                                "
                            >
                                −</button
                            ><input
                                v-model.number="line.quantity"
                                :aria-label="`${line.name} quantity`"
                                type="number"
                                min="0.001"
                                step="0.001"
                            /><button
                                type="button"
                                :aria-label="`Increase ${line.name}`"
                                @click="line.quantity += 1"
                            >
                                +
                            </button>
                        </div>
                        <strong class="line-total"
                            >PKR
                            {{
                                (line.price * line.quantity).toFixed(2)
                            }}</strong
                        >
                    </li>
                </ol>
                <div v-else class="empty-basket">
                    <strong>Basket is empty</strong
                    ><span>Scan an item or use a product key.</span>
                </div>
                <dl class="quote-breakdown">
                    <div>
                        <dt>Items & extras</dt>
                        <dd>PKR {{ subtotal.toFixed(2) }}</dd>
                    </div>
                    <div class="discount-row">
                        <dt>Tea pair promotion</dt>
                        <dd>− PKR {{ discount.toFixed(2) }}</dd>
                    </div>
                    <div>
                        <dt>Tax · server calculated</dt>
                        <dd>PKR {{ tax.toFixed(2) }}</dd>
                    </div>
                    <div class="grand-total">
                        <dt>Total</dt>
                        <dd>PKR {{ total.toFixed(2) }}</dd>
                    </div>
                </dl>
                <p class="quote-note">
                    Prices and tax use published version <b>m1-s0</b>. Payment
                    becomes available with finalized sale posting.
                </p>
                <div class="basket-actions">
                    <AppButton variant="quiet" @click="toggleSuspend">{{
                        state === "active" ? "Suspend cart" : "Resume cart"
                    }}</AppButton
                    ><AppButton
                        variant="primary"
                        :disabled="state !== 'active' || !lines.length"
                        @click="openPayment"
                        >Continue to payment</AppButton
                    >
                </div>
            </aside>
        </div>
        <section
            v-if="saleStage !== 'basket'"
            class="payment-workspace"
            aria-labelledby="payment-title"
        >
            <div class="payment-heading">
                <div>
                    <p class="kicker">
                        {{
                            saleStage === "posted"
                                ? "Posted sale"
                                : "Exact settlement"
                        }}
                    </p>
                    <h2 id="payment-title">
                        {{
                            saleStage === "posted"
                                ? "SALE-20260927-00001"
                                : "Take payment"
                        }}
                    </h2>
                </div>
                <StatusBadge
                    :tone="saleStage === 'posted' ? 'success' : 'info'"
                    >{{
                        saleStage === "posted" ? "finalized" : "not posted"
                    }}</StatusBadge
                >
            </div>

            <template v-if="saleStage === 'payment'">
                <div class="payment-grid">
                    <div class="tender-panel">
                        <div class="payment-total">
                            <span>Amount due</span
                            ><strong>PKR {{ total.toFixed(2) }}</strong>
                        </div>
                        <label
                            ><span>Cash received</span
                            ><input
                                v-model.number="cashTender"
                                type="number"
                                min="0"
                                step="0.01"
                        /></label>
                        <label
                            ><span>External card / wallet / bank</span
                            ><input
                                v-model.number="externalTender"
                                type="number"
                                min="0"
                                step="0.01"
                        /></label>
                        <label v-if="externalTender > 0"
                            ><span>Staff-declared external reference</span
                            ><input
                                v-model="externalReference"
                                maxlength="200"
                                autocomplete="off"
                        /></label>
                        <p
                            v-if="externalTender > 0"
                            class="manual-payment-note"
                        >
                            Recorded from a staff declaration. The POS has not
                            verified this payment with a bank or provider.
                        </p>
                        <button
                            type="button"
                            class="split-preset"
                            @click="useSplitTender"
                        >
                            Use split cash + wallet example
                        </button>
                    </div>
                    <aside
                        class="settlement-card"
                        aria-label="Settlement review"
                    >
                        <dl>
                            <div>
                                <dt>Cash received</dt>
                                <dd>PKR {{ cashTender.toFixed(2) }}</dd>
                            </div>
                            <div>
                                <dt>External recorded</dt>
                                <dd>PKR {{ externalTender.toFixed(2) }}</dd>
                            </div>
                            <div>
                                <dt>Change due</dt>
                                <dd>PKR {{ cashChange.toFixed(2) }}</dd>
                            </div>
                            <div class="settlement-net">
                                <dt>Applied to sale</dt>
                                <dd>PKR {{ netSettlement.toFixed(2) }}</dd>
                            </div>
                        </dl>
                        <p :class="paymentValid ? 'settled' : 'unsettled'">
                            {{
                                paymentValid
                                    ? "Fully settled"
                                    : "Settlement or reference incomplete"
                            }}
                        </p>
                        <div class="payment-actions">
                            <AppButton
                                variant="quiet"
                                @click="saleStage = 'basket'"
                                >Back to basket</AppButton
                            >
                            <AppButton
                                variant="primary"
                                :disabled="!paymentValid || paymentBusy"
                                @click="finalizeSale"
                                >{{
                                    paymentBusy
                                        ? "Posting once…"
                                        : "Finalize sale"
                                }}</AppButton
                            >
                        </div>
                    </aside>
                </div>
            </template>

            <div v-else class="sale-result">
                <article class="receipt-card">
                    <div>
                        <span>Receipt snapshot</span
                        ><strong>Ready · version 1</strong>
                    </div>
                    <div>
                        <span>Total</span
                        ><strong>PKR {{ total.toFixed(2) }}</strong>
                    </div>
                    <div>
                        <span>Cash</span
                        ><strong>PKR {{ cashTender.toFixed(2) }}</strong>
                    </div>
                    <div v-if="externalTender > 0">
                        <span>External · staff declared</span
                        ><strong>PKR {{ externalTender.toFixed(2) }}</strong>
                    </div>
                    <div>
                        <span>Change</span
                        ><strong>PKR {{ cashChange.toFixed(2) }}</strong>
                    </div>
                </article>
                <aside class="outcome-list" aria-label="Sale outcomes">
                    <div>
                        <StatusBadge tone="success">posted</StatusBadge
                        ><span
                            >Inventory, tender and drawer effects
                            committed</span
                        >
                    </div>
                    <div>
                        <StatusBadge tone="neutral">not requested</StatusBadge
                        ><span>Receipt printing</span>
                    </div>
                    <div>
                        <StatusBadge tone="neutral">not required</StatusBadge
                        ><span>Fiscal submission</span>
                    </div>
                    <div>
                        <StatusBadge tone="info">staff declaration</StatusBadge
                        ><span>External payment provenance</span>
                    </div>
                </aside>
            </div>
            <PrintOperationsPreview
                v-if="saleStage === 'posted'"
                :total="total"
            />
        </section>
        <OfflineRecoveryPreview />
        <footer class="checkout-status">
            <p role="status" aria-live="polite">{{ announcement }}</p>
            <button type="button" @click="retry">
                {{
                    connection === "online"
                        ? "Simulate network loss"
                        : "Mark online"
                }}
            </button>
        </footer>
    </div>
</template>

<style scoped>
.checkout-preview {
    display: grid;
    gap: 0.75rem;
    min-width: 0;
}
.checkout-command {
    display: grid;
    grid-template-columns: auto minmax(18rem, 1fr) minmax(12rem, 0.45fr) auto;
    gap: 0.75rem;
    align-items: end;
    padding: 0.85rem 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}
.checkout-command h1 {
    margin: 0;
    font-size: 1.6rem;
}
.checkout-command .kicker {
    margin: 0;
}
.scanner-field,
.customer-field {
    display: grid;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
}
.scanner-field input,
.customer-field select,
.product-search input,
.quantity-control input {
    border: 1px solid var(--border-strong);
    background: var(--input);
    color: var(--text);
    border-radius: 0.35rem;
    padding: 0.7rem;
}
.scanner-field input:focus,
.product-search input:focus {
    outline: 3px solid color-mix(in srgb, var(--accent) 30%, transparent);
    border-color: var(--accent);
}
kbd {
    padding: 0.1rem 0.3rem;
    border: 1px solid var(--border);
    border-radius: 0.2rem;
    background: var(--surface-raised);
}
.staff-chip {
    display: flex;
    gap: 0.45rem;
    align-items: center;
    border: 0;
    background: transparent;
    color: var(--text);
    font-weight: 700;
    padding: 0.55rem;
}
.staff-chip span {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: var(--accent);
    color: var(--accent-contrast);
}
.network-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.65rem 1rem;
    background: var(--warning-surface);
    border-left: 4px solid var(--warning);
}
.network-banner span {
    flex: 1;
}
.network-banner button,
.checkout-status button,
.scan-examples button,
.category-row button {
    border: 0;
    background: transparent;
    color: var(--accent);
    font-weight: 750;
    text-decoration: underline;
    cursor: pointer;
}
.checkout-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(23rem, 0.75fr);
    gap: 0.75rem;
    min-height: 36rem;
}
.product-panel,
.basket {
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}
.checkout-section-heading,
.basket-heading {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 1rem;
}
.checkout-section-heading h2,
.basket-heading h2 {
    margin: 0.1rem 0;
}
.product-search input {
    min-width: 17rem;
}
.category-row {
    display: flex;
    gap: 0.4rem;
    margin: 1rem 0;
    overflow: auto;
}
.category-row button {
    padding: 0.5rem 0.8rem;
    text-decoration: none;
    border: 1px solid var(--border);
    border-radius: 2rem;
    color: var(--text);
}
.category-row .selected {
    background: var(--text);
    color: var(--surface);
}
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}
.product-tile {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.2rem 0.6rem;
    text-align: left;
    padding: 0.85rem;
    border: 1px solid var(--border);
    border-top: 4px solid var(--accent);
    background: var(--surface-raised);
    color: var(--text);
    cursor: pointer;
}
.product-tile:hover {
    border-color: var(--accent);
    transform: translateY(-1px);
}
.product-tile:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.product-tile strong,
.product-tile small,
.product-tile b {
    grid-column: 2;
}
.product-tile small {
    color: var(--text-muted);
}
.product-monogram {
    grid-row: 1/4;
    display: grid;
    place-items: center;
    width: 2.4rem;
    height: 2.4rem;
    background: var(--info-surface);
    font-family: Bahnschrift, sans-serif;
    font-weight: 800;
}
.product-tile[data-tone="berry"] {
    border-top-color: var(--danger);
}
.product-tile[data-tone="leaf"] {
    border-top-color: var(--success);
}
.product-tile[data-tone="saffron"] {
    border-top-color: var(--warning);
}
.scan-examples {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    padding-top: 1rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}
.basket {
    display: grid;
    grid-template-rows: auto minmax(8rem, 1fr) auto auto auto;
    gap: 0.75rem;
}
.basket-lines {
    display: grid;
    align-content: start;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}
.basket-lines li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.55rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid var(--border);
}
.line-main {
    display: grid;
    gap: 0.2rem;
}
.line-main small {
    color: var(--text-muted);
}
.line-extra {
    color: var(--info) !important;
}
.quantity-control {
    display: flex;
    align-items: center;
    grid-row: 2;
    gap: 0.25rem;
}
.quantity-control button {
    width: 2rem;
    height: 2rem;
    border: 1px solid var(--border);
    background: var(--surface-raised);
    color: var(--text);
}
.quantity-control input {
    width: 4.5rem;
    padding: 0.4rem;
    text-align: center;
}
.line-total {
    grid-column: 2;
    grid-row: 2;
    align-self: center;
    font-variant-numeric: tabular-nums;
}
.quote-breakdown {
    display: grid;
    gap: 0.4rem;
    margin: 0;
    padding-top: 0.75rem;
    border-top: 2px solid var(--border-strong);
}
.quote-breakdown div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.quote-breakdown dd {
    margin: 0;
    font-variant-numeric: tabular-nums;
    font-weight: 700;
}
.discount-row {
    color: var(--success);
}
.grand-total {
    padding-top: 0.55rem;
    font-size: 1.25rem;
    border-top: 1px solid var(--border);
}
.quote-note {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.78rem;
}
.basket-actions {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 0.55rem;
}
.checkout-status {
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    background: var(--info-surface);
    border-left: 4px solid var(--info);
}
.checkout-status p {
    margin: 0;
}
.payment-workspace {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}
.payment-heading {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 1rem;
}
.payment-heading h2,
.payment-heading p {
    margin: 0;
}
.payment-grid,
.sale-result {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(20rem, 0.8fr);
    gap: 1rem;
}
.tender-panel,
.settlement-card,
.receipt-card,
.outcome-list {
    display: grid;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface-raised);
    border: 1px solid var(--border);
}
.tender-panel label {
    display: grid;
    gap: 0.3rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
}
.tender-panel input {
    padding: 0.7rem;
    border: 1px solid var(--border-strong);
    border-radius: 0.35rem;
    background: var(--input);
    color: var(--text);
}
.payment-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}
.payment-total strong {
    font-size: 1.35rem;
}
.manual-payment-note {
    margin: 0;
    padding: 0.7rem;
    color: var(--text-muted);
    background: var(--warning-surface);
    border-left: 4px solid var(--warning);
}
.split-preset {
    justify-self: start;
    border: 0;
    background: transparent;
    color: var(--accent);
    font-weight: 750;
    text-decoration: underline;
    cursor: pointer;
}
.settlement-card dl {
    display: grid;
    gap: 0.55rem;
    margin: 0;
}
.settlement-card dl div,
.receipt-card div,
.outcome-list div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.settlement-card dd {
    margin: 0;
    font-variant-numeric: tabular-nums;
    font-weight: 750;
}
.settlement-card .settlement-net {
    padding-top: 0.65rem;
    border-top: 2px solid var(--border-strong);
}
.settled,
.unsettled {
    margin: 0;
    padding: 0.7rem;
    font-weight: 750;
}
.settled {
    color: var(--success);
    background: var(--success-surface);
}
.unsettled {
    color: var(--warning);
    background: var(--warning-surface);
}
.payment-actions {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 0.55rem;
}
.receipt-card div {
    padding-bottom: 0.6rem;
    border-bottom: 1px solid var(--border);
}
.receipt-card span,
.outcome-list span {
    color: var(--text-muted);
}
.outcome-list div {
    justify-content: start;
}
.empty-basket {
    display: grid;
    place-items: center;
    align-content: center;
    color: var(--text-muted);
}
@media (max-width: 1000px) {
    .checkout-command {
        grid-template-columns: 1fr 1fr;
    }
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    .product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 639px) {
    .checkout-command {
        grid-template-columns: 1fr;
    }
    .checkout-layout {
        min-height: 0;
    }
    .product-search,
    .product-search input {
        width: 100%;
        min-width: 0;
    }
    .checkout-section-heading {
        flex-direction: column;
    }
    .product-grid {
        grid-template-columns: 1fr 1fr;
    }
    .product-tile {
        grid-template-columns: 1fr;
    }
    .product-tile strong,
    .product-tile small,
    .product-tile b {
        grid-column: 1;
    }
    .product-monogram {
        display: none;
    }
    .basket-actions {
        grid-template-columns: 1fr;
    }
    .payment-grid,
    .sale-result,
    .payment-actions {
        grid-template-columns: 1fr;
    }
    .checkout-status {
        align-items: start;
        flex-direction: column;
    }
}
</style>

<style scoped>
.checkout-controls-trigger {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 2.8rem;
    height: 2.8rem;
    color: var(--accent);
    background: var(--surface-raised);
    border: 1px solid var(--border);
    border-radius: 0.85rem;
    cursor: pointer;
}
.checkout-controls-trigger:hover {
    background: var(--selection);
    border-color: var(--accent);
}
.checkout-controls-modal {
    width: min(48rem, calc(100vw - 2rem));
    max-height: calc(100dvh - 2rem);
    overflow-y: auto;
}
.checkout-controls-content {
    display: grid;
    gap: 1rem;
    padding: 1.25rem;
}
.checkout-controls-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.checkout-controls-modal .checkout-command {
    grid-template-columns: 1fr;
    border: 0;
    padding: 0;
    box-shadow: none;
}
.checkout-controls-modal :deep(.display-control) {
    grid-template-columns: 1fr;
    border-radius: 0.75rem;
    box-shadow: none;
}
.checkout-controls-modal :deep(.display-state) {
    justify-content: flex-start;
    flex-wrap: wrap;
}
.checkout-controls-modal :deep(.display-actions) {
    justify-content: flex-start;
}
@media (max-width: 639px) {
    .checkout-section-heading {
        position: relative;
        padding-right: 3.25rem;
    }
    .checkout-controls-trigger {
        position: absolute;
        right: 0;
        top: 0;
    }
    .checkout-section-heading .product-search {
        width: 100%;
    }
}
</style>
