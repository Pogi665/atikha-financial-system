# Stage 1 delivery: configuration and daily entries

Stage 1 implements dedicated cash receipt and cash payment pages, a persistent General Journal workspace, private saved drafts, project and payer/payee masters, protected manual image evidence, and complete originating entries in CRB/CDB. It does not activate cash-advance liquidation, corrections, budgets, new financial statements or period closing. Those belong to later stages.

The initial implementation delivery left the working database and `config.php` unchanged and inserted no client data or inferred opening balances. Subsequently, the user applied migration 019 and explicitly delegated feature activation. The working workspace is now enabled; deployment evidence and deferred acceptance checks are recorded below. Do not rerun migration 019 or migration 015 on this working database.

## Implemented review amendments

- Existing receipt posting, review, discard and extraction paths reject an image reserved to a draft, even for its uploader. Remove it through its owning draft first. Reattachment starts a fresh manual review. Extraction cannot run while reserved. Legacy unreserved single-image OCR review/post behavior remains available through `general_journal.php?receipt_id=...`.
- Existing Chart of Accounts maintenance shares the accounting write lock. A designated unused advance account cannot change its normal balance/cash classification or be disabled. Remove its unused designation with an audited reason first. Multiple designations are supported without assuming the two account names in the references represent the same receivable.
- A matching already-posted draft retry authenticates the active Admin, verifies CSRF, owner and durable key, and compares normalized posting contents before considering an expired review token or changed master eligibility. A changed posting conflicts. New posts require a current signed review. Draft creation and uploads also have durable request keys.
- CRB coverage is noncash credits; CDB coverage is noncash debits. A PHP 4,500 expense / PHP 500 tax payable / PHP 4,000 cash payment has a PHP 4,500 evidence denominator. General Journal shows debit and credit coverage separately. Informational documents add no monetary support. Cash-only transfers show Not applicable.
- CRB/CDB SQL filters select matching journals then return complete lines. Browser search matches across a journal and retains all its lines and totals. Journal History keeps line filtering. Cash-book debit/credit totals include both sides and are labelled accordingly.

The supplied worksheet screenshot establishes separate CRB, CDB and Journal debit/credit column groups, and the account-list crops establish the visible account concepts. They do not show complete cash-book rows, transaction-routing rules or approval workflows. The client visit notes describe journal adjustments, general-ledger reporting and cash advances that are not immediately expenses; the user's clarifications establish the intended regular-use system and the continued need for payer/payee information. Assigning each complete journal entry to one originating book, private drafts, evidence coverage rules and the review/post interaction are selected capstone design decisions. Approval authority, later closing procedures and account ambiguities remain requirements for the later stages.

## Entry and evidence behavior

`cash_receipt.php` and `cash_disbursement.php` each generate exactly one cash line. Choose the cash/bank account, actual amount, payer/payee and noncash allocations. Advanced entries permit explicit noncash debit/credit allocations such as withholding. Multiple cash accounts and internal transfers use General Journal; a transfer has two different cash accounts with opposing equal amounts and no other lines.

Drafts may be incomplete or unbalanced. Save explicitly; unsaved changes trigger navigation warnings. Uploading saves the entry first, then reserves the image. No financial records are created until Review and Post. Review is an accounting check and does not claim Management approval. A failed post keeps saved work. After posting, the entry is read-only.

Account, project and payer/payee controls are searchable while retaining their original record-ID fields. Typing a query does not change the selected record or invalidate a reviewed entry; selection is committed explicitly. Escape, Tab or leaving the control restores the selected label. Inactive/unavailable draft references remain visible but are not offered as eligible choices. Posted controls remain read-only.

My Drafts filters by last saved date only, in Asia/Manila, while showing each draft's accounting date separately. The server converts inclusive local date ranges to UTC timestamp boundaries; stored `updated_at` remains UTC and `updated_at_display` supplies the Manila display. Invalid filters fail without broadening the search. Failed or superseded requests retain the last successful list.

