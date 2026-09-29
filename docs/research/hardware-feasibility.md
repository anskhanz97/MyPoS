# Windows POS hardware feasibility and qualification plan

Version: 1.0 | Researched: 2026-09-28 | Ticket: [T002](../tickets/002-technical-and-product-research.md)

## Decision

MyPoS will support a Windows browser till. USB barcode scanners operate initially as keyboard-wedge devices. Thermal receipt printers and attached cash drawers use a signed local helper when silent, reliable ESC/POS printing is required. No printer, scanner or drawer model is called supported until it passes the qualification matrix in this document.

No hardware was connected or purchased during T002. Every feasibility statement below is either based on official documentation or explicitly identified as an engineering recommendation.

## Why a local helper is the default

Normal web pages cannot universally enumerate installed printers, send raw ESC/POS bytes silently, or pulse a cash drawer. Product evidence supports two practical patterns:

- Lightspeed Hub is a Windows desktop bridge between a browser/cloud POS and supported peripherals.
- Odoo supports an IoT bridge and a narrower local-network-access method for certain network printers. The direct method has browser, permission, network and static-IP conditions.

Sources: [Lightspeed Hub](https://retail-support.lightspeedhq.com/hc/en-us/articles/360045636354-Setting-up-Lightspeed-Hub), [Lightspeed supported hardware](https://retail-support.lightspeedhq.com/hc/en-us/articles/229131008-Supported-hardware), [Odoo POS hardware](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/pos_hardware.html), [Odoo IoT](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/configuration/pos_iot.html), and [Odoo local network access](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/configuration/pos_lna.html), accessed 2026-09-28.

## Helper candidate and security model

QZ Tray is the leading initial candidate because its documentation covers raw printing, request signing, Windows deployment and service operation. T002 does not select a commercial license or certify QZ Tray.

Verified documentation facts:

- QZ Tray supports digitally signed messages. Its documentation recommends server-side signing and warns against exposing a private key in browser code.
- QZ Tray is LGPL software. Its licensing documentation describes premium certificates for suppressing warnings as well as self-managed certificate options.
- The documentation covers unattended Windows deployment, service mode and command-line security configuration.

Sources: [QZ signing](https://qz.io/docs/signing), [licensing](https://qz.io/docs/licensing), [deployment](https://qz.io/docs/deployment), [Windows service](https://qz.io/docs/windows-service), and [command-line options](https://qz.io/docs/command-line), accessed 2026-09-28.

Recommended security contract:

1. The browser requests a short-lived print authorization from the Laravel server for an authorized sale/receipt and register.
2. The server returns a canonical payload, expiry, nonce and signature. The browser never receives the signing private key.
3. The helper accepts only the MyPoS origin, validates signature and expiry, enforces an allowlisted printer, and rejects arbitrary file/network commands.
4. Every request includes merchant, store, register, document type, document ID, copy/original status and an idempotency key.
5. The helper returns accepted/printed/failed status with an error category. The server records print audit metadata; it does not equate helper acceptance with physical paper output.
6. Cash-drawer pulses are permitted only for authorized cash events or an audited manager action.
7. Helper updates are signed, versioned and rolled out through a staged channel. The web application negotiates a minimum and supported protocol range.

## Alternatives

| Approach | Benefits | Limits | Decision |
|---|---|---|---|
| Browser print dialog | No helper; suitable for manual A4 printing | User interaction, layout variability, cannot reliably pulse drawer or send raw ESC/POS | Supported for office reports, not default receipt path |
| Direct network printer API | Avoids local installed-printer bridge | Vendor/model/browser/network constraints; TLS/CORS/static IP and permission issues | Optional future adapter after model qualification |
| QZ Tray/helper | Raw printing, installed printers, silent operation and Windows deployment | Adds local software, certificate/licensing/update duties | Initial preferred path for pilot evaluation |
| Custom native helper | Full control and smallest purpose-built protocol | Highest build, signing, installer, security and support cost | Do not build until QZ evaluation fails a written requirement |
| Cloud print relay | Central management | Internet dependency and privacy/latency concerns; unsuitable for offline cash sales | Excluded from critical receipt path |

## Device classes

### Thermal receipt printer

Minimum candidate properties:

- ESC/POS-compatible 80 mm printer; 58 mm may be evaluated only if a product requirement remains.
- USB and/or Ethernet interface with a stable Windows driver or raw-print path.
- Cutter capability and cash-drawer kick connector where required.
- Configurable code page or raster rendering that can print all required English and fiscal text.
- Status feedback where available: online, paper out, cover open, cutter error.
- Vendor model and firmware still supported, with locally obtainable consumables and service.

Candidate families for a later qualification purchase may include Epson TM and Star TSP lines because established POS documentation lists specific Epson and Star models. That evidence does not qualify any model for MyPoS.

### Barcode scanner

Initial support uses USB HID keyboard-wedge mode:

- Sends barcode characters followed by a configurable Enter terminator.
- Can disable unwanted prefixes/suffixes.
- Supports the symbologies required by the catalogue, at minimum EAN-8, EAN-13, UPC-A and Code 128. QR is optional unless a product use case is approved.
- Scans quickly into a dedicated capture layer without stealing text-entry focus.
- Provides an operator signal for unreadable or unknown barcodes.

Odoo documents barcode-scanner and device-camera input as POS options; this establishes workflow feasibility, not model compatibility. Source: [Odoo POS use](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/use.html), accessed 2026-09-28.

### Cash drawer

The initial drawer is printer-driven through an RJ11/RJ12-style kick connection supported by the chosen printer. Pinout and voltage must match the printer and drawer documentation; connector appearance alone is not compatibility.

The drawer opens on:

- completion of an authorized cash sale;
- an authorized cash in/out operation when the workflow requires access;
- a manager-approved no-sale open with reason; or
- a controlled diagnostic test.

Every non-sale open is audited. UI-only permission checks are insufficient; the server or valid offline authorization snapshot must approve the action.

## Qualification matrix

Each candidate printer/scanner/drawer combination gets a dated evidence record containing device model, serial/asset ID, firmware, driver/helper version, Windows build, browser version, connection type and test operator.

| Area | Required qualification cases |
|---|---|
| Install | Standard user operation after administrator installation; clean install; repair; uninstall; reboot persistence |
| Discovery | Correct allowlisted printer; duplicate names; disconnected device; default-printer change |
| Receipt | Short/long receipt, 80 mm layout, logo, barcode/QR if required, discount/tax lines, return, copy marker, cutter |
| Encoding | All approved English symbols, currency formatting, long product names, fallback behavior |
| Reliability | 100 sequential receipts, rapid double-click/idempotency, spooler restart, paper-out recovery, USB reconnect |
| Offline | Print while server is unreachable using an authorized cached receipt; reconnect and audit upload |
| Drawer | Cash sale pulse, denied non-cash pulse, manager no-sale pulse, wrong cable/pinout handling |
| Scanner | All required symbologies, rapid consecutive scans, unknown code, duplicate scan, focused text field, Caps/Num Lock variations |
| Security | Wrong origin, expired signature, modified payload, replayed nonce, unapproved printer, old helper protocol |
| Updates | Browser update, helper update, driver update and Windows reboot without losing configuration |
| Themes/accessibility | Printer status and error/retry flows usable by keyboard and readable in light and dark themes |

Pass criteria:

- No duplicate physical receipt from a retried idempotency key unless the operator explicitly chooses audited reprint.
- All 100 sequential receipt jobs are accepted once and the operator can identify any physical failure.
- Sale completion remains committed if printing fails; the UI provides retry/reprint without reposting the sale.
- Cash drawer never opens for an unauthorized request.
- Scanner input adds the intended item exactly once per completed scan.

## Installation and update operations

Recommended pilot flow:

1. An administrator installs the pinned helper and printer driver from a controlled package.
2. Device enrollment records helper ID, certificate fingerprint, store/register assignment and allowed printer.
3. A diagnostic page performs signed test print, status query and drawer test with an audit reason.
4. The helper starts with Windows or as an approved service according to the chosen QZ deployment mode.
5. Updates go to a test register, then a small canary group, then the estate. Rollback packages remain available.
6. The web app warns on deprecated helper versions and blocks only when the protocol is unsafe or incompatible; a documented recovery route remains.

Do not place a signing private key, merchant secret or database credential in the helper installer or browser bundle.

## Failure behavior required in the application

- **Helper absent:** show install/diagnostic instructions; allow authorized browser-dialog fallback where appropriate.
- **Printer unavailable:** keep the sale complete, show a persistent failed-print state, allow retry or audited reprint.
- **Paper out:** retain the same print job/idempotency key until resolved.
- **Ambiguous result:** mark `print_outcome_unknown`; do not silently submit a new sale. Operator may verify and reprint with copy marking.
- **Drawer failure:** do not reverse a completed sale. Show a manager-recoverable operational incident.
- **Scanner malformed input:** preserve the cart, show a non-blocking error, and allow manual search.
- **Helper/server version mismatch:** present supported versions and a controlled update route.

## Explicit exclusions

T002 does not qualify scales, customer displays, biometric readers, kitchen printers, preparation displays, delivery devices or integrated card terminals. A later approved capability must add its own security, country, hardware and recovery contract.

