# How to execute one ticket at a time

## User invocation

Example: `Execute T006 from docs/tickets. Read its context and dependencies, implement only that ticket, verify it, and update its completion record.`

Numbered order was the default implementation sequence. T001–T004 produced research/design evidence and frozen contracts; T005 began application code. T001–T038 now have completion records. Future work should start from current code and these records rather than replaying tickets blindly.

## Before implementation

1. Read the requested ticket, [charter](01-product-charter.md), [Q&A](02-conversation-and-decisions.md), [architecture](03-architecture-and-contracts.md), [frontend standard](04-frontend-and-two-themes.md), [invariants](05-data-and-domain-invariants.md) and [security/operations](07-security-offline-and-operations.md).
2. Check prerequisite completion records and current repository state. Do not infer completion from filenames or ticket order. If a dependency is absent, explain the concrete dependency and perform only independently valid work in the requested scope.
3. Inspect actual implementation/contracts before editing. Later user instructions and approved decisions beat stale proposed defaults; record the change and affected downstream tickets.
4. Keep `C:\XEPOS` read-only. Do not reuse its `.env`, contact its production integrations or run destructive commands against configured databases.
5. Use skills when they improve the task. Frontend-design applies to new UI, but the POS usability/accessibility/theme contract takes precedence over decorative choices.

## During implementation

Implement cohesive domain logic, API, validation, authorization and screens required by the ticket. Do not substitute static mock screens for required workflows. Do not start unrelated future modules merely to make the ticket look complete.

If an interface is needed from a later ticket, define a narrow explicit contract and test double only where the current ticket permits it. Label the integration unverified until the dependent implementation exists. Integrate earlier features as downstream tickets become available; T037 catches cross-module completion.

Use exact decimals and tenant context, idempotency, audit/reversal patterns, and both themes consistently. Keep optional/deferred features disabled unless their ticket is requested. Record genuine missing decisions; research and choose routine technical details rather than repeatedly asking questions already answered here.

## Verification

Run meaningful scoped domain/API/browser tests. Use MySQL for transactional/concurrency checks. For UI, inspect light and dark, focus, validation, empty/loading/error states and narrow layout where applicable. Check that theme toggling preserves active work. Test printer/PDF output independently when changed.

Do not claim production deployment, legal compliance, provider approval, hardware certification or completed tests without evidence. Complete reversible local checks before requesting final external permissions. The documentation request alone does not authorize production writes, purchases, provider messages or deployment.

## Required completion record

Append/update these fields in the ticket and [status index](10-ticket-status.md):

- Status: planned / in progress / implemented-awaiting-integration / verified / blocked / deferred.
- Date and application commit, or explicit no-commit working-tree snapshot description.
- Files/modules changed and migration/schema changes.
- APIs/events/contracts introduced or changed.
- Tests/commands actually run with result and environment.
- Light/dark visual checks and evidence where applicable.
- Hardware/provider checks, clearly distinguishing simulation from real validation.
- Decisions made, exceptions and downstream impacts.
- Remaining limitations and exact next action; no vague "done except integration".

Never overwrite the requirement text with a success summary. Keep requirements and completion evidence separate. New changes update version/history and traceability.

## Ticket size and scope control

Each ticket is one domain-focused work package, not a promise to fit one short response. A ticket can require sustained work. If it truly needs subdivision, preserve the parent acceptance criteria and create explicit child IDs with dependencies before coding; do not silently drop work. The user remains able to request one file at a time.

## Documentation maintenance

Markdown is authoritative; a future PDF can be generated after the source library is stable, but no PDF is required for this delivery. Keep relative links portable within `docs`; user-facing chat links should use absolute filesystem paths. Update the Q&A only with actual new user answers, and label agent-proposed technical decisions as such.
