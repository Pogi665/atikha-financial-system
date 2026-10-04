# Phase 3 deployment and verification

Phase 3 reads posted double-entry journals. Migration 017 has been rehearsed on
disposable databases; it has **not** been applied to `atikha_finance` automatically.

## Deploy

1. Back up the database and the current Phase 2 application files. Verify the backup
   can be restored. Put the application in maintenance before deployment.
2. Confirm migrations 015 and 016 are already applied and `Categories`, both journal
   tables and `user_identities` use the expected InnoDB schema. Do not rerun 015:
   its cleanup statements are destructive.
3. Apply **only** `migrations/017_trial_balance_snapshots.sql`, once, using a client
   that stops on error. It adds two tables and preserves all existing data. For
   the local XAMPP database, PowerShell can run:

   ```powershell
   Get-Content -Raw migrations/017_trial_balance_snapshots.sql |
       & C:\xampp\mysql\bin\mysql.exe -u root atikha_finance
   ```

   Use your actual connection settings when different. DDL implicitly commits;
   inspect partial state after an error rather than rerunning the full migration.
4. Deploy the Phase 3 PHP and assets together. Clear PHP OPcache if enabled.
   The forecast response is version 2; old JS and new PHP must not be mixed.
5. Check both roles, then leave maintenance. Journal posting remains the Phase 2
   service. No legacy transactions are migrated or manufactured.

## Accounting definitions and interface changes

- Trial Balance is cumulative through the selected month-end; zero and inactive
  accounts remain visible. Normal balance does not override the actual debit/credit
  sign. There are no automatic annual closing entries.
- KPIs stop at Asia/Manila today: Asset debits minus credits, Income credits minus
  debits, Expense debits minus credits. Assets include noncash assets.
- Monthly charts use completed months. Current budgets use expenses through today;
  historical budgets use their complete month. Expense credits reduce spending.
- Recorded expense breakdowns use twelve completed months independently of AI.
  Negative category amounts remain listed; percentage charts are suppressed.
- Monetary financial readouts use exact centavos, checked against the signed 64-bit
  range. Larger aggregates show unavailable rather than silently rounding. Chart and
  forecast numbers are approximate plotting/estimation values, never balance proof.
- Forecast JSON uses `version: 2`, `basis: net_expenses_v1`, `income`, `expenses`,
  and `projected_expenses`. Cached input basis, fingerprint and 24-hour TTL must
  match. A failed/ongoing/throttled generation returns a trailing three-month
  baseline; durable attempt rows throttle paid calls across users for 60 seconds.
  Projections can be negative. Donor concentration, cash runway and solvency
  ratings are unavailable. No live AI call is needed for verification.
- Admin submits frozen revisions; Management reviews a specific revision via
  `reports.php?snapshot_id=ID`. Capture/review timestamps are explicitly UTC.
  Reviewed figures cannot be overwritten by the application. A newer revision
  does not erase or approve an older revision. Backdated posting does not change
  snapshots; compare against the live report and submit a new revision when needed.
- The review endpoint uses `entity_type=trial_balance`, signed submission keys,
  the displayed source fingerprint and CSRF. A changed displayed report returns
  HTTP 409. Legacy `report`, `fund` and `expense` review actions return HTTP 410.
  Board review actions retain their existing behavior.
- Snapshot, lines and audit writes commit together. Notification failure is a
  warning after successful commit; the item remains in the review queue. Identical
  retries do not duplicate snapshots, reviews, audits or notifications.
- Financial Records is journal line history. Account filters use IDs; its totals
  follow all filtered/search results, not merely the visible page. A filtered subset
  need not balance. Its dialog always displays the complete posted journal.
- CRB/CDB select cash-flagged Asset debit/credit lines. Transfers can appear in both;
  neither view classifies operating cash flow or verifies unrestricted funds.

## Repeatable tests

Tests require explicit disposable names. Never point them at the application DB.
Choose a fresh unused name for each database bootstrap:

```powershell
C:\xampp\php\php.exe scripts/test_accounting.php --database=atikha_test_phase3_example
python scripts/test_accounting_browser.py --database=atikha_test_phase3_example
node scripts/test_management_dashboard.js
node scripts/test_dashboard_sparklines.js
C:\xampp\php\php.exe scripts/test_dashboard_sparklines.php
```

Browser checks require Playwright and Edge. They copy the application into
`.migration-private`, use isolated sessions, block external requests, and stub
Gemini plus Chart.js explicitly. They verify real DataTables interactions, HTTP,
layout and print CSS; they do not prove live Gemini integration or actual Chart.js
canvas rendering. Screenshots and PHP server logs stay in the private fixture.
The existing Phase 2 `test_journal.php` suite remains a posting/account regression
check; run it against its own empty `atikha_test_journal_*` database.
`test_management_dashboard.py` delegates to the journal browser suite and now
requires the same explicit disposable database argument. Historical `test_ledger.php`
and `test_records_browser.py` exercise the retired single-entry helper/receipt
contract; they are not Phase 3 acceptance tests. Use the journal suite for current
Financial Records behavior. Existing attachment code is unchanged.

Covered scenarios include the 1,370-per-side example, revisions and retries,
concurrent submissions/posting, failed audit rollback, source changes, role and
CSRF denial, inactive/contra accounts, reversals, signed forecasts, invalid cache
data, date boundaries, overflow, search/pagination, full-entry dialogs and board
review compatibility. These synthetic tests are separate from live verification.

## Live read-only checks

After manual deployment, verify `SHOW CREATE TABLE` for both snapshot tables.
Use SELECTs to confirm existing journal/account/budget/audit rows were preserved.
Open Reports for each posted period, compare its debit and credit totals with
independent posted journal sums, and inspect any journal integrity warning.
Check KPI cutoff labels, Ledger filters, CRB/CDB and both role dashboards.
Dashboard JavaScript can write disposable forecast cache through its POST endpoint;
use direct SQL/helper checks when a strictly read-only verification is required.

Legacy `Reports` history remains separate and read-only. A reviewed revision is
evidence of the captured figures, not approval of subsequent live postings.
Rollback should restore a compatible application version without deleting new
journals or snapshots. Restoring an older database backup discards later work and
must be a separately assessed recovery action.
