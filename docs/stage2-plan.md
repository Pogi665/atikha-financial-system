# Stage 2 implementation plan: cash advances, liquidation and cash returns

Status: revised plan approved by the user, with implementation separately authorized on October 6, 2026. This replaces the earlier Stage 2 plan and incorporates the user-provided ChatGPT and Gemini reviews. See `stage2-delivery.md` for implementation and verification evidence. Working-database migration and feature activation remain separately authorized deployment steps.

## 1. Outcome, evidence and review disposition

Complete a cash-advance workflow using Stage 1's private drafts, searchable selectors, protected evidence, balanced journal posting, historical snapshots and durable retry handling.

The inspected source baseline is `c74ddfe` (`stage_1`). Stage 1 implementation and deployment are recorded as complete in `docs/stage1-delivery.md`; full working-system acceptance remains pending. The repository was clean before this planning-document addition. That source inspection does not constitute a fresh verification of the working database.

### Requirements and their sources

- Client visit notes describe cash advances that are not immediately expenses, liquidation linked to advances, returning unused money to cash, journal adjustments and posted/archived records that are not edited directly.
- The user's clarifications establish the intended regular-use finance system and the need to retain payer/payee information. They also clarify that the client can use different software when required by individual projects.
- Supplied account-list crops show advance-account concepts. The worksheet shows separate CRB, CDB and Journal movement columns. The crops do not establish complete cash-book layouts, approval authority, deadlines or transaction-routing rules.
- Balanced entries, server authorization, atomic posting, immutable evidence and concurrency safeguards are accounting/system controls. Do not attribute their complete implementation to cropped screenshots.
- The operational choices below were selected by the user during Stage 2 planning. They are design decisions, not independently confirmed client policies. The manuscript remains background context and does not add modules to this stage.

### Review findings incorporated

Gemini supports the release/liquidation/return model, exact advance-reduction formula, evidence protections, deadline history and checkpoint execution. Retain that structure. Do not adopt claims that the structure alone guarantees accurate financial statements. Implementation will modify repository files and validate disposable environments; delivering raw SQL snippets is not a substitute for integration or authorization to apply them to working data.

ChatGPT's earlier review requires liquidation-specific coverage, server-derived control lines, atomic operation links, per-account reconciliation, historical statuses, explicit evidence reuse limitations and inactive-reference rules. Its final review adds four requirements incorporated below:

1. Enforce Management privacy across existing book/history HTML and JSON, metadata readers, printouts and downloads, while retaining accurate internally calculated coverage.
2. Separate filtered register totals from unfiltered account reconciliation; use consistent read snapshots and check operation/control-line links in both directions.
3. Preserve version-3 context through every draft action, including upload, removal, resume and discard.
4. Bind return-proof confirmation to the actual advance and return context; changes require fresh confirmation and the posted audit preserves the confirmed context.

### Selected scope and defaults

- One posted release per advance. Additional funding creates a separate advance.
- Multiple partial liquidations and cash returns are allowed.
- External approval details are optional; no digital Management approval module is introduced.
- Excessive settlements remain unposted drafts. No automatic reimbursement payable or excess payment workflow is added.
- Staff enter a required due date. Audited extensions take effect today in Asia/Manila.
- An active person-type Stage 1 party identifies the accountable employee. This is not an HR employment-verification claim.
- The advance's originating project is a reference; journal-line project allocations remain authoritative.
- Settling the original inactive employee/project is permitted in the narrowly linked workflow.
- Liquidation supports manual liability credits without calculating tax or producing tax filings.
- Release documents are optional; cash returns require reviewed supporting proof; liquidation requires complete monetary coverage of claimed cost/asset lines.
- Admin prepares/posts transactions and manages deadline extensions. Management gets read-only posted summaries and printouts, without advance drafts, document images or private review/approval details.
- Actual past accounting dates are permitted through today in Manila. Settlements must be on or after release.
- More than one outstanding advance per employee is allowed; show existing advances without imposing an unconfirmed blocking policy.

Excluded: top-ups, historical/opening-advance import, digital approvals, automated reimbursements, reversal/replacement, period closing, frozen report bundles, new financial statements and live OCR improvements. Those belong to later work. No historical journal is inferred into an advance.

## 2. Workflows and accounting behavior

### 2.1 Release

