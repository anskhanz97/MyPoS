# Complete planning Q&A and decision history

Recorded 2026-09-28. This captures every planning question and answer, including later changes. Option wording is condensed where necessary; free-text answers are retained where they establish a distinct requirement. Latest answers control scope. Labels such as Recommended describe the choices originally offered, not independent validation.

## Original request and context

The user has worked on retail and hospitality desktop C# tills that sync to separate web clouds. All available web code is in `C:\XEPOS`; C# code is unavailable. The goal is a new complete web POS in Vue/Laravel/MySQL, drawing on the existing web schema/modules. The user requested slow, detailed source study, internet research of strong POS products and best practices, and multiple durable module documents before building.

The user later clarified that desktop-rule reconstruction is unnecessary and excluded it. Existing web code remains useful; no dependency on recovering C# behaviour remains.

## Initial direction questions

### Q001 — Product shape

Question: One commercial platform for both business types, two separate products, or a single-business deployment?

Answer: One shared multi-merchant SaaS. User clarification: "we will provide the features of both under one roof" and "doesnot need to label our product or features as retail or hospiltialty". Do not restrict purchase orders or any included module by historical project label. Later scope answers remove table/kitchen workflows entirely.

### Q002 — Offline operation

Question: Continue core sales, require internet, or provide full local-network store continuity?

Answer: Continue core sales. Later Q028 limits this to cash with cached pricing/discounts; no offline loyalty redemption or external payment confirmation.

### Q003 — First market

Question: UK, Pakistan or multiple countries?

Answer: Pakistan.

### Q004 — Code reuse

Question: Modernize selectively or preserve database/API compatibility?

Answer: Modernize selectively. Preserve useful features/rules, redesign schema and code where web sales or tenancy require it.

### Q005 — Existing data

Question: New merchants first or full existing-merchant/history migration?

Answer: New merchants first, with catalog/customer imports. Full historical migration is not a launch requirement.

### Q006 — Till devices

Question: Windows first, Windows/Android, or include iPads?

Answer: Windows first: Chrome/Edge, USB scanners and thermal printers; responsive management elsewhere.

### Q007 — Printing helper

Question: Allow a small installed Windows printing helper or require browser-only hardware access?

Answer: Allow helper. POS application remains web-based.

### Q008 — Pakistan coverage

Question: Nationwide research, Punjab first or Sindh first?

Answer: Nationwide study, distinguishing federal and provincial applicability and launch gates.

## Explicit removals following plan review

### D001 — Recipes removed

User: "the first annotation is out of my scope for the app remove it completely". Removes recipes, ingredient consumption, preparation batches, yield, substitutions and wastage-management module. Fixed product bundles/extras were subsequently selected and do not restore recipes.

### D002 — Reservations and delivery removed

User: "remove 2nd annotation too". Removes reservations, waitlists, seating-capacity planning, booking deposits/no-shows, collection-slot scheduling, delivery zones/dispatch/tracking. Q009 subsequently removes table and kitchen workflows as well.

### D003 — C# reconstruction removed

User: "we donot need to look for or cover misisng C# code at all. drop that as well.... think for yourself search the internete you will find answers." Do not create a legacy desktop evidence-recovery workstream.

The user asked for module-by-module questions with options, followed by a revised plan. The following are those answers.

## Sales, catalog and purchasing questions

### Q009 — Table sales

Question: Keep tables/kitchen tickets, add kitchen display, or counter checkout only?

Answer: Counter checkout only. No table billing or kitchen workflows.

### Q010 — Stock tracking

Question: Standard plus batches/expiry, standard only, or also serialized items?

Answer: Standard plus batches/expiry. No serial tracking at launch.

### Q011 — Advance sales

Question: No advance-sale workflows, deposits/layaway, or full quotation-to-sale?

Answer: No advance-sale workflows. Q014 later also excludes customer credit.

### Q012 — Catalog

Question: Flexible catalog, standard plus variants, or flexible plus services?

Answer: Flexible catalog: standard products, size/color variants, weighted goods, pack conversions, fixed bundles and selectable extras. No recipes or non-stock service module.

### Q013 — Purchasing

Question: Orders through returns, receiving only, or add approval thresholds/invoice matching?

Answer: Orders through returns: purchase orders, partial receiving, batch/expiry capture, purchase returns, supplier balances and payment records. Advanced procurement approvals/matching were not selected.

### Q014 — Customer credit

Question: Controlled credit accounts, pay in full only, or basic running balance?

Answer: Pay in full only. Customer profiles/history remain; no credit limits, debt accounts or customer statements of unpaid sales.

### Q015 — Payments

