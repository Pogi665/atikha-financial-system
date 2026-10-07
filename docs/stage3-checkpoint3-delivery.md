# Stage 3 checkpoint 3 delivery

## Status and scope

Checkpoint 3 implements **ordinary financial correction posting**. Originals stay posted and immutable. A correction posts an exact General Journal reversal and, when selected, a validated replacement in one coordinated database transaction. Reversal-only is supported without a replacement.

Working migration 021 and Stage 3 activation remain pending. No working financial posting, protected-file change, configuration change, commit or push was performed. Existing uncommitted checkpoint work is preserved. Migration 021 is unchanged; no additional migration is required for this checkpoint.

Advance release/liquidation/return correction posting remains explicitly unavailable on the server and in the review UI until Checkpoint 4. Their existing private draft/review support remains available in isolated Stage 3 fixtures. This report supersedes Checkpoint 2's ordinary-posting HTTP 503 limitation, without enabling advance corrections.

## Implemented behavior

- `includes/correction_posting.php` owns the ordinary correction transaction through the existing actor lock and singleton accounting write barrier. It derives eligibility and workflow from persisted records, verifies the saved canonical request and review fingerprint, and generates separate deterministic reversal/replacement journal keys. It never sequences transaction-owning ordinary posting endpoints to construct a bundle.
- Original line amounts, account/project IDs and project snapshots are swapped exactly. Reversal party ID/snapshot are copied unchanged. Replacement accounts must be active and eligible; ordinary replacements may retain exact original inactive party/project references using their historical snapshots. Changed references require normal eligibility. Legacy original book metadata is preserved, while its default replacement is GJ. Transfers retain their normal GJ validation.
- Target uniqueness, exact line mappings, same accounting date, parent/root links, evidence associations, draft finalization, audits, stale reservation cleanup and accounting version increment commit together. Failures roll back the entire bundle.
- A matching successful retry recovers the same correction and journal IDs after a lost response, a new authenticated session or expired review token. Changed canonical content under the same durable key conflicts. Ownership and active Admin authorization still apply. Current master eligibility and expired tokens are not prerequisites for recovering a successful result.
- Reused images retain their bytes, file hashes, primary journal, original review and allocations. A replacement receives its own freshly reviewed association and allocations linked to the immediate target association. Newly attached images receive the replacement as their primary posted journal. Reversals make no new evidence claims.
- Successful posting releases only other correction drafts' reused posted-image reservations for that exact target, inside the winning transaction. Other drafts, their values and private unposted-image reservations remain intact. Audit failure restores the cleanup. Stale review/post requests are rejected; reopening retains the existing explanatory message and posted correction link.
- Review, post, successful recovery and reopening show complete immutable posted bundles, with the relevant cash-book link, Journal History and posted comparison/print navigation. Posted controls remain read-only. Ordinary review offers **Post correction** or **Post reversal**; advance review continues to disable posting.
- Flag-independent Financial Records metadata identifies corrected originals, generated reversals and replacements, including correction IDs, root/parent links, dates and permitted reasons. Cash-book search selects complete matching entries; Journal History retains line filtering. Both can search correction context. A dated correction link remains visible even when its accounting date is outside the displayed range.
- `journal_corrections.php` provides an authorized posted list, chain comparison and print presentation. Accounting dates and Asia/Manila recorded timestamps are distinct. It uses existing association-aware evidence readers: Management receives permitted financial summaries and accurate coverage without private advance document URLs or review snapshots. Posted financial readers remain available with the UI flag disabled.
- Cash books explicitly show **gross originating book activity**. Original and replacement rows remain; GJ reversals supply their ledger offsets. Originals are not excluded from journal-derived balances. Existing frozen Trial Balance figures and reviewed states are unchanged. In-range corrections, including net-zero metadata changes, alter the live source fingerprint; out-of-range historical reports remain unchanged.

These correction choices are selected system controls and capstone design decisions. The client visit notes support adjustment-journal handling; cropped worksheet references alone do not establish all routing, date, retry or reservation policies.

## Backend verification

The final clean run used the new disposable database **`atikha_test_stage1_s3c3c20261006`** and isolated receipt storage. **330 assertions passed**:

- Stage 1: 73.
- Stage 2: 96.
- Stage 3 checkpoint 1: 49.
- Stage 3 checkpoint 2: 59.
- Stage 3 checkpoint 3: 53.

The new checks exercise ordinary posting, exact reversals, inactive references, changed account/project/party/reference, new evidence, repeated correction lineage, reuse through the latest replacement, legacy and transfer posting, ownership/context boundaries, stale competitors, durable recovery and concurrent Admin posting.

Injected failures at replacement insertion, line mapping, evidence allocation and audit insertion verify rollback of financial records, receipts, evidence, correction mappings, drafts, both reservation tables and accounting write state. The audit failure after stale cleanup also restores the competing reservation. Expected injected audit failures appear on stderr; the final PHP exit code is zero.

A real disposable Trial Balance revision was submitted and reviewed before a net-zero metadata correction. Its frozen figures, snapshot lines and review state remain identical afterward; the corresponding live fingerprint changes.

An early concurrency harness run lacked its isolation constant. It was corrected to prevent loading the working configuration, then the clean final run passed. A later test assertion supplied cash-book context through the wrong helper argument; that assertion was corrected to use the supported `view=cdb` filter. Neither failure required a working database change.

## Browser and syntax verification