The release form contains accountable employee, purpose, originating project or Organization operations, designated control account, paying cash/bank account, amount, accounting date, reference and required liquidation due date.

Optional external approval information contains approver name, approval date and reference. If any approval information is entered, require name and date; the reference remains optional. The approval date cannot follow the release date. Label this information **External approval recorded by accounting staff**. Accounting review is not approval.

Saving creates only an owner-private draft. Create the posted advance and permanent number only in the successful posting transaction. Number format is `CA-` plus a ten-digit, zero-padded advance ID; numbers need not be gapless.

Generate exactly two lines, both tagged with the originating project:

- Debit the designated advance-control account.
- Credit the selected paying cash/bank account.

The book is CDB and the journal kind is `advance_release`. This creates an asset balance, not an expense. Require a positive amount under the existing journal money/total limits.

The original party must currently be an active person. A selected originating project must be active. The control account must be designated, active, noncash, Asset type and Debit normal balance. Cash accounts must satisfy existing active cash-account rules.

Show other outstanding advances for the selected employee as information. Do not automatically reject another release to that employee.

### 2.2 Liquidation

Open a posted advance and display its original release, current outstanding amount, due date, evidence, cost allocations and complete proposed journal together.

Allowed lines are positive debits to eligible noncash Expense or Debit-normal Asset accounts; optional positive credits to active noncash Liability accounts with an explanatory allocation note; and one generated credit to the original advance-control account. Liability credit accounts must have Credit normal balance. Exclude cash lines, other designated accounts, income/equity lines and independently editable control lines.

Calculate in exact centavos:

**Advance reduction = expenditure/asset debits - liability credits**

The reduction must be positive and no greater than the current outstanding advance. Generate the control credit from that reduction. An excessive claim is determined by the advance reduction, not simply the gross cost/document total. Do not silently trim submitted amounts or create a payable for the excess.

Every claimed expenditure/asset debit must be fully covered by reviewed monetary document allocations. Informational documents do not count. Neither the liability credits nor the control credit are evidence-eligible expenditure lines.

The book is GJ and the journal kind is `advance_liquidation`; liquidation moves no cash. Multiple partial liquidations are allowed while the advance remains outstanding.

Worked examples required in implementation tests and walkthroughs:

- PHP 10,000 release, PHP 8,000 supported expenses: expense debits PHP 8,000; advance credit PHP 8,000; outstanding PHP 2,000.
- PHP 4,500 supported gross expense with PHP 500 withholding payable: expense debit PHP 4,500; liability credit PHP 500; advance credit PHP 4,000. Required evidence is PHP 4,500; the advance reduction is PHP 4,000. This can settle a PHP 4,000 outstanding advance.
- Supported equipment purchase: equipment Asset debit and advance credit. Do not force all liquidation debits into expenses.

If a review/post fails, retain the saved draft and explain the eligible amounts and current balance. Revising or splitting a claim requires explicit staff action and valid evidence allocations.

### 2.3 Return unused cash

The return form contains linked advance, accounting date, receiving cash/bank account, amount, purpose and reference. The receiving account may differ from the release account.

Generate exactly two lines, both retaining the originating project:

- Debit the receiving cash/bank account.
- Credit the original advance-control account.

The book is CRB and the journal kind is `advance_return`. Amount must be positive and cannot exceed current outstanding balance. A partial return before any liquidation is allowed. A settled advance cannot receive another settlement.

Require at least one manually reviewed supporting image, such as a cash receipt, deposit proof or acknowledgment. Release and return attachments are informational supporting proof, without expenditure allocations. Hide monetary-allocation controls in those two modes. All attached documents must be reviewed before posting.

#### Return-proof confirmation

Provide an explicit **Confirm return proof** action after the current return draft is saved and its supporting documents are reviewed. Display the linked advance and exact returned PHP amount beside the confirmation.

The server stores a confirmation fingerprint covering workflow/draft identity, linked advance, normalized amount, receiving account, accounting date and canonical reviewed-proof context, including receipt IDs and verified file hashes. Store the confirming actor and UTC timestamp. Do not accept client-supplied actor/timestamp values.

Saving changed confirmation-relevant fields clears the server-owned confirmation. Changing the amount, account, date or proof context also clears the visible confirmation and invalidates financial review. The advance target is immutable after draft creation; attempts to change it conflict rather than move a confirmation to another advance.

