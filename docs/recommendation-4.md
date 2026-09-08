# Recommendation 4: transaction reporting

Implemented in the repository; the live database has NOT been migrated.

## Behavior and compatibility

- Purpose is required on manual create/edit and OCR confirmation (1–1000 trimmed characters). Editing a legacy transaction requires a purpose. OCR never supplies Purpose or Allocation/Project Code.
- Allocation/Project Code is optional, up to 50 characters. Blank input is stored as NULL. Incoming funds reuse their existing Project_Code. Unallocated is a legitimate status.
- Legacy NULL/blank purposes display `Not specified`; allocations display `Unallocated`. A printable notice counts distinct affected transactions and separately counts missing purposes and unallocated transactions. Financial Records counts the complete filtered selection, not just the visible page.
- Organization Balance After Transaction is all prior incoming amounts minus expenses, followed by each selected-period transaction in date order. Same-day ordering is Incoming, then Expense, then ascending record ID. Source amounts remain positive. Balance arithmetic uses integer cents; currency formatting preserves cents.
- Financial Records keeps newest-first, 50-row pagination. Type/category filtering and pagination happen after organization balances are calculated. The monthly report is unpaginated and uses the Income Statement's selected month/year and category totals from the same ledger snapshot.
- Query failures display an error and suppress financial results. Empty periods display their opening and unchanged closing balance.
- Reports use landscape A4 printing, repeating headers, wrapping cells, and a full-width detailed table. The existing year selector, roles, review workflow, category helpers, and monthly category budgets are retained.
- Period rows are loaded into memory before filtering/pagination. This favors one consistent ledger calculation; exceptionally large date ranges may need a later server-side pagination optimization.

## Manual deployment — do not run automatically

Run from `C:\xampp\htdocs\Financial Management System` in PowerShell. These commands target the configured live MariaDB on port 3306 as root; adjust connection arguments only if the deployment uses different credentials. The disposable test server used port 3307 and is unrelated.

1. Schedule maintenance and stop application writes. Save a copy of the previous application release. Make a fresh database/upload backup and verify restoration into a new disposable database using the existing backup workflow:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts/backup_migration.ps1 -CopyDatabase atikha_test_rec4_predeploy_YYYYMMDD -Directory .migration-private/rec4-predeploy-YYYYMMDD
   ```

   Replace `YYYYMMDD` with the deployment date and use a previously unused database/directory name. This backup command is provided for the administrator; it was not executed against live data during implementation. Do not continue if restoration verification fails.

2. Confirm migrations 001–010 are already reflected in the target schema. Do not rerun those migrations or the role cleanup. Record the following preflight output and compare it after migration:

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' --host=127.0.0.1 --port=3306 --user=root --default-character-set=utf8mb4 atikha_finance --execute="SHOW COLUMNS FROM Incoming_Funds; SHOW COLUMNS FROM Expenses; SHOW INDEX FROM Incoming_Funds; SHOW INDEX FROM Expenses; SELECT COUNT(*) AS incoming_count, SUM(Amount) AS incoming_total FROM Incoming_Funds; SELECT COUNT(*) AS expense_count, SUM(Amount) AS expense_total FROM Expenses; SHOW TRIGGERS;"
   ```

   Expected existing Incoming_Funds.Project_Code: nullable VARCHAR(50). Purpose should be absent or already nullable VARCHAR(1000); Expenses.Project_Code should be absent or already nullable VARCHAR(50). Stop on conflicting definitions. Existing same-name date indexes must index the correct date columns.