Question: Cash plus recorded external payments, integrated automated payments, or cash only?

Answer: Cash plus external payments. Cashiers record payments made on external card/wallet/bank channels. Split payments are allowed if the full sale is settled. Automated payment integration may follow separately.

### Q016 — Returns

Question: Original-sale-linked returns/exchanges, also unlinked returns, or refunds only?

Answer: Linked returns plus exchanges. Full/partial returns, difference settlement, return windows and manager controls. No unlinked returns selected.

### Q017 — Loyalty

Question: Points/rewards, full tiers/visits/cards/portal suite, or no loyalty initially?

Answer: Points and rewards. Member-specific earning/redemption, expiry, refund reversal and sharing across the merchant's stores. Q033 adds a limited member portal without selecting the rest of the full suite.

## Configurable modes and workforce

### Q018 — Promotions

Question: Full counter-sale promotions, simple discounts, or manual discounts only?

User answer: "There should be a toggle for these settting/mode, to be turned onn and off between Manual Discounts only or A counter sale Set that includes scheduled prices, coupons , quantity discounts, and buy X get Y etc.... so 2 modes in our case... used as wished by the end user.... on demand."

Decision: Two modes, with preserved definitions/history. Advanced includes the offered counter-sale set; Q022 determines configuration scope.

### Q019 — Stored value

Question: Neither gift cards/store credit, refund store credit only, or both?

Answer: Neither at launch. Loyalty points are separate from gift/store-credit monetary balances.

### Q020 — Workforce

Question: Access/attendance, access only, or also rota/leave?

User answer: "Point 1 and 3 both are needed. Access + Attendance + Leave System + Management + Payroll".

Decision: Include all those capabilities. Q021 selects operational payroll depth.

### Q021 — Payroll depth

Question: Operational payroll, Pakistan statutory payroll engine, or export-only preparation?

Answer: Operational payroll: monthly/hourly pay, overtime, allowances, deductions, advances, payslips, approval and recorded payouts. Statutory amounts are entered/imported, not automatically legally calculated.

### Q022 — Promotion-mode scope

Question: Merchant default with store override, one mode per merchant, or per register?

Answer: Merchant default plus store override, authorized and audited. No per-register pricing-mode divergence.

### Q023 — Cash control

Question: Register sessions, drawer per cashier, or simple daily totals?

Answer: Register cash sessions with opening float, cashier attribution, paid-in/out, blind count, expected cash, variance and manager review.

## Integrations, branch ownership and offline

### Q024 — Launch integrations

Question: Exports first, accounting connectors, or accounting plus commerce?

Answer: Exports first. Catalogue source connectors for later. Pakistan fiscal requirements remain a separate obligation to assess.

### Q025 — Branch data

Question: Shared core/local stock and prices, or mostly independent stores?

User answer: "We should have a toggle beetween these 2 points as well.... if the business owner wasnt shared core so he can have it, if he want all his store to be independent he can have it too".

Decision: Offer both at onboarding. Q027 explicitly locks the choice, superseding an unrestricted live toggle interpretation.

### Q026 — SaaS billing

Question: Administrator-managed subscriptions, self-service automated billing, or unpaid pilot?

Answer: Administrator-managed subscriptions. Build plans/entitlements, manually record payments and activate/renew merchants.

### Q027 — Changing store mode

Question: Guided conversion, choose at setup then lock, or explicitly link independent records?

Answer: Choose at setup, then lock. Future changes require separately planned migration.

### Q028 — Offline payment/discount limits

Question: Cash with cached discounts, also external payments, or cash at standard prices only?

Answer: Cash plus cached discounts. Downloaded prices/promotions and permitted manual discounts remain available. Loyalty redemption/external payment confirmation require connectivity.

### Q029 — Stock guards

Question: Block insufficient stock with manager exceptions, warn and permit negatives, or strict offline allocation?

Answer: Warn on low/negative stock; permitted cashiers can sell with warnings and audit. Expired batches remain blocked. Offline stock can require reconciliation.

## Presentation, reports and customer surfaces

### Q030 — Languages

Question: English with bilingual receipts, bilingual throughout, or English only?

Answer: English only. Unicode data preserved. No Urdu UI or bilingual template promise at launch.

### Q031 — Reporting

Question: Standard reports/saved filters, dashboard builder, or custom report builder?

Answer: Standard reports plus saved filters, drill-down and exports. Covers sales, margin, tax, payments, returns, stock/expiry, purchasing, loyalty, staff/payroll and cash.

### Q032 — Receipt delivery

Question: Print/PDF/email, print/PDF only, or also SMS/WhatsApp?

