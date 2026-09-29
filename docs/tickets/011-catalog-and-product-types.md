# T011 — Implement flexible catalog, variants, barcodes and pack conversions

Version: 1.0 | Created: 2026-09-28 | Status: Verified — executed locally 2026-09-29

## Outcome

Provide the complete in-scope product catalog for counter checkout and purchasing. Support finished products and extras without recipes, services, serialized assets or repair/rental workflows.

## Dependencies and decisions

- Prerequisites: [T007](007-merchant-and-store-tenancy.md), [T008](008-authentication-and-fixed-roles.md), [T010](010-register-provisioning-and-settings.md)
- User-decision references: Q010, Q012, Q029, Q034 in [Q&A](../02-conversation-and-decisions.md).
- Execute only this ticket when requested. A dependency is satisfied by verified completion evidence, not its filename.
- Read [execution guide](../09-ticket-execution-guide.md), [charter](../01-product-charter.md), [architecture](../03-architecture-and-contracts.md), [themes](../04-frontend-and-two-themes.md), [invariants](../05-data-and-domain-invariants.md) and [security/operations](../07-security-offline-and-operations.md).

## Context carried into this ticket

The product is a Vue/Laravel/MySQL multi-merchant counter POS for Pakistan. Windows tills, English UI, light/dark themes. Shared or independent operational store data is selected and locked at onboarding; loyalty is always merchant-wide. Sales are fully settled, with cash/manual external tenders; offline is cash with cached prices/discounts. No tables/kitchen/recipes/delivery/reservations, customer debt, advance-sale workflows or C# reconstruction. The full charter and Q&A override any accidental interpretation of a source feature.

## Records, artifacts and ownership

Create scoped products, variants, unique-within-scope SKUs/barcodes, UOMs/conversions, fixed bundle components, extras/options, images and store availability. Version structural changes affecting existing sales or stock.

## Required implementation or research work

- Build create/edit/list/detail screens with bounded search and filters. Support shared catalog with local availability and independent store catalogs. Product archiving preserves old sales and remaining stock visibility.
- Implement size/color variants, weighted quantities, carton-to-piece conversion and fixed bundles of finished products. Prevent conversion cycles, invalid factors and recursive bundles. Extras may affect price/finished-product stock according to an explicit definition, not ingredient recipes.
- Parse configured scale-label barcode formats with checksums/bounds as researched; initially no direct scale device integration. Preserve leading barcode zeros and intentional multiple barcodes. Batch tracking can be enabled only with a plan for existing stock conversion in T015.
- Capture tax classification references without hard-coded Pakistan legal rates. Images are validated and isolated by tenant. Do not ship source placeholder data as the merchant's catalog.

## Interfaces, permissions and user experience

Typed catalog APIs return decimal quantities/prices and normalized barcode fields. Search endpoints are paginated/bounded and suitable for register snapshot generation. Catalog writes are authorized administrative actions, not cashier free-form mutation during finalize.

All UI introduced here follows the shared light/dark theme contract. Include populated, empty, loading, validation and error states; keyboard/focus behaviour; and authorized store context. Where no UI is introduced, record this section as not applicable rather than invent screenshots. Do not use a front-end permission check as the only authorization boundary.

## Acceptance and verification

- Test variants with colliding merchant/store identifiers, weighted units, carton conversion and bundle stock expansion. Reject cyclic bundles and invalid factors. Barcode scan preserves zeros and distinguishes quantity-encoded formats. Archived item still renders in historical sale snapshots. Review CRUD/search in both themes.

For code changes, use meaningful domain/API tests and MySQL integration tests where transactions or constraints matter. Exercise retries, tenant/store substitutions and state conflicts relevant to this ticket. Validate actual UI in both themes when present. Record simulated versus real hardware/provider results. Never run tests against XEPOS production/configured databases.

## Source/reference starting points

Use the source map from T001 and primary-source decisions from T002/T003. Read actual current implementation before adapting source code. See the shared research register for verified versus proposed facts.

## Boundaries and failure handling

