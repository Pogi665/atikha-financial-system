# Stage 3 final delivery: linked journal corrections

October 7, 2026. **Stage 3 source implementation and isolated integrated verification are complete. Subsequent working migration 021 is verified and the Stage 3 configuration flag is enabled; restoration rehearsal and full working acceptance remain pending.** The user authorized Checkpoint 5 execution after the supplied plan reviews and separately requested working activation later. This document grants no migration, activation, financial-posting or commit/push permission.

## Delivered behavior

The [approved specification](stage3-plan.md) and [Checkpoint 5 supplement](stage3-checkpoint5-plan.md) govern this delivery. Checkpoints [1](stage3-checkpoint1-delivery.md), [2](stage3-checkpoint2-delivery.md), [3](stage3-checkpoint3-delivery.md) and [4](stage3-checkpoint4-delivery.md) remain dated evidence; their overlapping totals are not added to this checkpoint's fresh results.

- Originals, evidence and snapshots stay immutable. A correction posts an exact General Journal reversal and, where selected, a validated replacement in one transaction. Reversal without replacement requires a reason. A replacement can itself be corrected; a generated reversal cannot.
- Reversal and replacement share one validated accounting date, from the original date through today in Asia/Manila. Posting time remains separate. Backdating can change live historical reports; frozen Trial Balance revisions retain their reviewed figures.
- Ordinary correction entry requires the Stage 1 and Stage 3 flags plus complete installed 019/020/021 schemas. Advance correction entry additionally requires the Stage 2 flag. Migration 020 remains an installation prerequisite even when its pages are disabled.
- The additional advance gate uses persisted target/operation/draft provenance. It covers page/resume, save, review, evidence actions, proof confirmation, posting and successful retries. Stage 2 off pauses those actions without altering drafts or files; matching retries recover after it is enabled again, including already-corrected targets.
- Financial Records offers correction starts according to server-derived availability per journal. Financial eligibility is separate. Posted comparisons, books, evidence associations and Management privacy remain effective with UI flags disabled.
- Release corrections require all effective settlements to be reversed first. Replacing a settlement leaves its dependency on the release intact. A replacement release creates a new advance number and requires active eligible references; replacement liquidations/returns retain the original advance.
- Both correction and normal settlement writers use the same lifecycle grouped by accounting date. A later reversal cannot fund an earlier settlement. Register totals follow filters; reconciliation covers every advance and every valid control line in each account through the selected accounting date.
- Reusing an original document follows validated correction lineage with fresh review and a separate association. Primary ownership, bytes, hashes and prior reviews remain intact. Successful posting releases only stale reused-image reservations for competing drafts targeting that exact journal, transactionally; their drafts, private uploads and ordinary reservations remain.

Separate originating books and the adjustment-journal concept are supported by client notes/references to the extent recorded in [client context](client-context.md). Exact routing, private drafts, feature gates, date limits, reversal/replacement and reservation policy are selected system decisions and controls, not policies proved by cropped worksheets. No new client policy or module was inferred from reviewer praise.

## Checkpoint 5 changed components

- `includes/stage3_common.php`: ordinary gate no longer depends on the Stage 2 UI flag; complete schema remains required.
- `includes/correction_drafts.php`, `includes/correction_posting.php`, `journal_correction.php`, `journal_correction_actions.php`: workflow-aware entry helpers and guards, preserving ungated authorized posted readers/downloads and existing lock order.
- `includes/accounting_query.php`, `assets/js/financial_records.js`: per-journal correction-start availability.
- `scripts/preflight_stage3.php`: complete-schema next-step text now points to this delivery instead of Checkpoint 1. It remains read-only and does not verify protected file bytes.
- Integrated profiles in existing Stage 1/2, journal/accounting and receipt runners; independent complete-schema browser fixtures; new `test_stage3_checkpoint5.php`, `test_stage3_checkpoint5_browser.py`, `test_stage3_transitions.php`, `test_stage3_fixture.py` and `test_source_manifest.py`.

No new migration was required. Working configuration, database, receipts and drafts were not used as fixtures or modified. Earlier uncommitted changes were preserved.

## Worked accounting outcomes

All following records are fictional disposable NGO fixtures, not Atikha transactions:

