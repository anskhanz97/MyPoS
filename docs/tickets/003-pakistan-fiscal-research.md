# T003 — Define Pakistan fiscal applicability and launch gates

Version: 1.0 | Created: 2026-09-28 | Status: Verified — completed 2026-09-28

## Outcome

Determine the fiscal requirements relevant to the target merchant activities and locations, using current official sources. Produce implementable, jurisdiction-specific contracts where evidence permits, and explicit external verification gates where it does not.

## Dependencies and decisions

- Prerequisites: [T001](001-source-audit.md), [T002](002-technical-and-product-research.md)
- User-decision references: Q003, Q008, Q024, Q030, Q040 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create docs/research/pakistan-fiscal-applicability.md, fiscal-api-contracts.md and fiscal-launch-gates.md. Record authority, jurisdiction, activity, effective date, legal basis, required documents, credentials, sandbox/production access and evidence status. No real credentials belong in these files.

## Required implementation or research work

- Distinguish FBR digital invoicing/POS rules and applicable provincial/territorial requirements. Do not assume a universal rate, uniform invoice format or one integration for every merchant. Excluded table/delivery functionality must not reappear through compliance research.
- Investigate invoice numbering, tax identification/product classifications, prices inclusive/exclusive of tax, returns/corrections, tax-exempt cases, receipt/QR obligations, transmission timing and retained evidence. Determine whether and how cash selling during outages can operate for each applicable regime.
- Research uncertain outcomes, idempotency or reference lookup, rejected records, unavailable service, backdated corrections and credential renewal. Separate a local sale receipt from fiscal acceptance; document what the user may truthfully display in each state.
- Consider statutory requirements affecting refund policies and payroll inputs without expanding operational payroll into a statutory engine. Identify merchant facts required during onboarding. Where facts/API access are unavailable, state the exact gating question rather than invent a legal rule.

## Interfaces, permissions and user experience

Specify a FiscalAdapter contract and jurisdiction configuration schema proposal for T004/T035. Tax calculations are versioned, reproducible and linked to applicability. No automated merchant card acquisition is required. Publication/deployment compliance is not established by this research ticket.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Every claimed obligation links to an official dated source. A supported-merchant applicability checklist exists. Offline requirements have no unexamined conflict with fiscal requirements. Sandbox plans and production gates are separate. Unverified legal/provider matters cannot be marked complete merely because an adapter interface exists.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Official FBR and relevant revenue-authority documentation; SBP only where payment context is relevant.

## Boundaries and failure handling

If external clarification is required, finish all available research and record a named launch blocker; do not contact third parties without authorization.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — completed.**
- Execution date / application commit or working-tree evidence: 2026-09-28. This workspace has no Git metadata; evidence is the three saved fiscal research artifacts and passing documentation audit.
- Files/modules changed: added [Pakistan fiscal applicability](../research/pakistan-fiscal-applicability.md), [fiscal API contracts](../research/fiscal-api-contracts.md), and [fiscal launch gates](../research/fiscal-launch-gates.md). Updated this completion record and [ticket status](../10-ticket-status.md).
- Schema/API/event changes: none implemented. Proposed `FiscalJurisdictionConfig`, fiscal document/attempt records, state machine and `FiscalAdapter` contract are inputs to T004/T035 only.
- Tests actually run and results: `scripts/check-docs.py` PASS — 63 Markdown files, 38 numbered tickets, 609 checked local links, 60,254 words, zero errors. Official FBR/PRAL DI v1.12, S.R.O. 69(I)/2025, S.R.O. 1413(I)/2025, STGO 01/2026, SRB rules/FAQ/API material, KP/KPRA rules/API portal, BRA rules, ICT ordinance and selected consumer/payroll sources were reviewed. No sandbox, production endpoint, credential, receipt verification or real outage test was performed.
- Light/dark visual evidence: not applicable because no UI was introduced. The launch gates require all fiscal states and receipt/reconciliation flows to be keyboard accessible and verified in both complete themes.
- Hardware/provider evidence: no hardware or live provider operation occurred. KPRA officially documents a local Windows queueing utility, but its security, installer, canonical payload and replay behavior remain untested. Public KPRA documentation also contains conflicting live field names, recorded as a blocking sandbox question.
- Decisions/contract deviations and downstream impact: no product-scope deviation. Fiscal applicability is per legal registration, store and activity; no universal Pakistan rate or adapter. FBR DI is presumed required for a federally sales-tax-registered merchant until an approved decision establishes otherwise. Authority acceptance remains separate from local sale completion. The general 72-hour offline lease is subordinate to the authority-specific outage rule. T004 must freeze the normalized state/contracts; T035 must implement only verified adapters and satisfy merchant-specific production gates.
- Remaining blockers/limitations and next action: fiscal compliance is not certified by this research. FBR duplicate/lookup/QR payload/offline behavior and legacy POS transition need licensed-integrator sandbox evidence. PRA, SRB, BRA and ICT need current authority-specific outage/onboarding evidence; AJK and GB remain unsupported pending authoritative technical packs. These are explicit launch blockers rather than T003 research-completion blockers. Proceed to T004 and preserve every gate.
