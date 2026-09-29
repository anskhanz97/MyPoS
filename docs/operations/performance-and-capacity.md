# Performance and capacity record

Version 1.0 — 2026-09-29

No production capacity claim is made. Current evidence comes from a Windows development host, PHP 8.3.30, MySQL 8.4 on localhost and synthetic test data. The full schema has 150 tables and 107 migration rows. A fresh schema build observed during fiscal verification took about 108 seconds; release planning must treat migration time as material and measure it on a production-sized copy.

Representative domain tests cover catalog, pricing/quote, checkout, reports, queue/outbox, offline replay and imports with MySQL constraints. T037 records their aggregate durations and failures. The only HTTP load surface measured independently before a deployed pilot is the minimal health endpoint; authenticated route p95 and multi-user concurrency remain a pilot measurement gate. Do not turn unit/integration duration into a throughput promise.

## Pilot workload

Seed two merchants with both store data modes, at least 10 stores, 50 registers, 100 staff, 25,000 variants, 100,000 customers, 12 months of sales and realistic inventory/cost/outbox records. Run 1, 5, 20 and 50 concurrent register journeys for barcode search, cart mutation, quote and cash finalization; run reports/exports concurrently on separate workers. Measure p50/p95/p99, errors, database queries, lock waits, CPU, memory, connection count, queue age and generated artifact size.

Provisional acceptance targets are p95 under 150 ms for barcode lookup, 300 ms for cart mutation/quote, two seconds for online sale finalization excluding external providers, and five seconds for ordinary bounded reports. Error rate must remain below 1% with no lost/duplicated posting and no cross-tenant result. These are targets to validate, not achieved results.

Use cursor pagination and bounded date windows, eager-load known relationships, index scope plus lookup/order columns, and move exports/email/fiscal transport to queues. Examine slow-query plans before adding indexes; every additional posting-table index increases write cost. Keep database connection pools bounded below server capacity and reserve connections for recovery/operations.
