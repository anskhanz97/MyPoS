# T001 source baseline

Audit date: 2026-09-28  
Source: `C:\XEPOS`  
Audit mode: static, read-only inspection  
Target decision authority: [product charter](../01-product-charter.md), [Q&A and decisions](../02-conversation-and-decisions.md), [architecture](../03-architecture-and-contracts.md), and [domain invariants](../05-data-and-domain-invariants.md)

## Purpose and limits

This baseline makes the five XEPOS projects reproducible research inputs for the new web POS. It does not certify that the applications run, that every migration can execute from zero, or that a route is operational merely because it exists. No source application, migration, queue, scheduler, test suite, provider call, or configured database was run.

The audit deliberately does not reconstruct missing C# till rules. Where the web repositories only receive till data, the capability is recorded as ingestion-only and the new web behavior must be designed in its owning ticket.

## Repositories and immutable identity

| Project | Role | Revision | Branch | Audited files | Aggregate SHA-256 |
|---|---|---|---|---:|---|
| `api-retail-project` | Retail cloud API and administration backend | `d1aac368ed2cc4af7eed91ddd7bf49b81b2bda79` | `Ans-Khan-dev` | 1,268 | `59fa226ea22db685262538c6e70996055eb6f3caa965603c8fea07f555306ed7` |
| `portal-retail-xepos` | Retail Vue portal | `cd247bab6806b437b4c2247d7149801f0c3cde1e` | `Ans-Khan-dev` | 587 | `1b04dd9067bb7563c54af68fef3b45a02bf4d0afa5108c5c7d1374545ded8523` |
| `xepos-hq-hospitality` | Hospitality Laravel API and Vue portal | `c46ade7f5cf83389ab22fa8844ef33af5edb8cf9` | `Ans-Khan-dev` | 1,549 | `69d062591f726999db833ee0a7ca7b728674f96e2f9df365dbf3d02cd0a1af34` |
| `api-loyalty-xepos` | Loyalty API | `650976057a5ae296b7823da6743028895e25d2b7` | `Ans-Khan-dev` | 263 | `67e3b30df323fff5841e8a42db4579c241a19cad6b088aed98828868d1843cfa` |
| `loyalty-portal` | Loyalty React portal and public join UI | `834c5b2b2fa73005198223a621a418dd3e6b28a8` | `Ans-Khan-dev` | 154 | `72e9cf06a1d9b98bbe877b997994babc8beaf719eec8e5afc9de305b59fb7f57` |

All five repositories use the recorded `origin` under `github.com/xegroup`. Tracked files were clean at baseline. `api-loyalty-xepos` additionally contained the pre-existing untracked file `docs/Loyalty _TILL_API_INTEGRATION.md`; it was neither read for secret-bearing content nor modified. This exception is part of the before/after state and must remain unchanged.

`C:\XEPOS` itself is not a valid sixth repository. Its `.git\HEAD` points to `refs/heads/master`, but that ref and a remote are absent, so `git rev-parse HEAD` cannot resolve. The five nested repository revisions above are the authoritative Git baseline; the file manifest covers the audited source tree.

## Reproducible evidence

- [Before inventory and per-file hashes](evidence/source-inventory-and-fingerprint-before.json) contains every included file path, size and SHA-256, plus models, class locations, route statements, frontend paths, migrations, screens and tests.
- [Before summary](evidence/source-baseline-summary-before.json) contains the repository identities, aggregate hashes and inventory counts.
- [Audit generator](../../scripts/audit_xepos.py) is deterministic for unchanged source content. It reads XEPOS and writes only under `C:\MyPoS\docs\research\evidence`.
- The final `after` evidence is produced after all documentation is written and compared field-for-field with this baseline.

Excluded from hashing and enumeration: `.git`, dependency directories, runtime storage, caches, compiled/build output, IDE metadata, environment files, and filenames likely to contain credentials. This prevents dependency noise and credential capture. The manifest still covers application source, manifests, migrations, routes, tests, and normal documentation.

## Inventory counts

| Project | Routes | Screens | Controllers | Requests | Middleware | Models | Migrations | Observers | Services | Jobs | Commands | Tests |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Retail API | 833 statements | — | 158 | 108 | 3 | 140 files / 138 names | 257 | 34 | 68 | 29 | 6 | 29 named tests |
| Retail portal | 267 paths | 391 | — | — | — | — | — | — | — | — | — | 0 |
| Hospitality | 550 statements / 176 frontend paths | 307 | 101 | 149 | 12 | 123 | 259 | 3 | 44 | 19 | 27 | 32 named tests |
| Loyalty API | 127 statements | — | 25 | 0 dedicated request files | 5 | 17 | 46 | 1 | 32 | 0 | 21 | 39 named tests |
| Loyalty portal | route declarations are component-based and captured with screens | 18 | — | — | — | — | — | — | 1 API client | — | — | 0 |

Counts distinguish inventory coverage from quality. For example, a controller can expose simple CRUD, an integration callback, or an ingestion endpoint; the capability register supplies that meaning.

## Technology baseline

- Retail API: PHP 8.2, Laravel 12, Sanctum 4, Filament 4 (`composer.json:9-12`).
- Retail portal: Vue 3, Vue Router 4, Vite 6 (`package.json:32,35,48`).
- Hospitality: PHP 8.1, Laravel 10, Sanctum 3 and an integrated Vue 3/Vite 6 portal (`composer.json:8-12`, `package.json:18-19,44`).
- Loyalty API: PHP 8.2, Laravel 12 and Sanctum (`composer.json:9,14-15`).
- Loyalty portal: React 18, TypeScript 5 and Vite 5 (`package.json:52,81,83`).

The target remains Laravel, Vue and MySQL. React code can provide interaction evidence but is not a target stack decision.

## Test isolation decision

No XEPOS tests were run.

- Retail API explicitly sets SQLite `:memory:` in `phpunit.xml:25-26`.
- Loyalty API explicitly sets SQLite `:memory:` in `phpunit.xml:26-27`.
- Hospitality comments out its SQLite test settings in `phpunit.xml:24-25`. Three integration tests use `DatabaseTransactions`, so they may connect to the configured environment database. This makes the suite unsafe to run without a deliberately isolated test environment.
- The two standalone frontend repositories contain no discovered test files.

Static inspection was sufficient for T001. Later reuse of a source test must first port it into the target repository and run it against an isolated target test database.

## Audit confidence

Evidence confidence labels used in the companion documents:

- **High:** explicit route plus implemented code and persistence or a concrete placeholder.
- **Medium:** coherent model/migration/controller evidence, but no safe runtime verification.
- **Low:** name or schema artifact without an observable end-to-end path.

No inspected capability is marked runtime-verified. “Implemented” in this audit means substantive source code exists; it does not mean production correctness was independently demonstrated.

## UI and hardware applicability

T001 introduces no application UI, endpoint, schema, or device integration. Light/dark screenshots, keyboard checks, hardware tests, and provider tests are therefore not applicable. The target’s mandatory complete light and dark themes remain governed by T006 and every later UI ticket.