Ordinary save must not silently reconfirm a return. The explicit confirmation action binds the current saved context and advances its revision. Review/post requires a matching confirmation; a stale Boolean checkbox alone is insufficient. Bind financial review to the current draft revision and context after confirmation rather than to an obsolete pre-confirmation revision.

Preserve the confirmed context, actor and timestamp in the posted review/audit record. A matching already-successful posting retry recovers the original confirmed operation without requiring a new confirmation.

### 2.4 Dates, extensions and project context

- Validate real dates using the existing accounting date range and explicit Asia/Manila timezone. Entry dates cannot be future dates; settlement dates cannot precede the release. UTC recording timestamps remain separate from accounting dates.
- Initial due date must be on or after the release date. It can be in the future.
- Extend only an outstanding advance. New due date must be later than the current effective deadline and not before today's effective date. Require a nonempty reason and concurrency revision.
- Extensions take effect today in Manila. Keep immutable initial deadline and extension history; do not rewrite earlier aging.
- Multiple extensions on one day are ordered by their recorded sequence/ID. No shortening or backdated effective extension is introduced.
- Original employee and control account are immutable. Settlements cannot substitute another person or another designated account.
- Release, return and generated control lines retain the originating project. Liquidation cost/liability lines default to that project but can select another active project or Organization operations.
- Permit the inactive original party/project only while settling its linked advance. Unrelated inactive references remain prohibited. Original context uses release snapshots; new allocation projects receive posting-time snapshots.
- Do not infer that split-project activity independently balances as a project ledger.
- Existing designation/account maintenance protections remain enforced after release and after complete settlement. Used control designations cannot be removed to bypass the workflow.

## 3. Storage, draft integration, APIs and posting controls

### 3.1 Migration 020 and authoritative links

Prepare `migrations/020_stage2_advances.sql` as an additive, apply-once migration requiring complete Stage 1 schema. Use InnoDB, restrictive foreign keys, explicit UTC writes and validated JSON snapshots consistent with Stage 1.

Create these tables:

- `cash_advances`: ID/number, original party/project/control account, purpose/reference/context snapshots, required release-journal link, initial due date, optional external approval snapshot, creator/UTC timestamps and concurrency revision.
- `cash_advance_operations`: advance ID, operation kind (`release`, `liquidation`, `return`), unique journal/draft links and the prescribed unique control-line link. Enforce one release per advance using a nullable generated release-only key with a unique index, alongside service validation.
- `cash_advance_due_changes`: advance ID, old/new deadline, effective date, reason, actor and UTC recorded timestamp.

Release-journal links are unique. Every operation's control line must belong to its linked journal and use the advance's original account and prescribed direction. Validate those relations during posting and reconciliation; a line-ID foreign key alone does not establish them.

Create the release journal first, then advance/operation links within the same transaction. No partially created advance is visible as posted. Do not maintain editable released/settled/outstanding totals or status caches as financial sources of truth.

Protect operation links and due-date history from updates/deletes. Protect posted advance identity, release metadata, original due date and snapshots; only its concurrency revision changes during settlements/extensions. Existing immutable evidence and posted-history rules remain intact.

Migration 020 creates no financial data, master records or inferred advances. Preserve existing records and hashes. Do not include a hardcoded `USE atikha_finance`; select and verify the target explicitly. Do not rerun 019 or 015.

### 3.2 Complete version-3 draft lifecycle

Extend `journal_drafts` with authoritative workflow kind, defaulting existing drafts to ordinary, and nullable linked advance ID. Retain version 2 for ordinary drafts. Use version 3 for advance-specific payload context plus the existing stable line/document structure.

Release drafts have no posted advance ID until successful posting. Liquidation/return drafts link to an existing advance. Persist workflow/target when the dedicated service creates the draft; they cannot be changed afterward. Derive the book and journal transaction kind on the server.

The shared loader must validate supported payload versions, stored workflow and linked context together. Split version-specific normalization deliberately: ordinary v2 shapes/hashes remain unchanged; advance v3 fields are validated and cannot be dropped by a v2 normalizer.

All advance-draft actions must preserve v3 context: create, save, load/resume, upload and upload retry, attach existing image, review documents, remove, discard, review financial entry, confirm return proof, post, successful post recovery and reopening posted work.

