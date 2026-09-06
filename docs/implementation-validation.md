# Two-role implementation and validation

Implementation completed; **live schema and destructive cleanup have not been executed**.

## Live state and dry run

The final read-only verification matched every original live table row and all 19 upload files against the verified backup. Users 1 and 8 remain unchanged, and Users 2, 7, 9 and 10 still exist. The live identity registry has not been created. Both live operational ENUMs still include Staff; both audit immutability triggers remain installed.

- Incoming funds: 13 rows, PHP 2,420,000.00.
- Expenses: 56 rows, PHP 887,479.40.
- Audit: 239 current rows; 81 belong to retiring actors (26 Test Staff, 49 Ron, 4 John, 2 Juan).
- Receipt transfers planned: 6 (1 Test Staff, 5 Ron), all to Admin 1, with per-receipt audit entries.
- Notification deletions planned: 9 (1 Ron, 4 John, 4 Juan); no reassignment.
- Completed reset deletions planned: 2 (Test Staff and John); no reassignment.
- Ron's board message: 1, retained unchanged with historical sender 7.
- No current retiring-user funds, expenses, report snapshots, forecast-cache rows or reset-resolver references.
- User deletions planned: exactly 2, 7, 9, 10, after dependency validation.

Executed read-only live command:

```powershell
php scripts/consolidate_users.php --database=atikha_finance --dry-run --expected-audit-count=239
```

Result: PASS, no writes, schema checkpoint 009 still pending.

## Validation results

- PHP syntax: 62 first-party PHP files, zero errors; subsequently edited PHP files rechecked.
- PowerShell syntax: 7 files, zero parser errors.
- Python syntax: scripts/test_http.py passed AST parsing.
- Git diff whitespace checks passed.
- Database export restored and verified: all 12 tables (definitions and row hashes), trigger definitions, and 19 upload files matched.
- Migration integration suite: 22 assertions passed on a restored disposable database.
- Cleanup failure injection: receipt transfers, workflow deletions and audit appends all rolled back together.
- Successful rehearsal: six receipts transferred; nine notifications, two reset requests and four Users removed; Ron's board message unchanged.
- All retained account rows/credentials and every financial row remained identical on the rehearsal copy.
- All 239 source audit rows remained unchanged; twelve new migration entries produced 251 rows on the copy.
- All 81 retired-user entries remained searchable/exportable under original actors; full-name search passed with native PDO prepares.
- Audit UPDATE and DELETE attempts were rejected by the existing triggers on the disposable database.
- Schema checkpoints 009 and 010 both passed, including reruns. Cleanup rerun refused without duplicate events.
- Fresh-install schema setup passed, with no credential seeds. Bootstrap password hashing, identity/audit insertion and repeat-use refusal passed.
- MFA suite: 8 assertions passed; synthetic fixture changes rolled back.
- HTTP suite: 88 assertions passed in an isolated application copy, covering real MFA verification, Admin financial functions and account creation, Management permissions/notes/inbox/forecast-refresh authorization, Staff role rejection, and OCR/CSRF/access guards.
- Four negative execution guards passed: noninteractive bootstrap refusal, premature live ENUM dry-run refusal, MFA live-target refusal, and HTTP-test live-target refusal.
- Final live hash comparison passed: no original database row or upload-file change.

Test environment: PHP 8.2.12, XAMPP MariaDB 10.4.32, Windows PowerShell, Python 3.13. No live application POST or destructive live SQL was used.

## Warnings and implementation details

