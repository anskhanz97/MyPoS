# Browser offline feasibility and operating contract

Version: 1.0 | Researched: 2026-09-28 | Ticket: [T002](../tickets/002-technical-and-product-research.md)

## Decision

MyPoS can support bounded cash-only offline selling in a modern Windows browser, provided the application treats offline operation as a separate transaction protocol rather than a generic cached website. IndexedDB stores a versioned authorized snapshot, register session and durable outbox. A service worker supplies the tested application shell. A single register writer owns offline posting. The server remains authoritative after synchronization.

The browser cannot guarantee permanent storage, secret protection against a local administrator, or conflict-free use across uncoordinated devices. Those limits drive the lease, capacity, encryption and recovery rules below.

## Evidence from browser standards and products

Verified platform facts:

- IndexedDB provides transactional storage for structured data. It is the appropriate browser API for a durable catalogue and outbox; localStorage is not.
- Browser storage is best-effort by default. `navigator.storage.persist()` can request persistent treatment, but users and user agents can still remove data and quota can still be exhausted.
- IndexedDB durability can be relaxed; a completed transaction can still face a small loss window during an operating-system crash. Application shutdown can abort active transactions, and unload handlers are not a safe place to start durable writes.
- Service workers require a secure context. An updated worker normally waits while an older controlled page remains open, so update activation needs an explicit application protocol.
- Web Locks can coordinate same-origin tabs/workers. BroadcastChannel can notify same-origin contexts, but neither makes two physical tills one register.