Shared internal evidence helpers can operate on validated v3 drafts. Ordinary public accounting endpoints must reject advance drafts, including upload/attach/remove/discard actions; route those actions through the dedicated advance API. Generic saved-draft listing may describe advance drafts without gaining mutation authority.

Retain ownership, optimistic revisions, durable submission/upload keys, reservation protections and safe file handling. Removing a document invalidates related return confirmation; discarding releases reservations and soft-discards its still-reserved documents without physically deleting evidence.

Extend My Drafts with workflow labels and correct advance resume/discard destinations. Preserve owner-only results and last-saved-date-only Manila filters, UTC timestamps, failed-refresh preservation and request sequencing. Existing ordinary draft behavior is unchanged.

### 3.3 Public pages and request contracts

- `cash_advances.php`: posted register, advance detail/timeline, historical aging, separate reconciliation and printable summaries.
- `cash_advance_entry.php`: private release/liquidation/return workspace and clearly marked draft print preview.
- `cash_advance_actions.php`: authenticated lookups (`lists`, `register`, `advance`, `draft`) and mutations (`save`, `upload`, `attach`, `remove`, `discard`, `confirm_return_proof`, `review`, `post`, `extend_due`).

Admin can use all permitted operations. Management can use only posted register/detail lookups and summary printouts; it cannot receive master-entry lists, private drafts or mutation access. Validate active role on the server, not through supplied role parameters or visible buttons.

Retain the existing JSON success/error envelope and CSRF-protected mutations. Financial identity uses draft ID, revision, submission key and review token. Amounts are decimal strings; dates are `YYYY-MM-DD`; stored timestamps are UTC with explicit Manila display fields. Reject malformed scalar inputs, invalid IDs/dates/ranges and unrecognized operations rather than broadening requests.

Use existing status conventions: 400 invalid inputs, 401 unauthenticated, 403 unauthorized, 404 missing/inaccessible owner-private records, 409 stale/conflicting state, and 503 unavailable schema/feature or temporary failures. Private/unavailable draft lookups must not expose another user's draft.

Reuse Stage 1's selector/document components and scoped styles. Search typing does not change selected IDs or invalidate review; committing a changed selection does. Busy and posted states disable the visible controls. Generated control lines are shown in the journal preview but cannot be independently edited.

### 3.4 Dedicated authorization, atomic posting and retries

Keep ordinary control-account guards. An HTTP transaction-kind or advance flag never bypasses them. The dedicated operation validates stored workflow, linked advance, original account and generated line before authorizing its prescribed control-account use.

Lock order for review/post is actor, shared accounting write state, draft, existing advance, masters, receipts, then sorted accounts. Extensions use the same write barrier before the advance. Draft/evidence actions retain the compatible Stage 1 locking order; they do not create financial links.

Inside the posting transaction, use current reads to validate the actual remaining balance and current complete reconciliation of the original account. Do not use an earlier UI result, historical-view balance, filtered register total or stale read snapshot for financial authorization.

Atomically write journal/header/lines, immutable evidence, operation link, release advance record or advance revision, confirmed return context, posted draft state and all audit records. Any audit/evidence/link failure rolls back the entire financial operation. Row locks must run within the transaction; see [MariaDB FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).

Review fingerprints bind canonical draft contents/revision, advance revision/current balance, original context, relevant master/account versions, evidence versions and return confirmation. Use existing 15-minute reviewed-post semantics.

Successful matching retries recover the original journal and operation before checking expired review tokens, changed master eligibility or a now-settled advance. Validate active authenticated owner, CSRF, durable key and unchanged canonical content. Changed content using the same key conflicts. Do not create another operation on retry.

## 4. Evidence calculations, privacy, register and reconciliation

### 4.1 Liquidation coverage and proof semantics

Liquidation eligible coverage is the sum of claimed expenditure/asset debits only. Each such line must be completely covered; combined allocations cannot exceed a line, and each monetary document's allocations must equal its accepted amount. Informational documents add zero monetary coverage.

For PHP 8,000 expense debit and PHP 8,000 advance credit, the denominator is PHP 8,000, not PHP 16,000. For withholding, cover the gross cost debit even though the advance credit is smaller.

