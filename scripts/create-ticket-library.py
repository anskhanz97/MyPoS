"""One-time authoring helper. Markdown files are the maintained source of truth.
Refuses to overwrite tickets; do not rerun to reset future completion evidence.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT / 'docs'
TICKETS = DOCS / 'tickets'
items = []

def ticket(slug, title, deps, decisions, objective, records, work, interface, checks, sources='', gate=''):
    items.append(dict(slug=slug, title=title, deps=deps, decisions=decisions,
                      objective=objective, records=records, work=work,
                      interface=interface, checks=checks, sources=sources, gate=gate))

ticket('source-audit', 'Inventory and audit the five reference projects', [], 'Q004, Q005; D001–D003',
'''Create a reproducible evidence-backed map of relevant XEPOS functionality without editing or operating the source applications. Distinguish working rules from models, placeholder screens, ingestion handlers and historical artifacts. This is a research ticket, not application implementation.''',
'''Produce source-baseline.md, source-module-map.md, source-schema-map.md and source-capability-register.md under docs/research. Capture Git roots/revisions where available; otherwise use a dated file-hash manifest excluding secrets and generated dependencies. Record why the XEPOS root HEAD does not resolve rather than inventing a commit.''',
'''Enumerate all five projects' application routes, screens, middleware, requests, controllers, models, migrations, observers, services, jobs, commands and tests. Skip vendor/node_modules/build artifacts and credentials. Trace relevant screen → request → domain logic → persistence → side effects.
Classify every discovered capability as implemented, partial, placeholder, ingestion-only, excluded or unverified. Start with known payroll/rota/leave placeholders and the public member join flow. An excluded source feature gets a disposition, not a target specification.
Reconcile migration histories, model assumptions, tenant/store identifiers and money/quantity casts. Record source-to-target reuse/adapt/replace/retire recommendations. Inspect test isolation before any runtime check; do not launch code using its existing environment or integration credentials.
Trace purchase receiving, stock movement, invoice writing, refund handling, loyalty and reporting where present. Missing C# rules are not an investigation task. Flag source behaviour that needs a newly designed web counterpart.''',
'''No application endpoint changes. Evidence entries include path/line or symbol, claim, confidence, scope decision and owning ticket. Separate source contracts from proposed target contracts. Review route aliases and duplicate concepts across projects without assuming similar names imply identical semantics.''',
'''Every application route group and relevant model family has an entry or an explicit exclusion. Payroll/rota/leave placeholders are not called complete. No secrets are included. Source fingerprints before/after remain unchanged. Identify unresolved schema/runtime facts rather than claiming a working migration from inspection alone.''',
'C:\\XEPOS\\api-retail-project; portal-retail-xepos; xepos-hq-hospitality; api-loyalty-xepos; loyalty-portal')

ticket('technical-and-product-research', 'Research POS practices and recommend the technical stack', [1], 'Q001–Q008, Q024; D003, D005',
'''Produce a current, primary-source-backed implementation recommendation for the agreed counter POS. Compare concrete workflows and failure behaviour; do not import excluded restaurant, delivery or recipe functionality.''',
'''Create docs/research/product-comparison.md, stack-versions.md, hardware-feasibility.md, offline-feasibility.md and architecture-recommendation.md. Record access dates and exact framework/provider documentation versions. Recommendations and verified facts must be separately labelled.''',
'''Study relevant Odoo, ERPNext, Lightspeed, Square, Shopify POS and sufficiently documented Pakistan providers. Separate browser tills from native clients/cloud back offices. Compare scanning, purchasing, batches, promotions, returns, offline limits, workforce, loyalty and reporting; record country/plan/hardware restrictions.
Verify supported Laravel/PHP, Vue/TypeScript/Vite/Node and MySQL versions and compatible authentication, queue, testing, PDF and decimal libraries. Recommend a pinned set and avoid unneeded packages. Evaluate modular monolith boundaries, same-origin sessions and API compatibility.
Compare printing-helper approaches, offline authorization, licensing, installation, updates and supported Windows devices. Identify a qualification list for thermal printer/drawer/scanner; do not purchase hardware. Assess browser storage durability, restart, multi-tab coordination, update safety and encryption limits.
Propose measurable workload profiles, initial performance targets, offline lease/cache bounds and hosting capacity/cost assumptions. Estimates must name assumptions; no fabricated load results. Include accessibility and both-theme testing strategy.''',
'''No production integration or new app APIs. Deliver decision-ready technical options with a recommended default, tradeoffs and dependencies for T004. Exact hardware/payment/fiscal support cannot be inferred from another country's vendor docs.''',
'''Each important recommendation has a supporting official source or is explicitly an engineering judgment. Dependency support dates are checked. Offline cash and printing feasibility has a test plan. No research claim implies successful hardware or provider testing. Excluded modules stay excluded.''',
'Start from docs/06-research-evidence-and-open-questions.md.')

ticket('pakistan-fiscal-research', 'Define Pakistan fiscal applicability and launch gates', [1,2], 'Q003, Q008, Q024, Q030, Q040',
'''Determine the fiscal requirements relevant to the target merchant activities and locations, using current official sources. Produce implementable, jurisdiction-specific contracts where evidence permits, and explicit external verification gates where it does not.''',
'''Create docs/research/pakistan-fiscal-applicability.md, fiscal-api-contracts.md and fiscal-launch-gates.md. Record authority, jurisdiction, activity, effective date, legal basis, required documents, credentials, sandbox/production access and evidence status. No real credentials belong in these files.''',
'''Distinguish FBR digital invoicing/POS rules and applicable provincial/territorial requirements. Do not assume a universal rate, uniform invoice format or one integration for every merchant. Excluded table/delivery functionality must not reappear through compliance research.
Investigate invoice numbering, tax identification/product classifications, prices inclusive/exclusive of tax, returns/corrections, tax-exempt cases, receipt/QR obligations, transmission timing and retained evidence. Determine whether and how cash selling during outages can operate for each applicable regime.
Research uncertain outcomes, idempotency or reference lookup, rejected records, unavailable service, backdated corrections and credential renewal. Separate a local sale receipt from fiscal acceptance; document what the user may truthfully display in each state.
Consider statutory requirements affecting refund policies and payroll inputs without expanding operational payroll into a statutory engine. Identify merchant facts required during onboarding. Where facts/API access are unavailable, state the exact gating question rather than invent a legal rule.''',
'''Specify a FiscalAdapter contract and jurisdiction configuration schema proposal for T004/T035. Tax calculations are versioned, reproducible and linked to applicability. No automated merchant card acquisition is required. Publication/deployment compliance is not established by this research ticket.''',
'''Every claimed obligation links to an official dated source. A supported-merchant applicability checklist exists. Offline requirements have no unexamined conflict with fiscal requirements. Sandbox plans and production gates are separate. Unverified legal/provider matters cannot be marked complete merely because an adapter interface exists.''',
'Official FBR and relevant revenue-authority documentation; SBP only where payment context is relevant.',
'If external clarification is required, finish all available research and record a named launch blocker; do not contact third parties without authorization.')

ticket('freeze-domain-contracts', 'Freeze schema, domain contracts and acceptance fixtures', [1,2,3], 'All confirmed Q&A; engineering defaults in docs/03',
'''Convert the approved product scope and research into the shared implementation contracts used by all later tickets. This is the last design-only foundation ticket; no blank or speculative contract should masquerade as decision-complete.''',
'''Create docs/contracts/domain-model.md, permission-matrix.md, api.yaml, event-catalog.md, state-machines.md, arithmetic-policy.md and golden-scenarios.md. Add architectural decision records with rationale and downstream impact. Choose final numeric scales and limits with examples.''',
'''Resolve merchant/store scope, independent/shared catalog/customer ownership and separately merchant-wide loyalty links. Define stable IDs, idempotency keys/fingerprints, statuses, audit/reversal rules and source-document references.
Freeze exact price/tax/discount/rounding and weighted-quantity rules, sale/tender/refund equations and snapshot requirements. Set moving-average baseline and identify detailed valuation cases owned by T018. Specify business date versus timestamp and report period boundaries.
Define quote/finalize/return/exchange, stock movement/receive/transfer, loyalty and offline submission commands with minimal public payloads and error semantics. Define print/fiscal/email outbox contracts. Every command declares authorization and transaction boundary.
Create golden fixtures executable in PHP and TypeScript for arithmetic; map cross-module S01–S28 scenarios to tickets. Fix deployment/workload assumptions and distinguish external gates from code-ready requirements. Resolve routine choices through evidence, not repeated product questions.''',
'''OpenAPI defines common pagination, money-as-string representation, validation/conflict/retryable errors, request IDs and versioning. Public membership DTO is explicitly limited. Freeze module interfaces, not unrestricted CRUD endpoints for every table. Version changes after this ticket require documented migration/compatibility effects.''',
'''All launch modules have entities, commands, states, permissions and acceptance scenarios. Both store modes and both themes are represented. No unsupported legal constants. Fixtures conserve totals and quantities. No critical implementation choice remains ambiguous except explicitly gated provider/hardware facts.''')

ticket('application-foundation', 'Create Laravel/Vue/MySQL application and test foundation', [4], 'Q001, Q004, Q006; D004',
'''Bootstrap the new application in C:\\MyPoS without copying source secrets or modifying XEPOS. Establish a runnable, documented local baseline and CI checks using the frozen stack.''',
'''Create domain-organized application directories, environment examples without secrets, migrations, lockfiles, local development instructions and CI configuration. Add a disposable MySQL test database configuration, queue/scheduler entrypoints and generated API types where the chosen tooling supports them.''',
'''Inspect current files before creating anything; preserve documentation. Install only the selected versions. Set same-origin development routing and authenticated/public API boundaries. Configure serialization/decimal utilities, error responses, request IDs and health endpoints.
Set up unit, MySQL integration and browser test runners. Use factories with clearly synthetic merchants, stores and staff. Configure static analysis/lint/type checks, backend formatting and frontend build. Do not generate demo data that could be mistaken for real customers.
Add migration workflow, worker/scheduler local commands and environment validation. No production endpoints or credentials may be inherited from XEPOS. Start with minimal modules and shared infrastructure rather than placeholder screens for every future feature.''',
'''Expose only health/bootstrap infrastructure required at this stage. Follow T004 error/API conventions. A passing local skeleton is not an implemented POS. Record runtime versions and reproducible start/test commands.''',
'''Clean dependency install and frontend production build succeed. App boots, migrations run on disposable MySQL and test setup is isolated. A simple API integration test and browser shell smoke test pass. Missing environment variables yield clear errors. Existing docs remain intact.''')

ticket('frontend-shell-and-themes', 'Build the UI foundation with complete light and dark themes', [5], 'Q006, Q030; D005',
'''Create the shared Vue design system and shells that every feature uses. Both bright/light and dark modes are first-class and easily toggled before business screens are added.''',
'''Create semantic theme tokens, preference handling, shared components, typography/layout standards, component gallery, management/checkout/public shells and screenshot baselines. Document the chosen visual direction and how to extend tokens.''',
'''Apply frontend-design skill with operational clarity. Implement all semantic surfaces, text, borders, focus, inputs, status and chart colors. Avoid hard-coded component-specific light-only colors. Choose readable licensed typography with offline fallback.
Provide accessible visible theme toggles, persistence and pre-paint restoration. Switching preserves cart/form state and focus. Define staff preference and offline register fallback; public view preference is separate. Keep print/PDF styles white-background and readable.
Build buttons, forms, quantity/money fields, dialogs, approval prompts, tables, filters, tabs, badges, toasts and loading/empty/error states. Show store/register context and reserved areas for connectivity/pending work. Navigation grows as tickets implement real modules.
Qualify checkout layout on Windows till sizes and responsive management/member screens. Keyboard navigation, reduced motion, touch targets and contrast are acceptance requirements, not optional polish.''',
'''Provide a typed theme preference API/store and token conventions. No business setting changes when toggling appearance. Do not add restaurant navigation or unavailable feature teasers. Component tests can use fixtures but user-facing operational screens must be truthful.''',
'''Light/dark screenshots cover component states and shells. Contrast/focus checks pass; keyboard toggle works. Reload restores choice without flash. Toggling a populated test form retains content. Narrow layout has no trapped controls. Print preview remains black-on-white. Record actual visual evidence.''')

ticket('merchant-and-store-tenancy', 'Implement merchants, stores and locked data-ownership modes', [5,6], 'Q001, Q025, Q027, Q041',
'''Implement the tenant foundation, owner onboarding and store ownership rules used by every later module. Shared/independent choice is made during setup and cannot be casually switched after configuration.''',
'''Persist merchants, stores, selected operational data mode, merchant memberships and settings ownership. Introduce reusable scope/authorization helpers, composite ownership constraints and tenant-aware job/cache/storage conventions.''',
'''Build merchant setup and store management with clear explanation of shared products/customers versus independent store records. Present a review step before locking the selection. Block unsupported later mode mutation in the API as well as UI.
Define owner/store membership and central reporting context. Shared mode still has per-store stock and price overrides. Independent mode preserves distinct operational IDs even when SKU/name/phone match.
Reserve merchant-wide loyalty identity and explicit links to local customers; do not deduplicate all customer records across stores as a shortcut. Ensure background jobs and object paths carry tenant context; no fallback to an arbitrary first merchant.''',
'''Merchant/store administration APIs follow T004; authorized context is server-derived. Store switching clears incompatible transient UI state and cannot mix caches. Cross-tenant object IDs always fail authorization. No live store-mode conversion endpoint.''',
'''Create two merchants with both modes and colliding local identifiers. Test API/list/cache/job scoping and invalid related-record IDs. Attempt locked mode change and reject it. Validate store switch and both themes. Data model permits shared loyalty without shared operational customer history.''')

ticket('authentication-and-fixed-roles', 'Implement staff authentication, fixed roles and approvals', [7], 'Q020, Q037, Q040',
'''Provide owner, manager and cashier access with registered-till PIN switching and auditable manager approvals. Keep platform operator access distinct; do not build custom role design.''',
'''Persist user/staff identity links, merchant/store access, fixed role assignments, device-bound staff sessions, PIN hashes, approval records and security audit events. Employment records later created in T029 can exist without login access.''',
'''Implement secure login/logout/recovery, CSRF/session controls and rate limits using researched stack practices. PIN switching is allowed only on an authorized device and for permitted staff. Do not treat a short PIN as a public internet password.
Enforce the frozen permission matrix server-side and mirror it in navigation. Restrict payroll/salary configuration and approval to owners by default; managers can manage authorized attendance/leave/operating functions. Cashiers cannot browse administrative financial data.
Create reusable approval UX requiring the correct manager identity and a reason where applicable. Re-authentication/approval must not lose the cart. Audit failed and successful sensitive actions without logging secrets. Document offline authorization snapshot contract for T023.''',
'''Auth endpoints never reveal secret hashes. Approval tokens are short-lived, action/scope-bound and single-use where needed, not blanket admin sessions. User deletion/deactivation preserves financial attribution. Owner/admin enhanced authentication is researched/implemented according to T004, not implied by a role name.''',
'''Test every role against sensitive API operations, direct URLs and other-store IDs. Verify PIN attempt limits, session revocation and approval replay rejection. Payroll amounts stay hidden from unauthorized users. Staff switch preserves register context safely and applies theme preference without mixing carts.''')

ticket('plans-and-platform-administration', 'Implement platform administration and manual SaaS subscriptions', [8], 'Q026, Q024',
'''Allow authorized platform administrators to provision merchants, define plans/entitlements, record subscription payments and activate/renew service. No automated recurring billing or payment acquisition is introduced.''',
'''Create plans, entitlement definitions, subscriptions, subscription payment records, lifecycle history and support audit records. Keep platform billing separate from merchant sales, supplier balances and payroll.''',
'''Build platform screens for merchant state, plan assignment, renewal dates and manual payment recording. Validate duplicate references and audit all entitlement changes. Define trial/grace/restricted/cancelled states from T004 without inventing legal billing terms.
Show owners a clear read-only subscription/status view and actionable renewal information. Restriction behaviour must preserve export/support access and submission of valid pending offline sales; it cannot erase merchant records or silently grant unlimited new selling.
Provide controlled support diagnostics, with tenant context and access audit. Define offboarding/export and retention hooks for later operational ticket. Avoid exposing production secrets or cross-merchant reports through support UI.''',
'''Platform APIs require platform authorization rather than merchant-owner role. Entitlement checks are domain services used by later modules, not only hidden menu items. Add idempotent manual payment/renewal commands and a compatibility hook for pending offline submissions.''',
'''Test unauthorized platform access, duplicate renewal submission, grace/restriction transition, and separation from POS tender records. Ensure a changed subscription does not delete or misassign pending transaction evidence. Verify admin/owner views in light and dark.''')

ticket('register-provisioning-and-settings', 'Implement registers, device provisioning and versioned settings', [8,9], 'Q006, Q007, Q022, Q023, Q034',
'''Create register/device identity and configuration needed for checkout, offline bootstrap, printers and cash sessions. This ticket configures devices; actual printing and second-display operation follow later.''',
'''Persist registers, provisioned device credentials, revocation history, settings versions and register capabilities. Store timezone/business-day settings, feature options and merchant/store setting precedence with explicit ownership.''',
'''Build register setup, device pairing/provisioning, revocation and diagnostics. Initial provisioning requires connectivity. Avoid placing unlimited merchant credentials in local storage; use scoped credentials/session mechanisms chosen in T004.
Expose printer/drawer/customer-display settings without pretending unsupported hardware is connected. Provide clear device health placeholders only as actual unconfigured states. Pricing mode is merchant default plus store override, never per register.
Design active-register ownership and multi-tab coordination hooks. Record the device/config version for future sale/offline commands. Settings changes are audited and effective-time/versioned; disabling a capability must not make outstanding records inaccessible.''',
'''Device bootstrap returns only authorized store/register data and published configuration. Pair/revoke APIs use secure one-time flows. Offline leases and printing transport are finalized by T023/T024; this ticket provides their identity contract.''',
'''Pair valid device, reject cross-merchant pairing, revoke device and verify online denial. Concurrent tabs have identifiable ownership. Test merchant/store setting precedence, retained historical versions and theme persistence. No device credential appears in logs/exports.''')

ticket('catalog-and-product-types', 'Implement flexible catalog, variants, barcodes and pack conversions', [7,8,10], 'Q010, Q012, Q029, Q034',
'''Provide the complete in-scope product catalog for counter checkout and purchasing. Support finished products and extras without recipes, services, serialized assets or repair/rental workflows.''',
'''Create scoped products, variants, unique-within-scope SKUs/barcodes, UOMs/conversions, fixed bundle components, extras/options, images and store availability. Version structural changes affecting existing sales or stock.''',
'''Build create/edit/list/detail screens with bounded search and filters. Support shared catalog with local availability and independent store catalogs. Product archiving preserves old sales and remaining stock visibility.
Implement size/color variants, weighted quantities, carton-to-piece conversion and fixed bundles of finished products. Prevent conversion cycles, invalid factors and recursive bundles. Extras may affect price/finished-product stock according to an explicit definition, not ingredient recipes.
Parse configured scale-label barcode formats with checksums/bounds as researched; initially no direct scale device integration. Preserve leading barcode zeros and intentional multiple barcodes. Batch tracking can be enabled only with a plan for existing stock conversion in T015.
Capture tax classification references without hard-coded Pakistan legal rates. Images are validated and isolated by tenant. Do not ship source placeholder data as the merchant's catalog.''',
'''Typed catalog APIs return decimal quantities/prices and normalized barcode fields. Search endpoints are paginated/bounded and suitable for register snapshot generation. Catalog writes are authorized administrative actions, not cashier free-form mutation during finalize.''',
'''Test variants with colliding merchant/store identifiers, weighted units, carton conversion and bundle stock expansion. Reject cyclic bundles and invalid factors. Barcode scan preserves zeros and distinguishes quantity-encoded formats. Archived item still renders in historical sale snapshots. Review CRUD/search in both themes.''')

ticket('pricing-and-promotion-modes', 'Implement versioned prices and two promotion modes', [11,8], 'Q018, Q022, Q028',
'''Build a deterministic pricing engine and management UI with Manual Discounts and Advanced Promotions. Mode changes are an owner/authorized-manager configuration action, not a destructive deletion of rules.''',
'''Create price lists/store overrides, customer-group prices, scheduled price versions, coupon definitions/usages, quantity/mix-and-match/buy-X-get-Y rules, rule priority and allocated discount snapshots.''',
'''Implement merchant default/store override mode and publish/version workflow. Manual mode uses standard prices and permitted manual line/cart discounts. Advanced mode adds the agreed rule set with documented precedence, stacking limits and eligibility.
Resolve arithmetic and tax ordering using frozen fixtures. Discount allocation must permit exact partial refunds, including free/discounted items, caps and rounding residue. Coupon usages are claimed idempotently during finalize, not permanently consumed by a quote.
Specify active-cart behaviour on mode/rule changes: show a visible reprice/version change and require a fresh valid quote before online finalization; historical completed sales never reprice. Offline uses downloaded valid snapshots with explicitly documented restrictions for globally limited coupons.
Create understandable rule forms, effective date/time preview and a calculation explanation useful to managers. Avoid introducing a generic scripting engine or invisible surprise stacking.''',
'''Expose quote-calculation service and serialized rule snapshot contract for browser fixtures. Finalizing services revalidate an online quote version. Store-only setting changes cannot leak to another store. Record which rules/manual approval produced each allocation.''',
'''Golden tests cover overlapping promotions, threshold boundaries, fractional quantities, free items, time windows, mode switches and partial-return allocations. Repeated quote does not consume coupons. Unauthorized manual discount fails server-side. Both-theme rule editor and checkout explanation remain legible.''')

ticket('customers-and-local-records', 'Implement customer profiles and store-scoped operational records', [7,8], 'Q014, Q017, Q025, Q041',
'''Manage customer profiles and purchase association without customer credit accounts. Preserve independent-store data boundaries while enabling later merchant-wide loyalty links.''',
'''Create operational customer records, permitted contact/preferences fields, customer groups and optional loyalty identity links. Normalize phone data with Pakistan-aware parsing; do not merge unrelated merchants or stores by phone alone.''',
'''Build customer list/search/create/edit/archive, duplicate review and sale association APIs. Shared mode shares operational profiles; independent mode keeps each store's record distinct. Central authorized views label store ownership.
Capture only useful contact fields and explicit notification/marketing preferences. An email receipt request and marketing consent are not the same preference. Customer absence must not block anonymous cash sales.
Define safe links to later loyalty members, never automatic cross-store exposure of full profiles/history. Customer edit/archive cannot rewrite historical sale customer snapshots. No debt balance, customer credit limits, unpaid statements or layaway screens.''',
'''Customer API is authenticated and scoped. Public membership endpoints are not implemented here and must not reuse this full response serializer. Purchase-history association will integrate after T022. Search is bounded and normalized without leaking cross-tenant matches.''',
'''Test same phone across merchants and independent stores, shared-mode lookup, archive/history preservation, consent separation and anonymous checkout support. Unauthorized store lookup fails. Form validation and error/empty states work in both themes.''')

ticket('inventory-ledger', 'Implement inventory movements, balances and audited adjustments', [11,8], 'Q010, Q029, Q038',
'''Establish the authoritative finished-product inventory ledger and balance projection used by purchasing, sales, transfers and counts. This is not an ingredient/wastage-management module.''',
'''Create inventory locations, append-only stock movements, source references, balance projections, adjustment reasons and cost placeholders governed by T018. Use exact base-UOM quantities and tenant/store keys.''',
'''Implement idempotent movement posting, reversible corrections, balance rebuild/reconciliation and safe concurrent updates. Do not allow arbitrary balance editing without source movements. Product scope/location must be checked server-side.
Allow authorized negative-stock sales later with warning/audit, while administrative adjustments require a reason. Distinguish sellable and unavailable/quarantine disposition for returned or expired stock without inventing a recipe waste workflow.
Build stock-on-hand and movement views with source-document drill-down, bounded filters and adjustment entry/approval permissions. Expose data freshness and unexplained discrepancies rather than silently overwriting projection values.
Ensure fixed bundles expand to finished-product movements exactly once and pack conversions use catalog contracts. Batch dimensions are added compatibly by T015; do not duplicate quantity ledgers.''',
'''Provide internal postMovement/reverseMovement contracts and read APIs; only trusted domain commands may post financial stock effects. Source-document uniqueness prevents retries duplicating stock. Inventory APIs never accept an unverified tenant scope from the body.''',
'''Concurrent movements preserve balances, duplicate source operations are idempotent, rebuild equals projection, and cross-store invalid product/location pairs fail. Quantity conversions conserve base units. Historical movements cannot be deleted through UI. Check stock/adjustment screens in both themes.''')

ticket('batch-and-expiry-tracking', 'Implement batches, expiry and sellable-stock selection', [14,11], 'Q010, Q029',
'''Add optional product batch/expiry tracking with expired-batch sale blocking and traceable receiving/selling/returning. Do not add serial-number tracking.''',
'''Create product batch definitions, per-location batch balances, expiry dates, batch movement references and batch selection snapshots. Keep moving-average valuation separate from physical batch choice.''',
'''Define expiry-date boundary in store business time, mandatory capture rules for tracked products and display labels. Validate received batch consistency. Enable tracking on existing stock only through a controlled allocation procedure, never by leaving unexplained unbatched units.
Provide availability/expiry views and proposed earliest-expiry selection, with permitted manual selection of non-expired batches. Expired stock is visible but unavailable for sale. Negative-stock policy cannot authorize sale from an expired batch; unallocated shortage handling must be explicit.
Record original batch on sales and returns where known; returns can route to unavailable stock. Snapshot expiry facts for offline validation and server reconciliation. Missing/changed batch information is an actionable conflict, not silent reassignment.''',
'''Batch read/write APIs are scoped to product/store. Reservation/selection contracts feed T021/T022/T023. Explain difference between a warning for soon-expiring stock and a hard block for expired stock. No unsupported pharmaceutical traceability certification claims.''',
'''Test two batches with different expiry, expiry exactly at boundary, transfers/returns preserving identity and attempted expired sale online/offline fixture. Validate no batch from another product/store can be selected. Check stock alerts/labels in both themes, not color alone.''')

ticket('stock-transfers-and-counts', 'Implement stock transfers, stocktakes and reconciliation', [14,15], 'Q010, Q025, Q027, Q029',
'''Provide controlled movement between stores and physical stock counts without losing inventory history or assuming independent catalogs use identical IDs.''',
'''Create transfer headers/lines, shipment/receipt states, in-transit quantities, explicit independent-catalog product/UOM mappings, stocktake sessions/count lines and posted count adjustments.''',
'''Implement draft → dispatched → received/partially received transfer lifecycle with cancellation/correction rules. Ship and receive effects post separately and idempotently. Handle short/excess receipt using documented authorized resolution, not untracked quantity edits.
Shared products can map directly when valid. Independent stores require explicit product/variant/UOM mapping and compatible batch transfer. Do not match on names or barcodes silently. Preserve cost provenance for T018 valuation.
Build stocktake opening, counting, review and posting. Define concurrency policy while live sales continue: compare to a captured baseline plus intervening movements or use a controlled count window as frozen by T004. Do not overwrite live balances with stale counts.
Provide discrepancies, source/target store permissions and status visibility. No local-network offline stock transfer execution is required.''',
'''Operation APIs for dispatch, receive and postCount use state/version checks. Repeated commands return original results. Count/transfer exports are scoped. Audit mappings and reasons for discrepancies.''',
'''Test partial transfer receipt, duplicate dispatch/receive, different store product IDs, unit conversion and expired batch handling. Simulate a sale during count and reconcile correctly. Unauthorized destination access fails. Review transfer/count screens in light/dark.''')

ticket('suppliers-and-purchase-orders', 'Implement suppliers and purchase order lifecycle', [11,13,8], 'Q013',
'''Provide supplier records and purchase-order planning with explicit statuses and remaining quantities. Receiving and liabilities integrate in T018/T019 rather than pretending an order itself received stock.''',
'''Create supplier records/contact data, scoped purchase orders/lines, ordered UOM/quantity, expected unit costs/tax references, dates and status history. Preserve referenced suppliers after archiving.''',
'''Build supplier list/detail/edit and PO draft/edit/issue/cancel/detail workflows. Validate product/store/UOM and amount limits. Allow partial future receipts and track ordered/received/returned separately.
Define edits after issue/partial receipt: prevent reductions below already fulfilled quantities and preserve history. Cancellation only affects outstanding units, not existing receipts or financial records. No advanced approval-threshold or three-way invoice-matching module is required.
Generate a readable purchase-order PDF/export with supplier and merchant details; email send hook integrates at T034. Do not send external emails during tests. Provide source document references and reconciliation links for later tickets.''',
'''PO APIs expose domain operations and remaining quantities, not arbitrary status overwrites. Supplier balance UI may show an explicit not-yet-integrated state during this ticket; it must be integrated in T019 before launch. Customer credit remains absent.''',
'''Test create/issue/cancel, invalid UOM/scope, editing after simulated receipt, duplicate issue and supplier archive. PDF uses light print styling independent of UI theme. Supplier/PO pages pass permission and light/dark checks.''')

ticket('receiving-and-moving-average-cost', 'Implement partial receiving and moving weighted-average valuation', [17,14,15,16], 'Q013, Q038',
'''Receive purchased goods with batches/expiry and update inventory/cost exactly once. Define and implement moving weighted-average valuation, including negative-stock and correction cases.''',
'''Create receipt headers/lines, PO allocation, batch allocations, receipt cost snapshots, stock source references, supplier charge entries and valuation/correction records. Keep posted cost evidence immutable.''',
'''Build receive-PO screen with remaining quantity, partial receipt, batch/expiry and unit conversion. Validate over-receipt policy explicitly; do not silently accept more than ordered. Receipt posting atomically records stock effects and financial source entries, with outbox events after commit.
Implement average-cost equations for positive stock and defined policy for zero/negative stock. Preserve provisional/adjusted cost history when receipt later resolves negative inventory; reports must reveal pending corrections. Sales keep original cost snapshot plus explicit adjustment linkage, never an unexplained rewrite.
Define landed cost/tax treatment only to the agreed operational scope and researched contracts. Backdated or corrected receipt follows reversal/adjustment workflow and cannot corrupt intervening averages. Transfers carry cost consistently between source/destination without treating transfer as a new supplier purchase.
Create golden examples for carton receipt/piece sale, fractional quantity, partial receiving and duplicate submission. Integrate PO progress and stock views.''',
'''postReceipt command has idempotency key, expected PO state/version, decimal quantities/costs and batch allocations. Domain transaction locks relevant balance/cost rows in deterministic order. Supplier charge is not a recorded supplier payment.''',
'''Test duplicate receipt, concurrent receipts, exact weighted-average examples, zero/negative stock corrections, reversal and independent-catalog transfer valuation. Expired/missing batch capture follows policy. Receipt and valuation detail visible in both themes; no float drift.''')

ticket('purchase-returns-and-supplier-balances', 'Implement purchase returns, supplier balances and payments', [18,17], 'Q013, Q014',
'''Complete procurement with linked purchase returns and operational supplier balances/payment allocations. Customer credit remains excluded; supplier liabilities are an intentional separate feature.''',
'''Create purchase-return documents/lines linked to eligible receipts, stock/cost reversal entries, supplier credits, payment records, allocations and auditable supplier balance projections.''',
'''Return only eligible remaining received quantities, with correct units/batches and stock availability policy. Distinguish physical goods returned from a supplier financial credit; uncertain/unmatched credit remains visible. Apply frozen valuation adjustment rules instead of refunding arbitrary current cost.
Record supplier payments, partial allocations, references and payment method. Prevent duplicate payment recording and over-allocation; show unallocated amounts explicitly. A payment entry does not initiate a bank transfer.
Build supplier ledger/balance/statement, overdue/due fields only where supported by the supplier document terms, purchase-return screen and payment-allocation screen. Correct posted entries through reversal, retaining attribution. Integrate cash-source links later with T020/T031 without double-counting.''',
'''Operation APIs post purchase return and supplier payment atomically with idempotency. Supplier statements use authoritative charge/credit/payment entries. No unrestricted edit/delete of posted balances. Cross-store merchant policies are explicit.''',
'''Test partial/repeated return, excessive return rejection, batch/cost linkage, partial/multiple payment allocation, reversal and rebuilt balance equality. Ensure customer module gains no debt account. Both-theme financial screens and exports stay scoped.''')

ticket('cash-register-sessions', 'Implement cash sessions, floats, cash movements and closeout', [10,8,19], 'Q023, Q015',
'''Manage register-based cash accountability, with one explicit active cash session per register according to the frozen concurrency rule. Staff identity is recorded on each movement even when a drawer is shared.''',
'''Create opening floats, cash sessions, paid-in/out records, reasons/source links, denomination counts if useful, blind closing declarations, expected totals, variances and manager review records.''',
'''Implement open-session, paid-in/out and close-review flows. Cashier sees a blind count entry before expected amount is revealed as allowed by role. Distinguish cash session from employee attendance shift.
Define handover and concurrent cashier rules. A close request uses a cutoff and pending-sale status; no unexplained missing sales. T022 contributes cash sale/change/refund effects, T023 supplies provisional offline close and later reconciliation. Do not call an offline unresolved close fully reconciled.
Link supplier/payroll/expense cash outflows to the same movement identity as later modules integrate. Never count both a payment record and its drawer movement as two expenses. Manager variance review requires reason/audit; closed sessions are corrected through documented adjustments.''',
'''Cash-session APIs reject incompatible register/store and stale status. saleFinalized/refundPosted consumers are idempotent. Expected cash equations distinguish gross tender, change and refunds. Provide source links and report cutoff metadata.''',
'''Test duplicate opening, simultaneous open attempts, paid-out permissions, blind count, variance review, handover and late-arriving sale fixture. Verify expected cash arithmetic and no hidden external tender counted as cash. UI works in both themes and with keyboard input.''')

ticket('checkout-cart-and-quotes', 'Build cashier checkout, scanning, carts and quotes', [6,11,12,13,15,20], 'Q009, Q012, Q014, Q015, Q018',
'''Create fast staff counter checkout with real catalog/pricing/stock integration. This ticket delivers cart/quote workflows; final posting follows T022. No tables, kitchen, ordering portal or credit-sale UI.''',
'''Create carts/lines, suspended-cart state, customer association, quote snapshots/version, scan processing and selected batch/variant/extra data. Cart is mutable; finalized sale will be immutable.''',
'''Build product search/category shortcuts, barcode scanning, repeated scan increment, variant/extras selection, weighted quantity entry, editable line quantities and permission-controlled discounts. Preserve scanner focus without stealing an active text input. Show price/tax/discount explanation and totals.
Associate optional customer, suspend/resume authorized carts, handle unavailable product/rule changes and stock warnings. Expired batches block selection. Concurrent cart edits have version checks. A suspended cart does not reserve goods indefinitely or create layaway/deposit obligations.
Quotes use authoritative server calculations and published version references. Surface reprice conflicts clearly. Provide the same golden calculation fixtures to the future offline engine. Payment button is integrated only when T022 is ready; no fake successful sale in production UI.
Theme toggle, staff switch and transient network failure must preserve unsaved work appropriately. Keep pending/retry status visible and understandable.''',
'''Cart/quote APIs accept quantities/product identifiers rather than trusted final totals. Quote result includes allocations, selected versions and validation issues. Define required full-settlement input contract for T022; payment entry cannot store card secrets.''',
'''Browser tests cover scan/manual search, intentional repeated barcode, variants, weighted items, bundles/extras, suspend/resume and expired stock block. Golden quote matches engine. Test theme toggle on populated basket, keyboard flow, narrow supported checkout size and stale-cart conflict.''')

ticket('sale-finalization-and-tenders', 'Implement atomic sale posting and fully settled split tenders', [21,14,18,20], 'Q014, Q015, Q016, Q028',
'''Turn an authorized quote/cart into exactly one authoritative sale with inventory and cash effects, fully settled through cash and recorded external payments. Establish reliable asynchronous hooks for receipts, loyalty and fiscal work.''',
'''Create sales/line snapshots, tender records, cash change, idempotency records/fingerprints, inventory source links, finalized receipt data and transactional outbox events. Preserve public sale/receipt identifiers.''',
'''Validate store/register/session, permissions, active pricing quote, batches, decimal totals and full settlement server-side. Support cash and manually confirmed card/wallet/bank references, including split payment. Do not accept customer debt or partial unpaid sale.
Use one authoritative transaction for core sale/tender/stock/cash/outbox effects with deterministic locks. Pricing and coupon usage claims finalize here. Record manual confirmation provenance and uncertain external-payment handling; no UI may imply bank verification.
Resolve retry/timeouts by idempotency lookup. Same key/body returns original result; changed body conflicts. Prepare receipt view/reprint data from immutable snapshots. Later T027 attaches loyalty reservation/commit without trusting a browser discount claim.
Voiding a draft differs from returning/reversing a completed sale. Do not delete a paid sale to fix an error. Preserve enough metadata for offline synchronization and fiscal adapters without old till ingestion flags.''',
'''POST sale command and GET by client transaction identity implement T004 schema. Outbox consumers are retry-safe. The response clearly identifies posted state; receipt printing and email are separate outcomes. External refs are limited metadata, never PAN/CVV.''',
'''MySQL tests simulate concurrent finalization, crash/response loss after commit, duplicate key, altered retry body, split cash/external and cash change. All posted effects occur once or none. Tenant/register substitutions fail. Sale detail and tender status are truthful in both themes.''')

ticket('offline-pwa-and-reconciliation', 'Implement offline cash checkout, durable queue and recovery', [22,10,12], 'Q002, Q028, Q029',
'''Make previously provisioned Windows registers continue core cash sales through an internet outage and restart, preserving evidence until authoritative reconciliation. Do not extend offline access to every back-office module.''',
'''Implement service-worker/app caches, versioned bootstrap snapshots, IndexedDB transactions/outbox, device/client transaction IDs, acknowledgements, reconciliation states, register ownership leases and diagnostic metrics.''',
'''Download only authorized catalog/pricing/tax/staff context and define lease/age limits from T002/T004. Implement browser arithmetic from the same golden fixtures as PHP. Offline allows cash and cached valid promotions/manual discounts; loyalty redemption and external-payment confirmation are disabled. Limited-use coupons need the frozen offline policy.
Commit local sale and printable snapshot atomically before success. Retry original IDs with backoff; distinguish queued, submitting, accepted and needs-reconciliation. Never drop rejected/conflicting cash-sale evidence. Reconnect must not duplicate stock, cash or later loyalty earning.
Support valid offline restart, multi-tab ownership and clear pending counts. Handle quota errors, denied persistence, stale authorization, clock drift, source changes and subscription restriction. Do not silently clear data on logout or update. A lost/cleared device has explicitly limited recovery.
Create a controlled unresolved-sales screen and safe diagnostic/export procedure. Define provisional closeout integration and update compatibility. Do not promise offline local-network shared-store synchronization.''',
'''Bootstrap and batch/single submission APIs validate device and tenant, preserve per-command result and request fingerprint, and accept supported older formats. Server decides posted/reconcile state. Public connectivity indicators reflect actual request reachability, not navigator.onLine alone.''',
'''Test network loss before/after local commit and server commit, browser restart, duplicate submission, two tabs, stale catalog, expired batch, negative stock and disk-full simulation. PHP/TS fixture parity passes. Toggling themes offline preserves cart/queue. Clearly label hardware/fiscal cases pending T024/T035.''')

ticket('receipts-printing-and-drawer', 'Implement thermal/PDF receipts, print helper and cash drawer', [22,23,10], 'Q007, Q030, Q032, Q034',
'''Print reliable sale receipts and PDFs from frozen sale data with optional local helper and drawer control. A committed sale survives printing failure; printing status is not payment status.''',
'''Create receipt templates/configuration, print job identities/attempts, helper pairing configuration, device diagnostics, reprint audit and PDF generation. Record submission/acknowledgement/unknown physical completion distinctly.''',
'''Implement selected helper transport and offline authorization established by T002. Do not expose signing private keys in frontend bundles or require an unavailable cloud signer for every offline print. Qualify a named Windows printer/driver/paper width combination.
Render merchant/store details, sale identifiers, lines, discounts, tax, tenders/change and relevant verified fiscal fields. Offline receipts truthfully reflect pending status under T035 rules. Historical receipts use snapshots, not today's product names/prices.
Add explicit reprint marked as copy where appropriate, print queue retry with duplicate protection and a manual recovery path for uncertain completion. Drawer commands require permission and reason when not tied to a sale; reconnect must not repeatedly open drawer.
English templates preserve Unicode names as supported/tested. PDF/thermal output remains black-on-white regardless of screen theme. Email attachment/link integration follows T034. Avoid OS-wide unsafe configuration changes.''',
'''Print API/bridge accepts narrowly scoped validated jobs from authorized origins/registers. Expose diagnostic states without leaking helper credentials. Printing cannot call sale finalize again. Local job IDs correlate to sale and attempt, not duplicate financial commands.''',
'''Test helper absent, printer offline/out of paper, lost acknowledgement, explicit reprint, offline printing and drawer permissions. Render PDFs and inspect actual layout. Physical qualification must state hardware tested; simulation alone is not certification. Review setup/queue in both themes.''',
gate='If no printer is attached, implement and simulate safely; keep physical-device acceptance explicitly pending.')

ticket('optional-customer-display', 'Implement optional second-screen customer display', [21,22,24], 'Q034; D005',
'''Support a merchant/register-controlled read-only customer display when a second screen exists. Checkout remains fully usable without it. This is part of launch capability, not a mandatory peripheral.''',
'''Create display enablement/pairing settings, read-only register display session, minimal basket projection and disconnect/idle/completed states. Keep display credentials distinct from staff sessions.''',
'''Provide enable/disable, open/pair display and connection status controls. Use a tested second browser window/session approach suitable for Windows if selected in T004. Enabling must not require directly connected scales.
Show product names, quantities, applicable discounts and total; optionally a neutral completion message. Do not show internal costs, payroll, staff-only approvals, customer phone/email, loyalty private data or manual external-payment secrets.
Handle cart updates, suspended cart, cancellation, payment complete, reconnect and staff/register switching. Clear prior customer's content at the defined idle/completion transition. Use its own supported light/dark preference and readable large typography.
Design offline register-to-display updates for supported same-device context; do not claim networked kiosk features. Read-only session cannot issue finalize/refund commands.''',
'''Publish a minimal display projection via the chosen local/session channel with origin/register checks. Do not broadcast full internal cart objects. Pairing expires/revokes and cross-register display access is rejected.''',
'''Test no second screen, enable/disable, disconnect/reconnect, wrong register, read-only tampering, privacy clear and light/dark. Verify display actions never change the basket. Record actual two-window/monitor behaviour and limitations.''')

ticket('returns-and-exchanges', 'Implement original-sale returns, exchanges and deadline approvals', [22,24,20], 'Q016, Q019, Q038, Q040',
'''Support linked partial/full returns and exchanges while conserving original money, quantities and stock effects. Default ordinary window is 7 days, configurable up to 30, with manager late-return exception.''',
'''Create return documents/lines, original allocation references, refund tenders, exchange links, stock disposition, policy snapshots and manager approval/audit records. Keep remaining refundable quantities/value projections.''',
'''Build original-sale lookup, eligible line/quantity selection, reason/disposition entry, refund tender recording and receipt. Apply original tax/discount allocations, not current promotions/prices. Repeated partial returns cannot exceed original eligible amounts.
Enforce ordinary window server-side; late request requires action-bound manager approval and reason. Do not silently expand policy for all sales. Research statutory exceptions from T003 remains authoritative.
Exchange coordinates a return and fully paid new sale, settles the difference and links documents. For external methods record actual refund confirmation provenance; do not fabricate automatic provider refund. No store-credit/gift-card issuance or unlinked returns.
Restock only to permitted original/appropriate non-expired batch disposition. Adjust cash session and valuation via domain contracts. Emit loyalty reversal event for T027 and fiscal correction event for T035; later integration tests must verify them.''',
'''Return/exchange commands use idempotency and lock original remaining returnable amounts. Distinguish pending external action from completed monetary refund. Closed-session refunds occur in the active authorized session with references, not by rewriting old closeout.''',
'''Test partial return rounding, repeated/concurrent return, return after price change, deadline boundaries, manager exception replay, exchange differences and non-restockable batch. No excess refund/quantity. Receipt, approval and detail UI reviewed in both themes.''')

ticket('loyalty-points-and-rewards', 'Implement merchant-wide points, rewards and refund reversals', [22,26,13], 'Q017, Q019, Q028, Q041',
'''Provide merchant-specific points/rewards across all stores, including independent operational catalogs/customers. Earn/redeem/expire/reverse are distinct ledger operations; no tiers, visit schemes, gift-card balances or payment-card linking.''',
'''Create programme/rule versions, merchant-wide members, local customer links, points ledger/projection, reward definitions, redemption reservations/commits and expiry/reversal references. Normalize phone within merchant scope.''',
'''Configure earning/redemption/expiry and reward eligibility with clear monetary/tax treatment from T004. Prevent double earning on retry and double spending under concurrent checkout. Quote may reserve temporarily; finalization commits or releases according to the frozen lifecycle.
Integrate staff checkout online member lookup/redemption and sale/return events. Offline queues earn only after authoritative acceptance; redemption is blocked offline. Fully paid sale equations account for points consistently without creating customer monetary debt.
Define reversal when earned points were already spent, negative points handling and later earning repayment if selected by contract. Partial returns reverse allocated earning exactly once. Expiry operations are scheduled/idempotent and retain reasons.
Build manager member/reward/rule/ledger views and cashier limited lookup. Separate merchant-wide identity from store-specific customer history. Cross-merchant member matching is forbidden. Public portal follows T028 and cannot reuse private DTOs.''',
'''Internal loyalty commands require tenant and sale identity; member search can accept normalized phone only within authorized context. Ledger rebuild must equal projection. Public reward labels are separate from secret redemption credentials. Outbox ordering/retry handles late sale/return events.''',
'''Test cross-store earn/redeem in independent mode, concurrent redemption, duplicate earn, expiry replay, refund after redemption and offline earn replay. Check cash totals/report reconciliation and tenant isolation. Rules/ledger/cashier view pass both-theme visual checks.''')

ticket('public-member-join-and-lookup', 'Build the single-form public loyalty join and limited profile page', [27,6], 'Q033, Q036, Q039, Q041',
'''Recreate the agreed existing loyalty experience in Vue: merchant URL, one joining/returning form and a view-only points/rewards page. Phone-only lookup is intentionally limited, not authenticated identity verification.''',
'''Create public merchant/program slug mapping, minimal request/response DTOs, member enrollment idempotency/deduplication and throttling records. Keep enrollment commands separate from lookup refresh.''',
'''Inspect the existing React Join.tsx, Laravel JoinController and success view for UX context. New visitors submit required phone and optional allowed profile fields; existing members see only points, available rewards and masked identity. Use Pakistan-aware normalization, not the original UK default.
The UI may use one form, but repeated refresh must call a read-only lookup endpoint. Repeated join does not create duplicate member or welcome credit. Scope to active merchant/program and validate any optional store attribution against the merchant.
Do not return full member model, full contact details, history, receipts, notes, editable fields, redemption token or QR credential usable as authorization. No customer ordering/redemption/profile editing. Avoid claims that entering a phone proves identity or makes the page private.
Add bounded lookup/enrollment rates and abuse monitoring without introducing mandatory SMS/PIN contrary to the chosen flow. Provide friendly invalid/inactive state and responsive mobile layout with light/dark toggle. Separate marketing consent from enrollment.''',
'''Proposed public endpoints: GET programme info; POST enrollment; POST limited lookup to avoid phone in URL/query logs. Responses are explicit allowlisted DTOs. No endpoint should search arbitrary merchants if slug is missing. Do not leak matched-by-email private profile data.''',
'''Verify new/returning flows, duplicate concurrent join, phone normalization, disabled programme, cross-merchant/store manipulation, rate limits and refresh without writes. Assert response field allowlist and masked identity. Test mobile/light/dark and no member action can finalize a sale or redeem.''',
'C:\\XEPOS\\loyalty-portal\\src\\pages\\Join.tsx; api-loyalty-xepos\\app\\Http\\Controllers\\Public\\JoinController.php')

ticket('workforce-attendance-rota-leave', 'Implement staff records, attendance, rota and leave', [8,7], 'Q020, Q021, Q037',
'''Build the real workforce module requested by the user rather than reproducing existing coming-soon screens. Keep employment records and attendance separate from login rights and cash sessions.''',
'''Create employment records, work schedules/rota assignments, clock-in/out events, attendance corrections, leave types/requests/balances/approvals and payroll input snapshots. Every correction records actor/reason.''',
'''Build staff list/detail, rota calendar/list, clock actions, attendance review and leave request/approval screens. Owner manages employment/pay configuration; manager attendance/leave permissions follow the fixed matrix. Cashier sees only permitted own attendance/leave actions.
Define overnight shifts, missed punches, duplicate clocks, breaks, lateness and overtime eligibility as configurable operational policy. Avoid inventing statutory rules; T003/T030 identify legal inputs separately. A cashier switching PIN is not automatically clocking in or opening a cash session.
Handle overlapping rota assignments, leave conflicts, balances and approval cancellations with audit. Freeze approved payroll-period inputs and route later corrections into explicit adjustments. Do not silently rewrite posted salary results.
Provide attendance exports and a typed payroll-input service. Privacy applies to salary/leave notes; do not expose sensitive notes on public displays or cashier lists.''',
'''Authenticated workforce operations use scoped staff identities; staff ID is not proof of permission. Clock actions are retry-safe. This module remains online for writes at launch; offline checkout does not imply offline rota/payroll editing.''',
'''Test duplicate/missing punches, overnight shift, leave overlap/approval/reversal, unauthorized salary field access and changed attendance after period freeze. Export scope is correct. Calendar/table/forms work in both themes and keyboard navigation.''')

ticket('operational-payroll', 'Implement operational payroll, payslips and recorded payouts', [29,20], 'Q020, Q021, Q037',
'''Calculate and approve operational payroll using monthly/hourly pay, attendance/overtime, allowances, deductions and advances. Statutory figures are entered/imported; no automatic tax/contribution compliance engine is promised.''',
'''Create effective-dated pay terms, payroll periods/runs/items, input snapshots, manual statutory adjustments, advance ledger/allocations, approvals, payslip snapshots and payout records.''',
'''Build owner-restricted pay setup, payroll draft calculation, review, adjustment, approve/post, payslip and payout flows. Define decimal rounding, partial periods, unpaid leave and overtime formula from frozen policy with worked examples.
Snapshot attendance/salary basis and effective dates. Posted runs cannot be recomputed by editing current salary or attendance. Corrections create adjustment/reversal records and explain impact. Prevent duplicate period posting and repeat advance deductions.
Support partial/recorded payout and outstanding payroll payable; distinguish employee liability from excluded customer credit. Recording a payout does not initiate a bank transfer. Link cash payouts to register movement exactly once where authorized, and avoid double-counting in expense reports.
Generate confidential English payslips/PDFs with print-safe styling. Payroll visibility follows fixed role matrix; operational manager permissions do not automatically expose pay. Provide exports, reconciliation and auditable approval.''',
'''Payroll calculation returns explainable line items; posting and payout APIs use idempotency/state/version checks. Inputs identify entered/imported statutory amounts explicitly. Secure downloads are authorized and time-limited where appropriate; public member portal has no access.''',
'''Golden tests cover monthly/hourly pay, overtime, leave, allowance/deduction, advances, partial payout, duplicate post and correction after posting. Payslip totals reconcile with payout/liability. Owner-only access and both-theme review screens are verified; inspect PDF layout.''')

ticket('expenses-and-settlement-records', 'Implement expenses and operational payment reconciliation', [19,20,22,30], 'Q035, Q015',
'''Provide categorized expenses and reconciliation of recorded external payments/settlements without building general-ledger accounting or automatically moving money.''',
'''Create expense categories/records, attachments, payment-source links, settlement batches/lines, fees/differences and reconciliation exceptions. Reuse existing supplier/payroll/cash source identities.''',
'''Build expense create/review/correct views with date, category, amount, payee and payment source. Validate attachments and scope. Cash-paid expense posts one linked cash movement, not a duplicate independent outflow.
Record settlement statements/references for manually recorded external tenders and identify unmatched/mismatched records, fees and timing differences. A matching user-entered record is operational reconciliation, not cryptographic bank confirmation. Keep provenance visible.
Supplier and payroll payments stay in their owning ledgers; reports classify their effects without double-counting expenses. No chart of accounts, general journals or full balance-sheet claims.
Posted corrections are audited. Provide operational exports and clean hooks for later accounting connector tickets only if separately authorized.''',
'''Expense/settlement operations are scoped, idempotent and permission controlled. Reconciliation status changes preserve original tender metadata and user identity. File downloads never accept arbitrary filesystem paths.''',
'''Test duplicate expense submission, cash link once, attachment validation, partial/unmatched settlement, fee difference and reversal. Combined cash/supplier/payroll/expense totals reconcile. Sensitive reports and light/dark financial screens pass checks.''')

ticket('standard-reporting', 'Implement standard reports, saved filters and drill-down', [26,27,30,31,16,18], 'Q031, Q025, Q038, Q041',
'''Build trustworthy standard reporting for merchant/store operations with saved filters, exports and source-document drill-down. Do not build an arbitrary report designer or dashboard-layout builder.''',
'''Create metric definitions, authorized report queries/projections, refresh/reconciliation metadata and per-user saved filters. Reporting data is derived from authoritative ledgers/snapshots, not a new editable source of truth.''',
'''Implement sales, discounts, tax, tenders, returns, gross margin, stock/expiry/movements, purchases/supplier balances, expenses, loyalty, attendance/leave/payroll and cash closeout reports. Define gross/net/tax/discount/refund semantics once.
Handle business-day cutoff, store timezone, return date versus original sale date, cash change, external recorded tenders and provisional cost adjustments. Show report freshness/limitations where asynchronous or unresolved records matter.
Support store-specific and consolidated views in both ownership modes. Independent-store identical SKUs are not automatically one product; label mapping/aggregation explicitly. Merchant-wide loyalty totals must not be counted once per store.
Implement bounded pagination, indexed filters and background large exports using T033 infrastructure. Avoid heavy dashboard queries on checkout write paths. Save filter definitions, not unauthorized cached result sets. Payroll report permissions remain restricted.''',
'''Report APIs expose fixed approved metrics, bounded date ranges/pagination and clear totals. Every aggregate can be reconciled/drilled down to source documents within user scope. Export jobs must re-check scope at execution and download.''',
'''Seed golden cross-module dataset and reconcile sale/tax/tender/return/stock/cost/payroll/cash totals. Test midnight cutoff, partial returns, negative cost adjustments and both store modes. Test report authorization and measured query plans. Charts/tables/exports work in both themes with non-color cues.''')

ticket('imports-and-exports', 'Implement validated imports, opening balances and portable exports', [11,13,15,18,32], 'Q005, Q024, Q031',
'''Enable new merchants to import catalogs/customers/opening stock and export operational records. Full historical XEPOS migration and live connectors remain outside launch.''',
'''Create import jobs, staging rows, validation errors, mapping presets, commit IDs, source hashes and export job/file metadata. Keep opening stock/cost as explicit authorized ledger entries; do not import customer debt.''',
'''Provide documented CSV/XLSX formats as supported by selected libraries, sample templates and bounded upload validation. Preview column mapping, units, scope, duplicates, tax references and batch/expiry before commit. Dry-run cannot mutate operational balances.
Commit imports idempotently with row-level outcomes and transactional boundaries appropriate to size. Define partial failure/retry policy; never silently skip financial rows. Shared/independent stores require explicit ownership mapping. Normalize phone without cross-merchant merges.
Opening stock includes quantities, batches and starting cost; supplier opening balances may use a separate audited supported template if defined, while customer credit balances are rejected. Existing sale history is not migrated.
Export catalog, customers, operational finance and reports with scope checks, formula-injection protection, encoding and bounded background jobs. Retention and download authorization are explicit. Payroll exports need elevated permissions.''',
'''Import API separates validate/preview from commit. Export jobs carry explicit actor/scope and expire downloadable artifacts according to policy. Document stable schema/version for future accounting integration; no arbitrary SQL export endpoint.''',
'''Test malformed/oversized file, duplicate key, bad UOM/batch, wrong store, Unicode, retry and mixed-validity rows. Dry-run makes zero ledger changes. Opening cost/quantity reconciles. Exports cannot leak other tenants or execute spreadsheet formulas. Both-theme progress/error review is verified.''')

ticket('email-and-in-app-notifications', 'Implement email receipts and in-app operational alerts', [24,27,30,33,9], 'Q032, Q030',
'''Deliver launch email/PDF receipts and operational in-app alerts through a retry-safe notification service. SMS/WhatsApp remain off until T038 is explicitly requested.''',
'''Create notification preferences, templates, delivery jobs/attempts, deduplication identities and in-app alert read states. Separate transactional receipt requests from marketing consent.''',
'''Implement email sending through a configured provider abstraction, safe test sink and durable outbox. Receipt send/retry is independent of sale success and cannot repeat payment/stock. Use sale snapshots and authorized short-lived links or controlled attachments.
Provide email settings/test facility limited to authorized users, receipt send/resend action and truthful delivery status. Acceptance by an email provider is not proof the customer read a receipt. Do not email real customers while running tests.
Add useful in-app alerts for expiring batches, unresolved offline submissions, failed printing/fiscal/email work and other agreed operating exceptions. Scope alerts by role/store and avoid noisy duplicates. Sensitive payroll notifications must not disclose pay amounts in broad alerts.
Enable optional purchase-order/payslip sending only within their existing workflow permissions. Define future channel interface and toggles without rendering working SMS/WhatsApp features prematurely.''',
'''Notification requests reference source entity, audience, channel and deduplication key. Webhooks, if selected provider uses them, are authenticated/idempotent. Never include provider secrets in frontend settings responses or logs.''',
'''Test duplicate event, provider timeout/retry, unsubscribe/transactional preference separation, unauthorized resend and invalid recipient. Verify PDF/link authorization and sensitive content. UI states/alerts work in light/dark. Use test sink; record real-provider validation separately.''')

ticket('verified-fiscal-adapters', 'Implement applicable Pakistan fiscal adapters and exception handling', [3,4,22,23,26,24,32], 'Q003, Q008, Q024, Q040',
'''Implement only fiscal regimes whose applicability and technical contracts have been verified in T003. Integrate sales, corrections and receipts without equating a local sale with an accepted fiscal submission.''',
'''Create scoped fiscal configurations, protected credentials, immutable submission snapshots, provider references, statuses/attempts and correction/reconciliation links. Preserve authority/jurisdiction/version evidence.''',
'''Map sale/tax/item classifications into verified payloads. Use documented authentication, timeout/retry/idempotency/reference lookup behaviour. Preserve unknown outcome rather than blindly creating a second invoice. Separate retryable transport failures from rejected business data.
Implement linked returns/corrections as required by verified regime. Receipt fields/QR/numbering display actual acceptance or legally supported pending state. Apply researched outage policy for offline tills; if a regime disallows a proposed behaviour, enforce the documented constraint and flag affected merchant launch readiness.
Provide authorized fiscal status/configuration/exception views and manual repair paths with audit. Never expose credentials or allow arbitrary payload tampering that disconnects tax evidence from a sale.
Run sandbox scenarios where access exists. Production registration/credentials/provider approvals are explicit launch gates; code completion without them is not compliance certification.''',
'''FiscalAdapter implements T003/T004 interface with per-authority mapping, outbox consumer and status DTOs. Sale posting coupling/decoupling must match verified legal behaviour, not generic async assumptions. Third-party accounting remains deferred.''',
'''Test accepted/rejected/timeout/duplicate/unknown submission, refund correction, credential error and offline reconciliation using fixtures plus real sandbox where available. Check receipt/report agreement and scope isolation. Record exact source/API versions and pending production gates.''',
gate='Do not fabricate missing credentials or regulatory conclusions. Complete supported code/tests and leave precise integration/launch gate evidence.')

ticket('production-hardening-and-recovery', 'Harden security, performance, deployment and recovery', [5,7,8,9,23,24,32,33,34,35], 'Q002, Q006, Q026; cross-cutting requirements',
'''Prepare the completed modules for a controlled deployment/pilot through measured performance, adversarial permission checks and reproducible recovery. Do not publish to production merely because this ticket is requested.''',
'''Create deployment/environment runbooks, workload definition/results, security checklist/findings, backup/restore evidence, monitoring/alert configuration, retention policy, incident procedures and update compatibility matrix.''',
'''Exercise horizontal/vertical access across APIs/jobs/cache/files/reports, session/PIN controls, public lookup limits, XSS/CSRF/upload protections and secret/log redaction. Verify database least privilege and configuration defaults; fix demonstrated defects within scope.
Measure representative catalog/search/checkout/report/queue workloads on stated hardware. Tune indexes/eager loading/pagination and isolate expensive reporting. Track actual p95/error/concurrency results without inventing production scale. Keep cashier UI responsive in both themes.
Configure reproducible server/worker/scheduler deployment, health checks, migration compatibility and rollback/forward approach. Validate old offline clients and pending queues through update. Verify print-helper update/security lifecycle on supported devices.
Test encrypted backup restoration in isolation and tenant-specific recovery procedure. Set RPO/RTO/retention from verified deployment choices and report measured restoration. Include merchant onboarding, subscription restriction recovery, offboarding/export and incident triage.''',
'''No broad new product module. Operational endpoints are restricted and expose necessary health/metrics only. Deployment automation uses environment-specific credentials supplied securely. Production side effects need authorization separate from local hardening.''',
'''Run scoped security/integration/load suite, restore backup and reconcile ledgers, restart workers without duplicate effects and test client update with unsent cash sale. Document untested hardware/provider limits. Critical defects fixed; no false claim that a checklist alone proves security/compliance.''')

ticket('integrated-pilot-and-release-gate', 'Run complete cross-module acceptance and prepare the pilot', [25,26,27,28,29,30,31,32,33,34,35,36], 'All launch decisions',
'''Verify the whole agreed counter POS as an integrated product, reconcile all module interfaces and produce a concrete pilot/release readiness report. This is not permission to expand scope or automatically deploy.''',
'''Create docs/verification/launch-acceptance.md, scenario-results.md, known-limitations.md, operator-training.md and pilot-runbook.md. Update every ticket status with actual evidence and unresolved dependencies.''',
'''Execute S01–S28 with realistic synthetic datasets in both store modes and both themes. Include cash/external split payment, cached offline promotions, restart/replay, original-sale returns/late approvals, cross-store loyalty, purchase/stock cost and payroll/cash expense reconciliation.
Verify remaining downstream integrations: loyalty hooks in checkout/returns, fiscal corrections, receipt/email outbox, reports/exports and second display. Remove or clearly disable unfinished placeholder affordances; no required workflow may be substituted by demo data.
Test onboarding to first sale, staff training flows, device setup, owner renewal, incident recovery and offboarding. Record actual printer/customer-display/browser configurations and sandbox results. Classify blockers versus known non-blocking limitations against agreed launch scope.
Review all source-to-target decisions, Q&A traceability and excluded-feature boundaries. Provide an implementation/release summary with risks grounded in actual tests. User decides production/pilot rollout based on this concrete evidence.''',
'''All documented public/domain contracts match running implementation. Generated API types, schema docs and golden fixtures are up to date. Any API change has compatible offline version handling. Deferred messaging/connectors remain outside launch sign-off.''',
'''All required scenario outcomes have passed evidence or a named blocker. Report totals reconcile to source ledgers; no cross-tenant exposure; pending offline records recover safely. Both-theme visual review and PDF/thermal checks completed. No claim of readiness if critical fiscal/hardware prerequisites remain unmet.''')

ticket('optional-sms-whatsapp', 'Deferred extension: opt-in SMS and WhatsApp notifications', [34,37], 'Q032',
'''When explicitly requested after launch foundation, add optional provider-backed SMS/WhatsApp channels with merchant on/off settings. This ticket is deferred and must not become a dependency for launch or phone-only member lookup.''',
'''Create channel-provider configurations, protected credentials, templates/approval metadata, consent records, usage/cost metadata, delivery attempts and callback state, reusing the notification abstraction.''',
'''Research current Pakistan support, provider terms, template/consent requirements, pricing and delivery callbacks before selecting integration. Do not purchase messaging or contact providers without authorization.
Allow authorized merchant setup/test, enable/disable per channel and allowed message class. Enabling without valid configuration must fail clearly. Disabling stops new sends and handles queued work according to a documented policy; it does not silently erase delivery history.
Implement receipts/allowed alerts using minimum necessary data, idempotent outbox, opt-out handling and rate/cost controls. Keep transactional and marketing purposes separate. Phone-only loyalty lookup remains the chosen launch behaviour; do not change it to mandatory OTP as a side effect.
Show truthful accepted/delivered/failed states based on provider evidence. Use a sandbox/test sink until real sends are explicitly authorized. Record provider-dependent restrictions and costs.''',
'''Provider adapters implement existing NotificationChannel contract; callbacks authenticate and deduplicate. Settings responses never expose stored secrets. Feature entitlements/toggles work per merchant without affecting email/in-app alerts.''',
'''Test toggles, incomplete setup, queued sends after disable, duplicate callbacks, invalid recipient, opt-out, failure retry and tenant isolation. No unintended real messages. Light/dark settings and delivery screens verified. Document actual provider validation separately.''',
gate='Deferred. Execute only when specifically requested; never auto-enable it while implementing email or loyalty.')

assert len(items) == 38
TICKETS.mkdir(parents=True, exist_ok=True)
for i, t in enumerate(items, 1):
    t['id'] = f'T{i:03}'
    t['filename'] = f'{i:03}-{t["slug"]}.md'

context = '''The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.'''

def bullets(s):
    return '\n'.join('- ' + p.strip() for p in s.strip().split('\n') if p.strip())

for t in items:
    path = TICKETS / t['filename']
    if path.exists():
        raise SystemExit(f'Refusing to overwrite maintained ticket: {path}')
    deptext = ', '.join(f'[{items[n-1]["id"]}]({items[n-1]["filename"]})' for n in t['deps']) or 'None — start here.'
    status = 'Deferred — explicit later request required' if t['id']=='T038' else 'Planned — not executed'
    body = f'''# {t['id']} — {t['title']}

Version: 1.0 | Created: 2026-09-28 | Status: {status}

## Outcome

{t['objective']}

## Dependencies and decisions

- Prerequisites: {deptext}
- User-decision references: {t['decisions']} in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

{context}

## Records, artifacts and ownership

{t['records']}

## Required implementation or research work

{bullets(t['work'])}

## Interfaces, permissions and user experience

{t['interface']}

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

{bullets(t['checks'])}

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

{t['sources'] or 'Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.'}

## Boundaries and failure handling

{t['gate'] or 'If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.'}

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: {status}
- Execution date / application commit or working-tree evidence: not executed.
- Files/modules changed: none.
- Schema/API/event changes: none implemented.
- Tests actually run and results: none for this ticket.
- Light/dark visual evidence: pending if UI applies.
- Hardware/provider evidence: pending if applicable.
- Decisions/contract deviations and downstream impact: none yet.
- Remaining blockers/limitations and next action: execute prerequisites, then this ticket.
'''
    path.write_text(body, encoding='utf-8')

index = '''# POS implementation ticket library

Created 2026-09-28. This library records the agreed scope and execution instructions. **The application has not been built and the implementation tickets have not been executed.** The current work creates documentation only.

## Start here

Ask: `Execute T001 from docs/tickets, follow its dependencies and completion checklist.` Continue in numerical order by default. T001–T004 are source audit/research/contract work; T005 begins application implementation. T038 is a deferred messaging extension, not part of launch.

Every ticket includes context, prerequisite links, user-decision references, artifacts/data ownership, work steps, interfaces/permissions, acceptance checks, boundaries and an execution record. Shared documents hold the full Q&A and cross-module contracts so later sessions do not need this chat.

## Shared documents

- [Product charter and exclusions](01-product-charter.md)
- [All 41 Q&A answers and five explicit instructions](02-conversation-and-decisions.md)
- [Architecture and proposed contracts](03-architecture-and-contracts.md)
- [Frontend and easy light/dark switching](04-frontend-and-two-themes.md)
- [Data/domain invariants](05-data-and-domain-invariants.md)
- [Research evidence and remaining verification](06-research-evidence-and-open-questions.md)
- [Security, offline and operations](07-security-offline-and-operations.md)
- [28 integrated acceptance scenarios](08-cross-module-scenarios.md)
- [One-ticket execution workflow](09-ticket-execution-guide.md)
- [Ticket status](10-ticket-status.md)
- [Decision-to-ticket traceability](11-requirement-traceability.md)

## Numbered tickets

'''
for t in items:
    index += f'- [{t["id"]} — {t["title"]}](tickets/{t["filename"]})\n'
index += '''
## How completeness is treated

This is a detailed execution library, not a claim that research, provider access or physical hardware checks are already done. Open technical/fiscal facts have owning research tickets. Later discovery must update evidence and downstream contracts without silently expanding product scope.

The explicit removals remain excluded. Advanced feature ideas discussed before the final answers are preserved as history only, not as authorization. Optional customer display is included at launch; SMS/WhatsApp is deferred; automated payment/accounting/commerce connectors require a new separately authorized scope.
'''
(DOCS/'README.md').write_text(index,encoding='utf-8')
status = '# Ticket status\n\nDocumentation created 2026-09-28. No application ticket has been executed. Update after each ticket; keep evidence in its completion record.\n\n'
for t in items:
    state='Deferred' if t['id']=='T038' else 'Planned'
    status += f'- [{t["id"]}](tickets/{t["filename"]}) — {state}: {t["title"]}.\n'
(DOCS/'10-ticket-status.md').write_text(status,encoding='utf-8')

mapping={
1:[1,4,7],2:[2,23,36],3:[3,35],4:[1,4,5],5:[33],6:[2,5,6,24],7:[2,24],8:[3,35],
9:[21,22],10:[14,15,16],11:[21,22],12:[11,21],13:[17,18,19],14:[13,22],15:[22,31],16:[26],17:[27],18:[12,21],19:[26,27],20:[8,29,30],21:[30],22:[10,12],23:[20],24:[2,33],25:[7,16,32],26:[9],27:[7],28:[23],29:[14,15,23],30:[6,24,28],31:[32],32:[24,34,38],33:[28],34:[24,25],35:[31],36:[28],37:[8,29,30],38:[18,26],39:[28],40:[26],41:[7,27,28]}
trace = '# Requirement-to-ticket traceability\n\nAll questions are preserved in [Q&A](02-conversation-and-decisions.md). Exclusions are active acceptance constraints, not missing tickets. Proposed implementation defaults are distinguished in the architecture document.\n\n## Confirmed questions\n\n'
for q, ids in mapping.items():
    links=', '.join(f'[{items[n-1]["id"]}](tickets/{items[n-1]["filename"]})' for n in ids)
    trace += f'- Q{q:03}: {links}.\n'
trace += '''
## Explicit instructions and cross-cutting requirements

- D001 recipes/ingredients/preparation/wastage excluded: charter, T001 source disposition, all catalog/inventory/checkout boundaries.
- D002 reservations/delivery removed; counter-only selected in Q009: charter and T021/T022; no hidden table/kitchen work.
- D003 no C# recovery: T001/T002 research boundaries.
- D004 numbered detailed Markdown execution files: this library and execution guide.
- D005 light/dark themes: T006 foundation and every subsequent UI ticket; T037 integrated review.
- Tax/rounding/financial conservation: T004 fixtures; T012/T018/T022/T026/T027/T030; T032 reconciliation.
- Tenancy/permissions: T007/T008 across every API/job/report/export; T036 adversarial checks.
- Offline durability/idempotency: T022/T023/T024/T035/T036, S08–S13 and S23–S28.
- Audit/corrections/retention: shared invariants and every posting ticket; T036 recovery.
- Source evidence and competitor/official research: T001–T003; no claim of full study in the initial inventory.
- Cross-module tests, hardware feasibility, pilot and documentation maintenance: T002/T024/T025/T035/T036/T037.

## Deferred and intentionally absent

- SMS/WhatsApp: T038, later explicit request.
- Live accounting/commerce connectors, automated payment acquisition and automatic SaaS billing: no implementation ticket in this launch library; research/export extension points only.
- Historical data migration, store-mode conversion, customer credit, advances/layaway, recipes, reservations/delivery/kitchen/table service, serials, direct scales, repairs/rentals, full ledger, statutory payroll engine, custom report/dashboard builders and broad self-service ordering: excluded or separately scoped later, never silently implemented.
'''
(DOCS/'11-requirement-traceability.md').write_text(trace,encoding='utf-8')
(ROOT/'README.md').write_text('# MyPoS\n\nApplication planning and implementation ticket library. No application code has been implemented yet.\n\nStart with the [documentation index](docs/README.md), then execute numbered tickets individually. The full Q&A, scope, dependencies, two-theme frontend standard and acceptance checks are preserved there.\n',encoding='utf-8')
print(f'Created {len(items)} numbered tickets plus indexes. Markdown is now authoritative.')