**76 Edge browser/HTTP assertions passed** in copied private applications with private sessions and isolated evidence: 37 Checkpoint 3 checks and 39 affected Checkpoint 2 regressions. External requests were blocked. No live application sessions, configuration or receipt directory were copied into these test applications.

The new run verifies visible selector cancellation, review/post controls, a committed response deliberately lost followed by visible retry, expired-token recovery in a new authenticated session, changed-content conflicts, immutable reopening, CDB/history/comparison links, escaped correction reasons, posted list/detail, complete cash-book search (four original/replacement lines), Journal History search (six original/reversal/replacement lines), reverse-only posting, role privacy and flag-disabled reader behavior. It creates one new synthetic ordinary fixture when the supplied prior fixture target is already corrected, so repeat runs stay isolated.

The regression run covers manual reused-image review, lost-upload recovery, document removal, v4 rejection by old routes, draft privacy, replacement-book changes, advance gross-cost preview, return-proof invalidation, stale reopening and disabled-flag privacy. Its actions leave journal counts unchanged.

Screenshots were captured at **1366x768 and 1920x1080** for reviewed and posted comparisons, plus print media. Visual inspection found readable original/reversal/replacement tables and no horizontal page overflow. Printed output removes navigation. A nested main landmark discovered during browser verification was replaced by a div inside the shared layout. Visual inspection also found stale draft/eligible labels after posting; the final browser run verifies accurate posted status and corrected-target labels.

The initial browser assertions were amended to wait for asynchronous posted detail loading, expect the existing 422 validation status for an incomplete request, and use a unique synthetic search reason to exclude earlier isolated runs. The final runs passed with no JavaScript errors. Only Edge was tested here; Chrome, physical printing and full Stage 1/2 browser acceptance remain outside this focused checkpoint validation.

Nine affected PHP files, two JavaScript files, both browser-runner Python files and `git diff --check` passed syntax/whitespace checks. Scoped CSS changes require no Tailwind rebuild.

## Worked results and acceptance boundaries

- **Posted in isolation:** CDB expense debit/cash credit 1,000 corrected to 900 produces a GJ cash debit/expense credit 1,000 plus a CDB replacement 900. Ledger result is 900; gross CDB activity is 1,900. A subsequent replacement correction retains the full chain.
- **Posted in isolation:** Duplicate reversal-only produces one exact GJ reversal and no replacement. It records an accounting correction, not a physical return of funds.
- **Posted in isolation:** Wrong account/project/party/reference, transfer metadata and legacy corrections use their validated workflows. New proof and reused proof retain their distinct ownership/provenance rules.
- **Failed in isolation as expected:** Expired first-post review, unsaved/changed content, stale target, generated reversal target, inactive replacement account, advance correction attempt and injected bundle failures leave no partial posting.
- **Concurrent isolation case:** Two separately reviewed Admin drafts for one target yield exactly one successful bundle.
- **Read-only working check:** Preflight verifies schema readiness and preservation hashes. This is not financial acceptance or a restoration rehearsal.

## Working deployment evidence

Read-only preflight reports `deployment_state="021 not applied"`, `schema_ready_for_021=true`, `problems=[]`, `checks_passed=true`, `writes_performed=false`. Counts remain **2 journals, 4 lines, 1 receipt, 2 drafts and 27 audit rows**, with no advances or posted evidence associations. All recorded preservation hashes match Checkpoint 2. Local `config.php` was not edited.

No deployment action is required from the user for this checkpoint. Do not activate Stage 3 yet. Complete Checkpoints 4 and 5, then use the separately authorized deployment sequence. Back up the current database/application/configuration/protected files and rehearse restoration and migration on a disposable copy. DDL may implicitly commit: inspect/recover after an error rather than rerunning a partial migration. Never rerun 015, 019, 020 or an already-applied 021 on the working database. Switching the UI flag off does not undo posted journals or restore a database.

## Remaining work

- Checkpoint 4: advance-operation reversals/replacements, new replacement-release identities, historical lifecycle validation, correction-aware settlement posting and register/reconciliation integration.
- Checkpoint 5: final integrated Stage 3 acceptance matrix and delivery, including remaining cross-browser/report boundaries.
- Working-data restoration rehearsal, migration 021, activation and legitimate agreed-data acceptance remain pending. Previously deferred Stage 1/2 working evidence, posting/retry, cash-book and access checks remain pending. Fictional demonstrations stay isolated.
- Later financial statements, report bundles and period closing are outside this checkpoint. Browser print-media verification does not establish physical-printer acceptance.

## Reproducible commands and private artifacts

Run the backend suite only with a **new** explicit disposable database name:

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint3.php --database=atikha_test_stage1_cp3review20261006
```

The suite creates its private fixture file. Supply that path to the browser runner; do not use working data:

```powershell
python scripts/test_stage3_checkpoint3_browser.py --fixture=.migration-private/stage1-core-26658c7906/stage3-checkpoint3-fixture.json --browser=msedge
```

Final backend log: `.migration-private/stage3-cp3-backend.log`. Test fixture: `.migration-private/stage1-core-26658c7906/stage3-checkpoint3-fixture.json`. Final browser artifacts: `.migration-private/stage3-cp3-browser-140a8a2627` and `.migration-private/stage3-cp2-browser-988507432b`. New screenshots include `review-1366.png`, `review-1920.png`, `posted-1920.png`, `detail-1366.png`, `detail-1920.png` and `detail-print.png`. Private artifacts are excluded from Git; they contain synthetic test data and sessions, not release files.