Apply this basis consistently in draft review, posting validation, immutable review snapshots, advance details, and existing book/Journal History coverage. Keep ordinary GJ's two-sided coverage behavior unchanged. Release/return details show their own supporting-proof status rather than treating control-account movement as supported expenditure.

Preserve original image bytes/hashes, uploader privacy, reservations, duplicate protection and manual review with OCR disabled. New document intake does not invoke Gemini automatically.

A posted image cannot be claimed again in another liquidation, including an unused portion. Multiple liquidations require distinct documents. Explain this limitation before posting a partially accepted document; do not introduce cropping, reuploading or metadata changes as a reuse workaround. Correction-linked reuse remains later work.

### 4.2 Management privacy across all paths

Apply viewer-aware projection to the new advance API and to existing CRB/CDB and Journal History initial HTML, embedded page data, JSON responses, journal detail rendering, metadata readers, printouts and direct evidence downloads.

Specifically, `receipt_journal_metadata()` currently supplies image URLs and review details, and `accounting_records()` incorporates them into journals. Separate internal evidence collection/coverage calculation from the public viewer-aware projection. Thread trusted authenticated viewer context through existing presentation paths; never take a role from request parameters. Unknown viewer context must fail closed for advance-private fields.

For Management advance responses, allow posted financial information and the permitted coverage/proof summary. Exclude attachment IDs, filenames, URLs, hashes, uploader data, per-document declared/accepted amounts, allocations, exclusion reasons, private review records and external approval metadata. Do not merely hide these in JavaScript or redact after JSON serialization.

Compute correct coverage internally before removing private attachment details. A fully supported liquidation must remain fully supported in Management's summary. Non-advance Stage 1 evidence permissions remain unchanged.

`receipt_attachment.php` must deny Management bytes linked to advance operations, including guessed direct URLs. Classify advance sensitivity from trusted persisted operation/journal context, not a client flag. If persisted advance classification is inconsistent, fail closed rather than expose its private evidence.

These protections depend on deployed schema/data, not `STAGE2_ADVANCES_ENABLED`. Turning the UI flag off must not restore private advance attachments in existing history/book responses or downloads.

### 4.3 Register, totals and historical status

Filters: as-of date (default today in Manila), accountable person, originating project, control account, settlement status, overdue indicator and text search across number/original employee/reference/purpose. The project filter concerns the originating reference; it is not a filter of liquidation expense-project activity.

Validate as-of dates through today; permit future due dates but no future accounting/posting dates. Use 25-row server pages, ordered by release accounting date descending then ID descending. Text terms match case-insensitively across the displayed number/employee/reference/purpose fields, with every term required somewhere in the record.

Show released, liquidated, returned and outstanding amounts, effective deadline, settlement status, overdue days and bucket. Filtered summary totals include all matching advances, not only one displayed page. Preserve last successful results on failed/stale requests and indicate unapplied filters.

Settlement status is Outstanding when no settlement has posted; Partially settled when some reduction has posted and a positive balance remains; Settled when the remaining amount is zero. Integrity errors are explicit, not silently converted to a status.

Overdue is independent: positive outstanding amount and as-of date later than the effective deadline. Due today is not overdue. Buckets are 1-30, 31-60, 61-90 and over 90 days, using Manila calendar dates rather than browser timezone interpretation.

Include only currently posted operations with accounting date on or before as-of, and exclude advances released later than as-of. Use the initial deadline plus the latest extension effective on/before as-of. A currently settled advance may therefore be outstanding/overdue in an earlier view.

Historical results describe currently recorded postings through the selected accounting date. Later backdated entries can restate them; Stage 2 does not freeze report revisions. Historical views are read-only; new settlement actions use current detail/current balance.

Detail timelines show operation accounting date, UTC-recorded time displayed in Manila, kind, journal reference and advance reduction. Printable views label Draft versus Posted and show the selected as-of basis. Management printouts obey the same privacy projection.

### 4.4 Full-account reconciliation, separate from register filters

For each advance, calculate in exact centavos from prescribed linked control lines:

**Outstanding = release-control debits - liquidation-control credits - return-control credits**

Liquidated amounts use advance-control credits, not gross expense debits or document totals. Drafts and unposted confirmations have no effect.

For each designated account through the same accounting date, compare all advances' outstanding balances with the full posted control-account ledger debit-minus-credit balance. Never net discrepancies across separate accounts.

