# Notification implementation reference

T034 sends transactional email through Laravel's mail transport and keeps every request in a durable delivery outbox. T038 adds owner-managed SMTP configuration in the integration hub. Provider credentials are encrypted in merchant integration records, redacted from responses and logs, and may be preserved when other settings change.

Receipt delivery references an immutable `ReceiptSnapshot`, renders its PDF at send time and cannot alter or repeat the sale, payment, stock or cash entries. Merchant/channel/source/recipient deduplication prevents duplicate receipt requests. Attempts record accepted/failed outcomes and safe operator messages. Queue retries use the same delivery; provider acceptance is displayed as delivery processing rather than proof that the customer read the message. Transactional receipt delivery is independent of marketing consent.

In-app alerts have merchant/store scope, a fixed role audience, source identity deduplication and per-user read state. Payroll-category alerts are forcibly rewritten to a generic owner-only message, so broad alerts cannot expose pay amounts. Preferences cover operational in-app/email channels. T038 adds optional receipt SMS/WhatsApp endpoints, consent/opt-out records, provider callbacks and owner configuration UI; these channels are never required for checkout or loyalty.

The notification center shows non-color-only categories, read state, retry-safe delivery stages, masked recipient data and the privacy boundary. Both themes were visually reviewed:

- `docs/evidence/t034-notifications-light.png`
- `docs/evidence/t034-notifications-dark.png`