Posted receipts/payments link separately to their originating book and Journal History, across all dates. Posted General Journal entries show one Journal History link. These are list destinations, not links claiming to open one exact entry; the same navigation applies to recovered or reopened posted results.

Line project IDs are authoritative. A default project is a shortcut for new lines; Apply to all is explicit. Organization operations uses a null project. Split-project entries provide activity allocation, not a claim that each project's ledger independently balances. Posted project names/codes and payer/payee labels are snapshots; master renaming preserves historical labels.

Images use existing authenticated downloads and protected storage: JPEG, PNG or WebP, 8 MiB maximum, 20 megapixels, up to 20 per draft. Manual evidence works with OCR disabled and does not call Gemini. Review each original, choose informational or monetary support, confirm PHP amounts, and allocate accepted evidence to noncash lines. Combined evidence cannot exceed a line; one document cannot support both sides. Partial acceptance needs an exclusion reason. Attached unreviewed documents block posting; missing/partial evidence remains allowed for ordinary entries with truthful review labels. Future liquidation remains evidence-gated in Stage 2.

Removing an image releases its reservation. Discarding a draft soft-discards its still-reserved images; it does not physically delete files. Posted evidence, its allocations, original image hashes and primary journal ownership are preserved. Legacy associations are explicitly labelled without invented monetary reviews. Correction-linked evidence reuse is deferred to Stage 3 and current duplicate hash protections remain enforced.

## Transaction and compatibility rules

The shared lock order is actor, accounting write state, draft, masters, receipts, then sorted accounts. Draft-only operations omit the global state lock. Posting atomically writes header/lines, immutable evidence associations and allocations, primary receipt ownership, posted draft status and audit records. Audit failures roll everything back. Master/account writers participate in the same barrier and increment its change version. Review tokens last 15 minutes and bind draft revision, canonical contents, configuration versions and document versions.

Legacy journal payload hashes and session submission signatures are unchanged. Existing unclassified journals retain null source books and display as Legacy General Journal; they are not guessed into cash books. New legacy-form submissions after 019 originate in GJ. Legacy project placeholder entry is blocked after 019; new project allocations use validated masters through the saved-draft flow. Existing Trial Balance snapshots, report review and budget semantics remain unchanged.

The explicit boolean `STAGE1_WORKSPACE_ENABLED` defaults to false. UI/API availability also requires all Stage 1 schema components. The controls protecting designated accounts and reserved evidence remain enforced once the schema exists, even with the UI flag off. Disabling the flag is therefore not a way to bypass accounting safeguards.

## Manual deployment and recovery

This procedure remains guidance for a future installation or recovery. The current working database has already received 019, so do not repeat its migration step. For the recorded working deployment, the user chose to omit restored-copy rehearsal and defer broad manual acceptance checks; that choice is not evidence of successful restoration or rehearsal.

1. Obtain separate authorization for the working-database migration and activation. Use a maintenance window with all accounting writers stopped. Record the deployed revision and current schema. **Never rerun migration 015 on the working database.**
2. Run the read-only preflight: `C:\xampp\php\php.exe scripts/preflight_stage1.php --database=atikha_finance`. Investigate every failure. Existing nonnull project placeholders or journal drafts require reviewed resolution; do not delete or invent mappings to pass preflight.
3. Back up the complete working database, protected receipt files and current application/configuration. Keep the manifest private, with counts and hashes. Verify restoration to a separate disposable database/files directory. A backup filename alone is not proof of recovery.
4. Rehearse 019 on that restored copy. The migration contains `USE atikha_finance`; prepare a rehearsal-only copy that explicitly targets the disposable database and verify that target before execution. Selecting another database in a SQL client alone does not override that statement. Validate original financial counts, balances, receipt ownership/hashes, historical source-book fallback and report snapshots. Do not run the synthetic bootstrap against a restored client database; it is for new empty disposable databases only.
5. Before working-database migration or activation, enable the workspace only in the isolated rehearsal application's configuration and smoke-test there: donation receipt, split payment, manual withholding, transfer, draft resume, partial evidence, posting retry, old receipt restrictions and designated account maintenance. Confirm Apache denies direct storage URLs, authenticated evidence downloads work and private drafts remain owner-only. Keep any dummy parties, projects and postings confined to the disposable environment. Record rehearsal and smoke-test results before proceeding.
6. Keep the working application's flag false; apply **only** `migrations/019_stage1_foundation.sql` once to the working database using a SQL client that stops on errors. It requires 018. DDL implicitly commits. If it fails midway, stop and inspect; do not rerun it blindly or execute a destructive down migration. Restore database and files together if returning to the prior deployment.
7. Confirm all eight tables, six header/line columns, foreign keys, checks, four immutable evidence triggers and singleton coordination row exist. Verify all original journals and balances remain unchanged. Legacy receipt associations must have purpose `legacy` and no invented review amounts.
8. Explicitly enable `STAGE1_WORKSPACE_ENABLED` as the boolean `true` in the working application's local configuration only after authorization, successful rehearsal/smoke testing and working-schema verification. OCR remains an independent flag. Confirm the working application's storage access controls, authenticated downloads and draft privacy. Use approved real configuration in production; do not seed the synthetic test parties/projects there.

