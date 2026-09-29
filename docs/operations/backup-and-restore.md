# Backup, restore and tenant recovery

Version 1.0 — 2026-09-29

## Policy and targets

The provisional pilot target is an RPO of 15 minutes and an RTO of two hours. Meeting the RPO requires provider-managed point-in-time recovery or encrypted MySQL binary logs in addition to nightly full backups. Keep daily backups for 35 days, monthly backups for 12 months and fiscal/financial evidence for the applicable legal period. These are proposed pilot controls; the hosting provider, merchant legal advice, storage region and cost must be approved before production.

Back up MySQL plus every private disk holding import sources, export artifacts and generated documents required for recovery. A database-only backup can leave artifact references unusable. Encrypted merchant integration credentials require the matching application-encryption key; protect and restore that key through a separately controlled recovery procedure. Encrypt backups before they leave the database host, store backup keys separately, restrict access, make copies immutable where available and alert on missed jobs.

## Tools

`scripts/backup-mysql.ps1` performs a consistent transaction dump without requiring MySQL `PROCESS`, writes no password into the command line, encrypts the dump as a streaming AES-256-CBC envelope with PBKDF2-SHA256 (200,000 iterations), writes a SHA-256 manifest and deletes the plaintext. Supply database values in environment variables and the encryption password through a protected file.

`scripts/restore-mysql.ps1` verifies the requested SHA-256 before decryption and refuses any target database whose name does not end in `_restore_verify`. It never targets the configured live schema. A DBA must pre-create/grant the isolated schema when the runtime identity cannot do so.

Example:

```powershell
$env:DB_HOST='db.internal'
$env:DB_PORT='3306'
$env:DB_DATABASE='mypos'
$env:DB_USERNAME='mypos_backup'
$env:DB_PASSWORD=(Get-Content C:\secure\db-password.txt -Raw).Trim()
.\scripts\backup-mysql.ps1 -OutputDirectory D:\protected-backups -EncryptionPasswordFile C:\secure\backup-key.txt -MySqlDump C:\mysql\bin\mysqldump.exe
.\scripts\restore-mysql.ps1 -EncryptedBackup D:\protected-backups\mypos-....sql.enc -EncryptionPasswordFile C:\secure\backup-key.txt -TargetDatabase mypos_quarterly_restore_verify -ExpectedSha256 <manifest-hash> -MySql C:\mysql\bin\mysql.exe
```

## Restore drill record

On 2026-09-29 the test database was dumped and encrypted locally. The encrypted artifact measured 311,320 bytes with SHA-256 `20d1c004b24ef88ccc5f718c5d1b8bd303e0cb49861e0eb7fcd2b91b04158bcb`. It restored into `mypos_t036b_restore_verify`. Source and restored schemas each contained 150 tables and 107 migration rows. The combined grant/restore/verification command completed in 19.8 seconds; the backup command completed in under five seconds. This tiny local database and local disk do not predict production RTO. No object-store recovery or point-in-time replay was available to test.

For every quarterly drill, verify manifest, restore to isolation, compare schema/migration counts, compare financial-ledger row counts and sums, run reconciliation commands, inspect unresolved outbox/offline/fiscal states, and perform a synthetic sale/return using a disposable tenant. Destroy the isolated copy through the approved DBA process after evidence retention.

## Tenant-specific recovery

A merchant export is useful for portability but is not a transactionally complete tenant restore. Shared-table recovery requires a consistent database copy, referential extraction by merchant, collision-safe public IDs and reconciled ledgers. Until a tested selective-restore tool exists, recover the full backup to isolation, identify affected rows, prepare a reviewed repair migration, rehearse it, then execute under incident control. Never copy encrypted credential values into another environment without an approved key plan.
