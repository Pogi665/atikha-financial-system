# Financial Records, cash books, and local board previews

Financial Records uses locally served DataTables 2.3.8 and jQuery 3.7.1. The complete
date/type/category matching dataset is loaded before client search, ordering, and
fixed ten-row pagination. There is no PHP pager or page-length selector in these views.
Reports retain their original calculations, renderer, review actions and print styles;
only their two book quick-link destinations changed.

## Routes and calculations

- `financial_records.php`: all types; optional date/type/category filters.
- `financial_records.php?view=crb`: Incoming only, Received from.
- `financial_records.php?view=cdb`: Expense only, Paid to.
- Old `type=Incoming/Expense` links remain filtered Financial Records views.
- Chart of Accounts `filter_category` / `filter_type` aliases retain precedence and
  binary-exact stored-name matching. Inactive historical names remain available.
- The redesigned views default to All dates. Shared monthly parser/report defaults remain unchanged.

Search includes purpose, project and category even though those fields are in the dialog.
Summaries and completeness cover all active search/filter matches, across all pages.
Summary arithmetic uses integer cents/BigInt. Missing purpose requires attention;
Unallocated can be legitimate. The combined count includes each affected record once.

Running figures are calculated before display filtering, search, ordering and paging.
They are labeled **Cumulative Net Recorded Cash Flow After Transaction**, include prior
recorded history and hidden transactions, and are not verified cash/bank balances.
There is no invented opening balance, permanent asset ledger, payment-method inference,
schema migration or double-entry accounting change.

Transaction identities always contain both type and record ID. Available actor names
come from historical identities (or the existing Users fallback).

## Supporting documents and permissions

Only existing `Receipts.ExpenseID` associations are used. No incoming/voucher association
is invented. Receipt delivery requires all three conditions:

1. A validated active Admin session.
2. `UploadedBy_UserID` equals the logged-in user's ID.
3. The receipt's `ExpenseID` equals the requested expense.

Management and another Admin remain denied. The endpoint resolves only stored receipt
paths inside the receipt directory and rejects missing files, traversal and symlinks
outside that directory. It does not expose filesystem paths or public upload URLs.
Existing OCR/public-upload behavior elsewhere is not changed by this work.

## Board attachment behavior

Previews are local object URLs: JPG/JPEG/PNG have thumbnails/enlargement; PDFs have a
browser embed and Open PDF fallback; DOC/DOCX have a metadata card explicitly saying
Content preview unavailable. Selection never sends a message or uploads a file.

Replace uses an unnamed temporary picker and promotes it only after validation. The
form retains one named attachment control. Cancel/invalid replacement retains the
original. Remove clears the file input and object URLs; the same file can be selected again.

Empty/generic browser MIME is inconclusive. Known incompatible MIME, unsupported
extension, empty, unreadable/undecodable and oversized files receive inline errors.
The exact limit is 8 MiB = 8,388,608 bytes. Existing server content-based MIME validation
remains authoritative. Draft text is retained on server validation errors, but browsers
require a selected file to be reselected after a server-rendered response. If PHP rejects
the entire request body before parsing it, it cannot recover the draft.

## Isolated checks

Use only fresh `atikha_test_*` databases. Existing fixture/bootstrap loaders strip live
USE statements and refuse production targets. No test should connect its application
copy to the production database or copy live config/receipts/board uploads.

- `php scripts/test_ledger.php --database=atikha_test_<unique>` requires an empty database.
- `php scripts/test_chart_of_accounts.php --database=atikha_test_<unique> --port=8133`
  requires a separate empty database.
- `python scripts/test_http.py --database=atikha_test_<unique>` requires an isolated
  schema with all existing prerequisites and only the fixture Admin. Do not seed it
  with ledger test transactions, which overlap its historical report period.
- `python scripts/test_records_browser.py --database=atikha_test_<unique>` uses a
  separate ledger-bootstrapped database, isolated sessions/uploads, and headless Edge
  through Playwright. Playwright is a test dependency, not an application dependency.
  A retained manifest can be reused with `--fixture=.migration-private/<run>/fixture.json`
  only for that same disposable database.

Browser tests capture actual multipart bytes through a loopback proxy, verify a single
attachment file part, and compare the transmitted bytes with files stored by the real
isolated board submission route. They do not infer successful replacement from filenames.
They retain fixture copies and screenshots, and shut down test servers after completion.

## Manual checks and limits

Automated browser checks are separate from PHP syntax and database/HTTP checks. A
headless Edge run does not establish compatibility with every browser or native picker.

On an isolated fixture, manually verify:

1. Open Replace, press **Cancel in the operating-system picker**, then Send. Inspect the
   multipart file part or stored-file hash: it must still be the original. Automated
   tests use an empty chooser selection to exercise the same retained-original path.
2. Open a genuine multipage PDF; inspect the embed, Open PDF fallback, and behavior in
   a browser without embedded PDF support. Check DOC/DOCX cards have no implied content preview.
3. At the intended desktop width, inspect both themes, table controls, long party/purpose
   text, and receipt image/link failure states. At a narrow viewport, inspect dialogs;
   the unchanged 1024px application shell still requires horizontal navigation.
4. Exercise real OS file-read failures and an outside-directory symlink where the OS
   permits creating one. Automated tests cover an injected read failure, missing files,
   and traversal; OS-specific behavior is a separate check.
5. Preview/print an existing report and confirm its layout/review controls remain intact.
   Its source changes are limited to two quick-link URLs.

No live migration, production fixture insertion, commit or push is part of this change.
