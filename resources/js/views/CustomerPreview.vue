<script setup lang="ts">
import { computed, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";

type Customer = {
    name: string;
    reference: string;
    phone: string;
    email: string;
    group: string;
    store: string;
};

const customers: Customer[] = [
    {
        name: "Ayesha Khan",
        reference: "CUS-1048",
        phone: "+92 300 1234567",
        email: "ayesha@example.com",
        group: "VIP",
        store: "Merchant-wide",
    },
    {
        name: "Bilal Traders",
        reference: "CUS-1082",
        phone: "+92 42 35781234",
        email: "accounts@bilal.example",
        group: "Wholesale",
        store: "Merchant-wide",
    },
    {
        name: "Hina Raza",
        reference: "CUS-1091",
        phone: "+92 301 7654321",
        email: "hina@example.com",
        group: "Standard",
        store: "Merchant-wide",
    },
];

const query = ref("");
const selected = ref<Customer>(customers[0]!);
const showForm = ref(false);
const attempted = ref(false);
const draftName = ref("");
const archived = ref(false);
const receiptEmail = ref(true);
const marketing = ref(false);
const savedMessage = ref("");
const viewState = ref<"populated" | "empty" | "loading" | "error">("populated");

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();
    const digits = term.replace(/\D/g, "");
    return customers.filter((customer) => {
        const textMatch = [
            customer.name,
            customer.reference,
            customer.phone,
            customer.email,
        ]
            .join(" ")
            .toLowerCase()
            .includes(term);
        const storedDigits = customer.phone.replace(/\D/g, "");
        const localDigits = storedDigits.startsWith("92")
            ? `0${storedDigits.slice(2)}`
            : storedDigits;
        return (
            textMatch || (digits.length >= 7 && localDigits.includes(digits))
        );
    });
});

function beginCreate(): void {
    showForm.value = true;
    attempted.value = false;
    draftName.value = "";
    savedMessage.value = "";
}

function saveCustomer(): void {
    attempted.value = true;
    if (!draftName.value.trim()) return;
    savedMessage.value = `${draftName.value.trim()} is ready for duplicate review.`;
}
</script>