1. **Payment 1,000 corrected to 900:** original CDB debit expense / credit bank 1,000; GJ debit bank / credit expense 1,000; replacement CDB debit expense / credit bank 900. Gross CDB activity is 1,900; corrected net expense/cash reduction is 900. Ledger calculations retain all three journals.
2. **Release 10,000, liquidation 8,000 corrected to 7,000:** GJ reversal restores 8,000 to the advance, then supported replacement reduces it by 7,000. Outstanding rises from 2,000 to 3,000, on the same original advance. Before the correction date the original liquidation remains effective.
3. **Gross cost 4,500 with liability 500:** debit supported cost 4,500, credit liability 500, credit advance 4,000. Required monetary support is 4,500; advance reduction is 4,000. The control credit is not a second expenditure claim. Eligible asset purchases use the same gross-cost rule.
4. **Return correction:** the browser fixture releases 10,000 on October 2, liquidates 8,000 on October 3 and returns 2,000 on October 4. October 7 corrections replace liquidation with 7,000 and return with 1,500. Outstanding on October 7 is 1,500; historical October 4 outstanding remains zero. Return proof binds the exact amount/context and must be confirmed again after changes.
5. **Release replacement:** browser originals/replacements have distinct advance IDs and links; the old advance is Replaced, and the new release has 9,000 outstanding. A release with an effective replacement settlement remains blocked. Reconciliation validates both control accounts if changed.

Original CRB/CDB/GJ/transfer posting recovery returns the **original journal**, including after later corrections, a changed session and expired review token. It does not return the replacement or make duplicate writes. Changed content under the same key conflicts.

## Verification record

Fresh final verification passed **694 complete-schema backend operational/readiness/integrity assertions**, **19 migration/partial-schema assertions**, **370 mixed browser/HTTP assertions** and **10 presentation-only assertions**. These are separated below; old overlapping delivery counts and additional fixture-builder reruns are not added. The main integrated profile exited zero with 487 assertions; four deliberately partial-schema assertions in that profile belong to the transition count, not the complete-schema operational count.

### Tested source and isolation

Repository HEAD is `a5a681b` (`stage_2`). Checkpoints 1-4, supplements and this checkpoint are uncommitted/untracked working source; HEAD alone cannot identify them.

- Final public implementation/test/lockfile manifest SHA-256: `802eeedd8f631a4a875d36f1c92ae8d64604458b79004d7be891ae43a3735bdd`. Private manifest: `.migration-private/cp5-source-cab0d195b1.json`.
- Application-only SHA-256: `a26010a1901923ca7983decfc7ef8bb55e4e1c7451dbbc7e9a1323c22fa0479b`. Scope is public root PHP, `includes/` and `assets/`; local configuration/connection, exports, uploads, sessions and private artifacts are excluded.
- The complete manifest also covers migrations, PHP/JavaScript/Python test/support scripts and dependency lockfiles, excluding Python cache files. Documentation is tracked separately and does not change the executable fingerprint.
- Earlier runs used manifest `26ea8c847dfcd11e345fccf3bf81c67b26d2d166177d8eabc5a1f5f8ff202cbf`, then `dd8c2b9a8baf591d0657d1ae1ab5088ef36b7dbf1e1a98c85388cb5d016b3aaf`. Differences are the final browser-harness corrections and retention of legacy session context in the backend harness. Application bytes did not change between these runs. The final main profile runs the final backend harness; final Checkpoint 4/5 browser runs use their corrected runners.
- Hash comparisons of independent browser application copies match final application bytes. Receipt testing intentionally substitutes `includes/gemini_client.php` with its private provider transport double; that difference is explicitly retained in the private source comparison.
- Every fixture uses a guarded disposable database, private evidence and a copied app with separate connection/configuration/sessions. Browser external traffic is blocked; OCR provider responses are doubled. Fixture cloning verifies full 020/021 and copies independent image files. This is fictional-fixture isolation, not restoration of current working data.

### Completed backend inventory

