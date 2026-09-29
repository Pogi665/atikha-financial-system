# User management deployment and verification

## Behavior

`admin_users.php` lists all login accounts by default. Administrators can search by
name/email, filter by role/status, create accounts, edit their details, optionally
reset passwords, and disable/reactivate other accounts. Only `Admin` and
`Management` are accepted. Self-disable and self-demotion are rejected on the server.

Passwords remain in `Users.Password` as `password_hash()` hashes. Blank edit
passwords preserve the hash. Disabled users retain their ID, password, financial
references, and historical identity. Identity snapshots and earlier audit events
are not rewritten by edits. New account, edit, and status audit events commit in
the same transaction as the account mutation. Repeated status actions are no-ops.

Authentication and MFA require `Is_Active = 1`. Authenticated requests reload the
current role/name and reject disabled or missing users. HTML requests redirect to
login; JSON endpoints return their existing envelope with HTTP 401. Database/schema
validation errors fail closed with HTTP 503. Already-running requests are not
cancelled. A session rejected while disabled is cleared; a dormant session that
is never used during the disabled interval can resume after reactivation.

The login page, predefined permissions, and Chart of Accounts UI are unchanged.
Password-reset resolution does not reactivate a disabled account.

## Deployment order

1. Schedule a maintenance window and stop application writes. Save the current code
   revision and a full database backup including triggers and routines. Verify that
   the backup can be restored into an isolated database before proceeding.
2. Inspect the **actual target database**: verify `Users` has the two-role enum,
   MFA columns from migration 008, the identity registry from 009, and audit tables
   and protection triggers. Record user IDs, account count, identity count, audit
   count, and financial reference counts. Check whether `Is_Active` already exists;
   if it exists, verify its type/default and values instead of assuming compatibility.
3. Apply `migrations/013_user_account_status.sql` with the target database explicitly
   selected. It adds only `Is_Active TINYINT(1) NOT NULL DEFAULT 1` and is repeatable
   on MariaDB. Do not run `database.sql` against an existing installation.
4. Verify the new column/default, active status for pre-existing accounts on first
   application, and unchanged IDs, credentials, historical rows, and references.
   DDL commits independently; an application transaction cannot undo this step.
5. Deploy the code requiring the new column, then reopen the application. Deploy
   the migration before the code: without the column, authenticated access fails
   closed. This workspace implementation does **not** apply the live migration.
6. Validate with a separate test account: create, edit, blank-password save,
   optional password reset, disable, denied login/session, and reactivate. Verify
   audit events contain no credentials. Test Admin and Management navigation and
   the pending reset workflow. Check server logs for HTTP 503/schema errors.

Stop on any validation failure. Do not disable foreign-key checks or audit
protection triggers. Keep the additive column if rolling code back; rolling back
the access checks makes disabled accounts accessible again, so keep the site in
maintenance until enforcement is restored. Use the verified backup/recovery
procedure for database recovery rather than destructive ad hoc SQL.

## Automated checks

Create empty databases named `atikha_test_*`, then run:

```text
php scripts/test_user_management.php --database=atikha_test_users_NAME
php scripts/test_mfa_flow.php --database=atikha_test_users_NAME
php scripts/test_chart_of_accounts.php --database=atikha_test_accounts_NAME
```

The user and account suites require separate **empty** databases. The MFA suite
uses a transaction and rolls its fixture back. The user suite serves copies in a
temporary directory with a disposable database connector, isolated session files,
and a test-only mail stub; it never sends real MFA email. Fixture databases and
temporary directories are retained for inspection. Never deploy the fixture login
endpoint or mail stub. The suite rehearses both fresh-schema and existing-schema
migration paths, plus audit failure rollback and HTML/JSON session denial.

The old `scripts/test_two_roles.php` exercises a specific historical Staff cleanup
with fixed actor IDs and record counts. It cannot run against these synthetic
fixtures; current role vocabulary and permissions are covered by the user/account
suites without replaying that historical migration.

## Validation record

On 2026-09-30, the isolated user-management suite passed 63 assertions, the existing
Chart of Accounts suite passed 88, and the MFA suite passed 8. PHP syntax checks
passed for all changed PHP files. The deliberately injected audit outages and
missing-column errors in fixture logs are expected test cases.

Browser automation reported no available browser. Desktop/mobile appearance and
keyboard interaction are therefore not visually verified. Before release, inspect
the list and inline form at desktop and narrow mobile widths; verify horizontal
table scrolling, labels, focus order, confirmation dialogs, validation messages,
and the retained reset modal. HTTP tests verify rendered escaping, filters, form
behavior, and reset completion, but do not substitute for visual inspection.