If the flag is disabled after Stage 1 postings, legacy cash books return to their cash-line presentation while Journal History retains all postings. This is a UI rollback, not restoration of the pre-019 database. Review pending drafts and reservations before disabling; reserved images stay protected. Do not destroy new tables to make the old UI editable.

## Verification commands and artifacts

`scripts/test_stage1.php --database=atikha_test_stage1_<unique_suffix>` creates a new guarded disposable database and private files. It tests migration preservation, canonical money, ordinary coverage, reservations, unused advance controls, durable recovery across sessions/expired review, stale revisions, configuration invalidation, audit rollback and concurrent posting.

`python scripts/test_stage1_browser.py --database=atikha_test_stage1_<different_unique_suffix>` creates another new disposable fixture and copied app with isolated sessions/config/uploads. It blocks external requests, exercises actual HTTP/Edge flows, and saves screenshots under `.migration-private/stage1-browser-*`. Chrome can be selected with `--browser=chrome`. Dependencies use the existing private Playwright installation; environments without it need Playwright installed separately.

Existing regression suites cover journal posting, receipt evidence and accounting/Financial Records behavior. Test runs deliberately inject audit failures to prove rollback; those error log lines are expected test output. Synthetic fixtures contain visibly labelled NGO training, transport and donation examples and are not presented as Atikha records.

Screenshots cover 1366×768 and 1920×1080; the laptop layout stacks documents below the entry and larger screens place them alongside. The shared sidebar/shell is retained. Keyboard traversal, validation recovery and escaped text are exercised. Live Gemini extraction is outside the verification boundary. Initial automated tests used disposable databases; subsequent working deployment evidence is recorded separately below.

### Verified on October 5, 2026

- Final Stage 1 run: 73 backend checks and 35 Edge HTTP/browser checks passed on `atikha_test_stage1_browserf`. Artifacts: `.migration-private/stage1-browser-a9ade1f01c/` (including laptop payment, desktop review, cash-book and setup screenshots).
- Chrome: 31 HTTP/browser checks passed on the earlier Stage 1 build in `atikha_test_stage1_browserc`. The final posted-label and history-status additions were subsequently verified in Edge; they were not rerun in Chrome.
- Existing journal suite: 88 checks passed on `atikha_test_journal_stage1reg`.
- Existing receipt suite: 50 checks passed on `atikha_test_phase4_stage1regb`; isolated workers now skip production configuration so constant warnings cannot corrupt their JSON results.
- Existing accounting backend suite passed, and the existing accounting/Financial Records browser suite passed 95 checks on `atikha_test_phase3_stage1regb`. Its field-order assertion now normalizes whitespace introduced by the pre-existing label markup. Artifacts: `.migration-private/accounting-browser-18c23a2d54c1/`.
- PHP syntax checks, JavaScript syntax checks and `git diff --check` passed. Existing changes to dashboard/Tailwind and the prior Records filtering work were preserved.
- At initial delivery, read-only working-database preflight reported MariaDB 10.4.32 and no prerequisite problems. No working migration or feature activation had been performed at that point. The preflight alone did not prove backup restoration.

