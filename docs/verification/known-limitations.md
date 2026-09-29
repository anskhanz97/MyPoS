# Known limitations and open gates

Version 1.0 — 2026-09-29

## Release blockers

- There is no immutable Git release commit or clean-checkout CI evidence yet.
- No production hosting topology, TLS endpoint, secret store, central telemetry, object storage or point-in-time database recovery is configured.
- Authenticated catalog/checkout/report p95 and multi-register capacity have not been measured against the proposed production-sized dataset.
- No physical Windows thermal printer, local helper, driver or cash drawer has been qualified. Browser tests and protocol simulations do not replace this.
- Receipt email and optional SMS/WhatsApp use tested durable application behavior, but no deployed SMTP, Meta or Twilio provider has been qualified.
- Google Drive, Microsoft OneDrive and Stripe configuration/connection checks require real merchant credentials. Stripe does not acquire payments in the current scope.
- FBR DI v1.12 is sandbox-only in this build and real sandbox credentials have not been exercised. Production FBR activation is rejected. PRA, SRB, KPRA, BRA and ICT adapters are blocked until current official/integrator contracts and merchant applicability evidence exist.
- Fiscal/legal retention and merchant-specific tax applicability require qualified current advice; the software research is not certification.

## Functional boundaries

The application deliberately excludes recipes, ingredients, food preparation/yield and wastage; reservations, queues and delivery operations; reconstruction of missing C# rules; customer debt, layaway or advance-sale liabilities; product serial tracking; and direct payment acquisition/card-data storage. SMS/WhatsApp are implemented as optional receipt channels and are not a launch prerequisite.

Manual external card/wallet/bank tenders store staff-declared reference evidence. They do not prove provider capture or settlement. Public loyalty lookup does not prove phone ownership. Device/browser storage erased before synchronization cannot be reconstructed by the server.

Merchant closure/deletion is unavailable. Export exists, but selective tenant restore, approved erasure policy and legal-hold automation are not complete. Cancellation preserves business records.

## Operational limitations

The local restore covered a small database artifact; it did not restore private object storage or replay binlogs. The provisional RPO/RTO depends on a hosting/PITR decision. The CSP currently permits inline styles for Vue/printing, though scripts are same-origin and the previous inline bootstrap was removed. PHPUnit’s eight committed-state concurrency tests are intentionally isolated from transactional tests and are slower on Windows/MySQL.

Only Chromium was automated. The pilot must qualify the selected supported browser/version and kiosk policies. Accessibility coverage includes keyboard/focus, contrast and responsive checks; it is not a complete assistive-technology audit.