Employee, originating-project, status, overdue, text search and pagination filters affect register rows/totals only. They do not narrow reconciliation. If an account filter is selected, reconciliation may show that account, but still includes all its advances and lines. Label this area **Full control-account reconciliation - independent of register filters**.

Example: employees owe PHP 6,000 and PHP 4,000; the ledger is PHP 10,000. Filtering to the first employee shows PHP 6,000 filtered outstanding and PHP 10,000 full-account outstanding/ledger, with zero discrepancy.

Produce register balances, full-account reconciliation and detail totals in one consistent database read snapshot per response. A concurrent posting must not make the displayed components represent different states.

Validate links in both directions:

- Each operation has a posted linked journal and exactly its prescribed control line, belonging to that journal, using the original account and correct direction; each advance has exactly one valid release.
- Every posted designated-control-account line belongs to exactly one valid operation. Detect extra control lines, missing links, wrong kinds/accounts and offsetting unlinked debits/credits even when the net balance matches.

Show linkage discrepancies alongside numerical discrepancies and block new financial operations against the affected account. Posting checks current, complete account reconciliation inside the write transaction, independent of any historical filters. Never invent opening entries or historical links to clear an error.

## 5. Checkpoints, acceptance and deployment

### 5.1 Reviewable implementation checkpoints

1. Additive schema, v3 draft contracts, dedicated authorization, read-snapshot reconciliation and server-owned return-confirmation foundation. Test preservation and integrity before financial UI work.
2. Register/detail, release form, private save/resume and atomic release posting.
3. Liquidation form, expenditure-only evidence coverage, manual liability credits and historical coverage integration.
4. Return form, amount-bound proof confirmation, partial settlement, deadline history and aging.
5. My Drafts routing, complete Management privacy across old/new responses, printing, focused regressions and delivery documentation.

Checkpoint 1 deliberately establishes the confirmation and privacy contracts needed by later checkpoints; full return/privacy UI follows in checkpoints 4/5. Do not activate Stage 2 until all privacy integrations and acceptance checks are complete.

Each workflow must include its screen and a browser walkthrough, not backend code alone. Retain existing shell/role branches and scoped workspace styles without new dependencies or unrelated redesigns. Adapt visible-selector tests rather than selecting hidden native controls.

### 5.2 Financial and integrity acceptance

- PHP 10,000 release -> PHP 8,000 supported liquidation -> PHP 2,000 return; register, control ledger, book routing and history agree.
- Multiple partial liquidations/returns; return before liquidation; settled advance blocks further settlement; distinct advances for additional funding.
- Equipment debit; PHP 4,500 gross/PHP 500 withholding/PHP 4,000 advance reduction; evidence coverage remains gross while register reduction remains net.
- Missing/partial/informational-only/unreviewed/duplicate/overallocated liquidation proof is rejected without financial changes. Release proof is optional; return proof is required.
- Zero/negative or excessive reductions leave advance/journal state unchanged. Reusing an already-posted document is rejected even if some accepted amount was excluded previously.
- Forged workflow kinds, altered advance targets, changed control accounts and ordinary-endpoint bypass attempts are rejected.
- Concurrent settlements cannot both consume the same remaining balance. Review becomes stale after another settlement, extension or relevant configuration change.
- Lost-response retries across login sessions/expired tokens recover one journal and one operation. Changed contents using the durable key conflict.
- Audit/evidence/link failure leaves no partial advance/journal/operation/evidence state.
- Reconciliation separately checks every designated account, missing/extra/wrong control links, and offsetting unlinked entries with zero net difference.

### 5.3 Draft, confirmation, date and snapshot acceptance