User answer: "initially we start with print/pdf + email then comes also the SMS/Whatsapp on demand... to be turned onn and off when felt like it ."

Decision: Print/PDF/email at launch. Later messaging module with merchant enable/disable controls, provider configuration and delivery records.

Implementation note (2026-09-29): the user later explicitly requested this extension. T038 now provides optional merchant-controlled SMS/WhatsApp receipt delivery and the wider integration hub; it remains outside the launch dependency chain.

### Q033 — Customer self-service

Question: Staff checkout only, add loyalty portal, or also kiosk?

User answer: "Stafff chceckout only recommmed sounds good but just with an addition that Customer should have a page/portal to view points/rewards only for viewing purpose like a profile of their own.... but ordering is all for the staff...".

Decision: Staff-only ordering plus limited view-only loyalty page, with joining permitted. No customer redemption or ordering.

### Q034 — Hardware

Question: Scanner/printer/drawer, also connected scales, or also customer display?

User answer: "to start with point 1 makes sense as it is recommended but Also a customer Display is an addon, to be turned onn and off as per custoemrs easiness if he has a second screen then we must be equipped tp support it if not then ok by our side....".

Decision: First option plus optional second display. Do not carry over direct scales from the third option. Use entered weight or printed scale barcodes initially.

### Q035 — Finance depth

Question: Expenses/operational records, operational records only, or full accounting?

Answer: Expenses plus operational records, settlement records and exports. Full ledger accounting is excluded.

## Final identity, costing and policy details

### Q036 — Member access approach

Question: Email sign-in link, phone OTP, or email/password?

User answer: "i think the approach we have on loyalty portal is the best choice... a particular url for customers if not signed iup fill in the form and join.... if already joined thne enter you phone number etc and se your profile/points.... one form serves multiple purpose.... study the loyalty portal porject".

Action taken: Inspected Join.tsx, JoinController.php, useJoinPolling.ts and MemberSuccessView.tsx. Q039 settles data disclosure/verification. Recreate experience in Vue; original portal is React.

### Q037 — Staff access

Question: Customizable roles/manager approvals or fixed simple roles?

Answer: Fixed simple owner, manager and cashier roles with PIN switching/audited approvals. Do not build a custom role designer. T008 documents exact permissions, including payroll confidentiality.

### Q038 — Stock valuation and additional refund instruction

Question: Moving weighted average, FIFO, or research and choose?

User answer: "yes we can go with first option as its recommended... but we should have a no refund policy after a week or 30 days at max.... ofcourse the days should be configurable".

Decision: Moving weighted average. Configurable normal refund window up to 30 days; proposed default 7. Q040 explicitly permits manager exception.

### Q039 — Member lookup protection

Question: Phone/member PIN, phone-only limited view, or phone/SMS code?

Answer: Phone-only limited view. Only points and available rewards plus masked identity. User was informed anyone knowing the number could see that limited balance. No full private profile or authentication claim; no SMS launch dependency.

### Q040 — Refund deadline enforcement

Question: Strict cutoff or manager exception?

Answer: Manager exception with mandatory reason/audit. The ordinary setting remains capped at 30 days; the exception must be distinguishable from changing the global policy.

### Q041 — Loyalty in independent stores

Question: Match independent store mode or always merchant-wide loyalty?

Answer: Always merchant-wide loyalty, even where catalogs and operational customer records are separate.

## Latest delivery instructions

### D004 — Ticket library, not immediate application coding

User now requests detailed Markdown files covering execution of the plan, every Q&A and all agreed information, divided into numbered tickets to request one at a time. Deliver durable context, dependencies, implementation requirements and acceptance checks. Do not execute the app backlog merely because documentation is being written.

### D005 — Two themes

User: "for the frontend you may use your skills but i want 2 themse or this app one darkmode and one bright mode to be toggle onn and off easily".

Decision: Complete light/dark themes across frontend surfaces, easily toggled and persisted, verified in every UI ticket. Details in [frontend standard](04-frontend-and-two-themes.md).

## Important supersessions

- Initial comprehensive-feature aspiration does not override explicit exclusions.
- Separate retail/hospitality workflows were rejected; counter-only is the final sales model.
- Customer credit was temporarily present in an early option but later expressly rejected.
- Store mode was initially described as a toggle but then locked at setup by explicit answer.
- Independent operational data does not make loyalty store-local.
- Gift cards/store credit are absent; loyalty points remain.
- Payroll/rota/leave are requested despite corresponding source screens being placeholders.
- Limited phone lookup was intentionally chosen over PIN/OTP, but returning the full legacy member object was not authorized.
- Manager late-return exceptions supersede a strict interpretation of the ordinary deadline.
