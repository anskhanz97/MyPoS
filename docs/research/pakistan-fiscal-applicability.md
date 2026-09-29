# Pakistan fiscal applicability and merchant classification

Version: 1.0 | Researched: 2026-09-28 | Ticket: [T003](../tickets/003-pakistan-fiscal-research.md)

## Purpose and limitation

This document maps official information into product launch gates. It is engineering research, not legal or tax advice. A qualified Pakistani tax adviser and the relevant authority or licensed integrator must confirm each production merchant's classification, rates, exemptions, invoice content and outage procedure.

MyPoS supports goods retail and simple counter-service sales. This research does not add recipes, kitchen operations, tables, reservations, queues or delivery.

## Core conclusion

There is no single Pakistan POS tax configuration. At least three questions must be answered independently:

1. Is the merchant registered or required to be registered for federal sales tax on goods or an FBR-administered tax?
2. Does a store provide a taxable service governed by Punjab, Sindh, Khyber Pakhtunkhwa, Balochistan or Islamabad Capital Territory rules?
3. Does a specific notification, business class or authority direction require real-time POS/e-invoice integration for that store and activity?

A merchant can sell goods and provide services and can have stores in several jurisdictions. MyPoS therefore assigns applicability per legal registration, store and line/activity. A merchant-wide tax toggle is insufficient.

## Evidence statuses

- **Confirmed:** direct official legislation, notification, authority documentation or government portal reviewed on the access date.
- **Partially confirmed:** authority confirms the system or obligation but a current public API/outage contract was not available.
- **External verification required:** production applicability or behavior depends on merchant facts, credentials, authority onboarding or written advice.
- **Unsupported at launch:** MyPoS must not claim compliance or activate that adapter until the launch gate is passed.

## Federal sales tax and FBR digital invoicing

### Confirmed legal direction

S.R.O. 69(I)/2025 replaced Chapter XIV of the Sales Tax Rules, 2006 with rules for licensing, electronic sales-tax invoices and integration. The rules apply when FBR notifies registered persons or classes, and state that already integrated POS persons are treated as integrated under the new framework. They require real-time verifiable electronic invoices, electronic debit/credit notes and six-year electronic retention. The rule text lists invoice particulars including supplier/recipient registration data, invoice date, description, quantity, value excluding tax, rate, tax amounts, discount, reference, HS code, unit and applicable SRO data, with stated retail exceptions for some fields.

S.R.O. 1413(I)/2025 superseded S.R.O. 709(I)/2025 and phased all listed sales-tax registered persons through registration, testing and electronic-invoice issuance. The final category, registered persons not otherwise listed, had an issuance date of 1 December 2025. Public companies, large companies, importers and other categories had earlier 2025 dates.

Sales Tax General Order 01 of 2026 says S.R.O. 1413 obligates all sales-tax registered persons to integrate through a licensed integrator and permits one or more licensed integrators. It says a valid electronic sales-tax invoice may be cancelled, deleted or edited for a bona-fide mistake through FBR's computerized system within 72 hours; later action requires prior approval of the concerned Commissioner Inland Revenue under the specified process.