If a prerequisite is incomplete or an external fact cannot be verified, preserve completed work, record the exact missing evidence and affected acceptance criteria, and do not claim the ticket fully verified. Resolve routine technical choices using the frozen contracts and actual repository; do not reopen settled product questions.

Keep XEPOS read-only. Do not add excluded features, silently change architecture/contracts, copy credentials or perform production/provider side effects. A ticket does not authorize building every later ticket. If a necessary contract changes, document affected downstream tickets and compatibility before relying on it.

## Completion record — fill after execution

- Status: **Verified — executed locally on 2026-09-29.**
- Execution date / application commit or working-tree evidence: uncommitted local Git working tree in `C:\MyPoS`; catalog migrations, models, domain services, scoped APIs, Vue catalog preview, automated tests, OpenAPI contract, research record and visual evidence are present in the working tree.
- Files/modules changed: added products, variants, barcodes, units, direct base-unit conversions, bundle components, extra groups/options, product images, store availability and scale-format models/migrations. Added catalog scope resolution, transactional product writing, recursive bundle validation/expansion, barcode resolution and immutable checkout snapshot building. Added validated requests, minimal resources, controllers, routes and the responsive `Catalog` management preview with populated, empty, loading, validation, error and archive states.
- Schema/API/event changes: added 11 catalog tables and public binary UUIDs while preserving internal numeric keys. The API gained eight catalog path families for units, products, bundle replacement, barcode resolution, scale formats and authenticated product-image upload/read/delete, bringing the OpenAPI contract to 57 paths and 93 schemas. Catalog responses expose public IDs and decimal strings; internal image paths and numeric stock-variant keys are withheld. Security audit codes include `catalog.product.created`, `catalog.product.updated`, `catalog.bundle.updated` and `catalog.image.created`.
- Tests actually run and results: MySQL 8.4 `migrate:fresh` passed all 39 migrations. The focused catalog suite passed 10 tests/65 assertions for shared availability, independent-store identifier reuse, permissions and substitutions, archived snapshots, optimistic conflicts, batch conversion gating, bounded search, private image isolation/read access, one default variant, public-ID serialization, weighted scale labels, optional checksum, bounds, direct conversions, nested bundle expansion and cycle rejection. The full PHPUnit suite passed 83 tests/405 assertions. PHPStan passed with zero errors and Pint passed. Prettier, Vue TypeScript and ESLint passed; Vitest passed 21 tests; the Vite production build passed; Playwright Chromium passed all 10 browser journeys. The OpenAPI document parsed successfully and contains 57 paths/93 schemas. The documentation validator passed 71 Markdown files and 711 local links.
- Light/dark visual evidence: inspected [catalog light](../evidence/t011-catalog-light.png) and [catalog dark](../evidence/t011-catalog-dark.png). Both show search, variants, archive context, carton conversion, extras, weighted-label configuration, nested fixed bundles and explicit loading/empty/error states with immediate persistent theme switching.
- Hardware/provider evidence: scale input was simulated with barcode strings and configurable field layouts; optional EAN-13 check-digit validation was tested. No direct weighing scale, scanner or provider was connected or claimed.
- Decisions/contract deviations and downstream impact: current GS1 General Specifications 26.0.0 supports variable-measure retail restricted-circulation numbering but leaves national/internal field structure configurable, so prefix, product/value positions, decimal places, quantity-versus-price meaning and checksum are merchant/store configuration. Exact barcodes resolve first and preserve leading zeros. Units convert directly to a base unit, fixed bundles consume finished-product variants, and extras optionally consume a finished variant plus quantity; recipes remain excluded. Shared catalog availability is explicit, so stores created later begin without product availability until an administrator publishes that decision. Each product has exactly one default variant. Tax fields are references only and T012/T035 own actual price/tax behavior.
- Remaining blockers/limitations and next action: no hardware qualification remains in T011 scope. Variant structure is created atomically and protected by product structure versions; this ticket exposes bundle replacement and metadata/archive updates, while future inventory records use immutable product snapshots rather than destructive catalog edits. Proceed to T012 versioned prices and the selected automatic-versus-code promotion modes.
