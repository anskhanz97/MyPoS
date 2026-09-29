# Integration hub implementation reference

Version 1.0 — 2026-09-29

The owner-only integration hub provides searchable provider cards and configuration panels inside the normal MyPoS shell. It follows the shared responsive design system, supports complete bright/dark themes and never requires editing `.env` for merchant-owned provider settings.

## Supported configuration

- SMTP for merchant receipt email.
- Meta WhatsApp and Twilio SMS for optional receipt messages.
- Google Drive and Microsoft OneDrive connection configuration.
- Stripe connection configuration and read-only credential verification; no payment capture.
- Manual bank, Raast, Easypaisa and JazzCash destination/reference details for staff-recorded external payments.

Merchant integration records own provider, state, version, non-secret settings, encrypted credentials, capability metadata and last test/error evidence. Responses contain presence flags and masked/safe metadata, never stored secret values. An omitted secret preserves the existing value; an explicit owner action is required to replace or clear one. Optimistic versions prevent two settings screens from overwriting each other.

## Activation and testing

A provider starts disabled. Activation validates all required configuration. The owner may use a safe connection test; sandbox message delivery does not contact a provider, and live connection checks must not send customer messages or create payments. Disabling a provider stops new delivery while preserving historical attempts and callbacks.

The staff-safe active endpoint exposes only enabled capabilities needed by checkout and receipt screens. Frontend visibility is convenience only: controllers, policies and tenant constraints enforce ownership and permissions.

## Messaging behavior

Receipt SMS/WhatsApp requests reference an immutable receipt and use a stable delivery identity. Opt-out is checked before queueing. Provider attempts and cost metadata are append-only evidence. Meta and Twilio callbacks verify their configured challenge/signature, deduplicate provider event identity and only advance to truthful provider-supported states.

Transactional receipt consent is distinct from marketing consent. Phone-only public loyalty lookup remains unchanged and does not prove ownership of the phone number.

## UI and verification states

The screen includes provider search/category filtering, connected/configuration/error state, active count, setup guidance, validation feedback and deliberate secret-preservation controls. It is keyboard operable and responsive. Focused API tests cover owner authorization, tenant isolation, encryption/redaction, incomplete activation, optimistic conflicts, sandbox delivery, disable behavior, callback authentication/deduplication, opt-out and cost evidence. Type checking, linting, production build and focused Playwright light/dark coverage passed locally. Real-provider qualification remains open until merchant test credentials are supplied.
