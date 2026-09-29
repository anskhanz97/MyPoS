# Product comparison and workflow research

Version: 1.0 | Researched: 2026-09-28 | Ticket: [T002](../tickets/002-technical-and-product-research.md)

## Purpose and evidence rules

This document compares established POS products as workflow references for MyPoS. It is not a purchasing recommendation and it does not prove that any cited product works with Pakistani tax, payment, or hardware requirements. Official product documentation is treated as evidence of documented behavior. Pakistani-provider feature pages are recorded as vendor claims because public technical and failure-mode documentation is limited.

The MyPoS scope remains a multi-merchant counter POS for retail and simple counter-service hospitality. Recipes, ingredients, preparation, wastage, reservations, queues, delivery operations, customer debt, advance-sale workflows, and reconstruction of missing desktop C# behavior are excluded.

## Product shape matters

The products are not directly equivalent:

| Product | Till surface | Back office | Relevance to MyPoS |
|---|---|---|---|
| Odoo 19 POS | Browser POS, optionally connected to Odoo IoT/local devices | Integrated Odoo web suite | Strong reference for browser POS, sessions, cached operation, lot capture, promotions, and hardware bridges |
| ERPNext | Browser POS in the Frappe application | Integrated ERP web suite | Strong reference for purchase receipt, stock ledger, batches/serials, session opening and closing |
| Lightspeed Retail X-Series | Browser/cloud retail POS with Lightspeed Hub desktop helper | Cloud retail back office | Strong reference for purchasing, counts, returns/exchanges, reports, and helper-mediated printing |
| Square for Retail | Square POS applications and proprietary/supported hardware | Square Dashboard | Useful workflow reference, but payment availability and offline behavior are country/provider constrained |
| Shopify POS | Native iOS/Android application | Shopify web admin | Useful reference for permissions and explicit offline limitations; it is not a Windows/browser-till model |
| Pakistani providers reviewed | Usually advertised as web/cloud or desktop-plus-cloud | Varies | Useful only as local market feature signals unless technical claims can be independently qualified |

## Capability comparison

Legend: **documented** means an official source describes the workflow; **claim** means a vendor marketing page mentions it without sufficient implementation detail; **limited** means the capability exists with material restrictions.

| Capability | Odoo | ERPNext | Lightspeed | Square Retail | Shopify POS | Pakistani market signal |
|---|---|---|---|---|---|---|
| Barcode scanning | Documented, including scanner and device camera workflows | Supported through item/barcode workflows | Documented retail workflow | Supported on compatible app/hardware | Supported on compatible mobile hardware | Commonly claimed |
| Purchase orders / receiving | Available in broader Odoo inventory/purchase suite | Documented PO-linked purchase receipt, partial and rejected quantities | Documented PO creation and receiving/check-in | Plus/Premium; whole quantities and 500-line documented limit | Primarily Shopify admin/inventory workflows | Commonly claimed, rarely documented deeply |
| Lots / batches | Documented lot/serial capture in POS/product configuration | Documented in purchase receipt and stock ledger flows | Product-dependent inventory capability | Not used as the principal model here | Product/inventory dependent | Expiry/batch is claimed by some providers |
| Promotions / loyalty | Pricelists, discounts and loyalty programs documented | Loyalty points can be selected in POS profile/workflow | Promotions and loyalty available by product/plan | Discounts/loyalty tied to Square products and country | Discounts exist, but many are unavailable offline | Discounts and loyalty commonly claimed |
| Return from original sale | Documented, with standalone return also possible | Return/correction supported through ERP documents | Original-sale refund and exchange documented | Documented retail refund capabilities | Returns and exchanges unavailable offline | Refunds commonly claimed |
| Exchange | Can be represented through return/new sale flows | Can be represented through return/new transaction | Explicitly documented | Product workflow dependent | Online-only | Rarely documented publicly |
| Offline operation | Browser cache; significant warnings and unavailable back-office features | Public docs are not sufficient for the MyPoS failure contract | Payment offline modes are hardware/country specific and may not update POS sale/inventory | Provider-controlled offline payments with timing and loss risks | Cash/manual payments work with severe feature limits; device/logout risks | Often claimed without durability details |
| Workforce controls | Users, access rights and session controls | Roles, users, POS profiles | Staff and reporting features | Team management product | POS roles, permissions and manager approval | HR/payroll sometimes bundled, outside MyPoS MVP |
| Reporting | Integrated Odoo reporting | Integrated ERP reports | Sales and inventory reports documented | Dashboard/reporting dependent on plan | Shopify analytics/admin | Commonly claimed |

## Detailed findings by product

### Odoo 19

Verified facts:

- Odoo describes POS as browser based and able to continue during temporary network outages. An opening control initializes the register; closing control reconciles expected and counted payments.
- The POS usage guide documents barcode input, customer selection, refunds tied to an earlier order or performed as a standalone return, cash differences, and manager-controlled closing thresholds.
- Offline startup caches essential product and customer data and stores orders for later synchronization. Odoo explicitly warns users not to close or reload the page or clear the cache, and identifies backend-dependent features that are unavailable offline.
- Product lots/serial numbers, pricing rules, loyalty programs, compatible peripherals, IoT integration, and local-network printer access are documented separately.
- Local network access has browser/version, permission, and static-IP requirements. Odoo's IoT option includes a Windows virtual IoT form factor and subscription considerations.

Sources: [Odoo 19 Point of Sale](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale.html), [POS use](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/use.html), [pricing and loyalty](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/pricing.html), [products and lots](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/products.html), [POS hardware](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/pos_hardware.html), [POS IoT](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/configuration/pos_iot.html), [IoT systems](https://www.odoo.com/documentation/19.0/applications/general/iot.html), and [local network access](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/configuration/pos_lna.html). Accessed 2026-09-28.

Lessons for MyPoS:

- Adopt explicit opening/closing sessions, counted-versus-expected reconciliation, and original-sale returns.
- Improve on Odoo's fragile reload warning: MyPoS offline sales must survive tab close, browser restart, and operating-system restart through IndexedDB and an explicit recovery state.
- Treat direct network printing as a qualified option, not a universal browser capability.
- Do not copy Odoo's broad ERP surface. MyPoS keeps its agreed product boundary.

### ERPNext

Verified facts:

- ERPNext documents opening and closing POS sessions, warehouse assignment, POS invoice processing, and loyalty selection.
- A purchase receipt can be created against a purchase order, can record accepted and rejected warehouses, supports partial receiving, and participates in the stock ledger. Batch and serial data are captured where configured.
- ERPNext notes immutable-ledger behavior in current generations of its stock accounting model.

Sources: [ERPNext POS workflows](https://docs.frappe.io/erpnext/pos-workflows) and [Purchase Receipt](https://docs.frappe.io/erpnext/purchase-receipt). Accessed 2026-09-28.

Lessons for MyPoS:

- Model purchase orders and goods receipts as different records. Receiving changes stock; placing an order does not.
- Preserve partial receipt history and rejected quantities rather than overwriting the order.
- Use append-only inventory movements and compensating entries for posted corrections.
- ERPNext public material reviewed here does not establish the required browser-offline guarantees. MyPoS must define and test those guarantees itself.

### Lightspeed Retail

Verified facts:

- Lightspeed documents purchase-order creation, partial receipt, and an explicit inventory check-in step.
- Inventory counts and original-sale refunds/exchanges are documented operational workflows.
- Lightspeed Hub is a desktop application that connects the browser/cloud POS to supported printers and peripherals on Windows. Its supported-hardware pages list model-specific compatibility.
- Lightspeed's offline/standalone payment behavior is terminal- and country-specific. The cited workflow can capture payment without creating the corresponding POS sale or inventory change, making reconciliation necessary.

Sources: [creating purchase orders](https://retail-support.lightspeedhq.com/hc/en-us/articles/228840747-Creating-purchase-orders), [receiving purchase orders](https://retail-support.lightspeedhq.com/hc/en-us/articles/8153229791131-Receiving-items-in-purchase-orders), [inventory counts](https://retail-support.lightspeedhq.com/hc/en-us/articles/229129948-Performing-inventory-counts-in-Retail-POS), [refunds and exchanges](https://retail-support.lightspeedhq.com/hc/en-us/articles/229130768-Refunding-and-exchanging), [Lightspeed Hub](https://retail-support.lightspeedhq.com/hc/en-us/articles/360045636354-Setting-up-Lightspeed-Hub), [supported hardware](https://retail-support.lightspeedhq.com/hc/en-us/articles/229131008-Supported-hardware), [cash drawer setup](https://retail-support.lightspeedhq.com/hc/en-us/articles/228842547-Setting-up-a-cash-drawer), [offline payments](https://retail-support.lightspeedhq.com/hc/en-us/articles/27640682216731-Processing-payments-in-standalone-and-offline-mode), and [sales reports](https://retail-support.lightspeedhq.com/hc/en-us/articles/360042291334-Using-sales-reports). Accessed 2026-09-28.

Lessons for MyPoS:

- A signed local print helper is a proven product shape for browser POS hardware access.
- Hardware support must be model-specific and evidenced by a qualification run.
- MyPoS must not label an external terminal receipt as a completed POS sale until both records are reconciled. MVP avoids integrated payment capture and records manual external tenders only.

### Square for Retail

Verified facts:

- Purchase orders are restricted to qualifying Square for Retail plans; the documented workflow accepts whole quantities and has a 500-line limit.
- Square offline payments have device, payment-method, reconnect-window, and risk restrictions. Offline approval is not equivalent to guaranteed collection.
- Square payment processing is documented only in specified countries. Pakistan is not in the cited country list.

Sources: [Square purchase orders](https://squareup.com/help/us/en/article/8258-create-purchase-orders-with-square-for-retail), [offline payments](https://squareup.com/help/us/en/article/7777-process-card-payments-with-offline-mode), and [international availability](https://developer.squareup.com/docs/international-development). Accessed 2026-09-28.

Lessons for MyPoS:

- Square is a workflow reference, not a Pakistan payment-integration candidate without fresh provider confirmation.
- MyPoS cash-only offline behavior avoids pretending that card authorization is available.
- Configurable document-line limits and whole/fractional quantity policies should be explicit per product unit rather than inherited from a competitor.

### Shopify POS

Verified facts:

- Shopify POS runs on supported iOS and Android devices; Shopify's FAQ says desktop devices are not supported as the POS client.
- Offline mode permits limited selling with cash and some manual/custom payment handling. Product search outside the device cache, new customers, gift cards, returns, exchanges, voids, and automatic discounts have documented offline restrictions.
- Closing the app, logging out, or losing device state can affect unsynchronized data; offline card behavior has additional eligibility and reconnect requirements.
- Staff roles, manager approvals and activity tracking are documented. Supported hardware and the Shopify hardware store vary by country; Pakistan is not among the cited hardware-store countries.

Sources: [Shopify POS FAQ](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/faq), [selling offline](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/selling-offline), [offline features](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/selling-offline/offline-features), [offline payments](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/selling-offline/offline-payments), [POS staff management](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/staff-management/understanding-pos-staff-management), and [hardware availability](https://help.shopify.com/en/manual/sell-in-person/hardware/getting-started). Accessed 2026-09-28.

Lessons for MyPoS:

- Shopify validates the value of precise offline capability messaging and manager approval flows.
- Its native-mobile architecture does not satisfy MyPoS's Windows browser-till requirement.
- Offline restrictions must be enforced in the interface and domain service, not left as operator guidance.

## Pakistan provider scan

The following are market signals only:

- SmartPOS advertises multi-branch operation, barcode and thermal printing, inventory/expiry, loyalty, and HR functions. Source: [SmartPOS features](https://spos.pk/features/), accessed 2026-09-28.
- CloudPOS advertises cloud POS/ERP, barcode, discounts, refunds, offline operation, multi-store and CRM. Source: [CloudPOS](https://cloudpos.pk/), accessed 2026-09-28.
- URLPK advertises web-based POS, inventory, suppliers, purchasing, loyalty, reporting and backups. Source: [URLPK POS](https://www.urlpk.com/pos.html), accessed 2026-09-28.
- gomyPOS advertises a Pakistan-oriented retail SaaS offering. Source: [gomyPOS](https://gomypos.com/), accessed 2026-09-28.

These pages do not provide enough evidence to verify transaction atomicity, browser storage durability, offline conflict behavior, tenant isolation, audit semantics, hardware model compatibility, or recovery objectives. Those properties must be specified and tested by MyPoS.

## Adopted product decisions

The following are MyPoS engineering and product decisions derived from the research and the frozen Q&A:

1. Use a Windows browser till with a responsive Vue interface and a small signed local helper only where browser APIs cannot meet printing/drawer requirements.
2. Separate register sessions, sales, tenders, returns, purchases, receipts, stock movements and cash movements into explicit records with immutable posted history.
3. Make original-sale return the normal path. Require a permission and reason for an authorized standalone return.
4. Treat exchange as a return plus a new sale linked by an exchange record. Preserve both financial documents.
5. Allow cash and declared manual external tenders. Do not claim integrated card authorization.
6. Allow cash-only offline sales from a bounded, signed snapshot. Block features whose authoritative data is unavailable.
7. Make offline persistence survive reload and restart, with visible pending-sync counts and reconciliation states.
8. Support both shared and store-isolated operational data according to the onboarding choice; loyalty remains merchant-wide.
9. Qualify concrete printer/scanner/drawer models before listing them as supported.
10. Keep excluded restaurant, delivery, recipe, debt and advance-sale modules out of navigation, schema and permissions.

## Capability implications for later tickets

| Finding | Contract implication | Primary downstream tickets |
|---|---|---|
| Purchase order and receipt are separate | Receipt posts inventory movements and can be partial | T004, purchasing/inventory tickets |
| Offline competitor modes are narrow and fragile | Explicit lease, cached snapshot, durable outbox and recovery UX | T004 and offline ticket |
| Browser hardware commonly needs a bridge | Versioned helper protocol, signed requests, device qualification | T004 and hardware ticket |
| Country support is provider-specific | No provider capability until locally verified | fiscal/payment integration tickets |
| Returns/exchanges need original-document links | Immutable return and exchange contracts | sales/returns tickets |
| Staff approvals are common | Server-enforced permissions plus approval audit record | identity and POS tickets |

## Open evidence, not open scope

- Exact Pakistan fiscal integration requirements are researched in T003.
- Exact printer, scanner and drawer models remain unqualified until the hardware test ticket.
- Provider marketing claims remain unverified until a controlled trial.
- Performance figures in [architecture recommendation](architecture-recommendation.md) are design targets and must not be reported as measured results.

