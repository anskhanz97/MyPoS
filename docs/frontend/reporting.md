# Standard reporting implementation reference

T032 provides fixed sales, inventory, purchasing, expense, loyalty, workforce/payroll and cash reports. Queries read authoritative posted records and return a generation timestamp, scope, timezone, fixed metric definitions and known limitations. The implementation does not add a report designer or editable reporting ledger.

## Scope and semantics

- A `store_id` produces a store report and requires assignment plus `report.store`; omission requests a merchant-wide report and requires `report.consolidated`.
- Workforce reports additionally require `payroll.manage`, including store-level totals. Saved filters are private to their creating user and store IDs are resolved and authorized before saving.
- Report windows are inclusive and limited to 366 days. Sales use sale business dates; returns use their own business dates. Loyalty remains merchant-wide and is never multiplied by store count.
- Expense totals exclude supplier and payroll payouts. Cost and margin totals expose provisional cost adjustments rather than hiding them.
- CSV output is private/no-store and protects spreadsheet consumers from formula-leading values. T033 owns asynchronous large exports.

## Interface and verification

The management preview presents scope and freshness, fixed KPIs, a non-color-only chart, source rows, a personal saved-filter result and metric limitations. It uses shared theme tokens and responsive layout in both themes:

- `docs/evidence/t032-reporting-light.png`
- `docs/evidence/t032-reporting-dark.png`

MySQL feature tests verify store/consolidated reconciliation, cross-store exclusion, report permissions, payroll privacy, the date bound, per-user saved filters, foreign-store rejection and CSV output. The browser scenario verifies KPI drill-down, filter feedback and both themes.
