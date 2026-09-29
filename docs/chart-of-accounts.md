# Chart of Accounts deployment

`admin_accounts.php` is restricted to the existing `Admin` role. Income maps to
`Categories.Type = 'Fund'`. Account names and types are immutable because existing
transactions and budgets store names. Use Disable/Reactivate; historical records
are not renamed or deleted. Detail type and description are optional metadata.

## Migration 012: separate live deployment step

1. Put the application into your normal maintenance window and take a recoverable
   full database backup (including triggers). Verify that it can be restored into
   a separate database. Keep the backup outside the public web directory.
2. Run `SHOW CREATE TABLE Categories` and `SHOW COLUMNS FROM Categories` against
   the intended database. Confirm the migration-001 structure, unique `(Name, Type)`
   key, and absence of both `Detail_Type` and `Description`. If either new column
   already exists or the schema differs, stop and inspect; do not blindly rerun.
3. Snapshot the category rows and transaction counts, category values, and totals.
   Apply `migrations/012_chart_of_accounts.sql` once. Its `USE` targets
   `atikha_finance`; do not execute it directly against a disposable schema.
4. Verify the two nullable columns, unchanged original category fields, and
   unchanged transaction snapshots/totals. Existing metadata must remain NULL.
   Stop deployment on failure. DDL commits independently; rollback is not a SQL
   transaction rollback. Recover from the verified backup if needed.
5. Deploy the page, account helper, sidebar link, and manual-entry changes together.
   Never disable foreign-key checks or audit triggers. A code rollback can leave
   the additive nullable columns in place.
6. Sign in as Admin and check create/edit/disable/reactivate, filters, and manual
   category choices. Verify Management gets HTTP 403 and cannot see the link.
   Check mobile/desktop layout and that historical category edits are retained.

No live migration is run by the test script or the page. Until migration 012 is
applied, the new page shows its database/migration-unavailable message.

## Disposable integration checks

Create a new empty database named `atikha_test_<unique_suffix>`, then run:

```powershell
C:\xampp\php\php.exe scripts/test_chart_of_accounts.php --database=atikha_test_<unique_suffix> --port=8133
```

The script refuses non-test or nonempty databases, strips hardcoded `USE` statements
through the existing migration loader, and runs database and HTTP tests. It uses
temporary copies of the pages with a test-only connector and isolated sessions,
bound to localhost. It stops the test server and retains the disposable database
and temporary fixture for inspection. The test fixture's login endpoint must never
be copied into the application or exposed publicly.

The current legacy bootstrap does not include `Reference_Number`, although the
existing manual forms require it and the inspected live schema has it. The fixture
adds this prerequisite only to the test database; migration 012 intentionally does
not change transaction schemas.

The suite covers preservation, account lifecycle and audit rollback, authorization,
CSRF, escaping, filters, active/type validation, historical categories, empty
choices, stale forms, and query failures. Browser visual verification remains a
separate check; HTTP assertions do not verify layout or JavaScript interactions.
