# Phase 4 deployment and verification

OCR stores evidence and extraction proposals. Only an Admin's explicit General
Journal submission posts accounting records. All active Admin and Management
users may view evidence attached to posted journals; unposted evidence belongs
only to its uploading Admin.

## Deploy

1. Back up the database, application files, and uploads/receipts. Verify that
   the backups can be restored. Put the application in maintenance.
2. Confirm migrations 015–017 are already applied. **Never rerun 015**: it deletes
   financial history. Rehearse 018 on a new disposable copy first.
3. Check the deployed Receipts foreign key is named fk_receipts_user, and that
   every uploader exists in the historical identity table:

   ~~~sql
   SHOW CREATE TABLE Receipts;
   SELECT r.ReceiptID, r.UploadedBy_UserID
   FROM Receipts r
   LEFT JOIN user_identities u ON u.UserID=r.UploadedBy_UserID
   WHERE u.UserID IS NULL;
   ~~~

   The identity query must return no rows. Stop and investigate actual identities
   if it does; do not invent or reassign uploaders.
4. Deploy the new code and receipt-directory .htaccess. Apply **only**
   migrations/018_journal_receipt_evidence.sql, once, using a client that stops
   on error. Keep foreign-key checks enabled:

   ~~~powershell
   Get-Content -Raw migrations/018_journal_receipt_evidence.sql |
       & C:\xampp\mysql\bin\mysql.exe -u root atikha_finance
   ~~~

   Use the real connection settings when different. DDL implicitly commits.
   On error, stop and inspect partial state; restore from backup when necessary.
   Do not blindly rerun the complete migration.
5. Confirm PHP has 64-bit integers, fileinfo and GD with JPEG/PNG/WebP decoding.
   PHP upload/post limits must support an 8 MiB file plus multipart overhead.
   The application enforces 8 MiB and 20 megapixels independently.
6. Verify the configured CA bundle exists and that HTTPS certificate verification
   succeeds. The shared Gemini transport now verifies certificates. Do not
   disable verification to recover; correct the CA configuration instead.
   OCR uses 60-second request/10-second connect limits; forecast timeout defaults
   are preserved. Gemini failures keep manual entry available.
7. Verify **Apache**, not just the PHP development server, returns 403 for a
   direct receipt image URL without authentication. The new directory .htaccess
   needs Apache authorization/override support. Confirm a protected endpoint
   returns the original bytes only to appropriate users. Existing protected
   legacy-expense attachment access remains owner-only; old public receipt URLs
   are deliberately inaccessible.
8. Only after these checks pass, add this setting to the local, gitignored
   config.php (do not expose or replace its credentials):

   ~~~php
   define('OCR_JOURNAL_ENABLED', true);
   ~~~

   Undefined/false keeps scanning unavailable and its navigation link hidden.
   Missing 018 also fails closed. Ordinary manual journal posting remains
   available without the evidence schema.
9. Reopen the application. Perform a controlled test in the development copy:
   upload → review → choose actual credit account → confirm PHP → enter business
   purpose → Post Entry → inspect the complete journal and original receipt.
   Verify a retry does not create another journal.

Live migration 018 is **not executed by the implementation/test scripts**.

## Disposable tests

Each test invocation requires a fresh, explicitly named disposable database.
The scripts refuse atikha_finance. No live config, uploads, or sessions are
copied into the isolated HTTP application.

~~~powershell
C:\xampp\php\php.exe scripts/test_receipt_journal.php --database=atikha_test_phase4_core1
python scripts/test_receipt_browser.py --database=atikha_test_phase4_browser1
~~~

The browser script bootstraps its own fresh database through 018 and uses existing
local Playwright dependencies and headless Edge. It replaces only the isolated
Gemini transport with an explicit test double; no real AI requests occur. A PHP
development-server router denies storage URLs there. That router test is separate
from the required real Apache .htaccess check.

Evidence and disposable databases remain available for inspection. No automatic
cleanup deletes live or posted files. Screenshot paths and test counts are
printed at completion. Regression suites include test_journal.php,
test_accounting.php, their browser suites, and Board/attachment HTTP tests.

## Implementation verification

- Latest Phase 4 rehearsal: 50 core checks and 49 HTTP/headless Edge checks passed
  on atikha_test_phase4_http20261005g. Gemini was an explicit transport double.
- Regressions passed: 88 journal service checks, 43 accounting checks, 65 journal
  browser checks, 37 reporting/dashboard/review browser checks, and 48 dashboard
  JavaScript checks. The OCR suite also exercised actual Board replacement and
  removal uploads in its private application copy.
- PHP syntax checks and JavaScript syntax checks passed. OCR journal screenshots
  were inspected; the review form was verified at 1024px without page overflow.
- A read-only request to real Apache's receipt directory returned 403. This was
  separate from the isolated PHP-server router test.
- Final read-only application database check: 2 journal headers, 4 lines, no
  invalid posted journals, no legacy transactions or receipt rows. The Phase 4
  attempt table is absent and the feature flag is disabled: live 018 was not run.
- No live Gemini extraction or live AI certificate-handshake test was performed.
  Verify the real configured model, key and CA trust before enabling scanning.

## Contracts and limitations

- New columns live on Receipts, not journal headers. One OCR-assisted submission
  carries one receipt; its journal may have manually split lines.
- Amounts use exact decimal strings and existing integer-centavo validation.
  Description/business purpose and fund/project IDs are never inferred by OCR.
  A credit account is never automatically selected.
- Non-PHP or unknown-currency amounts are not prefilled into PHP journal lines.
  The user must verify PHP; there is no exchange-rate calculation.
- Every upload has an explicitly labelled manual fallback proposal, separate
  from actual extraction attempts. Five real extraction starts per user per
  five minutes are allowed; duplicate request retries do not consume more calls.
  Pending attempts have a 90-second lease and can be expired by an explicit retry.
- Processing does not hold database/session locks over network calls. Attempts
  transition once from Pending to Processed/Failed; completed attempts and their
  original response/proposal cannot be rewritten or deleted.
- Exact-byte SHA-256 reuse is blocked globally at posting, including concurrent
  uploads by different Admins. A cropped/re-encoded/rescanned receipt has different
  bytes: hashes do not detect every duplicate economic transaction or prove
  authenticity. Human review remains necessary.
- Posted evidence cannot be discarded, replaced or reprocessed through these
  endpoints. Unposted discard is audited before deletion and then records the
  actual deletion outcome. Missing/changed posted evidence returns an error,
  never an unrelated substitute image.
- Extraction retries make earlier review tokens stale. Posting rollback covers
  header, lines, association, unique evidence reservation, and required audit.
  Ordinary pre-018 manual journal submission hashes retain their original format.
- Scan Receipt can be disabled with the flag without hiding already posted
  evidence. Existing financial reporting continues to read journal lines only;
  receipt metadata is loaded separately to avoid multiplying totals.
- Abandoned uploads and orphaned legacy files are not automatically removed or
  assigned to journals. Financial figures and review snapshots are unaffected by
  extraction failures.
