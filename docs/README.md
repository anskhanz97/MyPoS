# MyPoS documentation index

Created 2026-09-28; reconciled 2026-09-29. This library records product decisions, contracts, research, implementation tickets, operational guidance and verification evidence. **T001–T038 are implemented in the current working tree. External provider, hardware, hosting, fiscal and clean-release gates remain open.**

## Start here

For maintenance, begin with the root [project memory](../README.md), the relevant ticket completion record, current contracts and the actual implementation. The original numbered order remains useful history: T001–T004 established evidence/contracts, T005 began the application and T038 added the optional integration hub and messaging extension outside launch dependencies.

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
- [Documentation verification and its limits](12-library-verification.md)
- [Integration hub frontend and provider behavior](frontend/integrations.md)

## Frozen implementation contracts

- [Domain model, ownership and command boundaries](contracts/domain-model.md)
- [Fixed-role permission matrix](contracts/permission-matrix.md)
- [OpenAPI 3.1 contract](contracts/api.yaml)
- [Event and outbox catalogue](contracts/event-catalog.md)
- [Aggregate state machines](contracts/state-machines.md)
- [Arithmetic, rounding and valuation policy](contracts/arithmetic-policy.md)
- [Golden fixtures and S01–S28 ownership](contracts/golden-scenarios.md)
- [Architecture decision records](contracts/architecture-decisions.md)

## Numbered tickets

- [T001 — Inventory and audit the five reference projects](tickets/001-source-audit.md)
- [T002 — Research POS practices and recommend the technical stack](tickets/002-technical-and-product-research.md)
- [T003 — Define Pakistan fiscal applicability and launch gates](tickets/003-pakistan-fiscal-research.md)
- [T004 — Freeze schema, domain contracts and acceptance fixtures](tickets/004-freeze-domain-contracts.md)
- [T005 — Create Laravel/Vue/MySQL application and test foundation](tickets/005-application-foundation.md)
- [T006 — Build the UI foundation with complete light and dark themes](tickets/006-frontend-shell-and-themes.md)
- [T007 — Implement merchants, stores and locked data-ownership modes](tickets/007-merchant-and-store-tenancy.md)
- [T008 — Implement staff authentication, fixed roles and approvals](tickets/008-authentication-and-fixed-roles.md)
- [T009 — Implement platform administration and manual SaaS subscriptions](tickets/009-plans-and-platform-administration.md)
- [T010 — Implement registers, device provisioning and versioned settings](tickets/010-register-provisioning-and-settings.md)
- [T011 — Implement flexible catalog, variants, barcodes and pack conversions](tickets/011-catalog-and-product-types.md)
- [T012 — Implement versioned prices and two promotion modes](tickets/012-pricing-and-promotion-modes.md)
- [T013 — Implement customer profiles and store-scoped operational records](tickets/013-customers-and-local-records.md)
- [T014 — Implement inventory movements, balances and audited adjustments](tickets/014-inventory-ledger.md)
- [T015 — Implement batches, expiry and sellable-stock selection](tickets/015-batch-and-expiry-tracking.md)
- [T016 — Implement stock transfers, stocktakes and reconciliation](tickets/016-stock-transfers-and-counts.md)
- [T017 — Implement suppliers and purchase order lifecycle](tickets/017-suppliers-and-purchase-orders.md)
- [T018 — Implement partial receiving and moving weighted-average valuation](tickets/018-receiving-and-moving-average-cost.md)
- [T019 — Implement purchase returns, supplier balances and payments](tickets/019-purchase-returns-and-supplier-balances.md)
- [T020 — Implement cash sessions, floats, cash movements and closeout](tickets/020-cash-register-sessions.md)
- [T021 — Build cashier checkout, scanning, carts and quotes](tickets/021-checkout-cart-and-quotes.md)
- [T022 — Implement atomic sale posting and fully settled split tenders](tickets/022-sale-finalization-and-tenders.md)
- [T023 — Implement offline cash checkout, durable queue and recovery](tickets/023-offline-pwa-and-reconciliation.md)
- [T024 — Implement thermal/PDF receipts, print helper and cash drawer](tickets/024-receipts-printing-and-drawer.md)
- [T025 — Implement optional second-screen customer display](tickets/025-optional-customer-display.md)
- [T026 — Implement original-sale returns, exchanges and deadline approvals](tickets/026-returns-and-exchanges.md)
- [T027 — Implement merchant-wide points, rewards and refund reversals](tickets/027-loyalty-points-and-rewards.md)
- [T028 — Build the single-form public loyalty join and limited profile page](tickets/028-public-member-join-and-lookup.md)
- [T029 — Implement staff records, attendance, rota and leave](tickets/029-workforce-attendance-rota-leave.md)
- [T030 — Implement operational payroll, payslips and recorded payouts](tickets/030-operational-payroll.md)
- [T031 — Implement expenses and operational payment reconciliation](tickets/031-expenses-and-settlement-records.md)
- [T032 — Implement standard reports, saved filters and drill-down](tickets/032-standard-reporting.md)
- [T033 — Implement validated imports, opening balances and portable exports](tickets/033-imports-and-exports.md)
- [T034 — Implement email receipts and in-app operational alerts](tickets/034-email-and-in-app-notifications.md)
- [T035 — Implement applicable Pakistan fiscal adapters and exception handling](tickets/035-verified-fiscal-adapters.md)
- [T036 — Harden security, performance, deployment and recovery](tickets/036-production-hardening-and-recovery.md)
- [T037 — Run complete cross-module acceptance and prepare the pilot](tickets/037-integrated-pilot-and-release-gate.md)
- [T038 — Merchant integration hub with opt-in SMS and WhatsApp](tickets/038-optional-sms-whatsapp.md)

## How completeness is treated

This is an implementation and evidence library, not a claim that provider access or physical hardware checks are done. Open environmental/fiscal facts have named gates. Later discovery must update evidence and downstream contracts without silently expanding product scope.

The explicit removals remain excluded. Advanced feature ideas discussed before final answers are history, not authorization. Optional customer display is included; SMS/WhatsApp are implemented as opt-in receipt channels; direct payment acquisition and accounting/commerce synchronization remain separately scoped.