1. The approved analysis baseline was 238 audit entries. Before this implementation's database work, an additional Admin LOGOUT event (ID 240) was present, bringing the count to 239. The implementation preserves it as well. The default expected count remains 238; commands explicitly acknowledge 239 and fail on later unreviewed drift.
2. The XAMPP database server was stopped at the start of execution. It was started with permission for read-only live inspection/export and disposable-database testing. Apache was not started for tests; the test server served an isolated copy.
3. No centralized session revalidation was added. One-time authenticated/pending-MFA session revocation and maintenance remain prerequisites for final execution. Do not clear the shared PHP temporary directory indiscriminately.
4. Schema DDL commits independently. Recovery instructions and final command ordering separate schema checkpoints from transactional cleanup.
5. Historical reads support the pre-009 schema while execution awaits approval. Source role changes are present now, but no live migration has run. Staff financial access is removed when these source files are served.
6. Audit full-name search reused a named parameter with native PDO prepares. Distinct placeholders were necessary to meet the required historical search acceptance check.
7. Retired historical board senders have no inbox: notification delivery now skips missing active recipients instead of redirecting alerts or attempting an invalid foreign-key insert.
8. SMTP delivery and paid Gemini calls were not exercised. Forecast-refresh authorization was tested through the insufficient-history response. Secure interactive Windows password entry requires a real console; automated tests verified its noninteractive refusal and the transactional bootstrap core.
9. Tests ran on the installed MariaDB engine, not a separate MySQL 8 server. New foreign-key names avoid the MariaDB same-name replacement error discovered and corrected during rehearsal.
10. Private exports/manifests/test application copies are gitignored under .migration-private, protected from Apache by its checked-in .htaccess. They are not production account seeds or source credentials. Disposable databases and test copies remain available for inspection; no automatic cleanup of these artifacts was performed.

## Final live migration commands

See [the exact approval-gated commands and recovery procedure](two-role-migration.md#exact-final-live-commands--do-not-run-before-approval).

The command sequence is: maintenance and app-session revocation; new export/upload backup; successful copy restoration verification; live dry run; schema checkpoint 009; transactional consolidation; schema checkpoint 010; final read-only verification. **Do not execute this sequence until final live approval.**

## Every changed source file

- `.gitignore`
- `_ajax_test.ps1`
- `_probe.ps1`
- `_roles_test.ps1`
- `_save_test.ps1`
- `_verify.ps1`
- `admin_users.php`
- `audit_trail.php`
- `board_inbox.php`
- `board_messages.php`
- `database.sql`
- `expenses.php`
- `forecast_ai.php`
- `funds.php`
- `includes/audit_query.php`
- `includes/expense_warnings.php`
- `includes/layout.php`
- `includes/nav.php`
- `includes/notifications.php`
- `includes/require_role.php`
- `includes/review_ui.php`
- `includes/user_roles.php`
- `management_reviews.php`
- `migrations/005_fund_project_code.sql`
- `migrations/007_notifications_review_comms.sql`
- `ocr_expense.php`
- `ocr_extract.php`
- `review_actions.php`
- `scripts/test_mfa_flow.php`

## Every created source/documentation file

- `.migration-private/.htaccess`
- `docs/implementation-validation.md`
- `docs/two-role-migration.md`
- `includes/admin_bootstrap.php`
- `includes/user_identities.php`
- `migrations/009_user_identity_history.sql`
- `migrations/010_two_operational_roles.sql`
- `scripts/backup_migration.ps1`
- `scripts/backup_verify.php`
- `scripts/bootstrap_admin.php`
- `scripts/cli_common.php`
- `scripts/consolidate_users.php`
- `scripts/http_fixture.php`
- `scripts/install_database.php`
- `scripts/migrate_schema.php`
- `scripts/migration_support.php`
- `scripts/read_secret.ps1`
- `scripts/test_http.py`
- `scripts/test_two_roles.php`
- `scripts/verify_migration.php`

Total: 29 changed and 20 created source/documentation files.

## Generated local artifacts

- Verified export: `.migration-private/validation-20260907/database.sql`.
- Verified upload copy: `.migration-private/validation-20260907/uploads/` (19 files, individually enumerated and hashed in the manifest).
- Verification manifest: `.migration-private/validation-20260907/manifest.json`.
- Earlier rehearsal exports/manifests/upload copies: `.migration-private/rehearsal-20260907/` and `.migration-private/rehearsal-20260907b/`.
- Isolated HTTP application, upload and session copies and server logs: `.migration-private/http-*/`.
- Disposable databases: `atikha_test_rehearsal_20260907`, `atikha_test_rehearsal_20260907b`, `atikha_test_fresh_20260907`, `atikha_test_validation_20260907`, `atikha_test_bootstrap_20260907`, `atikha_test_empty_20260907`.

These generated copies include dependency files and sensitive backup content and are intentionally excluded from the source inventory and Git.