- A v3 release/liquidation/return survives save and logout/login resume with full context. Test upload, upload retry, attach, remove and discard, not only review/post.
- Document changes preserve workflow identity while invalidating affected review/return confirmation. Discard releases reservations with no journal effect; ordinary endpoints cannot mutate v3 drafts.
- Confirm a PHP 2,000 return, change to PHP 1,500, and verify fresh confirmation is required. Repeat for bank/date/proof changes and forged advance/context confirmation. Verify the posted snapshot/audit preserves the exact confirmed context and actor/time.
- Verify ordinary v2 drafts, canonical hashes, monetary rules, evidence reservations and retry behavior remain unchanged after 020.
- Past release/settlement dates, settlement before release, future-date rejection, as-of before release and a currently settled advance shown historically outstanding.
- Deadline-day and 1/30/31/60/61/90/91-day aging boundaries; multiple same-day extensions; earlier deadlines preserved; extension effect starts today.
- Inactive original employee/project settlement succeeds; unrelated inactive references and ineligible accounts fail. Split-project lines retain authoritative allocation/snapshot semantics.
- Filter to the PHP 6,000 employee in the PHP 10,000 account example: filtered total PHP 6,000, full reconciliation PHP 10,000, zero difference.
- Coordinate a concurrent read/post to verify one response uses a consistent snapshot. Verify a historical filter cannot authorize posting against stale or incomplete balances.

### 5.4 Permissions, UI and Stage 1 dependency acceptance

- Admin ownership, cross-owner drafts, active-role checks, CSRF and anonymous access; Management cannot access advance drafts, mutations or master-entry lists.
- Management opens advance journals via existing CRB/CDB and Journal History, including initial HTML/embedded data and JSON. Coverage remains correct; no image URL, filename/hash, private review or approval field is returned.
- Management direct image requests fail. Repeat response/download privacy checks with the Stage 2 flag disabled. Ordinary Stage 1 evidence permissions remain unchanged.
- Admin still sees permitted advance evidence; missing private attachment details do not cause rendering errors in Management detail/print views.
- Visible selector mouse/keyboard selection, no-match/cancellation without ID changes, multiple rows, inline creation, validation recovery and posted disabling.
- My Drafts remains last-saved-only, preserves prior results on failures/stale responses and resumes/discards each workflow through the proper API.
- Browser screenshots and print layout checks at 1366x768 and 1920x1080; correct book/history/advance destinations after new post, reopening and retry recovery.

Use new guarded disposable databases, isolated sessions/configuration/uploads and visibly labelled synthetic NGO staff/training/transport/equipment examples. No synthetic records in the working database. Block external calls in automated tests; manual document review must work with OCR disabled.

Run relevant PHP/JavaScript syntax checks, focused Stage 2 backend/browser checks and affected Stage 1 journal/evidence/accounting regressions. Broaden only for changed behavior, failures or unresolved concerns. Record actual browser/build coverage; do not claim Chrome/live Gemini checks unless performed.

Stage 1's deferred working-system document upload/download, posting/retry, books and access-control checks remain pending. Exercise those dependencies in disposable Stage 2 tests without claiming working-system acceptance. New financial-statement and frozen-report checks remain later-stage dependencies.

### 5.5 Deployment and completion boundaries

- Add read-only Stage 2 preflight for complete Stage 1 prerequisites, missing/partial 020 detection, required engines/constraints, balanced journals, protected evidence/reservations and control-account integrity. It must not delete records or alter configuration to pass.
- Introduce `STAGE2_ADVANCES_ENABLED`, default false, requiring Stage 1 availability and complete Stage 2 schema. Before 020 and while disabled, ordinary Stage 1 stays available. Persisted advance privacy/coverage and account guards remain enforced independent of the Stage 2 UI flag.
- Deliver migration, preservation evidence, test results, private screenshot locations and a Stage 2 delivery guide separating implementation, working deployment and full acceptance.
- Record database/application/protected-file backups and recovery limitations. The previous waived rehearsal concerns Stage 1; do not represent any unperformed Stage 2 restoration/rehearsal as passing.
- Working migration/activation requires the user's later deployment instruction. During implementation, use only disposable migrations/tests; do not enable working configuration automatically.
- Apply 020 once to an explicitly verified target after backup and successful disposable checks. Stop on partial failure and inspect/recover instead of blindly rerunning. Never rerun 019 or 015. DDL implicitly commits: [MariaDB implicit-commit documentation](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).
- Disabling the feature is a UI rollback, not restoration of the pre-020 database. Preserve all advances, journals, links, reservations and confidentiality protections.

Completion criterion: all three workflows operate correctly; v3 draft/evidence actions and bound return confirmation pass; expenditure coverage and balance protections pass; the register reconciles individually and per account with both-direction link checks; historical aging and all privacy paths pass; focused checks and screenshots are recorded; delivery documentation accurately identifies deployed versus deferred acceptance work. Later-stage work is not implemented to satisfy Stage 2 acceptance.
