<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

const query = ref("");
const selected = ref("Green tea");
const archived = ref(false);
const products = [
    {
        name: "Green tea",
        sku: "TEA-001",
        kind: "Variants",
        detail: "250 g · 500 g",
        stock: "Finished product",
    },
    {
        name: "Loose almonds",
        sku: "NUT-014",
        kind: "Weighted",
        detail: "kg · scale ref 00123",
        stock: "Entered or label weight",
    },
    {
        name: "Tea gift set",
        sku: "GIFT-007",
        kind: "Bundle",
        detail: "2 finished components",
        stock: "Expanded at posting",
    },
];
const visibleProducts = computed(() =>
    products.filter((product) =>
        `${product.name} ${product.sku}`
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
</script>

<template>
    <div class="catalog-preview">
        <header class="page-heading catalog-heading">
            <div>
                <p class="kicker">Catalog · working preview</p>
                <h1>One scan, an exact sellable item.</h1>
                <p>
                    Products remain scoped, searchable and explicit about units,
                    bundles and availability.
                </p>
            </div>
            <StatusBadge tone="info">Merchant catalog</StatusBadge>
        </header>
        <section class="gallery-section" aria-labelledby="catalog-list-title">
            <div class="section-heading">
                <div>
                    <h2 id="catalog-list-title">
                        Lahore Test Store availability
                    </h2>
                    <p>3 available · bounded search · archived items hidden</p>
                </div>
                <AppButton variant="primary">Create product</AppButton>
            </div>
            <div class="catalog-workspace">
                <div class="catalog-list">
                    <label class="field"
                        ><span>Search name, SKU or barcode</span
                        ><input v-model="query" placeholder="Try TEA-001"
                    /></label>
                    <button
                        v-for="product in visibleProducts"
                        :key="product.sku"
                        type="button"
                        :aria-pressed="selected === product.name"
                        @click="selected = product.name"
                    >
                        <span
                            ><strong>{{ product.name }}</strong
                            ><small
                                >{{ product.sku }} · {{ product.detail }}</small
                            ></span
                        ><StatusBadge>{{ product.kind }}</StatusBadge>
                    </button>
                    <StatePanel
                        v-if="visibleProducts.length === 0"
                        state="empty"
                        title="No matching products"
                        message="Clear the search or create a catalog item."
                    />
                </div>
                <article class="catalog-detail">
                    <div class="section-heading">
                        <div>
                            <p class="kicker">Selected product</p>
                            <h2>{{ selected }}</h2>
                            <p>Structure v6 · PKR tax reference FOOD-STD</p>
                        </div>
                        <StatusBadge :tone="archived ? 'warning' : 'success'">{{
                            archived ? "Archived" : "Active"
                        }}</StatusBadge>
                    </div>
                    <dl class="catalog-facts">
                        <div>
                            <dt>Variant</dt>
                            <dd>Jasmine · 250 g</dd>
                        </div>
                        <div>
                            <dt>SKU</dt>
                            <dd>TEA-001-J250</dd>
                        </div>
                        <div>
                            <dt>Barcode</dt>
                            <dd>0896000123405</dd>
                        </div>
                        <div>
                            <dt>Base unit</dt>
                            <dd>piece</dd>
                        </div>
                    </dl>
                    <div class="catalog-rule-grid">
                        <div>
                            <small>Pack conversion</small
                            ><strong>1 carton = 24 pieces</strong
                            ><span
                                >Purchasing converts exactly to base
                                stock.</span
                            >
                        </div>
                        <div>
                            <small>Selectable extra</small
                            ><strong>Gift wrap · +PKR 50.00</strong
                            ><span>No ingredient or preparation rule.</span>
                        </div>
                    </div>
                    <div class="catalog-actions">
                        <AppButton>{{
                            archived ? "Restore product" : "Edit details"
                        }}</AppButton
                        ><AppButton
                            variant="danger"
                            @click="archived = !archived"
                            >{{
                                archived ? "Keep archived" : "Archive product"
                            }}</AppButton
                        >
                    </div>
                </article>
            </div>
        </section>
        <section class="gallery-section" aria-labelledby="catalog-types-title">
            <div class="section-heading">
                <div>
                    <h2 id="catalog-types-title">Sellable structures</h2>
                    <p>
                        All stock effects resolve to finished-product base
                        quantities.
                    </p>
                </div>
            </div>
            <div class="catalog-type-grid">
                <article>
                    <StatusBadge tone="success">Weighted</StatusBadge>
                    <h3>Loose almonds</h3>
                    <p>
                        Scale label <b>2100123012507</b> resolves item reference
                        <b>00123</b> and quantity <b>1.250 kg</b>.
                    </p>
                </article>
                <article>
                    <StatusBadge tone="info">Fixed bundle</StatusBadge>
                    <h3>Tea gift set</h3>
                    <p>
                        2 × tea tin + 1 × mug. Recursive bundle graphs are
                        rejected before publication.
                    </p>
                </article>
                <article>
                    <StatusBadge>Extras</StatusBadge>
                    <h3>Finished-product add-ons</h3>
                    <p>
                        Options can change price and consume a configured
                        finished item; no recipe semantics exist.
                    </p>
                </article>
            </div>
        </section>
        <section class="gallery-section" aria-labelledby="catalog-states-title">
            <div class="section-heading">
                <div>
                    <h2 id="catalog-states-title">Catalog states</h2>
                    <p>Search and editing explain their current boundary.</p>
                </div>
            </div>
            <div class="state-grid">
                <StatePanel
                    state="loading"
                    title="Loading catalog page"
                    message="Fetching up to 25 authorized products."
                /><StatePanel
                    state="empty"
                    title="No products yet"
                    message="Create a unit, then the first finished product."
                /><StatePanel
                    state="error"
                    title="Structure changed"
                    message="Reload before saving this versioned edit."
                />
            </div>
        </section>
    </div>
</template>