### Working deployment follow-up, recorded October 6, 2026

- User-provided preflight output reported `schema_ready_for_019: true`, an empty problem list and no writes.
- The user reported a private database export, `atikha_finance_before_019.sql`, sized 60,578 bytes. Robocopy output reported 17,492 files copied, zero failures and zero mismatches for the application backup, including configuration and receipt storage. These establish reported export/copy results; backup restoration was not verified.
- The user explicitly chose direct working deployment instead of restored-copy rehearsal. Rehearsal and recovery testing were not performed and must not be represented as passing.
- The user reported a successful import of migration 019. Subsequent read-only checks confirmed eight InnoDB tables, six added columns, the migration's foreign keys/checks, four immutable-evidence triggers and the singleton coordination row.
- Original fields in the two journals, four journal lines, one receipt and 32 accounts matched the pre-migration checksums. Journal debit and credit totals remained PHP 11,000.00 each. Existing originating-book values remained null rather than being inferred. There were no posted receipt links requiring legacy evidence backfill.
- With explicit user delegation, `STAGE1_WORKSPACE_ENABLED` was added as boolean `true`, preserving other configuration bytes. PHP syntax passed and the read-only workspace availability check returned true. No working financial records were posted during this activation.
- The user demonstrated saved receipt/payment drafts and balanced review previews: PHP 1,000.00 cash/income and PHP 500.00 expense/cash respectively. A subsequent read-only check showed two Draft-state records and unchanged journal counts/totals. These screenshots establish the displayed draft/review behavior, not successful live posting or document review.
- PHP configuration reported 40 MB upload and POST limits; Apache's configuration points to that PHP configuration directory. This is configuration inspection, not proof of a successful 8 MB HTTP upload.

### Deferred working-system acceptance

The user deferred the remaining manual checks to final integration: supporting-document upload/review/download, live posting and retry behavior, posted CRB/CDB/Journal History presentation, and working-server access-control checks. Live Gemini extraction remains unverified. Previously completed isolated automated checks are separate evidence and do not mark these manual checks complete.

### Completion supplement verified on October 6, 2026

- Implemented searchable account, project and party controls in the shared receipt, payment and General Journal workspace, without changing Financial Records. Native ID controls remain authoritative; search typing is separate from committed selection. Keyboard cancellation, unavailable selections, row rerendering, inline creation focus and posted disabling are covered.
- Implemented last-saved-only draft filtering with explicit Manila-to-UTC boundaries, scalar/date/range validation, owner-only results, additive Manila display timestamps, cancellation and stale-response protection. Failed refreshes retain the last successful list. Resume and discard behavior remains available.
- Added separate all-date book and Journal History destinations for posted receipts/payments, with one history destination for General Journal. New, recovered and reopened results share the posted-result rendering.
- The final isolated run passed 73 backend checks and 67 Edge HTTP/browser checks on `atikha_test_stage1_supplementd`. It covers selection without accidental draft changes, review invalidation on a different selection, date boundaries and incomplete accounting dates, invalid filters, ownership, failed/late responses, draft resume/discard, posting recovery and result navigation. Expected injected audit failures verify rollback.
- Artifacts are private in `.migration-private/stage1-browser-c2a24b90fe/`: `selector-1366.png`, `selector-1920.png`, `drafts-1366.png` and `drafts-1920.png`, plus the existing payment/review/book/setup captures. Selector and draft-list layouts were visually inspected at laptop and desktop sizes. This final supplement was verified in Edge; Chrome was not rerun.
- Relevant PHP and JavaScript syntax checks and diff whitespace checks passed. Existing uncommitted work was retained. This supplement made no working-database writes, migration or configuration change, added no dependencies and required no Tailwind build.

**Stage 1 implementation and deployment are complete; full working-system acceptance is pending.** The three supplementary UI gaps are closed and their focused isolated checks passed. Stage 2 can now be planned; the deferred manual checks remain scheduled for final integration.
