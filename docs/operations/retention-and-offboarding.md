# Retention, legal hold and offboarding

Version 1.0 — 2026-09-29

Retention is record-class based. Posted sales, returns, receipt snapshots, inventory/cost ledgers, cash/payroll/expense settlement evidence, fiscal documents/attempts and security/platform audit records are immutable business evidence and are not removed by routine pruning. Pakistan fiscal research identified a six-year electronic retention requirement for relevant electronic invoice evidence; merchant legal advice may require longer periods.

Import source and generated export artifacts expire after seven days by default. `ops:prune-expired-artifacts` deletes their private files and keeps bounded job, hash, validation and actor metadata. Application logs default to 14 daily files locally; central log retention and access must be configured by the operator. Offline clients retain acknowledged envelopes according to the bounded device policy; server evidence remains authoritative.

A legal hold suspends deletion for named merchants, date ranges and record classes and records the requester, reason and release authority. Backup expiry jobs must honor the same hold outside the application.

Offboarding requires owner authentication, a completed portable export, hash/manifest delivery, confirmation of unresolved offline/fiscal/queue items, device lease revocation, integration/provider credential revocation, subscription transition and a recorded retention/legal-hold decision. Merchant cancellation does not delete data. The `closed` state remains unavailable because selective deletion/recovery and legal policy have not been approved; this is an intentional release gate.
