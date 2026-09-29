# Import and export implementation reference

T033 implements CSV schema version 1 for catalog, customer and opening-stock imports. Uploads are private, UTF-8, limited to 5 MB and 2,000 rows, source-hashed and validated into row outcomes. Validation never mutates operational records. A commit is permitted only when every row is valid and uses a UUID idempotency key.

Catalog rows resolve an existing unit and respect the merchant's locked shared/independent ownership mode. Customer rows use the normal phone/email normalization path and never merge across merchants. Headers for customer debt, balances or credit are explicitly rejected. Opening stock resolves the authorized store, inventory location, variant and optional required batch/expiry, then posts both an opening stock movement and a moving-average cost entry.

Catalog, customer, expense, payroll and fixed-report exports are durable queued jobs. Worker execution repeats actor/scope authorization, limits output to 10,000 rows, emits UTF-8 BOM CSV, escapes spreadsheet formula prefixes and stores a private artifact for seven days. Payroll exports require `payroll.manage`. Downloads are restricted to the initiating actor.

The UI shows upload/validation/review/commit stages, row errors, the zero-mutation dry-run promise and durable export status. Both themes were visually reviewed:

- `docs/evidence/t033-data-transfer-light.png`
- `docs/evidence/t033-data-transfer-dark.png`
