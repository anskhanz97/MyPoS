# Architecture decision records

Version: 1.0 | Frozen: 2026-09-29 | Ticket: [T004](../tickets/004-freeze-domain-contracts.md)

## ADR-001 — Modular monolith and same origin

Status: accepted.

Decision: one Laravel 13 modular monolith serves a same-origin Vue application and versioned API. MySQL is authoritative; Redis supports queues/cache/locks; object storage holds exports/assets. Application, worker and scheduler are separate processes from one release.

Rationale: sale, tender, stock and cash need one database transaction. The current scale does not justify distributed transactions or independent services.

Impact: modules own tables/services and communicate through explicit services/outbox events. Extraction requires a later ADR.

## ADR-002 — Public UUIDv7 plus internal bigint

Status: accepted.

Decision: `BIGINT UNSIGNED` internal keys and UUIDv7 public IDs stored compactly. Client/offline commands use UUIDv7.

Rationale: efficient joins, opaque public identity and time-sortable decentralized IDs.

Impact: APIs never expose internal keys; imports/integrations map external identifiers separately.

## ADR-003 — Locked store data mode with separate loyalty scope

Status: accepted from Q025/Q027/Q041.

Decision: shared or independent operational catalogue/customer scope is chosen at onboarding and locked. Loyalty is always merchant-wide, joined explicitly to operational customers.

Impact: mode conversion is a separately designed migration. SKU/phone equality across independent stores does not merge records.

## ADR-004 — Append-only posted ledgers and compensating corrections

Status: accepted.

Decision: posted sale, tender, inventory, cash, loyalty, payroll, expense and fiscal effects are immutable. Corrections reference originals.

Rationale: auditability, retries and reconciliation require historical truth.

Impact: generic CRUD deletion is prohibited. Reports account for reversals.

## ADR-005 — Decimal strings and fixed precision

Status: accepted.

Decision: API money/quantity/rates are strings. PKR document totals/tenders settle to 2 decimals; internal allocations/unit amounts use 4 decimals, quantity 6, conversions 8 and rates 6. Half-up is the launch rounding mode unless a versioned fiscal rule requires another.

Impact: PHP `brick/math` and browser `decimal.js` consume shared fixtures. Floats are barred from authoritative calculations.

## ADR-006 — Command idempotency and transactional outbox

Status: accepted.

Decision: retryable mutations use scoped idempotency plus canonical payload fingerprint. Core effects and outbox are one MySQL transaction. Network delivery is at-least-once; business effects are effectively once.

Impact: workers and consumers deduplicate. Ambiguous responses resolve by key/lookup rather than new documents.

## ADR-007 — Bounded cash-only offline protocol

Status: accepted with fiscal gate.

Decision: IndexedDB holds an authorized snapshot and durable outbox. Default register lease is 72 hours, staff auth 24 hours, capacity 10,000 pending sales or 100 MiB. Offline finalization is cash with cached permitted discounts; no loyalty redemption or external-payment confirmation.

Impact: authority-specific fiscal outage rules may shorten or block offline selling. Updates preserve pending records.

## ADR-008 — Local helper for thermal print and drawer

Status: accepted for qualification.

Decision: evaluate QZ Tray/local signed helper for raw ESC/POS and drawer. Server signs short-lived canonical jobs; browser contains no signing private key.

Impact: T024 must qualify exact hardware/helper/version and preserve sale success when printing fails.

## ADR-009 — Fiscal acceptance separate from local sale

Status: accepted.

Decision: local posted sale and fiscal document are distinct records/states. Receipts claim authority acceptance only after confirmed authority-level success.

Impact: `pending`, `rejected` and `unknown` are user-visible/reconcilable. Merchant launch depends on T003 gates.

## ADR-010 — Fixed roles and narrow approvals

Status: accepted from Q037.

Decision: owner, manager and cashier roles are fixed. A short-lived, target-specific approval authorizes exceptional actions; it does not elevate the whole session.

Impact: no role designer. Manager payroll access is denied by default.

## ADR-011 — Two complete themes as foundation

Status: accepted from D005.

Decision: semantic-token light and dark themes apply to every surface and state. Explicit preference persists; switch never reloads or changes domain state. Print is black-on-white.

Impact: each UI ticket includes both-theme screenshots and keyboard/accessibility evidence.

## ADR-012 — Moving weighted average accounting

Status: accepted from Q038.

Decision: moving weighted average values stock independently of physical batch selection. Normal positive-stock receipt uses weighted cost. Negative/backdated/reversal cases use explicit cost reconciliation designed in T018.

Impact: margins can be provisional while reconciliation is pending; reports label this instead of using zero cost.

## ADR-013 — No universal Pakistan fiscal defaults

Status: accepted.

Decision: applicability/rates/fields are versioned per registration, store and activity. Production adapter activation requires authority-specific gates.

Impact: no `pakistan_tax_rate` global constant and no generic “FBR compliant” claim.

## ADR-014 — New merchants and staged imports

Status: accepted from Q005.

Decision: launch targets new merchants with catalogue/customer/opening-stock imports. Full historic migration is absent.

Impact: imports dry-run, identify row errors, commit idempotently and do not fabricate historic sales.

## Change procedure

An ADR change states: superseded decision, reason/evidence, schema/API/event changes, offline compatibility, data migration, affected tickets, rollout/rollback and new acceptance fixtures. Editing these outcomes silently is prohibited.