3. Apply only migration 011 while writes remain stopped, before serving the new PHP files:

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' --host=127.0.0.1 --port=3306 --user=root --default-character-set=utf8mb4 atikha_finance --execute="SOURCE migrations/011_transaction_reporting.sql"
   if ($LASTEXITCODE -ne 0) { throw 'Migration failed. Keep maintenance active.' }
   ```

   The existing `scripts/migrate_schema.php` runner only accepts 009/010; do not use it for 011. The new SQL uses MariaDB's guarded ADD COLUMN/INDEX syntax, verified on MariaDB 10.4.32. No existing migration or `database.sql` was rewritten. Fresh installations already apply all numbered migrations through the existing installer.

4. Repeat the preflight query. Verify unchanged transaction counts/totals, existing project codes, authorship, and audit triggers; verify the three new nullable fields and both date indexes. Compare original-column rows against the restored backup for full preservation evidence. New fields on legacy records must remain NULL, never invented descriptions.

5. Serve the new code and compiled CSS. Check a historical report, an empty month, Financial Records, manual editing, and an OCR confirmation. Confirm completeness counts and opening/closing balances. Reopen writes only after validation succeeds.

## Recovery

Each ALTER TABLE commits independently. A PHP rollback cannot undo migration 011. If migration or validation fails, keep maintenance active and stop deployment. Preserve the error and schema state. Restore the previous application release; it ignores the additive nullable columns, so leave those columns and any collected metadata intact. After checking definitions, migration 011 can resume missing additions safely.

If data preservation fails, keep the original database and evidence untouched and restore the verified backup into a separate recovery database for investigation and an explicit recovery decision. Do not drop the live database, disable foreign keys/audit triggers, or remove the new metadata as an automatic rollback.

## Validation

All database work used an isolated MariaDB 10.4.32 instance with a separate data directory under `.migration-private/recommendation4-db`, bound to 127.0.0.1:3307. The live database was not modified.

- `scripts/test_ledger.php`: 23 assertions passed, including preservation through migration/rerun, cents, historical balances, boundaries, ordering, negative balances, pagination/filtering, metadata, completeness, custom categories, empty periods, and query failure cleanup.
- `scripts/test_http.py`: 141 assertions passed, including Admin/Management reports, required legacy edits, OCR confirmation/retry, manual metadata changes, audit payloads, positive storage, escaping, completeness, and route-level database errors.
- Existing `scripts/test_two_roles.php`: 22 assertions passed using a restored disposable historical fixture plus a fresh installation through migration 011.
- Existing `scripts/test_mfa_flow.php`: 8 assertions passed.
- All 10 changed/new PHP files passed `php -l`; Python syntax and `git diff --check` passed.
- Tailwind rebuilt using `node_modules/.bin/tailwindcss.cmd -i src/input.css -o assets/css/tailwind.css --minify`. Only the existing stale Browserslist metadata warning was emitted. `npm test` is a placeholder, not a project test suite.
- Browser inspection covered Admin/Management screen and print layouts. A 69-row layout stress fixture generated eight A4 landscape pages; rendered first-detail, continuation, and final pages were inspected for readable columns, repeated headers, and closing balance. Static visual fixtures used the locally compiled stylesheet to avoid CDN dependence.

For repeatable ledger tests, create a new empty `atikha_test_*` database and run:

```powershell
$env:ATIKHA_DB_HOST = '127.0.0.1;port=3307'
& 'C:\xampp\php\php.exe' scripts/test_ledger.php --database=atikha_test_rec4_ledger_new
```

Run HTTP tests against a separate freshly installed disposable database containing `admin@example.invalid`, created using the existing bootstrap helper/test workflow. HTTP tests intentionally create fixtures and must not reuse a prior HTTP run's data. They never copy SMTP/Gemini configuration or call those external services.

## Changed files

- `funds.php`, `expenses.php`, `ocr_expense.php`: persisted metadata, validation, entry/edit UI, audit payloads, OCR retry handling.
- `includes/transaction_details.php`: shared validation, fields, and legacy display labels.
- `includes/ledger_query.php`, `includes/ledger_ui.php`: shared exact balances, selection/pagination, completeness, eight-column rendering.
- `reports.php`, `financial_records.php`: unified detailed reporting and explicit error handling.
- `migrations/011_transaction_reporting.sql`: additive fields and date indexes.
- `scripts/test_ledger.php`, `scripts/test_http.py`, `scripts/http_fixture.php`: isolated regression coverage.
- `assets/css/tailwind.css`: rebuilt styles.
- `docs/recommendation-4.md`: behavior, validation, deployment, and recovery instructions.
