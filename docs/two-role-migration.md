# Two-role migration runbook

Source implementation is ready for rehearsal. Live schema and cleanup require separate execution approval.
Run commands from `C:\xampp\htdocs\Financial Management System` in PowerShell.

## Invariants

- Keep Users 1 (System Administrator) and 8 (Zoe) unchanged, including all credential/MFA fields.
- Keep display labels **System Administrator** and **Management and Board**.
- Keep every original audit row and its original actor. Historical Staff identities are evidence, not operational roles.
- Transfer six receipt custody fields to UserID 1, with six audit entries containing original identity, new custodian, receipt IDs, and reason.
- Explicitly delete nine test-inbox notifications and two completed test reset requests, with two grouped audit entries. Do not transfer them.
- Retain Ron's board message and attachments unchanged; resolve Sender_UserID through user_identities.
- Delete only Users 2, 7, 9 and 10 after all direct Users dependencies are clear, with four audit entries.
- Preserve 13 funds totaling PHP 2,420,000.00 and 56 expenses totaling PHP 887,479.40.
- Keep all Management permissions, including review notes/status and forecast refresh, and all Admin functions.
- No centralized session-revalidation mechanism is added.

The analysis baseline was 238 audit rows. The implementation preflight on 2026-09-07 found **239**: an additional Admin LOGOUT event, ID 240. The 81 retiring-actor rows are unchanged in count. Preserve all 239; twelve migration entries produce 251 rows. The utility defaults to 238 and fails on drift; explicitly supply the reviewed current count. Never delete audit events to make a baseline match.

## Connection and isolation

CLI tools require `--database=atikha_finance` or a name matching `atikha_test_[a-z0-9_]+`.
Connection defaults match existing local setup: 127.0.0.1, root, empty database password.
For other configurations set `ATIKHA_DB_HOST`, `ATIKHA_DB_USER`, and `ATIKHA_DB_PASSWORD` locally; do not commit them.
The backup tool passes the database password to child clients through their environment, not command-line arguments.
Tests reject the live database. HTTP tests copy the application and dependencies, replace only the copied database selection, omit config.php, and use copied/isolated uploads and session storage. SMTP delivery and paid AI calls are not part of this suite.

The checked-in `.migration-private/.htaccess` denies Apache access. Exports, upload copies, manifests and HTTP test copies underneath it are gitignored. Keep these private; database exports contain credentials and personal data. Production backup storage should be outside the document root when available. The final approval concerns live execution, not publishing these backup files.

## Before final live execution

1. Obtain separate approval for live migration.
2. Stop application writes for the entire schema/data migration window (including Apache, jobs and other database clients).
3. Revoke existing Atikha authenticated and pending-MFA sessions using deployment/session administration while the application is stopped. Do not log out through application routes after taking the final backup, because logout appends an audit row. The local PHP session directory is `C:\xampp\tmp`; it can be shared with other applications. Do not indiscriminately delete its contents. This is an operational prerequisite, not a new session-revalidation feature.
4. Run the commands below individually. **Stop on any nonzero exit code.** Choose a new backup directory and disposable database name if these names already exist; the backup tool refuses overwrite.

## Exact final live commands — DO NOT RUN before approval

```powershell
$ErrorActionPreference = 'Stop'
$php = 'C:\xampp\php\php.exe'

# Mandatory new export + complete upload backup + restore/verification on a copy.
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/backup_migration.ps1 -CopyDatabase atikha_test_final_20260907 -Directory '.migration-private/final-20260907'
if ($LASTEXITCODE -ne 0) { throw 'Backup/restoration verification failed' }

$manifest = (Resolve-Path '.migration-private/final-20260907/manifest.json').Path
& $php scripts/verify_migration.php --database=atikha_finance "--backup-manifest=$manifest" --stage=before
if ($LASTEXITCODE -ne 0) { throw 'Live baseline differs from verified backup' }

& $php scripts/consolidate_users.php --database=atikha_finance --dry-run --expected-audit-count=239
if ($LASTEXITCODE -ne 0) { throw 'Preflight drift requires review' }

# Checkpoint A: independently committed DDL; audit triggers remain installed.
& $php scripts/migrate_schema.php --database=atikha_finance --phase=009 --execute --maintenance-confirmed "--backup-manifest=$manifest"
if ($LASTEXITCODE -ne 0) { throw 'Checkpoint A incomplete; keep maintenance active' }

# Data-only transaction: six transfers, eleven workflow deletions, four Users,
# and twelve audit events. Ron's message is never updated or deleted.
& $php scripts/consolidate_users.php --database=atikha_finance --execute --expected-audit-count=239 --maintenance-confirmed "--backup-manifest=$manifest"
if ($LASTEXITCODE -ne 0) { throw 'Cleanup failed; its data transaction rolled back' }

# Checkpoint B: two independently committed ALTER TABLE operations.
& $php scripts/migrate_schema.php --database=atikha_finance --phase=010 --execute --maintenance-confirmed "--backup-manifest=$manifest"
if ($LASTEXITCODE -ne 0) { throw 'Checkpoint B incomplete; keep maintenance active' }

& $php scripts/verify_migration.php --database=atikha_finance "--backup-manifest=$manifest" --stage=after
if ($LASTEXITCODE -ne 0) { throw 'Post-migration verification failed; do not reopen' }
```

