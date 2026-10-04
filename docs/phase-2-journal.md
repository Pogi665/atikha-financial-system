# Phase 2: General Journal and Account Maintenance

Implemented pages: `general_journal.php` and `admin_accounts.php`. Both use the existing active-user authentication and Admin role gate. Journal uses `includes/layout.php`; account maintenance retains its existing shell, modal, filters and activation controls.

## Manual deployment

1. Keep the application in maintenance and take a verified database backup. Confirm migrations 001–015 have completed. Do not rerun 015: it wipes financial data.
2. Apply `migrations/016_journal_posting_metadata.sql` once to `atikha_finance`, using a client that stops on error. It adds nullable submission keys, hashes and historical actor attribution without replacing existing records. DDL implicitly commits; restore or inspect partial state on failure rather than blindly rerunning.
3. Check that the migration's final SELECT returns all three columns. Confirm `uq_journal_entries_submission_key` and `fk_journal_entries_posted_identity` exist with `SHOW CREATE TABLE journal_entries`.
4. Check current users have historical identities before posting:

   ```sql
   SELECT u.UserID, u.FullName
   FROM Users u LEFT JOIN user_identities h ON h.UserID=u.UserID
   WHERE h.UserID IS NULL;
   ```

   Expected: no rows. Resolve missing identities through the established identity maintenance workflow; do not change authorization to use historical identities.
5. Confirm 64-bit PHP, `mbstring`, PDO MySQL, InnoDB and at least 410 available PHP input variables for this form (default `max_input_vars=1000` fits 100 lines). Journal is unavailable if migration 016 is missing or PHP is 32-bit.
6. Add the approved Asset/Liability/Equity accounts in Chart of Accounts. Existing Income/Expense accounts and NULL account codes remain eligible; the application creates no cash accounts, balances or accounting classifications on your behalf. Mark only Asset accounts as cash accounts. Choose each account's normal balance explicitly; the form supplies conventional defaults with permitted overrides.
7. As an active Admin, open General Journal. Entry date defaults to **Asia/Manila**, independently of global PHP/browser timezone. Enter 2–100 complete rows, choose accounts, put one positive Debit or Credit on each line and balance the totals. Fund/Project is an optional positive numeric ID, not a named or validated project lookup at this phase.

Migration 016 was tested on synthetic databases, not applied to the live database by the implementation agent. Do not create a real test posting merely to validate deployment: use a disposable copy or an actual approved business journal.

## Posting and account safeguards

Amounts are strictly validated decimal text and calculated as integer centavos (PHP 64-bit integers and JavaScript BigInt). Each line fits DECIMAL(15,2); 100 maximum-value lines stay within the integer range. The form mirrors amounts to named hidden controls so disabled inputs are still submitted; a final completion marker and explicit row count detect truncated requests.

The posting service validates authentication, active Admin status, CSRF and a session/user-bound signed submission key. It locks accounts in ID order, rechecks eligibility, and inserts the posted header, all lines and the audit in one PDO transaction. Any failure rolls everything back. Identical retries return the same entry, including concurrent retries; changing an already used submission returns HTTP 409. Keep the original form open to retry a failed request. Reload to start a new entry after a conflicting replay.

Names/types remain immutable. Codes, normal balances and cash flags are editable before posting; after posting normal balance and cash status are frozen, and an assigned code is frozen. A NULL code may be assigned once. Descriptions, detail types and active/inactive status remain editable. Account maintenance and posting share account-row locks so a concurrent edit cannot change the classification while posting.

Legacy entry pages (`funds.php`, `expenses.php`, `ocr_expense.php`) redirect authenticated GETs to the journal and reject writes with HTTP 410 before business writes/uploads. `ocr_extract.php` and fund/expense review writes also return 410. Board/report review actions retain their existing behavior. Old account transaction links are removed because they query legacy records.

## Phase boundary

Dashboard, AI forecast, Financial Records, CRB/CDB and Reports still read the empty legacy tables. **They do not yet report new journal entries.** This is a later reporting phase; no duplicate legacy financial rows are written. This phase has no trial balance, account journal browser, fund master, opening balances, drafts workflow, reversals or period locks. Posting is balanced through the service; the schema alone does not enforce journal-wide balance against direct SQL or unauthorized new endpoints. There are no posted-entry edit/delete endpoints.

## Repeatable disposable checks

Create an empty database matching `atikha_test_journal_[a-z0-9]+` using a dedicated test connection. Never select `atikha_finance`:

```powershell
C:\xampp\php\php.exe scripts/test_journal.php --database=atikha_test_journal_example
python scripts/test_journal_browser.py --database=atikha_test_journal_example
```

The PHP test bootstraps synthetic schema/data, applies 001–016 and checks rollback, audit integrity, authorization, idempotency, concurrent posting, strict money, account locks and maximum row/amount limits. Browser checks require Playwright and headless Edge (or `--browser=chrome`), then run actual routes against a copied application with isolated sessions and no production config/uploads. It blocks external browser requests. The copy and screenshots are retained under the ignored `.migration-private` directory for inspection; the built-in PHP server always stops on exit. Each full run requires a fresh empty database.

References: [PDO transactions](https://www.php.net/manual/en/pdo.transactions.php), [MariaDB FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).
