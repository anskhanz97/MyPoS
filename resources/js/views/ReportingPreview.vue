<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import AppButton from "../components/AppButton.vue";
import AppDialog from "../components/AppDialog.vue";
import AppDropdown from "../components/AppDropdown.vue";
import StatePanel from "../components/StatePanel.vue";
import StatusBadge from "../components/StatusBadge.vue";
type Option = {
    id: string;
    name: string;
    code?: string;
    internal_id?: number;
    classification?: string;
    archived?: boolean;
};
type Report = {
    key: string;
    title: string;
    group: string;
    type: string;
    sensitive: boolean;
};
type Column = { key: string; label: string; type: string };
type Cell = string | number | boolean | null;
type Row = Record<string, Cell>;
type Result = {
    report: string;
    title: string;
    columns: Column[];
    rows: Row[];
    summary: Row;
    definitions: string[];
    limitations: string[];
    period: { start: string; end: string; timezone: string; basis: string };
    freshness: { generated_at: string };
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    comparison?: { start: string; end: string; summary: Row };
};
type Catalog = {
    reports: Report[];
    stores: Option[];
    role: string;
    can_consolidate: boolean;
    lookups: Record<string, Option[]>;
};
type Saved = {
    public_id: string;
    name: string;
    report_key: string;
    filters: Record<string, string>;
};
type ExportJob = {
    public_id: string;
    state: string;
    total_rows: number;
    failure_message: string | null;
    artifact_path: string | null;
    download_available: boolean;
    options: { report_key: string; format: string };
};
type Variant = {
    id: number;
    public_id: string;
    sku: string;
    attributes: { size?: string };
    size?: string;
    minimum?: string;
    target?: string;
};
type Product = {
    public_id: string;
    name: string;
    variants: Variant[];
    is_miscellaneous: boolean;
    [key: string]: unknown;
};
type Setup = {
    classifications: Record<string, Option[]>;
    products: { data: Product[]; current_page: number; last_page: number };
    registers: {
        public_id: string;
        name: string;
        register_group_id: number | null;
        selected_group?: string;
    }[];
};
const merchants = ref<Option[]>([]),
    merchant = ref("");
const catalog = ref<Catalog | null>(null);
const selected = ref(
    new URL(location.href).searchParams.get("report") || "sales-summary",
);
const selectedGroup = ref(""),
    catalogueSearch = ref("");
const result = ref<Result | null>(null),
    detail = ref<Result | null>(null);
const busy = ref(true),
    detailBusy = ref(false),
    needsLogin = ref(false),
    showSetup = ref(false);
const error = ref(""),
    message = ref("");
const credentials = reactive({ email: "", password: "" });
const date = (d: Date) =>
    d.toLocaleDateString("en-CA", { timeZone: "Asia/Karachi" });
const earlier = new Date();
earlier.setDate(earlier.getDate() - 29);
const filters = reactive<Record<string, string>>({
    start: date(earlier),
    end: date(new Date()),
    store_id: "",
    register_id: "",
    employee_id: "",
    product_category_id: "",
    product_brand_id: "",
    reporting_category_id: "",
    product_reporting_type_id: "",
    search: "",
    group_by: "day",
    comparison: "previous_period",
    per_page: "50",
    sort: "",
    direction: "asc",
});
const initialParameters = new URL(location.href).searchParams;
const appliedFilters = ref<Record<string, string>>({});
const selectionChanged = computed(
    () => JSON.stringify(filters) !== JSON.stringify(appliedFilters.value),
);
for (const key of Object.keys(filters)) {
    const value = initialParameters.get(key);
    if (value !== null) filters[key] = value;
}
const period = ref("last30"),
    savedName = ref(""),
    productId = ref(""),
    setupSearch = ref("");
const saved = ref<Saved[]>([]),
    jobs = ref<ExportJob[]>([]);
const metadataFile = ref<globalThis.File | null>(null);
const metadataImport = ref<{
    public_id: string;
    state: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    rows: { row_number: number; errors: string[] | null }[];
} | null>(null);
const importCommitKey = ref("");
const detailDialog = ref<InstanceType<typeof AppDialog> | null>(null);
const setup = ref<Setup | null>(null),
    setupPage = ref(1);
const classification = reactive({
    kind: "categories",
    name: "",
    code: "",
    classification: "other",
});
const productValues = reactive<Record<string, string>>({}),
    miscellaneous = ref(false);
