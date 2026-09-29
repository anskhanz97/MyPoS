# Requirement-to-ticket traceability

All questions are preserved in [Q&A](02-conversation-and-decisions.md). Exclusions are active acceptance constraints, not missing tickets. Proposed implementation defaults are distinguished in the architecture document.

## Confirmed questions

- Q001: [T001](tickets/001-source-audit.md), [T004](tickets/004-freeze-domain-contracts.md), [T007](tickets/007-merchant-and-store-tenancy.md).
- Q002: [T002](tickets/002-technical-and-product-research.md), [T023](tickets/023-offline-pwa-and-reconciliation.md), [T036](tickets/036-production-hardening-and-recovery.md).
- Q003: [T003](tickets/003-pakistan-fiscal-research.md), [T035](tickets/035-verified-fiscal-adapters.md).
- Q004: [T001](tickets/001-source-audit.md), [T004](tickets/004-freeze-domain-contracts.md), [T005](tickets/005-application-foundation.md).
- Q005: [T033](tickets/033-imports-and-exports.md).
- Q006: [T002](tickets/002-technical-and-product-research.md), [T005](tickets/005-application-foundation.md), [T006](tickets/006-frontend-shell-and-themes.md), [T024](tickets/024-receipts-printing-and-drawer.md).
- Q007: [T002](tickets/002-technical-and-product-research.md), [T024](tickets/024-receipts-printing-and-drawer.md).
- Q008: [T003](tickets/003-pakistan-fiscal-research.md), [T035](tickets/035-verified-fiscal-adapters.md).
- Q009: [T021](tickets/021-checkout-cart-and-quotes.md), [T022](tickets/022-sale-finalization-and-tenders.md).
- Q010: [T014](tickets/014-inventory-ledger.md), [T015](tickets/015-batch-and-expiry-tracking.md), [T016](tickets/016-stock-transfers-and-counts.md).
- Q011: [T021](tickets/021-checkout-cart-and-quotes.md), [T022](tickets/022-sale-finalization-and-tenders.md).
- Q012: [T011](tickets/011-catalog-and-product-types.md), [T021](tickets/021-checkout-cart-and-quotes.md).
- Q013: [T017](tickets/017-suppliers-and-purchase-orders.md), [T018](tickets/018-receiving-and-moving-average-cost.md), [T019](tickets/019-purchase-returns-and-supplier-balances.md).
- Q014: [T013](tickets/013-customers-and-local-records.md), [T022](tickets/022-sale-finalization-and-tenders.md).
- Q015: [T022](tickets/022-sale-finalization-and-tenders.md), [T031](tickets/031-expenses-and-settlement-records.md).
- Q016: [T026](tickets/026-returns-and-exchanges.md).
- Q017: [T027](tickets/027-loyalty-points-and-rewards.md).
- Q018: [T012](tickets/012-pricing-and-promotion-modes.md), [T021](tickets/021-checkout-cart-and-quotes.md).
- Q019: [T026](tickets/026-returns-and-exchanges.md), [T027](tickets/027-loyalty-points-and-rewards.md).
- Q020: [T008](tickets/008-authentication-and-fixed-roles.md), [T029](tickets/029-workforce-attendance-rota-leave.md), [T030](tickets/030-operational-payroll.md).
- Q021: [T030](tickets/030-operational-payroll.md).
- Q022: [T010](tickets/010-register-provisioning-and-settings.md), [T012](tickets/012-pricing-and-promotion-modes.md).
- Q023: [T020](tickets/020-cash-register-sessions.md).
- Q024: [T002](tickets/002-technical-and-product-research.md), [T033](tickets/033-imports-and-exports.md).
- Q025: [T007](tickets/007-merchant-and-store-tenancy.md), [T016](tickets/016-stock-transfers-and-counts.md), [T032](tickets/032-standard-reporting.md).
- Q026: [T009](tickets/009-plans-and-platform-administration.md).
- Q027: [T007](tickets/007-merchant-and-store-tenancy.md).
- Q028: [T023](tickets/023-offline-pwa-and-reconciliation.md).
- Q029: [T014](tickets/014-inventory-ledger.md), [T015](tickets/015-batch-and-expiry-tracking.md), [T023](tickets/023-offline-pwa-and-reconciliation.md).
- Q030: [T006](tickets/006-frontend-shell-and-themes.md), [T024](tickets/024-receipts-printing-and-drawer.md), [T028](tickets/028-public-member-join-and-lookup.md).
- Q031: [T032](tickets/032-standard-reporting.md).
- Q032: [T024](tickets/024-receipts-printing-and-drawer.md), [T034](tickets/034-email-and-in-app-notifications.md), [T038](tickets/038-optional-sms-whatsapp.md).
- Q033: [T028](tickets/028-public-member-join-and-lookup.md).
- Q034: [T024](tickets/024-receipts-printing-and-drawer.md), [T025](tickets/025-optional-customer-display.md).
- Q035: [T031](tickets/031-expenses-and-settlement-records.md).
- Q036: [T028](tickets/028-public-member-join-and-lookup.md).
- Q037: [T008](tickets/008-authentication-and-fixed-roles.md), [T029](tickets/029-workforce-attendance-rota-leave.md), [T030](tickets/030-operational-payroll.md).
- Q038: [T018](tickets/018-receiving-and-moving-average-cost.md), [T026](tickets/026-returns-and-exchanges.md).
- Q039: [T028](tickets/028-public-member-join-and-lookup.md).
- Q040: [T026](tickets/026-returns-and-exchanges.md).
- Q041: [T007](tickets/007-merchant-and-store-tenancy.md), [T027](tickets/027-loyalty-points-and-rewards.md), [T028](tickets/028-public-member-join-and-lookup.md).

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

## Optional extensions and intentionally absent

- SMS/WhatsApp and the merchant integration hub: T038 implemented locally after explicit request; provider activation remains optional and credential-gated.
- Live accounting/commerce connectors, automated payment acquisition and automatic SaaS billing: no implementation ticket in this launch library; research/export extension points only.
- Historical data migration, store-mode conversion, customer credit, advances/layaway, recipes, reservations/delivery/kitchen/table service, serials, direct scales, repairs/rentals, full ledger, statutory payroll engine, custom report/dashboard builders and broad self-service ordering: excluded or separately scoped later, never silently implemented.