After verification, start the application and verify Admin/Management navigation and login. These new logins will legitimately append audit entries after the migration verification checkpoint.

## DDL and recovery

`CREATE TABLE`, `ALTER TABLE`, foreign-key changes, stored-procedure DDL and ENUM changes implicitly commit. They are **not** rolled back by the cleanup transaction. Each schema checkpoint must finish and be verified before the next step.

Migration 009 uses different new foreign-key names for MariaDB compatibility and guards each old constraint independently so an interrupted checkpoint can resume. It never drops the audit UPDATE/DELETE triggers. Migration 010's runner refuses unknown operational roles before either ALTER. A partial 010 can be safely retried once its prerequisite checks pass.

If data cleanup fails, transfers/deletions/audit additions roll back together. If DDL fails, stay in maintenance and either repair/resume that checkpoint or restore the verified database export and upload backup together. Restore first to a new empty recovery database, verify it with backup_verify.php, and only then perform a separately approved live database replacement. Do not disable foreign keys, drop audit protection, truncate audit data, reset AUTO_INCREMENT, or run cleanup again after success; reruns refuse before modifying data.

Live execution validates the verified export checksum, upload backup checksums, full original-table hashes before cleanup, retained account hashes, original financial/board hashes, and original audit hashes. Unexpected new dependencies fail closed. Before execution, the operator's `--maintenance-confirmed` attests that writers and old sessions have been stopped; the utility cannot enforce external server maintenance.

## Fresh installation

Only on a newly provisioned, empty database:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -u root -e 'CREATE DATABASE atikha_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
php scripts/install_database.php --database=atikha_finance --execute
php scripts/bootstrap_admin.php --database=atikha_finance
```

The installer refuses nonempty databases. Bootstrap requires all migrations, an interactive terminal, a hidden password with confirmation, and an empty Users/identity registry. It acquires a database advisory lock, validates the existing password policy, hashes with password_hash(), and creates the Admin, identity, and audit event transactionally. It refuses repeated use. The Windows helper reads keys without echo; redirected input and unavailable secure-console input fail closed. Run it from a real console, not an IDE output pane. No real email or known password is seeded. Configure SMTP privately afterward for normal email MFA, then use Admin's existing User Management feature to create Management accounts.

## Rehearsal and tests

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/backup_migration.ps1 -CopyDatabase atikha_test_rehearsal_new -Directory '.migration-private/rehearsal-new'
php scripts/migrate_schema.php --database=atikha_test_rehearsal_new --phase=009 --execute --maintenance-confirmed
php scripts/test_two_roles.php --database=atikha_test_rehearsal_new --fresh-database=atikha_test_fresh_new --expected-audit-count=239
php scripts/test_mfa_flow.php --database=atikha_test_fresh_new
python scripts/test_http.py --database=atikha_test_fresh_new
php scripts/verify_migration.php --database=atikha_test_rehearsal_new --backup-manifest=.migration-private/rehearsal-new/manifest.json --stage=after
```

The PHP migration suite intentionally changes only the restored disposable database and creates a separate fresh fixture database. The MFA fixture rolls back its row changes. HTTP tests leave synthetic records in their disposable database. Legacy PowerShell test entrypoints require `-Database atikha_test_NAME` and invoke the shared isolated HTTP suite instead of using live credentials or shared cookie jars.
