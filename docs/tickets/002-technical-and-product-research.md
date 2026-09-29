# T002 — Research POS practices and recommend the technical stack

Version: 1.0 | Created: 2026-09-28 | Status: Verified — completed 2026-09-28

## Outcome

Produce a current, primary-source-backed implementation recommendation for the agreed counter POS. Compare concrete workflows and failure behaviour; do not import excluded restaurant, delivery or recipe functionality.

## Dependencies and decisions

- Prerequisites: [T001](001-source-audit.md)
- User-decision references: Q001–Q008, Q024; D003, D005 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create docs/research/product-comparison.md, stack-versions.md, hardware-feasibility.md, offline-feasibility.md and architecture-recommendation.md. Record access dates and exact framework/provider documentation versions. Recommendations and verified facts must be separately labelled.

## Required implementation or research work

- Study relevant Odoo, ERPNext, Lightspeed, Square, Shopify POS and sufficiently documented Pakistan providers. Separate browser tills from native clients/cloud back offices. Compare scanning, purchasing, batches, promotions, returns, offline limits, workforce, loyalty and reporting; record country/plan/hardware restrictions.
- Verify supported Laravel/PHP, Vue/TypeScript/Vite/Node and MySQL versions and compatible authentication, queue, testing, PDF and decimal libraries. Recommend a pinned set and avoid unneeded packages. Evaluate modular monolith boundaries, same-origin sessions and API compatibility.
- Compare printing-helper approaches, offline authorization, licensing, installation, updates and supported Windows devices. Identify a qualification list for thermal printer/drawer/scanner; do not purchase hardware. Assess browser storage durability, restart, multi-tab coordination, update safety and encryption limits.
- Propose measurable workload profiles, initial performance targets, offline lease/cache bounds and hosting capacity/cost assumptions. Estimates must name assumptions; no fabricated load results. Include accessibility and both-theme testing strategy.

## Interfaces, permissions and user experience

No production integration or new app APIs. Deliver decision-ready technical options with a recommended default, tradeoffs and dependencies for T004. Exact hardware/payment/fiscal support cannot be inferred from another country's vendor docs.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Each important recommendation has a supporting official source or is explicitly an engineering judgment. Dependency support dates are checked. Offline cash and printing feasibility has a test plan. No research claim implies successful hardware or provider testing. Excluded modules stay excluded.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Start from docs/06-research-evidence-and-open-questions.md.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — completed.**
- Execution date / application commit or working-tree evidence: 2026-09-28. This workspace has no Git metadata, so evidence is the five saved research artifacts and the passing documentation audit.
- Files/modules changed: added [product comparison](../research/product-comparison.md), [stack versions](../research/stack-versions.md), [hardware feasibility](../research/hardware-feasibility.md), [offline feasibility](../research/offline-feasibility.md), and [architecture recommendation](../research/architecture-recommendation.md). Updated this completion record and [ticket status](../10-ticket-status.md).
- Schema/API/event changes: none implemented. The architecture document recommends contracts for T004; no application, database or provider was changed.
- Tests actually run and results: `scripts/check-docs.py` PASS — 60 Markdown files, 38 numbered tickets, 598 checked local links, 53,818 words, zero errors. The research acceptance checklist was mapped to the five artifacts. No load benchmark was run; all workload, latency, availability and cost figures are explicitly design targets or planning assumptions.
- Light/dark visual evidence: not applicable because no UI was introduced. The research requires WCAG 2.2 AA and automated plus manual validation of populated, empty, loading, validation, authorization, offline, conflict and error states in both complete themes.
- Hardware/provider evidence: official documentation was reviewed for Odoo 19, ERPNext, Lightspeed, Square, Shopify, browser storage APIs, QZ Tray and named framework dependencies. Pakistani provider pages are labeled as unverified marketing claims. No physical hardware, helper install, payment, fiscal endpoint or provider account was tested, and no printer/scanner/drawer is called supported.
- Decisions/contract deviations and downstream impact: no deviation from the agreed product charter. Recommend Laravel 13/PHP 8.5/Vue 3.5/TypeScript 6/Vite 8/Node 24 LTS/MySQL 8.4 LTS/Redis 8.2 Extended, a same-origin modular monolith, session cookies plus scoped tokens, signed local printing helper evaluation, and a 72-hour cash-only offline register lease with a 24-hour staff-auth lease and bounded IndexedDB outbox. T004 must freeze command, event, posting, helper and offline conflict contracts. T005 must resolve exact lockfile patches and install only used packages. T023 and T024 own real offline/hardware verification.
- Remaining blockers/limitations and next action: Pakistan fiscal requirements remain intentionally assigned to T003. Exact hosting price, observed capacity, browser durability and hardware compatibility remain unverified until their implementation/pilot tickets. Proceed to T003; keep recipes/ingredients/preparation/wastage, reservations/queues/delivery and C# rule recovery excluded.