Sources: [MDN IndexedDB](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API), [IndexedDB terminology and durability](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API/Basic_Terminology), [transaction durability](https://developer.mozilla.org/en-US/docs/Web/API/IDBTransaction/durability), [using IndexedDB and shutdown behavior](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API/Using_IndexedDB), [storage quotas and persistence](https://developer.mozilla.org/en-US/docs/Web/API/Storage_API/Storage_quotas_and_eviction_criteria), [service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers), [Web Locks](https://developer.mozilla.org/en-US/docs/Web/API/Web_Locks_API), and [BroadcastChannel](https://developer.mozilla.org/en-US/docs/Web/API/Broadcast_Channel_API), accessed 2026-09-28.

Product evidence also shows that offline POS operation is commonly constrained. Odoo warns against reload/close/cache clearing and disables backend-dependent operations. Shopify documents unavailable offline features and device-state loss risks. Square and Lightspeed offline payment modes have country, device, timing and reconciliation restrictions. See [product comparison](product-comparison.md) for the sourced details.

## Allowed and blocked capabilities

The offline contract is deliberately narrower than the online product.

### Allowed offline

- Sign in/unlock with a currently valid cached staff authorization for the assigned merchant, store and register.
- Resume the last valid open register session.
- Search and scan products present in the authorized snapshot.
- Apply cached price, tax and promotion rules whose complete inputs and validity window are present.
- Create a fully settled cash sale.
- Record a manual external tender only if the frozen product contract explicitly enables it offline for that merchant; default is disabled.
- Print or reprint locally available receipts through the qualified helper.
- Record cash movement types explicitly authorized in the snapshot.
- View pending synchronization status and local receipt history.

### Blocked offline

- Adding/editing products, prices, taxes, promotions, users, permissions or store configuration.
- Integrated card/mobile-wallet authorization.
- Creating customers or changing merchant-wide loyalty balances.
- Redeeming or awarding loyalty when current balance or duplicate protection cannot be established. The sale may identify the customer, but loyalty posts after reconciliation according to the later contract.
- Returns or exchanges unless a later ticket defines a safely cached original-sale and return-allowance contract. MVP default is online-only.
- Purchase orders, goods receiving, stock counts, transfers, inventory adjustments and batch administration.
- Reports requiring current cross-register or cross-store truth.
- Fiscal submission that requires live provider authorization. The sale retains a pending fiscal state under T003's eventual contract.
- Any excluded feature such as debt, advance sale, recipe, delivery, reservation or queue operation.

## Authorization and data lease

The following values are engineering defaults for implementation and threat modeling. They are not copied from a competitor and may only change through a recorded contract decision.

| Control | Initial default | Behavior at expiry |
|---|---:|---|
| Device/register enrollment | Revocable, revalidated every online start | No new offline session if revoked/unknown |
| Offline register lease | 72 hours from last successful server validation | Existing local records remain readable/recoverable; block new sales |
| Cached staff unlock | 24 hours from that staff member's last online authentication | Require online authentication; do not accept cached PIN |
| Catalogue/pricing/tax snapshot | 72 hours, with per-rule effective dates | Block new sale if core snapshot expired |
| Open register session | Until server closing rule or 72-hour lease, whichever is earlier | Recovery/sync only |
| Promotion | Must be fully evaluable and effective within cached snapshot | Suppress and explain if not safely evaluable |
| Pending outbox | Retain until server acknowledgement plus local retention window | Never discard merely because lease expires |

The UI shows the remaining offline window before expiry. At 24 hours offline it shows a persistent warning; at 48 hours it escalates; at 72 hours it enters recovery-only mode. Clock rollback detection uses the last trusted server time, monotonic elapsed time within a session and suspicious-change flags. It cannot make an untrusted workstation clock cryptographically trustworthy, so the server reviews timestamps on ingestion.

## Local data model

One IndexedDB database per application origin contains versioned stores such as:

- `meta`: schema version, application build, merchant/store/register identity, last trusted server time and migration state;
- `lease`: signed authorization and snapshot manifest, never private signing material;
- `staff_auth`: minimal cached verifier/authorization envelope with expiry and lockout metadata;
- `catalogue`: products, barcodes, units and display fields required for the till;
- `pricing`: price lists, taxes, discounts and validity fields required by the allowed calculator;
- `register_session`: the active session and opening state;
- `sales`: complete immutable local sale envelope and local posting status;
- `outbox`: ordered commands/events with client-generated IDs and idempotency keys;
- `print_jobs`: canonical receipt data and print attempts;
- `sync_receipts`: server acknowledgement, assigned numbers and conflict result;
- `diagnostics`: bounded non-sensitive recovery log.

Every locally posted sale and its outbox item is written in one IndexedDB transaction. Cart drafts are separate and may be discarded; a sale shown as completed must have a committed local envelope.

## Identifiers and idempotency

- Each device has a server-issued immutable device ID and each register has an immutable register ID.
- Each offline command uses a UUIDv7 or equivalent sortable unique ID generated once.
- The server enforces uniqueness on merchant plus command ID and returns the original result for exact retries.
- A sale contains a local receipt reference for immediate printing. The server later assigns the authoritative sequence according to the numbering contract without changing the economic content.
- Payload hash, schema version and snapshot version are part of ingestion. Reusing an idempotency key with a different hash is a security conflict.
- A batch acknowledgement is per record. One bad record cannot cause already accepted records to be reposted.

## Multi-tab and single-writer safety

One browser profile may have several tabs, but only one tab/worker may post for a register:

1. Acquire a named Web Lock such as `mypos:{registerId}:writer` before opening the till in sale mode.
2. Other tabs open read-only status or offer a deliberate takeover after checking the owner's heartbeat.
3. BroadcastChannel communicates cart/session/sync status and requests graceful release.
4. A local epoch/fencing token increments on takeover. Every local command includes it; stale writers are rejected locally and reviewed by the server.
5. Separate devices must never share a register identity. Enrollment enforces one active device assignment unless a manager performs a controlled replacement.

Web Locks coordinate cooperative contexts at an origin; they are not a tenant-security boundary. Server constraints remain authoritative.

## Storage capacity and pressure

Initial engineering bounds:

- Provision no more than 250 MiB for MyPoS managed offline data on a register profile.
- Keep at least 20% of the browser-reported quota free.
- Cap unsynchronized sales at 10,000 or 100 MiB, whichever occurs first.
- Cap cached searchable catalogue records initially at 100,000 products per operational scope. Larger catalogues require measured indexing/search validation or a narrowed till assortment.
- Retain acknowledged sale envelopes locally for 30 days or the most recent 20,000, subject to the quota reserve. Server records follow the authoritative retention policy.
- Warn at 50% and 75% of the outbox bound. At 90%, require manager acknowledgement and aggressively remove only acknowledged caches/logs. At the hard bound, enter sync/recovery-only mode.

At provisioning, call `navigator.storage.estimate()` and request persistence. A denied persistence request does not silently enable offline selling; it results in a visible reduced-confidence state and may block enrollment according to browser qualification.

## Encryption and local threat limits

- HTTPS is mandatory for service workers and all server traffic.
- Encrypt highly sensitive cached fields using Web Crypto where practical, with a key envelope tied to server-issued device authorization.
- Do not store payment card data, provider secrets, application signing private keys, customer passwords or reusable API credentials.
- Minimize customer PII in the offline snapshot. Store only data required by an allowed workflow.
- A key usable automatically by JavaScript in the same browser profile cannot protect against an attacker who controls that profile, malicious extensions, local administrator access or memory inspection. Encryption is defense in depth, not proof that a compromised till is safe.
- Protect the Windows account, use disk encryption, restrict extensions and local administrator access, and support remote device revocation.

## Service-worker update protocol

An uncontrolled service-worker update can split application code and IndexedDB schema. MyPoS therefore uses this sequence:

1. Download and verify a new build in the waiting state.
2. Compare its offline schema/protocol range with the active register state.
3. If a posted outbox is pending and the upgrade is incompatible, keep the old worker active and require sync or an approved recovery export.
4. Ask the operator to finish or save the cart. Never update in the middle of local posting.
5. Acquire the register writer lock, close database connections, activate the worker and run an idempotent migration with a progress screen.
6. On migration failure, preserve the old database, stop selling and show recovery instructions. Do not delete and recreate the database.
7. Record build and migration outcome in diagnostics and upload it after reconnect.

Emergency security blocks may force recovery-only mode, but they must preserve access to unsynchronized sales.

## Synchronization protocol

1. Detect connectivity through an authenticated health/sync handshake, not only `navigator.onLine`.
2. Refresh server time, device status, register assignment and lease before submitting new work.
3. Send bounded batches in local commit order with command ID, fencing epoch, payload hash, snapshot version and prior acknowledgement where applicable.
4. The server authenticates device and staff context, resolves merchant/store from trusted assignment, verifies schema/version, and starts a MySQL transaction.
5. It accepts an exact retry idempotently, posts valid records atomically, or returns a stable conflict code with operator action.
6. The client commits acknowledgements locally before deleting any transient retry state.
7. Download refreshed catalogue/rules as a versioned snapshot or delta with manifest/hash verification.

Conflict classes include expired/revoked device, closed/replaced register session, invalid snapshot, duplicate identifier with different payload, invalid product/tax rule, suspicious timestamp, and server invariant failure. No conflict is converted silently into a different sale.

## Restart and disaster scenarios

| Scenario | Required result |
|---|---|
| Tab closes after local sale commit | Sale and outbox remain; next start shows pending count and can reprint |
| Tab closes during IndexedDB transaction | Transaction aborts; UI must not have confirmed completion |
| Windows restarts | Committed records recover; writer lock can be reacquired with new epoch |
| Browser cache is cleared | Local data may be gone; device cannot recreate unsynced records. Enrollment warns about this and server detects gaps where possible |
| Service worker updates with pending sales | Compatible update migrates safely; incompatible update waits or enters recovery flow |
| Quota exceeded | Current transaction fails visibly; cart remains recoverable; no completed-sale message |
| Server receives batch then response is lost | Retry returns the same result by idempotency key |
| Two tabs attempt sale | Only lock owner can post; the other is read-only/takeover UI |
| Device clock changes | Mark suspicious; calculate lease conservatively; server reviews on sync |
| Register is revoked while offline | It can sell only until its existing lease expires; server quarantines prohibited post-revocation records for review under the contract |

## Verification plan

No offline behavior was executed in T002. The later offline implementation ticket must automate and manually verify:

- offline provisioning, persistence request denied/granted and quota reporting;
- 1,000 representative products plus the upper-bound catalogue profile;
- sale commit followed by tab close, browser kill and Windows restart;
- network loss at each boundary before/during/after local commit and server acknowledgement;
- exact retry, altered retry, out-of-order batch and partial batch conflicts;
- two tabs, forced takeover and a stale writer;
- service-worker compatible/incompatible upgrade with pending sales;
- quota pressure and hard-bound behavior;
- expired staff authorization, register lease and catalogue snapshot;
- light/dark, keyboard, screen-reader status announcements and 200% zoom for offline banners, conflicts and recovery screens;
- qualified printer operation while offline and after restart.

Results must distinguish simulated network loss from a real workstation/browser restart and distinguish helper acceptance from physical printer output.