Official sources, accessed 2026-09-28: [S.R.O. 69(I)/2025](https://download1.fbr.gov.pk/SROs/2025129141598258SRO69%28I%292025.pdf), [S.R.O. 1413(I)/2025](https://download1.fbr.gov.pk/SROs/2025811681810559SRO1413.pdf), [STGO 01 of 2026](https://download1.fbr.gov.pk/Docs/2026331133557466STGO01of2026.pdf), [FBR digital-invoicing legal provisions](https://www.fbr.gov.pk/di-legal-provisions/173967/173968), and [FBR digital-invoicing FAQ](https://fbr.gov.pk/faqs/173967/173969).

### FBR DI technical profile

FBR/PRAL Digital Invoicing API documentation version 1.12 describes bearer-token security, real-time post and validate endpoints, separate sandbox/production routes, reference APIs, HS code/unit/rate/sale-type data, sales invoices and debit notes. A successful post returns an FBR invoice number and item statuses. The document requires the Digital Invoicing logo and a QR code on each invoice and specifies a 1 inch QR, version 2 (25×25). The document says security tokens have a five-year validity, but production renewal must still be tested rather than scheduled from documentation alone.

Retail sandbox scenarios SN026–SN028 cover end-consumer sales at standard, Third Schedule and reduced rates and apply only where the sales-tax profile is registered as retailer. This confirms that HS/UOM/rate/sale-type mapping cannot be inferred solely from a local product tax percentage.

Source: [FBR/PRAL Digital Invoicing API Technical Specification v1.12](https://download1.fbr.gov.pk/Docs/20257301172130815TechnicalDocumentationforDIAPIV1.12.pdf), accessed 2026-09-28.

### Separate legacy POS context

FBR continues to publish Tier-1 POS pages, legal provisions, integrated-retailer lists and invoice verification using an FBR invoice number or receipt QR code. These remain relevant to already integrated retailers, but the 2025 digital-invoicing rules and merchant's licensed-integrator instructions control the production path. MyPoS must not assume the older Tier-1 POS protocol and the DI v1.12 protocol are interchangeable.

Sources: [FBR POS legal provisions](https://www.fbr.gov.pk/pos-legal-provisions/163085/163086), [integrated retailers as at 31 August 2026](https://fbr.gov.pk/pos-integrated-retailers/163085/163089), and [POS invoice verification](https://www.fbr.gov.pk/pos-invoice-verification/163085/163142), accessed 2026-09-28.

### Federal applicability decision

For launch, every merchant must declare and evidence:

- NTN/CNIC and STRN status;
- legal form and FBR sales-tax profile;
- registered activities and whether it is an importer, manufacturer, wholesaler/retailer or another notified class;
- whether it already uses FBR POS integration;
- the licensed integrator or PRAL onboarding path and issued sandbox/production credentials;
- approved business scenarios and product HS/UOM/rate/SRO mappings;
- approved outage/store-and-forward method.

If the merchant is federally sales-tax registered, MyPoS treats FBR DI as required until a qualified adviser/licensed integrator documents why it is not. If the merchant is not registered, the software must not simply add sales tax. FBR states that only a registered supplier may recover sales tax and the invoice/receipt must show STRN; NTN is not a substitute. Source: [FBR sales-tax charging clarification](https://fbr.gov.pk/sales-tax--to-be-recovered-only-if-the-supplier-is-registered/132123), accessed 2026-09-28.

## Provincial and territorial services matrix

Rates are deliberately absent from the product defaults. They vary by service, payment method, registration and notification and must be loaded as dated authority mappings.

| Jurisdiction | Confirmed system or rule | Evidence status | MyPoS launch position |
|---|---|---|---|
| Punjab | PITB confirms PRA's Restaurant Invoice Monitoring System, originally rolled out for food businesses; current public authoritative API/outage specification was not located in this pass | Partially confirmed | Hospitality/service merchant is unsupported until PRA supplies current applicability, E-IMS/RIMS specification, credentials, QR/receipt and outage rules |
| Sindh | Online Integration of Business Rules 2022, updated through 2026, require scheduled service providers to integrate POS for real-time reporting; SRB FAQ identifies restaurant categories and credit-note treatment; SRB publishes a 2026 POS API document | Confirmed obligation; sandbox/production behavior untested | Build only after SRB onboarding and contract tests against the current supplied API |
| Khyber Pakhtunkhwa | KP Finance Act 2025 makes RIMS/IMRS/e-invoicing a mandatory condition for restaurants, hotels, food businesses and similar businesses; official KPRA portal documents API, local Windows utility, QR and credit notes | Confirmed for described classes; documentation has payload inconsistencies | Adapter may proceed to sandbox only after KPRA confirms the canonical schema and business NTN/POS credentials |
| Balochistan | Act authorizes electronic monitoring/e-invoicing; 2018 rules require serial/electronic invoice particulars for taxable services | Base invoice duty confirmed; current class-specific online integration/API not found | No e-invoice-compliance claim until BRA gives written applicability and current technical route |
| Islamabad Capital Territory | FBR administers sales tax on services under the ICT Ordinance; restaurant/service rates can vary by payment method | Tax regime confirmed; relationship to FBR DI for each service profile requires confirmation | FBR/licensed-integrator confirmation required before activation |
| Azad Jammu & Kashmir | No sufficiently current official POS/e-invoice technical package was verified in this pass | External verification required | Unsupported for fiscal integration until AJK authority evidence and credentials are obtained |
| Gilgit-Baltistan | No sufficiently current official POS/e-invoice technical package was verified in this pass | External verification required | Unsupported for fiscal integration until GB authority evidence and credentials are obtained |

### Punjab

PITB, a Government of Punjab body, says RIMS was developed for PRA and rolled out for food-business registration and provincial sales-tax monitoring. This establishes that an invoice-monitoring system exists. It does not establish the current 2026 API, categories, rates, invoice schema, outage procedure or production onboarding. Source: [PITB RIMS](https://pitb.gov.pk/rims), accessed 2026-09-28.

Production gate: obtain the currently effective PRA notification/rules, merchant category decision, API/integration pack, test account, invoice sample, refund/credit-note procedure, QR rules, failure/replay behavior and offline instruction directly from PRA or its authorized onboarding route.

### Sindh

The Sindh Sales Tax Special Procedure (Online Integration of Business) Rules, 2022 require a registered person providing scheduled services to integrate all points of sale, including relevant internet points, for real-time reporting. The rules prescribe tax invoices and a one-rupee invoice service fee for integrated POS within their scope. The official SRB FAQ identifies covered restaurant categories, says scheduled restaurants must integrate, describes returns/exchanges through credit notes and says pre-payment/kutcha bills are not allowed. SRB's customer guidance expects an SRB invoice number and QR on integrated invoices.

The 2026 API document search result states that `rateValue` must be 8, 15 or another prescribed value and that the returned `QRCodeLink` must be embedded as a QR. This is enough to reject a universal rate but not enough to freeze the full adapter without the actual onboarding contract and sandbox.

Sources, accessed 2026-09-28: [SRB current acts/rules index](https://www.srb.gos.pk/srb/acts-rules-amendments/), [Online Integration Rules](https://www.srb.gos.pk/srb/wp-content/uploads/2024/09/Sindh-Sales-Tax-Special-Procedure-Online-Integration-of-Business-Rules-2022-updated-upto-1st-July-2024-EOE.pdf), [SRB POS FAQ](https://www.srb.gos.pk/srb/faq/), [SRB POS verification](https://www.srb.gos.pk/srb/pos-lucky-draw/), and [SRB POS API v1](https://www.srb.gos.pk/srb/wp-content/uploads/2026/01/New-POS-API-Document-V1.pdf).

### Khyber Pakhtunkhwa

The KP Sales Tax on Services Act permits electronic transaction/invoice monitoring and compulsory e-invoicing for notified classes. The KP Finance Act 2025 states that restaurants, hotels, food businesses and similar service providers must integrate or install RIMS/IMRS or another prescribed system. The official KPRA POS portal requires the software provider to be registered with KPRA before requesting business POS IDs.

The current RIMS guide documents direct HTTPS submission or a recommended Windows utility at localhost that queues invoices and later syncs. It documents merchant NTN, POS ID/key, invoice number, exclusive/tax/total amounts, date/time, payment mode, success transaction ID, QR verification URL and a separate idempotent credit-note API.

The same official page contains inconsistent field guidance for the live endpoint: one section describes `sales_tax` and `date`, while other current snippets describe `tax_amount` and `date_time`. MyPoS must not choose one by guess. The canonical OpenAPI/onboarding response and sandbox results are a production gate.

Sources, accessed 2026-09-28: [KP STS Act updated 2024](https://kpra.gov.pk/wp-content/uploads/2024/07/KP-STS-ACT-2022-Updated-2024.pdf), [KP Finance Act 2025](https://kpra.gov.pk/wp-content/uploads/2025/07/kp-finance-act-2025.pdf), [KPRA software-provider registration](https://posregistration.kpra.gov.pk/), and [KPRA RIMS technical guide](https://posregistration.kpra.gov.pk/docs).

### Balochistan

The Balochistan Sales Tax on Services Act authorizes electronic monitoring and compulsory fiscal/e-invoice systems when prescribed. The Balochistan Sales Tax on Services Rules, 2018 require a taxable service provider's serial or electronic invoice to carry provider registration, recipient information, serial/date, service description/tariff, exclusive value, rate, tax and inclusive value. A current notified-class API/outage contract was not found.

Sources: [Balochistan STS Act](https://bra.gob.pk/NewsDirectory/2015_BSTS_Act_Ver01.pdf) and [Balochistan STS Rules 2018](https://bra.gob.pk/NewsDirectory/Downloads/Balochistan_ST_onServicesRules2018.pdf), accessed 2026-09-28.

### Islamabad Capital Territory

FBR states that federal sales tax covers goods while sales tax on services in ICT is levied under the ICT (Tax on Services) Ordinance, 2001. The ordinance includes restaurant and related counter-service activities and demonstrates that rates may vary by payment method. MyPoS must resolve the applicable dated line rule and whether the merchant's ICT service invoices go through FBR DI/POS or another instructed process.

Sources: [FBR sales-tax basics](https://www.fbr.gov.pk/sales-tax/51148/101149) and [ICT Tax on Services Ordinance updated to 30 June 2025](https://download1.fbr.gov.pk/Docs/2025711172945966ICTOUpdatedupto30.06.2025.pdf), accessed 2026-09-28.

## Merchant onboarding facts

The owner must provide, and an authorized reviewer must approve:

1. Legal name, entity type, NTN/CNIC, STRN and each provincial/territorial registration number.
2. Registered address, store address, province/territory and place-of-provision facts.
3. Goods, services or mixed activity per store; FBR/provincial activity codes and effective dates.
4. Importer/manufacturer/wholesaler/retailer profile, Tier-1/previous POS integration status and turnover/category facts used by authority rules.
5. Authority or adviser decision identifying each required regime and the source/effective date.
6. Tax-inclusive or tax-exclusive pricing by price book and product/activity.
7. Product HS code, UOM, sale type, rate/SRO mapping for FBR; service CPC/tariff mapping where provincial rules require it.
8. Customer/buyer fields required for B2C versus registered/unregistered B2B cases.
9. Authorized integrator, sandbox/production account, registered POS/outlet IDs, credential owner and renewal/contact process.
10. Required receipt logo, invoice/reference number, QR, fee and verification text.
11. Official outage procedure and maximum transmission delay.
12. Credit/debit note, cancellation, refund and correction rules.

Changing a registration, activity, store jurisdiction or integrator creates a new versioned applicability decision. It must not rewrite tax data on posted sales.

## Tax calculation and receipt principles

- A tax rule is versioned with authority, jurisdiction, registration, activity/product classification, price basis, rate, rounding, exemption/SRO and effective interval.
- Posted sale lines retain the inputs and calculated components used. Later rule changes do not recalculate history.
- Inclusive pricing derives the exclusive/tax split using the selected rule and its rounding policy. Exclusive pricing adds tax. The UI clearly labels the basis.
- Product bundles allocate consideration to components only under a frozen tax rule; they do not introduce recipes.
- Exempt and zero-rated are different statuses and remain distinct in data and reporting.
- A local receipt number is never presented as an authority invoice/reference number.
- Authority acceptance, QR and logo appear only when allowed by that adapter's confirmed contract.

## Returns, corrections and consumer policy

The ordinary MyPoS return window remains merchant-configurable with a proposed default of seven days and a maximum of 30 days, plus manager exception and audit. That commercial setting cannot suppress a mandatory legal remedy or authority correction.

For fiscal records:

- Original sale remains immutable.
- A return posts a separate local return and authority credit/debit note or prescribed correction linked to the accepted original.
- FBR DI documentation uses invoice references for debit notes, while S.R.O. 69 requires electronic debit/credit notes. STGO 01/2026 adds the 72-hour correction rule for valid FBR electronic invoices.
- SRB FAQ describes returns/exchanges through credit notes. KPRA documents a separate credit-note API keyed to the original invoice and an idempotent credit-note number.
- If the original has not been fiscally accepted, the adapter applies its verified sequence; it must not invent an authority reference.

Sindh's Consumer Protection Act requires a goods receipt with listed sale/product details and clear disclosure of the return/refund policy before completing the transaction. It also provides consumer remedies beyond a merchant's voluntary policy. This does not prove identical requirements nationwide, so receipt/policy review remains jurisdiction-specific. Source: [Sindh Consumer Protection Act 2014](https://sindhlaws.gov.pk/setup/publications_SindhCode/PUB-15-000096.pdf), accessed 2026-09-28.

## Payroll boundary

MyPoS operational payroll records pay, overtime, allowances, deductions, advances, approval, payslip and payout. It is not an automatic statutory payroll engine.

Section 149 salary withholding and current tax-year rates can change, and social-security/workers' schemes vary by province and employer/employee category. The product therefore stores named statutory deductions/contributions entered or imported from a merchant-approved calculation, source period, authority, external reference and approver. It does not calculate or file them without a separately verified adapter.

Official context: [FBR Income Tax Ordinance updated to 30 June 2026](https://www.ipv6.fbr.gov.pk/Categ/Income-Tax-Ordinance/326), [FBR withholding rate cards including Tax Year 2027](https://www.fbr.gov.pk/withholding-taxes-rate-card/174298/174301), and [SESSI official portal](https://sessi.gov.pk/), accessed 2026-09-28.

## Applicability result states

Each store/activity/regime assessment ends in one of:

- `not_applicable` — supported by a dated approved assessment;
- `required_unconfigured` — obligation identified, credentials/mapping absent;
- `sandbox_only` — configuration is test-only;
- `production_ready` — all launch gates passed;
- `suspended` — credential, rule or authority status no longer valid;
- `verification_required` — merchant facts or authoritative instructions are incomplete.

Only `production_ready` may issue a receipt claiming live fiscal integration. `not_applicable` may issue a normal receipt without authority claims. All other states block production fiscal claims and follow the store launch policy in [fiscal launch gates](fiscal-launch-gates.md).