Main integrated command actually executed:

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint5.php --database=atikha_test_stage1_cp5finalb
```

This creates a **new** guarded database and prepares legacy fixtures before installing 019/020/021 once. All normal operational cases then run on complete 021. Reproduction requires another new name; never rerun a creation script against an existing fixture or the working database. Complete-schema workers verify their database/evidence context independently. Private final log: `.migration-private/cp5-final2.log`; resulting fixture directory: `.migration-private/stage1-core-f5a95548ea`.

Its **487 passed** comprise:

- Stage 1 **71**: private ordinary drafts, exact posting, master/control protections, documents/allocations, saved-date boundaries and durable recovery. Two absent-019/setup assertions remain in standalone mode and are covered separately in the migration profile.
- Stage 2 **96**: release/liquidation/return, gross/net support and assets, proof binding, overspending rejection, inactive references, due-date history/aging, ownership, concurrent settlements and full-account reconciliation. Integrated corruption tests respect 021's immutable history.
- Checkpoint 1 **48**: **44 complete-schema** reader/association/chain/control/privacy/integrity assertions plus **4 partial-trigger/preflight/download/restoration assertions**. The original 021 migration-preservation assertion moves to the separate transition profile rather than applying 021 again.
- Checkpoint 2 **59**: v4 drafts, fresh reuse review, uploads/reservations, stale cleanup and rollback, exact target ownership, confirmation, and read-only review.
- Checkpoint 3 **53**: actual ordinary atomic correction writer, failure injection, races, duplicate recovery, linked evidence, gross versus net books, unchanged frozen Trial Balance and changed live fingerprint.
- Checkpoint 4 **64**: actual advance corrections, new release identities, settlement blockers, dated lifecycle protection in both writers, old/new control reconciliation, original advance-request recovery, operation/proof rollback and concurrency.
- Checkpoint 5 **96**: original CRB/CDB/GJ/transfer recovery after correction, further correction and reversal-only; wrong-content rejection; legacy scalar recovery under its original session contract; ordinary posting/recovery with Stage 2 off; 66 paused-operation/action/mode cases without mutations; reenabled successful recovery; replacement provenance, flag restrictions and final integrity.

The 483 complete-schema cases above plus the older compatible profiles' 89 journal, 74 accounting and 48 receipt assertions total **694**. The four schema-fault cases plus two older bootstrap-preservation cases and the independent 13-case migration profile total **19**. The raw profile counters are retained below for traceability, without calling transition cases normal financial operations.

For the first three commands set `$env:ATIKHA_TEST_INTEGRATED='1'` in the test PowerShell process, then remove that environment variable after running them. Each command uses XAMPP PHP and its stated **new** disposable database:

- `scripts/test_journal.php --database=atikha_test_journal_cp5final`: **90 passed**, comprising 89 complete-schema operational assertions plus one migration-016 preservation assertion during fixture setup. Covers account types, exact-centavo balance/limits, legacy scalar posting/retry, authorization, audit rollback and corruption detection. Its old scalar project-tag success case belongs to the standalone pre-019 contract; integrated mode instead verifies explicit rejection through that route.
- `scripts/test_accounting.php --database=atikha_test_phase3_cp5final`: **74 passed** with 019/020/021 installed before operational checks. Covers ledger/Trial Balance/KPI/expense calculations, frozen snapshot/review/fingerprint compatibility, request validation, date/account filters, cash books, CSV/formula and large-value boundaries. Its final intentional corruption fixture remains confined to that separate database and is not a browser baseline. The printed database-retention message is not an assertion.
- `scripts/test_receipt_journal.php --database=atikha_test_phase4_cp5final`: **49 passed**, comprising 48 complete-schema operational assertions plus one migration-018 preservation assertion during setup. Covers historical submission compatibility, normalized provider proposals, staging/manual review, protected posting/retry, duplicate-file protection and failure rollback. The absent-018-only check remains in standalone mode; transition coverage is separate.
- `scripts/test_stage3_transitions.php --database=atikha_test_stage1_cp5transa`: **13 passed** on a separate new database. Explicit 019/020/021 availability, original-value preservation, exact database targeting, immutable posted headers and partial-trigger/restoration behavior. These are migration/transition checks, not financial operational cases.

All commands above use `C:\xampp\php\php.exe`. Actual exit codes were zero. Private logs are `cp5-journal-final.log`, `cp5-accounting-final.log`, `cp5-receipt-final.log`, and `cp5-transitions.log`; result inventory is `cp5-older-final-results.json` under `.migration-private/`.

### Browser/HTTP inventory

All ran on complete 019/020/021 with `python`, headless **Microsoft Edge** (`--browser=msedge` default). **370 mixed browser/HTTP assertions passed**, including each suite's inline layout checks, plus **10 separate presentation-only assertions**. Fixture names below are private, independent disposable baselines; no working data was copied.

- `scripts/test_stage1_browser.py --fixture=.migration-private/cp5-clone-6f7cd62f87be/stage1-fixture.json`: **67**. Visible searchable controls, keyboard cancellation/no-match, inline creation, restored/read-only controls, evidence, lost response/retry, posted book/history links, complete-entry cash-book search, last-saved Manila boundaries/incomplete dates/stale and failed refresh, owner/role/setup protections. Artifacts: `stage1-browser-6d2668d374`.
- `scripts/test_stage2_browser.py --fixture=.migration-private/cp5-clone-a2d31e36c861/stage2-fixture.json`: **56**. Normal release/liquidation/return, confirmation and evidence, register/aging/applied print scope, retry and flag-independent privacy. Artifacts: `stage2-browser-c4b98ea28a`.
- `scripts/test_receipt_browser.py --fixture=.migration-private/cp5-clone-3c252eddb73e/receipt-fixture.json`: **68**. Scan Receipt upload/local preview/layout, processed doubled response, provider-busy failure, retained manual General Journal handoff, review/posting protection and role/attachment restrictions. Artifacts: `receipt-browser-6f9e4e56b7`.
- `scripts/test_stage3_checkpoint2_browser.py --fixture=.migration-private/stage1-core-d6e0a881e7/stage3-checkpoint2-fixture.json`: **43**. Private v4 save/resume/upload/reuse/remove, interrupted upload recovery, fresh review, ordinary/advance route rejection, return confirmation invalidation, stale warnings, posted reopening and privacy. Uses its independently rebuilt complete-schema CP2 baseline rather than a target consumed by later checkpoints. Artifacts: `stage3-cp2-browser-3ba40c2e7c`.
- `scripts/test_stage3_checkpoint3_browser.py --fixture=.migration-private/cp5-clone-a3f30e3f46ae/stage3-checkpoint3-fixture.json`: **34**. Real atomic ordinary correction/reversal-only, lost response and new-session recovery, immutable reopening, escaped comparison, cash-book/history search and Management restrictions. Artifacts: `stage3-cp3-browser-c5edbd90d0`.
- `scripts/test_stage3_checkpoint4_browser.py --fixture=.migration-private/cp5-clone-f399b674b9cc/stage3-checkpoint4-fixture.json`: **48**. Real release/liquidation/return corrections, lost response, old/new identities, dependency blockers, gross cost coverage, confirmation, timeline/aging/register/reconciliation, applied print scope and privacy. Artifacts: `stage3-cp4-browser-5cace9f686`.
- Same Checkpoint 4 command with `--followup`: **10 presentation-only**. Posted identity/separator labels, keyboard cancellation, final review/print layouts and unchanged journal count. Artifacts: `stage3-cp4-browser-fcb6870789`.
- `scripts/test_stage3_checkpoint5_browser.py --fixture=.migration-private/cp5-clone-0d3d4c3d6e15/stage3-checkpoint5-fixture.json`: **54**. Ordinary CRB/CDB/GJ/transfer correction while Stage 2 off, original authenticated HTTP recovery after correction with new session/expired token, changed-content conflicts, paused advance actions and unchanged state, per-target starts and posted privacy across flag combinations. Artifacts: `stage3-cp5-browser-35ebf76b36`.

Corresponding private `cp5-*-browser*.log` logs contain case labels. Laptop 1366x768 and desktop 1920x1080 captures were inspected for selectors/drafts, ordinary review/posted comparison, advance review/timeline/register and Scan Receipt. Print-media captures/assertions verify presentation, not a physical printer. Browser counts include HTTP and presentation assertions; they are not counts of independently distinct financial transactions.

### Harness corrections and limits

Final runs replace failed/partial attempts. Integrated fixtures stopped deleting or updating immutable posted history merely to test corruption: they use rolled-back offsetting entries or a terminal dedicated fixture. Legacy project expectations respect the saved-draft contract after 019. UTF-8 amount expectations and scalar fixture party IDs were corrected. The browser harness now waits for the asynchronously populated posted comparison, chooses an actually corrected advance comparison and uses a 120-second timeout for that expensive comparison request after a 30-second parallel-run timeout. These do not change application accounting behavior or claim acceptable production latency.

Legacy scalar forms retain their original session-secret requirement. The integrated replay test now retains that context; it does not extend legacy forms to the saved-draft cross-session guarantee. An earlier target already corrected by a prior suite is reused for recovery rather than corrected twice.

Syntax checks passed for **144** PHP/JavaScript/Python files; changed harness files were rechecked after their last edits. `git diff --check` passed (line-ending notices are not failures). No Tailwind rebuild or placeholder `npm test` was used. Protected Playwright dependency access required scoped elevated test commands; the completed runs used only their isolated applications/databases. No independent external source-code review is claimed.

Excluded/pending: working-data restoration, working migration/activation/acceptance, latest Chrome parity, physical printing, live Gemini accuracy/recovery, SMTP/MFA integration, unrelated modules and future financial-statement/report-bundle calculations. They are not silently counted as passes. Fictional injected failures/races cannot substitute for legitimate working transactions.

## Subsequent working deployment: October 7, 2026

The user supplied a successful phpMyAdmin import screenshot for `021_journal_corrections.sql` into `atikha_finance` (16 queries executed), followed by a complete-schema read-only preflight result. The preservation row counts and hashes shown match the supplied pre-migration result: 2 journals, 4 lines, 4 receipts, 2 drafts, 46 audit rows, and zero posted evidence associations, evidence allocations, advances, advance operations and due changes. Those observations concern the preflight manifest's preserved fields, not protected file bytes or unlisted records.

At the user's explicit request to enable Stage 3, a fresh read-only preflight independently confirmed the intended database, `021 already applied`, `schema_complete=true`, `checks_passed=true`, no problems and `writes_performed=false`. The working configuration had no Stage 3 flag; one `define('STAGE3_CORRECTIONS_ENABLED', true);` was added beside the existing enabled Stage 1/2 flags. Other configuration bytes were preserved and `php -l config.php` passed. No database writes, financial postings, commit or push were performed in this activation task. Browser smoke checks remain pending.

Backup evidence: the user supplied a Robocopy summary showing 33,181 files and 3,958 directories copied, with zero skipped items, mismatches or failures. Direct read-only inspection of the current SQL export found 90,405 bytes and no `USE`, `CREATE DATABASE` or `DROP DATABASE` statements. This is export/copy evidence, not proof of restoration or protected-file byte preservation. The user chose direct working migration and deferred restoration rehearsal; recovery and full working acceptance remain unverified.

The final external reviews support source completion at delivery-report level. They are not independent source/artifact verification. The advance comparison request's representative single-user response time must be measured before working acceptance; the isolated test's increased timeout did not establish working performance.

### Historical Checkpoint 5 deployment boundary

Recorded prior deployment: working migrations 019/020 are applied and Stage 1/2 navigation is enabled. Working 021 and Stage 3 activation remain pending. Those are dated delivery facts, not reverified working-schema observations from Checkpoint 5. No working preflight, migration, configuration change or financial posting was performed here.

### Read-only preflight

From the application folder in PowerShell:

```powershell
C:\xampp\php\php.exe scripts/preflight_stage3.php --database=atikha_finance
```

Inspect the returned database, schema state, problems, preservation manifest and `writes_performed=false`:

- Absent 021, readiness true and no problems: prepare the backup/rehearsal; this does not authorize importing SQL.
- Complete 021: verify integrity and **do not rerun 021**. A complete schema does not prove activation or acceptance.
- Partial/incompatible schema or any integrity problem: keep Stage 3 disabled and inspect the specific failure/recovery path. Do not repeatedly import the migration.

Preflight's `file_bytes_verified=false` is intentional. Verify protected storage separately; database hashes alone do not prove receipt bytes or restoration.

### Backup, rehearsal, migration and activation order

1. Verify the intended working target and existing prerequisites with read-only preflight. Pause writes while taking a coordinated **current** database/application/configuration/protected-storage backup outside served folders and Git. Include all storage actually configured by the installation. Pre-020 exports are not current Stage 3 backups.
2. Record export size/SHA-256, source/configuration identity and private file hashes without publishing secrets, receipt details or exports. Inspect export database-selection/creation/deletion statements before restoring elsewhere.
3. Restore that backup into a deliberately isolated database/application with separate private files/sessions and external services blocked. Compare restored counts/hashes and protected file bytes. An empty database migration or fictional fixture clone is not a current-data restoration rehearsal. The user previously deferred rehearsal; it remains pending unless completed, rather than becoming a new invented permission requirement.
4. On the verified restored target, rehearse `migrations/021_journal_corrections.sql` once with Stage 3 off. Verify required tables/columns/FKs/triggers, preservation of original fields and integrity. Account explicitly for newly added fields in manifests. Smoke-test the copied application without fictional working postings.
5. With applicable explicit working migration authorization, back up intervening changes and verify the selected working database again. In phpMyAdmin select `atikha_finance`, then import **only** `migrations/021_journal_corrections.sql` once. This migration has no hardcoded `USE`, but selection still matters. Never import the base schema or rerun destructive 015 or already-applied 019/020/021. Migration 019's hardcoded database selection is a separate rehearsal hazard; do not use it in this deployment.
6. DDL can implicitly commit. On failure, keep activation off; diagnose the partial state against the backup instead of assuming SQL transaction rollback. Repeat read-only schema/integrity and original-field/file-preservation checks after a successful import. Record actual before/after counts/hashes.
7. With applicable explicit activation authorization and verified prerequisites, set `STAGE3_CORRECTIONS_ENABLED` to true in the working configuration. Keep the current Stage 1/2 flags as intended. Ordinary entry needs Stage 1 + Stage 3; advance entry adds Stage 2; complete 020 is still structurally required. Do not overwrite configuration with the example.
8. Perform nonposting smoke checks, then separately agreed legitimate-record acceptance. Record results and limitations. No Tailwind build is needed for Checkpoint 5's JavaScript/PHP changes.

### Recovery

Turning Stage 3 off is an **entry/UI rollback**, not a database restore. Posted corrections, evidence, advance lifecycle and privacy remain. Do not delete correction rows, change originals or disable history triggers to imitate rollback.

A true restore requires a verified matching database/files backup and a plan for legitimate records created afterward. Pause writes, preserve the current state, and restore only within an explicitly authorized recovery scope. Rehearsal and recovery remain unverified for the current working data until demonstrated.

## Concrete working acceptance checklist

Every item below is **Pending**. Isolated results do not change its status. Record setup, expected outcome, actual evidence and Passed/Pending/Blocked disposition during separately authorized working acceptance:

- **Schema/navigation, no posting:** verify complete 021 and intended flags; ordinary/advance correction starts follow their gates; permitted books/comparisons remain readable. Collect preflight and page evidence.
- **Draft/review/selectors, no financial posting:** use agreed nonfinancial inputs, confirm last-saved Manila filters, owner privacy, review/reopen and no ledger effect. Upload/review/discard still mutate drafts/files and must not consume another workflow's reservations.
- **Evidence/privacy, no financial posting:** use an agreed authorized record; verify correct association/review and permitted download; Management receives coverage/financial summary without private advance URLs/hashes/reviews in HTML, JSON, print and guessed downloads, including flags off.
- **Ordinary posting/retry/correction, posting required:** specify real source record, exact accounts/amount/book/date and authorization first. Expect balanced original; retry same ID/no writes; GJ exact reversal plus correct replacement; originals preserved. Capture journal IDs, line totals, book gross versus ledger net and audit.
- **Advance release/liquidation/return/correction, posting required:** identify the real employee, control account, bank, approval reference if used, due date, supported gross costs, liabilities and return proof beforehand. Agree exact expected before/after outstanding and historical dates. Capture IDs, evidence coverage, lifecycle, register and full-account reconciliation. Do not create a fake transaction just to pass acceptance.
- **Disposable demonstrations only unless a legitimate matching case exists:** duplicate cancellation, malformed evidence, overspending, rollback injection, races and historical-overconsumption cases. Working originals are not destructive-test fixtures.
- **Separate pending evidence:** actual current-data restoration/recovery, latest Chrome parity, physical printing, live provider recovery/real-image accuracy, later financial statements/report bundles and period closing.

The next action after source verification is delivery/source review, then separately authorized deployment preparation. Stage 3 source completion does not complete the entire system or authorize the next stage.
