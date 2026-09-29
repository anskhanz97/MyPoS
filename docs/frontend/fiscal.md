# Fiscal adapter implementation reference

T035 codes the only public contract that T003 found specific enough to map without guessing: FBR/PRAL Digital Invoicing API technical specification v1.12. The adapter uses fixed authority-owned sandbox/production hosts, encrypted bearer-token storage, effective-dated HS/UOM/rate/sale-type/SRO product mappings, immutable canonical payload snapshots and a stable local document identity. Browser responses expose only `credential_configured`.

The durable state machine is `queued → submitting → accepted | rejected | unknown`. A connection loss, authority 5xx or malformed response becomes `unknown`; it is never automatically assigned a new invoice number or retried. An owner must record official lookup/licensed-integrator evidence to reconcile it. Rejected documents may reuse the same immutable document after the mapping or credential is repaired. Attempts retain request/response hashes, safe categories and authority codes.

Sale and return events are consumed idempotently from the domain outbox. Returns produce a linked FBR debit-note payload with the original sale invoice reference. Offline sale finalization is blocked when an active required configuration has the research-mandated `block_sale` outage policy. Sale APIs and newly rendered receipt PDFs project the actual latest fiscal state/reference while the original sale and receipt snapshot remain immutable.

## Activation boundary

Only an evidence-backed FBR v1.12 **sandbox** configuration can be activated by this build. Production activation is deliberately rejected until a licensed integrator supplies and proves duplicate/lookup, QR payload, retail buyer, cancellation and outage behavior for the merchant. PRA, SRB, KPRA, BRA and ICT appear in the readiness view as blocked with the exact missing contract reason; no payload shape or rate is invented.

Automated tests use captured-shape fixtures and Laravel's fake HTTP transport for accepted, business-rejected and post-send-unknown outcomes. No authority sandbox or production system was contacted. Visual evidence:

- `docs/evidence/t035-fiscal-light.png`
- `docs/evidence/t035-fiscal-dark.png`