<template>
    <div class="customer-preview">
        <header class="page-heading customer-heading">
            <div>
                <p class="kicker">Customer records · shared ownership</p>
                <h1>Recognize the customer. Keep the sale optional.</h1>
                <p>
                    Search normalized contact details, review likely duplicates,
                    and keep receipt choices separate from marketing consent.
                </p>
            </div>
            <StatusBadge tone="success">3 active profiles</StatusBadge>
        </header>

        <section
            class="gallery-section"
            aria-labelledby="customer-directory-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="customer-directory-title">Customer directory</h2>
                    <p>
                        Anonymous checkout remains available without selecting a
                        profile.
                    </p>
                </div>
                <AppButton variant="primary" @click="beginCreate"
                    >New customer</AppButton
                >
            </div>

            <div class="customer-toolbar">
                <label class="field customer-search">
                    <span>Search name, phone, email or reference</span>
                    <input
                        v-model="query"
                        autocomplete="off"
                        placeholder="Try 0300 1234567"
                    />
                </label>
                <label class="field">
                    <span>Preview state</span>
                    <AppSelect v-model="viewState">
                        <option value="populated">Populated</option>
                        <option value="empty">Empty</option>
                        <option value="loading">Loading</option>
                        <option value="error">Error</option>
                    </AppSelect>
                </label>
                <AppButton variant="quiet" @click="query = ''"
                    >Clear search</AppButton
                >
            </div>

            <StatePanel
                v-if="viewState === 'loading'"
                state="loading"
                title="Loading customers"
                message="The current store scope is being prepared."
            />
            <StatePanel
                v-else-if="viewState === 'error'"
                state="error"
                title="Customer list could not load"
                message="Check the connection and retry without losing the sale."
            />
            <StatePanel
                v-else-if="viewState === 'empty' || filtered.length === 0"
                state="empty"
                title="No matching customers"
                message="Continue anonymously or create a profile after confirming the details."
            />
            <div v-else class="customer-workspace">
                <div class="customer-list" aria-label="Customer results">
                    <button
                        v-for="customer in filtered"
                        :key="customer.reference"
                        type="button"
                        :aria-pressed="
                            selected.reference === customer.reference
                        "
                        @click="
                            selected = customer;
                            archived = false;
                        "
                    >
                        <span
                            ><strong>{{ customer.name }}</strong
                            ><small>{{ customer.phone }}</small></span
                        >
                        <span
                            ><small>{{ customer.reference }}</small
                            ><StatusBadge>{{
                                customer.group
                            }}</StatusBadge></span
                        >
                    </button>
                </div>

                <article class="customer-detail" aria-live="polite">
                    <div class="customer-detail__heading">
                        <div>
                            <small
                                >{{ selected.reference }} ·
                                {{ selected.store }}</small
                            >
                            <h2>{{ selected.name }}</h2>
                        </div>
                        <StatusBadge :tone="archived ? 'warning' : 'success'">
                            {{ archived ? "Archived" : "Active" }}
                        </StatusBadge>
                    </div>
                    <dl class="customer-facts">
                        <div>
                            <dt>Normalized phone</dt>
                            <dd>{{ selected.phone }}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd>{{ selected.email }}</dd>
                        </div>
                        <div>
                            <dt>Customer group</dt>
                            <dd>{{ selected.group }}</dd>
                        </div>
                        <div>
                            <dt>Loyalty identity</dt>
                            <dd>Linked by matching phone</dd>
                        </div>
                    </dl>
                    <fieldset class="preference-panel">
                        <legend>Communication choices</legend>
                        <label
                            ><input v-model="receiptEmail" type="checkbox" />
                            Email receipts when requested</label
                        >
                        <label
                            ><input v-model="marketing" type="checkbox" />
                            Marketing consent recorded</label
                        >
                        <small>These choices are stored independently.</small>
                    </fieldset>
                    <div class="duplicate-note">
                        <strong>Duplicate review</strong>
                        <span
                            >No other profile in this customer scope has the
                            same phone or email.</span
                        >
                    </div>
                    <div class="customer-actions">
                        <AppButton>Use for sale</AppButton>
                        <AppButton
                            variant="danger"
                            @click="archived = !archived"
                        >
                            {{
                                archived ? "Restore profile" : "Archive profile"
                            }}
                        </AppButton>
                    </div>
                </article>
            </div>
        </section>

        <section
            v-if="showForm"
            class="gallery-section"
            aria-labelledby="new-customer-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="new-customer-title">Quick customer capture</h2>
                    <p>
                        Cashiers see only the contact fields needed during
                        checkout.
                    </p>
                </div>
            </div>
            <div class="customer-form">
                <label
                    class="field"
                    :class="{ 'field--error': attempted && !draftName.trim() }"
                >
                    <span>Customer name (required)</span>
                    <input
                        v-model="draftName"
                        autocomplete="name"
                        :aria-invalid="attempted && !draftName.trim()"
                    />
                    <small>{{
                        attempted && !draftName.trim()
                            ? "Enter a customer name."
                            : "Required for a saved profile."
                    }}</small>
                </label>
                <label class="field"
                    ><span>Pakistan phone</span
                    ><input value="0300 1234567" inputmode="tel" /><small
                        >Saved in +92 normalized form.</small
                    ></label
                >
                <label class="field"
                    ><span>Email</span
                    ><input
                        value="customer@example.com"
                        inputmode="email"
                    /><small
                        >Optional unless an email preference is enabled.</small
                    ></label
                >
                <AppButton variant="primary" @click="saveCustomer"
                    >Review duplicate</AppButton
                >
            </div>
            <p v-if="savedMessage" class="context-announcement" role="status">
                {{ savedMessage }}
            </p>
        </section>
    </div>
</template>