const setupProduct = computed(() =>
    setup.value?.products.data.find((p) => p.public_id === productId.value),
);
const dimensions = [
    {
        key: "product_category_id",
        kind: "categories",
        label: "Catalogue category",
        lookup: "product_categories",
    },
    {
        key: "reporting_category_id",
        kind: "reporting-categories",
        label: "Reporting category",
        lookup: "reporting_categories",
    },
    {
        key: "product_brand_id",
        kind: "brands",
        label: "Brand",
        lookup: "product_brands",
    },
    {
        key: "product_reporting_type_id",
        kind: "product-types",
        label: "Business product type",
        lookup: "product_reporting_types",
    },
    {
        key: "primary_supplier_id",
        kind: "suppliers",
        label: "Primary supplier",
        lookup: "suppliers",
    },
];
const base = computed(() => `/api/v1/merchants/${merchant.value}/reporting`);
const report = computed(() =>
    catalog.value?.reports.find((r) => r.key === selected.value),
);
const groups = computed(() => [
    ...new Set(catalog.value?.reports.map((r) => r.group) ?? []),
]);
const reports = computed(() =>
    (catalog.value?.reports ?? []).filter(
        (r) =>
            (!selectedGroup.value || r.group === selectedGroup.value) &&
            r.title.toLowerCase().includes(catalogueSearch.value.toLowerCase()),
    ),
);
const columns = computed(
    () =>
        result.value?.columns.filter(
            (c) =>
                ![
                    "group_key",
                    "document_id",
                    "line_id",
                    "source_id",
                    "supplier_id",
                ].includes(c.key),
        ) ?? [],
);
const metrics = computed(() =>
    Object.entries(result.value?.summary ?? {})
        .sort(([a], [b]) => {
            const priority = [
                "revenue",
                "sales_including_tax",
                "gross_profit",
                "margin_percent",
                "tax",
                "transactions",
                "amount",
                "inventory_value",
                "operating_profit",
                "rows",
            ];
            const rank = (key: string) =>
                priority.includes(key)
                    ? priority.indexOf(key)
                    : priority.length;
            return rank(a) - rank(b);
        })
        .slice(0, 6),
);
const pages = computed(() => {
    const p = result.value?.pagination;
    return p
        ? [
              ...new Set([
                  1,
                  Math.max(1, p.current_page - 1),
                  p.current_page,
                  Math.min(p.last_page, p.current_page + 1),
                  p.last_page,
              ]),
          ]
        : [];
});
const chartRows = computed(() =>
    [
        "daily-sales",
        "trends",
        "time-intervals",
        "time-period",
        "time-comparisons",
    ].includes(selected.value)
        ? (result.value?.rows.slice(0, 31) ?? [])
        : [],
);
const chartMaximum = computed(() =>
    Math.max(
        1,
        ...chartRows.value.map((r) => Math.abs(Number(r.revenue ?? 0))),
    ),
);
let poll: ReturnType<typeof setInterval> | undefined;
let requestVersion = 0;
async function api<T>(
    url: string,
    init: globalThis.RequestInit = {},
): Promise<T> {
    const token = document.cookie
        .split("; ")
        .find((c) => c.startsWith("XSRF-TOKEN="))
        ?.split("=")
        .slice(1)
        .join("=");
    const response = await fetch(url, {
        ...init,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            ...(init.body instanceof globalThis.FormData
                ? {}
                : { "Content-Type": "application/json" }),
            ...(token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {}),
            ...init.headers,
        },
    });
    if (response.status === 401) needsLogin.value = true;
    const body = await response.json();
    if (!response.ok)
        throw new Error(body.message || `Request failed (${response.status}).`);
    return body.data as T;
}
function params(extra: Record<string, string> = {}): string {
    return new URLSearchParams(
        Object.fromEntries(
            Object.entries({ ...filters, ...extra }).filter(
                ([, v]) => v !== "",
            ),
        ),
    ).toString();
}
function format(value: Cell | undefined, type = "text"): string {
    if (value === null || value === undefined) return "—";
    if (type === "percentage") return `${Number(value).toFixed(2)}%`;
    return type === "money"
        ? new Intl.NumberFormat("en-PK", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          }).format(Number(value))
        : String(value);
}
function metricType(key: string): string {
    return (
        result.value?.columns.find((c) => c.key === key)?.type ??
        (key.endsWith("_percent") || key.endsWith("_rate")
            ? "percentage"
            : "text")
    );
}
async function attempt(action: () => Promise<void>): Promise<void> {
    error.value = "";
    message.value = "";
    try {
        await action();
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : "Unable to complete this request.";
    }
}
async function boot(): Promise<void> {
    await attempt(async () => {
        merchants.value = await api<Option[]>("/api/v1/merchants");
        merchant.value =
            merchants.value.find(
                (m) => m.id === initialParameters.get("merchant"),
            )?.id ??
            merchants.value[0]?.id ??
            "";
        if (merchant.value) await changeMerchant();
    });
    busy.value = false;
}
async function login(): Promise<void> {
    busy.value = true;
    await attempt(async () => {
        await api("/api/v1/auth/login", {
            method: "POST",
            body: JSON.stringify(credentials),
        });
        credentials.password = "";
        needsLogin.value = false;
        await boot();
    });
    busy.value = false;
}
async function changeMerchant(): Promise<void> {
    result.value = null;
    catalog.value = null;
    filters.store_id = "";
    await attempt(async () => {
        const [metadata, savedFilters] = await Promise.all([
            api<Catalog>(`${base.value}/catalog`),
            api<Saved[]>(`${base.value}/filters`),
        ]);
        catalog.value = metadata;
        saved.value = savedFilters;
        if (!catalog.value.can_consolidate)
            filters.store_id = catalog.value.stores[0]?.id ?? "";
        else if (
            catalog.value.stores.some(
                (s) => s.id === initialParameters.get("store_id"),
            )
        )
            filters.store_id = initialParameters.get("store_id") ?? "";
        if (!catalog.value.reports.some((r) => r.key === selected.value))
            selected.value = catalog.value.reports[0]?.key ?? "sales-summary";
        await Promise.all([generate(), loadJobs()]);
    });
}
function setPeriod(): void {
    const end = new Date(),
        start = new Date();
    if (period.value === "today") start.setDate(end.getDate());
    else if (period.value === "last7") start.setDate(end.getDate() - 6);
    else if (period.value === "month") start.setDate(1);
    else if (period.value === "year") start.setMonth(0, 1);
    else if (period.value === "last30") start.setDate(end.getDate() - 29);
    else return;
    filters.start = date(start);
    filters.end = date(end);
}
async function generate(page = 1): Promise<void> {
    if (!merchant.value) return;
    const version = ++requestVersion;
    busy.value = true;
    error.value = "";
    try {
        const response = await api<Result>(
            `${base.value}/reports/${selected.value}?${params({ page: String(page) })}`,
        );
        if (version !== requestVersion) return;
        result.value = response;
        appliedFilters.value = { ...filters };
        const url = new URL(location.href);
        url.searchParams.set("report", selected.value);
        url.searchParams.set("merchant", merchant.value);
        for (const [key, value] of Object.entries(filters)) {
            if (value) url.searchParams.set(key, value);
            else url.searchParams.delete(key);
        }
        history.replaceState(null, "", url);
    } catch (e) {
        if (version === requestVersion)
            error.value =
                e instanceof Error ? e.message : "Unable to load report.";
    } finally {
        if (version === requestVersion) busy.value = false;
    }
}
async function choose(key: string): Promise<void> {
    selected.value = key;
    for (const name of [
        "register_id",
        "employee_id",
        "search",
        ...dimensions.map((d) => d.key),
    ])
        filters[name] = "";
    filters.sort = "";
    filters.direction = key === "top-bottom-sellers" ? "desc" : "asc";
    result.value = null;
    await generate();
}
async function sort(c: Column): Promise<void> {
    filters.direction =
        filters.sort === c.key && filters.direction === "asc" ? "desc" : "asc";
    filters.sort = c.key;
    await generate();
}
const detailGroup = ref("");
async function drill(row: Row, page = 1): Promise<void> {
    if (!report.value || !["sales", "transactions"].includes(report.value.type))
        return;
    detailGroup.value = String(row.group_key);
    detailBusy.value = true;
    detailDialog.value?.open();
    await attempt(async () => {
        detail.value = await api<Result>(
            `${base.value}/reports/${selected.value}/rows?${new URLSearchParams({ ...appliedFilters.value, group_key: detailGroup.value, page: String(page), sort: "label" })}`,
        );
    });
    detailBusy.value = false;
}
async function saveFilter(): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/filters`, {
            method: "POST",
            body: JSON.stringify({
                report_key: selected.value,
                name: savedName.value,
                filters: Object.fromEntries(
                    Object.entries(filters).filter(([, v]) => v !== ""),
                ),
            }),
        });
        saved.value = await api<Saved[]>(`${base.value}/filters`);
        savedName.value = "";
        message.value = "Report filters saved for your account.";
    });
}
async function applySaved(item: Saved): Promise<void> {
    Object.keys(filters).forEach((k) => {
        filters[k] = "";
    });
    Object.assign(filters, item.filters);
    filters.per_page ||= "50";
    filters.direction ||= "asc";
    selected.value = item.report_key;
    period.value = "custom";
    await generate();
}
async function deleteSaved(id: string): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/filters/${id}`, { method: "DELETE" });
        saved.value = saved.value.filter((s) => s.public_id !== id);
    });
}
async function exportReport(
    format: string,
    summaryOnly = false,
): Promise<void> {
    await attempt(async () => {
        if (!result.value || selectionChanged.value)
            throw new Error(
                "Generate the report with your current filters before exporting.",
            );
        await api(
            `${base.value}/reports/${selected.value}/exports?${params()}`,
            {
                method: "POST",
                body: JSON.stringify({ format, summary_only: summaryOnly }),
            },
        );
        message.value =
            "Export queued. You can continue working while it is prepared.";
        await loadJobs();
    });
}
async function loadJobs(): Promise<void> {
    if (merchant.value && catalog.value)
        jobs.value = await api<ExportJob[]>(`${base.value}/exports`);
}
async function loadSetup(page = 1): Promise<void> {
    setupPage.value = page;
    await attempt(async () => {
        const data = await api<Record<string, unknown>>(
            `${base.value}/setup?${new URLSearchParams({ store_id: filters.store_id ?? "", page: String(page), search: setupSearch.value })}`,
        );
        setup.value = {
            classifications: Object.fromEntries(
                Object.entries(data).filter(([, v]) => Array.isArray(v)),
            ) as Record<string, Option[]>,
            products: data.products as Setup["products"],
            registers: data.registers as Setup["registers"],
        };
        for (const register of setup.value.registers) {
            register.selected_group =
                setup.value.classifications["register-groups"]?.find(
                    (group) => group.internal_id === register.register_group_id,
                )?.id ?? "";
        }
        const policies = data.reorder_policies as {
            product_variant_id: number;
            minimum_quantity: string;
            target_quantity: string | null;
        }[];
        for (const product of setup.value.products.data) {
            for (const variant of product.variants) {
                variant.size = variant.attributes?.size ?? "";
                const policy = filters.store_id
                    ? policies.find(
                          (item) => item.product_variant_id === variant.id,
                      )
                    : undefined;
                variant.minimum = policy?.minimum_quantity ?? "";
                variant.target = policy?.target_quantity ?? "";
            }
        }
    });
}
function onMetadataFile(event: globalThis.Event): void {
    if (event.target instanceof window.HTMLInputElement)
        metadataFile.value = event.target.files?.[0] ?? null;
    metadataImport.value = null;
}
async function validateMetadata(): Promise<void> {
    await attempt(async () => {
        if (!filters.store_id || !metadataFile.value)
            throw new Error("Choose a store and a CSV file first.");
        const body = new globalThis.FormData();
        body.append("resource", "reporting_catalog");
        body.append("file", metadataFile.value);
        metadataImport.value = await api(
            `/api/v1/merchants/${merchant.value}/stores/${filters.store_id}/imports`,
            { method: "POST", body },
        );
        importCommitKey.value = crypto.randomUUID();
        message.value =
            "Validation finished. No product or reorder settings have changed.";
    });
}
async function commitMetadata(): Promise<void> {
    await attempt(async () => {
        if (!metadataImport.value || !filters.store_id) return;
        metadataImport.value = await api(
            `/api/v1/merchants/${merchant.value}/stores/${filters.store_id}/imports/${metadataImport.value.public_id}/commit`,
            {
                method: "POST",
                headers: { "Idempotency-Key": importCommitKey.value },
                body: "{}",
            },
        );
        await loadSetup();
        catalog.value = await api<Catalog>(`${base.value}/catalog`);
        message.value =
            "Reporting metadata imported. Historical transaction snapshots are preserved.";
    });
}
async function createClassification(): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/setup/${classification.kind}`, {
            method: "POST",
            body: JSON.stringify({
                ...classification,
                store_id: filters.store_id || null,
            }),
        });
        classification.name = "";
        classification.code = "";
        await loadSetup();
        catalog.value = await api<Catalog>(`${base.value}/catalog`);
        message.value = "Classification saved.";
    });
}
async function updateClassification(
    kind: string,
    option: Option,
): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/setup/${kind}/${option.id}`, {
            method: "PUT",
            body: JSON.stringify({
                ...option,
                store_id: filters.store_id || null,
            }),
        });
        catalog.value = await api<Catalog>(`${base.value}/catalog`);
        message.value =
            "Classification updated. Existing transaction snapshots are preserved.";
    });
}
function selectProduct(): void {
    const p = setupProduct.value;
    dimensions.forEach((d) => {
        productValues[d.key] =
            setup.value?.classifications[d.kind]?.find(
                (o) => o.internal_id === p?.[d.key],
            )?.id ?? "";
    });
    miscellaneous.value = Boolean(p?.is_miscellaneous);
    p?.variants.forEach((v) => {
        v.size = v.attributes?.size ?? "";
    });
}
async function saveProduct(): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/products/${productId.value}`, {
            method: "PUT",
            body: JSON.stringify({
                ...Object.fromEntries(
                    dimensions.map((d) => [
                        d.key,
                        productValues[d.key] || null,
                    ]),
                ),
                is_miscellaneous: miscellaneous.value,
                store_id: filters.store_id || null,
            }),
        });
        await loadSetup(setupPage.value);
        message.value =
            "Classifications saved. Historical sales remain unchanged.";
    });
}
async function saveVariant(v: Variant): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/variants/${v.public_id}`, {
            method: "PUT",
            body: JSON.stringify({
                size: v.size || null,
                ...(v.minimum
                    ? {
                          minimum_quantity: v.minimum,
                          target_quantity: v.target || null,
                      }
                    : {}),
                store_id: filters.store_id || null,
            }),
        });
        message.value = "Variant reporting settings saved.";
    });
}
async function saveRegister(r: Setup["registers"][number]): Promise<void> {
    await attempt(async () => {
        await api(`${base.value}/registers/${r.public_id}`, {
            method: "PUT",
            body: JSON.stringify({
                register_group_id: r.selected_group || null,
                store_id: filters.store_id || null,
            }),
        });
        message.value = "Register group saved.";
    });
}
onMounted(async () => {
    await boot();
    poll = setInterval(() => {
        if (jobs.value.some((j) => ["queued", "processing"].includes(j.state)))
            void loadJobs().catch(() => {});
    }, 4000);
});
onUnmounted(() => {
    if (poll) clearInterval(poll);
    requestVersion++;
});
</script>
<template>
    <section class="reports-workspace" aria-label="Reporting workspace">
        <header class="report-topbar">
            <div>
                <h2>Reports</h2>
                <p>
                    Sales, stock and daily operations. Every figure has a
                    source.
                </p>
            </div>
            <div class="top-actions">
                <label v-if="merchants.length"
                    >Merchant<AppSelect
                        v-model="merchant"
                        @change="changeMerchant"
                    >
                        <option
                            v-for="m in merchants"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ m.name }}
                        </option>
                    </AppSelect></label
                ><AppButton
                    v-if="catalog && catalog.role !== 'cashier'"
                    @click="
                        showSetup = !showSetup;
                        showSetup && loadSetup();
                    "
                    >{{
                        showSetup ? "Back to reports" : "Reporting setup"
                    }}</AppButton
                >
            </div>
        </header>
        <p v-if="error" class="report-alert error" role="alert">{{ error }}</p>
        <p v-if="message" class="report-alert success" role="status">
            {{ message }}
        </p>
        <form
            v-if="needsLogin"
            class="report-panel login-panel"
            @submit.prevent="login"
        >
            <h3>Sign in to view reports</h3>
            <p>
                Your account determines the merchants, stores and reports
                available.
            </p>
            <label
                >Email<input
                    v-model="credentials.email"
                    type="email"
                    autocomplete="username"
                    required /></label
            ><label
                >Password<input
                    v-model="credentials.password"
                    type="password"
                    autocomplete="current-password"
                    required /></label
            ><button class="report-primary" :disabled="busy">Sign in</button>
        </form>
        <p v-else-if="!merchant && !busy">
            No merchant is available for this account.
        </p>
        <template v-if="catalog">
            <template v-if="showSetup">
                <div class="report-panel">
                    <h3>Reporting setup</h3>
                    <p>
                        Classifications are captured on future transactions.
                        Historical gaps stay visible.
                    </p>
                    <label
                        >Store<AppSelect
                            v-model="filters.store_id"
                            @change="loadSetup()"
                        >
                            <option v-if="catalog.can_consolidate" value="">
                                Merchant-wide catalogue
                            </option>
                            <option
                                v-for="s in catalog.stores"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </AppSelect></label
                    >
                </div>
                <details class="report-panel">
                    <summary>
                        Bulk product classifications and reorder levels
                    </summary>
                    <p>
                        Choose a store. Export the current metadata, edit the
                        CSV, validate it, then commit only after all rows pass.
                        Blank classification codes clear that classification; a
                        blank minimum leaves its existing reorder policy
                        unchanged.
                    </p>
                    <div v-if="filters.store_id" class="toolbar-actions">
                        <a
                            :href="`${base}/setup/export.csv?store_id=${filters.store_id}`"
                            >Export current metadata CSV</a
                        ><a
                            :href="`/api/v1/merchants/${merchant}/stores/${filters.store_id}/import-templates/reporting_catalog.csv`"
                            >Download blank template</a
                        >
                    </div>
                    <label
                        >Metadata CSV<input
                            type="file"
                            accept=".csv,text/csv"
                            @change="onMetadataFile" /></label
                    ><AppButton
                        :disabled="!filters.store_id || !metadataFile"
                        @click="validateMetadata"
                        >Validate CSV</AppButton
                    >
                    <div v-if="metadataImport">
                        <p>
                            {{ metadataImport.state }} ·
                            {{ metadataImport.valid_rows }} valid ·
                            {{ metadataImport.invalid_rows }} invalid
                        </p>
                        <p
                            v-for="row in metadataImport.rows.filter(
                                (r) => r.errors?.length,
                            )"
                            :key="row.row_number"
                        >
                            Row {{ row.row_number }}:
                            {{ row.errors?.join("; ") }}
                        </p>
                        <AppButton
                            v-if="metadataImport.state === 'validated'"
                            @click="commitMetadata"
                            >Commit validated metadata</AppButton
                        >
                    </div>
                </details>
                <form
                    class="report-panel setup-grid"
                    @submit.prevent="createClassification"
                >
                    <h3 class="full-width">Add a classification</h3>
                    <label
                        >Type<AppSelect v-model="classification.kind">
                            <option value="categories">
                                Catalogue category
                            </option>
                            <option value="reporting-categories">
                                Reporting category
                            </option>
                            <option value="brands">Brand</option>
                            <option value="product-types">
                                Business product type
                            </option>
                            <option value="register-groups">
                                Register group
                            </option>
                        </AppSelect></label
                    ><label
                        >Name<input
                            v-model="classification.name"
                            required
                            maxlength="120" /></label
                    ><label
                        >Code<input
                            v-model="classification.code"
                            required
                            maxlength="60"
                            pattern="[a-zA-Z0-9_-]+" /></label
                    ><label v-if="classification.kind === 'product-types'"
                        >Wet / dry classification<AppSelect
                            v-model="classification.classification"
                        >
                            <option value="other">Other</option>
                            <option value="wet">Wet</option>
                            <option value="dry">Dry</option>
                        </AppSelect></label
                    ><button class="report-primary">Add classification</button>
                </form>
                <div v-if="setup" class="report-panel">
                    <h3>Product classifications and reorder levels</h3>
                    <div class="setup-grid">
                        <label
                            >Search products<input
                                v-model="setupSearch"
                                @keyup.enter="loadSetup()" /></label
                        ><AppButton @click="loadSetup()">Search</AppButton
                        ><label class="full-width"
                            >Product<AppSelect
                                v-model="productId"
                                @change="selectProduct"
                            >
                                <option value="">Choose a product</option>
                                <option
                                    v-for="p in setup.products.data"
                                    :key="p.public_id"
                                    :value="p.public_id"
                                >
                                    {{ p.name }}
                                </option>
                            </AppSelect></label
                        >
                    </div>
                    <div v-if="setupProduct" class="setup-grid">
                        <label v-for="d in dimensions" :key="d.key"
                            >{{ d.label
                            }}<AppSelect v-model="productValues[d.key]">
                                <option value="">Unclassified</option>
                                <option
                                    v-for="o in setup.classifications[d.kind]"
                                    :key="o.id"
                                    :value="o.id"
                                    :disabled="
                                        o.archived &&
                                        o.internal_id !== setupProduct?.[d.key]
                                    "
                                >
                                    {{ o.name
                                    }}{{ o.archived ? " (archived)" : "" }}
                                </option>
                            </AppSelect></label
                        ><label class="checkbox-label"
                            ><input v-model="miscellaneous" type="checkbox" />
                            Miscellaneous product</label
                        ><AppButton @click="saveProduct"
                            >Save product</AppButton
                        >
                        <div
                            v-for="v in setupProduct.variants"
                            :key="v.public_id"
                            class="variant-settings full-width"
                        >
                            <strong>{{ v.sku }}</strong
                            ><label
                                >Size<input
                                    v-model="v.size"
                                    maxlength="80" /></label
                            ><label
                                >Reorder minimum<input
                                    v-model="v.minimum"
                                    type="number"
                                    min="0"
                                    step="0.000001" /></label
                            ><label
                                >Target quantity<input
                                    v-model="v.target"
                                    type="number"
                                    min="0"
                                    step="0.000001" /></label
                            ><AppButton @click="saveVariant(v)"
                                >Save variant</AppButton
                            >
                        </div>
                    </div>
                    <div class="pagination">
                        <AppButton
                            :disabled="setup.products.current_page <= 1"
                            @click="loadSetup(setupPage - 1)"
                            >Previous products</AppButton
                        ><span
                            >Page {{ setup.products.current_page }} of
                            {{ setup.products.last_page }}</span
                        ><AppButton
                            :disabled="
                                setup.products.current_page >=
                                setup.products.last_page
                            "
                            @click="loadSetup(setupPage + 1)"
                            >Next products</AppButton
                        >
                    </div>
                    <details class="report-panel">
                        <summary>Edit or archive classifications</summary>
                        <template
                            v-for="kind in [
                                'categories',
                                'reporting-categories',
                                'brands',
                                'product-types',
                                'register-groups',
                            ]"
                            :key="kind"
                            ><h4>{{ kind.replaceAll("-", " ") }}</h4>
                            <div
                                v-for="option in setup.classifications[kind]"
                                :key="option.id"
                                class="variant-settings"
                            >
                                <label
                                    >Name<input v-model="option.name" /></label
                                ><label
                                    >Code<input v-model="option.code" /></label
                                ><label class="checkbox-label"
                                    ><input
                                        v-model="option.archived"
                                        type="checkbox"
                                    />
                                    Archived</label
                                ><AppButton
                                    @click="updateClassification(kind, option)"
                                    >Save classification</AppButton
                                >
                            </div></template
                        >
                    </details>
                    <h4 v-if="catalog.role === 'owner'">Register groups</h4>
                    <div
                        v-for="r in catalog.role === 'owner' && filters.store_id
                            ? setup.registers
                            : []"
                        :key="r.public_id"
                        class="variant-settings"
                    >
                        <strong>{{ r.name }}</strong
                        ><label
                            >Group<AppSelect v-model="r.selected_group">
                                <option value="">Ungrouped</option>
                                <option
                                    v-for="g in setup.classifications[
                                        'register-groups'
                                    ]"
                                    :key="g.id"
                                    :value="g.id"
                                >
                                    {{ g.name }}
                                </option>
                            </AppSelect></label
                        ><AppButton @click="saveRegister(r)"
                            >Save register group</AppButton
                        >
                    </div>
                </div>
            </template>
            <div v-else class="report-layout">
                <aside class="report-catalogue">
                    <label class="sr-only" for="report-search"
                        >Find a report</label
                    ><input
                        id="report-search"
                        v-model="catalogueSearch"
                        type="search"
                        placeholder="Find a report…"
                    /><label class="sr-only" for="report-group"
                        >Report category</label
                    ><AppSelect id="report-group" v-model="selectedGroup">
                        <option value="">All report categories</option>
                        <option v-for="g in groups" :key="g">{{ g }}</option>
                    </AppSelect>
                    <nav aria-label="Report catalogue">
                        <button
                            v-for="r in reports"
                            :key="r.key"
                            :class="{ active: selected === r.key }"
                            :aria-current="
                                selected === r.key ? 'page' : undefined
                            "
                            @click="choose(r.key)"
                        >
                            <span>{{ r.title }}</span
                            ><small>{{ r.group }}</small>
                        </button>
                    </nav>
                    <p v-if="!reports.length">No matching reports.</p>
                </aside>
                <main class="report-content">
                    <div class="report-panel report-toolbar">
                        <div>
                            <p class="report-breadcrumb">
                                Reports / {{ report?.group }}
                            </p>
                            <h3>{{ report?.title }}</h3>
                        </div>
                        <div class="toolbar-actions">
                            <AppButton :busy="busy" @click="generate()"
                                >Refresh</AppButton
                            >
                            <AppDropdown
                                v-if="catalog.role !== 'cashier'"
                                align="end"
                                aria-label="Export"
                            >
                                <template #trigger>Export</template>
                                <div role="group" aria-label="Export format">
                                    <button
                                        role="menuitem"
                                        @click="exportReport('csv')"
                                    >
                                        CSV</button
                                    ><button
                                        role="menuitem"
                                        @click="exportReport('xlsx')"
                                    >
                                        Excel (.xlsx)</button
                                    ><button
                                        role="menuitem"
                                        @click="exportReport('pdf')"
                                    >
                                        PDF with detail</button
                                    ><button
                                        role="menuitem"
                                        @click="exportReport('pdf', true)"
                                    >
                                        Summary PDF
                                    </button>
                                </div>
                            </AppDropdown>
                        </div>
                    </div>
                    <form
                        class="report-panel report-filters"
                        @submit.prevent="generate()"
                    >
                        <label
                            >Date range<AppSelect
                                v-model="period"
                                @change="setPeriod"
                            >
                                <option value="today">Today</option>
                                <option value="last7">Last 7 days</option>
                                <option value="last30">Last 30 days</option>
                                <option value="month">This month</option>
                                <option value="year">This year</option>
                                <option value="custom">Custom range</option>
                            </AppSelect></label
                        ><label
                            >From<AppDatePicker
                                v-model="filters.start"
                                required
                                @change="period = 'custom'" /></label
                        ><label
                            >To<AppDatePicker
                                v-model="filters.end"
                                :min="filters.start"
                                required
                                @change="period = 'custom'" /></label
                        ><label
                            >Store<AppSelect
                                v-model="filters.store_id"
                                @change="filters.register_id = ''"
                            >
                                <option v-if="catalog.can_consolidate" value="">
                                    All stores
                                </option>
                                <option
                                    v-for="s in catalog.stores"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </AppSelect></label
                        >
                        <label
                            v-if="
                                [
                                    'sales',
                                    'tenders',
                                    'sessions',
                                    'transactions',
                                    'extras',
                                    'allocations',
                                ].includes(report?.type ?? '')
                            "
                            >Register<AppSelect v-model="filters.register_id">
                                <option value="">All registers</option>
                                <option
                                    v-for="r in catalog.lookups.registers"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.name }}
                                </option>
                            </AppSelect></label
                        ><label
                            v-if="
                                [
                                    'sales',
                                    'tenders',
                                    'transactions',
                                    'extras',
                                    'allocations',
                                ].includes(report?.type ?? '') &&
                                catalog.role !== 'cashier'
                            "
                            >Employee<AppSelect v-model="filters.employee_id">
                                <option value="">All permitted staff</option>
                                <option
                                    v-for="e in catalog.lookups.employees"
                                    :key="e.id"
                                    :value="e.id"
                                >
                                    {{ e.name }}
                                </option>
                            </AppSelect></label
                        ><label
                            v-if="
                                [
                                    'sales',
                                    'transactions',
                                    'stock',
                                    'batch_stock',
                                    'valuation',
                                    'history',
                                    'movements',
                                    'stocktakes',
                                    'cost_entries',
                                ].includes(report?.type ?? '')
                            "
                            >Search<input
                                v-model="filters.search"
                                type="search"
                                :placeholder="
                                    ['sales', 'transactions'].includes(
                                        report?.type ?? '',
                                    )
                                        ? 'Product, SKU or receipt'
                                        : 'Product or SKU'
                                " /></label
                        ><label v-if="selected === 'time-period'"
                            >Group by<AppSelect v-model="filters.group_by">
                                <option value="day">Day</option>
                                <option value="week">Week</option>
                                <option value="month">Month</option>
                                <option value="year">Year</option>
                            </AppSelect></label
                        ><label v-if="selected === 'time-comparisons'"
                            >Compare with<AppSelect
                                v-model="filters.comparison"
                            >
                                <option value="previous_period">
                                    Previous period
                                </option>
                                <option value="previous_year">
                                    Previous year
                                </option>
                            </AppSelect></label
                        >
                        <details
                            v-if="report?.type === 'sales'"
                            class="full-width advanced-filters"
                        >
                            <summary>More filters</summary>
                            <div class="report-filters">
                                <label
                                    v-for="d in dimensions.filter(
                                        (d) => d.key !== 'primary_supplier_id',
                                    )"
                                    :key="d.key"
                                    >{{ d.label
                                    }}<AppSelect v-model="filters[d.key]">
                                        <option value="">All</option>
                                        <option
                                            v-for="o in catalog.lookups[
                                                d.lookup
                                            ]"
                                            :key="o.id"
                                            :value="o.id"
                                        >
                                            {{ o.name
                                            }}{{
                                                o.archived ? " (archived)" : ""
                                            }}
                                        </option>
                                    </AppSelect></label
                                >
                            </div>
                        </details>
                        <div class="filter-submit">
                            <button class="report-primary" :disabled="busy">
                                {{ busy ? "Generating…" : "Generate report" }}
                            </button>
                        </div>
                    </form>
                    <div v-if="result" class="report-summary">
                        <article v-for="[key, value] in metrics" :key="key">
                            <span>{{ key.replaceAll("_", " ") }}</span
                            ><strong>{{
                                format(value, metricType(key))
                            }}</strong
                            ><small v-if="metricType(key) === 'money'"
                                >PKR · filtered total</small
                            >
                        </article>
                    </div>
                    <div
                        v-if="result?.comparison"
                        class="report-panel comparison"
                    >
                        <strong
                            >Comparison: {{ result.comparison.start }} –
                            {{ result.comparison.end }}</strong
                        ><span
                            v-for="(value, key) in result.comparison.summary"
                            :key="key"
                            >{{ String(key).replaceAll("_", " ") }}:
                            {{ format(value, metricType(String(key))) }}</span
                        >
                    </div>
                    <div v-if="chartRows.length" class="report-panel">
                        <h4>Revenue excluding tax — displayed rows</h4>
                        <p>
                            Up to 31 groups on this page. Full-period totals
                            appear above.
                        </p>
                        <div
                            class="report-chart"
                            role="img"
                            aria-label="Revenue chart. Exact values are available in the report table."
                        >
                            <div
                                v-for="row in chartRows"
                                :key="String(row.group_key)"
                                :title="`${row.label}: ${format(row.revenue, 'money')}`"
                            >
                                <i
                                    :class="{
                                        negative: Number(row.revenue) < 0,
                                    }"
                                    :style="{
                                        height: `${Math.max(2, (Math.abs(Number(row.revenue)) / chartMaximum) * 100)}%`,
                                    }"
                                ></i
                                ><small>{{
                                    String(row.label).slice(-5)
                                }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="report-panel table-panel" :aria-busy="busy">
                        <div class="table-heading">
                            <h4>Report details</h4>
                            <StatusBadge v-if="result" tone="success"
                                >Live ledger</StatusBadge
                            >
                        </div>
                        <p v-if="busy" class="table-state" role="status">
                            Loading report…
                        </p>
                        <template v-else-if="result"
                            ><div
                                class="report-table-scroll"
                                tabindex="0"
                                role="region"
                                aria-label="Report table; scroll horizontally for more columns"
                            >
                                <table>
                                    <caption class="sr-only">
                                        {{
                                            result.title
                                        }}
                                        —
                                        {{
                                            result.period.start
                                        }}
                                        to
                                        {{
                                            result.period.end
                                        }}
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th
                                                v-for="c in columns"
                                                :key="c.key"
                                                :class="{
                                                    numeric: c.type !== 'text',
                                                }"
                                                :aria-sort="
                                                    filters.sort === c.key
                                                        ? filters.direction ===
                                                          'asc'
                                                            ? 'ascending'
                                                            : 'descending'
                                                        : 'none'
                                                "
                                            >
                                                <button @click="sort(c)">
                                                    {{ c.label }}
                                                    <span
                                                        v-if="
                                                            filters.sort ===
                                                            c.key
                                                        "
                                                        >{{
                                                            filters.direction ===
                                                            "asc"
                                                                ? "↑"
                                                                : "↓"
                                                        }}</span
                                                    >
                                                </button>
                                            </th>
                                            <th
                                                v-if="
                                                    [
                                                        'sales',
                                                        'transactions',
                                                    ].includes(
                                                        report?.type ?? '',
                                                    )
                                                "
                                            >
                                                Details
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, index) in result.rows"
                                            :key="`${row.group_key}-${index}`"
                                        >
                                            <td
                                                v-for="c in columns"
                                                :key="c.key"
                                                :class="{
                                                    numeric: c.type !== 'text',
                                                    muted: row[c.key] === null,
                                                }"
                                            >
                                                {{ format(row[c.key], c.type) }}
                                            </td>
                                            <td
                                                v-if="
                                                    [
                                                        'sales',
                                                        'transactions',
                                                    ].includes(
                                                        report?.type ?? '',
                                                    )
                                                "
                                            >
                                                <button
                                                    class="row-link"
                                                    @click="drill(row)"
                                                >
                                                    View lines
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="result.rows.length">
                                        <tr>
                                            <td
                                                v-for="(c, i) in columns"
                                                :key="c.key"
                                                :class="{
                                                    numeric: c.type !== 'text',
                                                }"
                                            >
                                                {{
                                                    i === 0
                                                        ? "Filtered totals"
                                                        : result.summary[
                                                                c.key
                                                            ] !== undefined
                                                          ? format(
                                                                result.summary[
                                                                    c.key
                                                                ],
                                                                c.type,
                                                            )
                                                          : ""
                                                }}
                                            </td>
                                            <td
                                                v-if="
                                                    [
                                                        'sales',
                                                        'transactions',
                                                    ].includes(
                                                        report?.type ?? '',
                                                    )
                                                "
                                            ></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <StatePanel
                                v-if="!result.rows.length"
                                state="empty"
                                :icon="
                                    report?.group === 'Stock'
                                        ? 'products'
                                        : report?.group ===
                                            'Workforce & loyalty'
                                          ? 'team'
                                          : 'document'
                                "
                                title="No records for this selection"
                                message="Try another date range or clear a filter."
                            />
                            <div class="pagination">
                                <span
                                    >{{
                                        result.pagination.total.toLocaleString()
                                    }}
                                    results · Page
                                    {{ result.pagination.current_page }} of
                                    {{ result.pagination.last_page }}</span
                                >
                                <div class="page-controls">
                                    <label
                                        >Rows<AppSelect
                                            v-model="filters.per_page"
                                            @change="generate()"
                                        >
                                            <option>25</option>
                                            <option>50</option>
                                            <option>100</option>
                                            <option>200</option>
                                        </AppSelect></label
                                    ><button
                                        :disabled="
                                            result.pagination.current_page <= 1
                                        "
                                        @click="
                                            generate(
                                                result.pagination.current_page -
                                                    1,
                                            )
                                        "
                                    >
                                        Previous</button
                                    ><button
                                        v-for="p in pages"
                                        :key="p"
                                        :class="{
                                            current:
                                                p ===
                                                result.pagination.current_page,
                                        }"
                                        :aria-current="
                                            p === result.pagination.current_page
                                                ? 'page'
                                                : undefined
                                        "
                                        @click="generate(p)"
                                    >
                                        {{ p }}</button
                                    ><button
                                        :disabled="
                                            result.pagination.current_page >=
                                            result.pagination.last_page
                                        "
                                        @click="
                                            generate(
                                                result.pagination.current_page +
                                                    1,
                                            )
                                        "
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="report-panel saved-filters">
                        <h4>My saved filters</h4>
                        <form @submit.prevent="saveFilter">
                            <label class="sr-only" for="saved-filter-name"
                                >Saved filter name</label
                            ><input
                                id="saved-filter-name"
                                v-model="savedName"
                                placeholder="Name this report selection"
                                required
                                maxlength="120"
                            /><button class="report-primary">
                                Save filters
                            </button>
                        </form>
                        <div
                            v-for="s in saved"
                            :key="s.public_id"
                            class="saved-item"
                        >
                            <button @click="applySaved(s)">{{ s.name }}</button
                            ><button
                                :aria-label="`Delete saved filter ${s.name}`"
                                @click="deleteSaved(s.public_id)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                    <div v-if="jobs.length" class="report-panel">
                        <h4>My exports</h4>
                        <div
                            v-for="job in jobs"
                            :key="job.public_id"
                            class="export-job"
                        >
                            <span
                                >{{ job.options.report_key }} ·
                                {{ job.options.format.toUpperCase() }}</span
                            ><span
                                >{{ job.state
                                }}<small v-if="job.failure_message">
                                    — {{ job.failure_message }}</small
                                ></span
                            ><a
                                v-if="job.download_available"
                                :href="`${base}/exports/${job.public_id}/download`"
                                >Download
                                {{ job.total_rows.toLocaleString() }} rows</a
                            >
                        </div>
                    </div>
                    <details v-if="result" class="report-panel definitions">
                        <summary>
                            Definitions, freshness and limitations
                        </summary>
                        <p>
                            {{ result.period.basis }} ·
                            {{ result.period.timezone }} · Generated
                            {{
                                new Date(
                                    result.freshness.generated_at,
                                ).toLocaleString()
                            }}
                        </p>
                        <p
                            v-for="definition in result.definitions"
                            :key="definition"
                        >
                            {{ definition }}
                        </p>
                        <p
                            v-for="limitation in result.limitations"
                            :key="limitation"
                        >
                            {{ limitation }}
                        </p>
                    </details>
                </main>
            </div>
        </template>
        <AppDialog ref="detailDialog"
            ><template #title>Source lines</template>
            <p v-if="detailBusy" role="status">Loading source records…</p>
            <StatePanel
                v-else-if="detail && !detail.rows.length"
                state="empty"
                icon="document"
                title="No source lines for this selection"
                message="Choose another report row or adjust the report filters."
            />
            <div v-else-if="detail" class="report-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th
                                v-for="c in detail.columns.filter(
                                    (c) =>
                                        !['group_key', 'document_id'].includes(
                                            c.key,
                                        ),
                                )"
                                :key="c.key"
                            >
                                {{ c.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in detail.rows" :key="i">
                            <td
                                v-for="c in detail.columns.filter(
                                    (c) =>
                                        !['group_key', 'document_id'].includes(
                                            c.key,
                                        ),
                                )"
                                :key="c.key"
                            >
                                {{ format(row[c.key], c.type) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    {{ detail.pagination.total }} source rows · Page
                    {{ detail.pagination.current_page }} of
                    {{ detail.pagination.last_page }}
                </p>
                <AppButton
                    :disabled="
                        detailBusy || detail.pagination.current_page <= 1
                    "
                    @click="
                        drill(
                            { group_key: detailGroup },
                            detail.pagination.current_page - 1,
                        )
                    "
                    >Previous source rows</AppButton
                >
                <AppButton
                    :disabled="
                        detailBusy ||
                        detail.pagination.current_page >=
                            detail.pagination.last_page
                    "
                    @click="
                        drill(
                            { group_key: detailGroup },
                            detail.pagination.current_page + 1,
                        )
                    "
                    >Next source rows</AppButton
                >
            </div></AppDialog
        >
    </section>
</template>
<style scoped>
.reports-workspace {
    display: grid;
    gap: 1.2rem;
    min-width: 0;
    color: var(--text);
}
.report-topbar,
.report-toolbar,
.toolbar-actions,
.top-actions,
.table-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.report-topbar h2 {
    font-size: 1.65rem;
    margin: 0 0 0.25rem;
    letter-spacing: -0.035em;
}
.report-topbar p,
.report-panel > p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0.3rem 0;
}
.report-layout {
    display: grid;
    grid-template-columns: 225px minmax(0, 1fr);
    gap: 1.2rem;
    align-items: start;
}
.report-catalogue {
    position: sticky;
    top: 1rem;
    display: grid;
    gap: 0.65rem;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.65rem;
}
.report-catalogue nav {
    max-height: 72vh;
    overflow: auto;
    display: grid;
    gap: 0.2rem;
}
.report-catalogue nav button {
    border: 0;
    background: transparent;
    color: var(--text);
    text-align: left;
    padding: 0.65rem 0.7rem;
    border-radius: 0.35rem;
    cursor: pointer;
    display: grid;
    gap: 0.2rem;
    font-size: 0.8rem;
}
.report-catalogue nav button small {
    font-size: 0.66rem;
    color: var(--text-muted);
}
.report-catalogue nav button:hover {
    background: var(--surface-muted);
}
.report-catalogue nav button.active {
    background: color-mix(in srgb, var(--accent) 12%, var(--surface));
    box-shadow: inset 3px 0 var(--accent);
    color: var(--accent);
}
.report-content {
    display: grid;
    gap: 1rem;
    min-width: 0;
}
.report-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.65rem;
    padding: 1.1rem;
}
.report-toolbar h3 {
    margin: 0;
    font-size: 1.2rem;
    letter-spacing: -0.025em;
}
.report-breadcrumb {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 0 0 0.3rem;
}
.report-filters,
.setup-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
    align-items: end;
}
label {
    display: grid;
    gap: 0.4rem;
    font-size: 0.73rem;
    font-weight: 600;
    color: var(--text-muted);
}
input,
select {
    width: 100%;
    min-width: 0;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    border-radius: 0.3rem;
    padding: 0.6rem 0.65rem;
    font: inherit;
    font-size: 0.8rem;
    min-height: 38px;
}
input:focus-visible,
select:focus-visible,
button:focus-visible,
summary:focus-visible,
a:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
}
.full-width {
    grid-column: 1/-1;
}
.filter-submit {
    display: flex;
    justify-content: flex-end;
    grid-column: 1/-1;
}
.report-primary {
    background: var(--accent);
    color: var(--accent-contrast, #fff);
    border: 1px solid var(--accent);
    border-radius: 0.3rem;
    padding: 0.62rem 1rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
}
.report-primary:disabled {
    opacity: 0.6;
    cursor: wait;
}
.advanced-filters summary,
.definitions summary {
    font-size: 0.8rem;
    cursor: pointer;
    color: var(--text-muted);
}
.advanced-filters > div {
    padding-top: 0.85rem;
}
.report-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: 0.75rem;
}
.report-summary article {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 1rem;
    border-radius: 0.6rem;
    display: grid;
    gap: 0.45rem;
    border-top: 3px solid color-mix(in srgb, var(--accent) 45%, var(--border));
}
.report-summary span {
    text-transform: capitalize;
    font-size: 0.73rem;
    color: var(--text-muted);
}
.report-summary strong {
    font-size: 1.35rem;
    letter-spacing: -0.04em;
    font-variant-numeric: tabular-nums;
}
.report-summary small {
    font-size: 0.64rem;
    color: var(--text-muted);
}
.table-panel {
    padding: 0;
    overflow: hidden;
}
.table-heading {
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--border);
}
h4 {
    font-size: 0.85rem;
    margin: 0 0 0.6rem;
}
.table-heading h4 {
    margin: 0;
}
.report-table-scroll {
    overflow: auto;
    max-width: 100%;
    max-height: 620px;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
    white-space: nowrap;
}
th {
    background: var(--surface-muted);
    color: var(--text-muted);
    font-weight: 600;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 1;
}
td,
th {
    padding: 0.8rem 0.9rem;
    border-bottom: 1px solid var(--border);
}
th button {
    font: inherit;
    color: inherit;
    border: 0;
    background: none;
    padding: 0;
    cursor: pointer;
    text-align: inherit;
}
tbody tr:hover {
    background: color-mix(in srgb, var(--accent) 4%, var(--surface));
}
.numeric {
    text-align: right;
    font-variant-numeric: tabular-nums;
}
tfoot {
    background: var(--surface-muted);
    font-weight: 700;
}
.muted {
    color: var(--text-muted);
}
.row-link {
    border: 0;
    background: none;
    color: var(--accent);
    cursor: pointer;
    font: inherit;
}
.table-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}
.table-state p {
    margin: 0.4rem 0;
    font-size: 0.8rem;
}
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    font-size: 0.74rem;
    color: var(--text-muted);
}
.page-controls {
    display: flex;
    align-items: center;
    gap: 0.2rem;
}
.page-controls label {
    display: flex;
    align-items: center;
    margin-right: 0.5rem;
}
.page-controls select {
    width: 70px;
    min-height: 32px;
    padding: 0.3rem;
}
.page-controls button {
    min-width: 30px;
    border: 1px solid var(--border);
    border-radius: 0.25rem;
    background: var(--surface);
    color: var(--text);
    padding: 0.4rem 0.55rem;
    cursor: pointer;
}
.page-controls button.current {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}
.page-controls button:disabled {
    opacity: 0.4;
    cursor: default;
}
.saved-filters form {
    display: flex;
    gap: 0.5rem;
    max-width: 500px;
}
.saved-item {
    display: inline-flex;
    gap: 0.7rem;
    align-items: center;
    margin: 0.65rem 0.65rem 0 0;
    padding: 0.4rem 0.6rem;
    background: var(--surface-muted);
    border: 1px solid var(--border);
    border-radius: 0.3rem;
}
.saved-item button {
    border: 0;
    background: none;
    color: var(--accent);
    font-size: 0.72rem;
    cursor: pointer;
}
.saved-item button:last-child {
    color: var(--text-muted);
}
.export-job {
    display: flex;
    justify-content: space-between;
    gap: 0.7rem;
    border-bottom: 1px solid var(--border);
    padding: 0.7rem 0;
    font-size: 0.75rem;
}
.export-job a {
    color: var(--accent);
}
.definitions p {
    font-size: 0.76rem;
    color: var(--text-muted);
    line-height: 1.6;
}
.report-alert {
    padding: 0.85rem 1rem;
    border-radius: 0.4rem;
    font-size: 0.82rem;
    border: 1px solid var(--border);
}
.report-alert.error {
    background: color-mix(in srgb, var(--danger, #b72f36) 8%, var(--surface));
    color: var(--danger, #b72f36);
}
.report-alert.success {
    background: color-mix(in srgb, var(--accent) 8%, var(--surface));
    color: var(--accent);
}
.login-panel {
    display: grid;
    gap: 1rem;
    max-width: 440px;
}
.login-panel h3 {
    margin: 0;
}
.comparison {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.76rem;
}
.report-chart {
    height: 170px;
    display: flex;
    gap: 0.4rem;
    align-items: flex-end;
    padding-bottom: 22px;
}
.report-chart > div {
    height: 100%;
    flex: 1;
    min-width: 7px;
    display: flex;
    justify-content: flex-end;
    flex-direction: column;
    position: relative;
}
.report-chart i {
    display: block;
    background: var(--accent);
    border-radius: 3px 3px 0 0;
    opacity: 0.8;
}
.report-chart i.negative {
    background: var(--danger, #b72f36);
}
.report-chart small {
    position: absolute;
    bottom: -20px;
    font-size: 0.6rem;
    color: var(--text-muted);
    white-space: nowrap;
}
.variant-settings {
    display: flex;
    gap: 0.8rem;
    align-items: end;
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}
.variant-settings strong {
    font-size: 0.8rem;
    margin-bottom: 0.7rem;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.checkbox-label input {
    width: 16px;
    min-height: 16px;
}
.report-panel h3 {
    font-size: 1rem;
}
:deep(.app-dialog) {
    max-width: min(1200px, 95vw);
    width: 95vw;
}
:deep(.app-dialog__body) {
    min-width: 0;
}
@media (max-width: 1200px) {
    .report-layout {
        grid-template-columns: 190px minmax(0, 1fr);
    }
    .report-filters,
    .setup-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pagination {
        align-items: flex-start;
        flex-direction: column;
    }
    .top-actions {
        flex-wrap: wrap;
    }
}
@media (max-width: 760px) {
    .report-layout {
        grid-template-columns: 1fr;
    }
    .report-catalogue {
        position: static;
        grid-template-columns: 1fr 1fr;
        padding: 0.75rem;
    }
    .report-catalogue nav {
        grid-column: 1/-1;
        display: flex;
        max-height: 110px;
        overflow: auto;
    }
    .report-catalogue nav button {
        min-width: 150px;
    }
    .report-topbar {
        align-items: flex-start;
        flex-direction: column;
    }
    .report-filters,
    .setup-grid {
        grid-template-columns: 1fr 1fr;
    }
    .report-panel {
        padding: 0.85rem;
    }
    .table-panel {
        padding: 0;
    }
    .report-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .report-summary strong {
        font-size: 1.15rem;
    }
    .page-controls {
        flex-wrap: wrap;
    }
    .report-toolbar {
        align-items: flex-start;
    }
    .toolbar-actions {
        gap: 0.4rem;
    }
    .saved-filters form {
        flex-wrap: wrap;
    }
    .export-job,
    .variant-settings {
        flex-wrap: wrap;
    }
    .report-chart small {
        display: none;
    }
}
@media print {
    .report-catalogue,
    .report-filters,
    .toolbar-actions,
    .top-actions,
    .saved-filters,
    .pagination {
        display: none;
    }
    .report-layout {
        display: block;
    }
    .report-panel {
        background: white;
        color: black;
        border-color: #ccc;
    }
    .report-table-scroll {
        max-height: none;
        overflow: visible;
    }
    th {
        position: static;
    }
}
</style>
