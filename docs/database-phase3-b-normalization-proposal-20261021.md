# Phase 3-B: copied recovery normalization and remaining validation

**Current result: isolated validation and original-export restoration completed successfully.** The completion command below is consumed; do not rerun it. Working cutover/startup/acceptance and U3-A integration remain separate. [Final evidence](#completion-execution-passed-isolated-validation-and-restoration) supersedes earlier pending/failure status without changing its historical meaning.

October 21, 2026. **User execution completed; normalization passed, validation stopped at missing receipt paths.** [Execution outcome and proposed completion](#execution-outcome-normalization-and-database-checks-passed-attachment-paths-stop) supersede the earlier manual-pending status. No repeat normalization is authorized. After choosing database recovery before resuming U3-A, the user explicitly instructed proceeding with preparation/testing of this guarded Phase 3-B helper and its manual handoff. Preparation performed no SQL, normalization, import, server startup or working change; the subsequent user-run batch did perform the single authorized copied normalization and candidate reads. Working restart/cutover and U3-A integration remain separate.

The index-only operation completed within its byte boundary and preserved the full pre-check logical baseline, but it left STATE_MOVED set. UUIDs still match. The strict table check stops on that marker before normal key/data checking, so repair acceptance remains incomplete. Resetting a flag by directly patching bytes, relaxing error classification, deleting logs/control files or recreating an empty permission table is excluded.

[MariaDB's Aria FAQ](https://mariadb.com/docs/server/server-usage/storage-engines/aria/aria-faq) describes offline zerofill as resetting recovery/transaction references and unused space while preserving logical records. Matching [ma_check.c](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_check.c) around3365-3395 applies it to index and data files and clears moved/non-movable state when recovery LSNs are reset. This is an operation on **mysql.tables_priv only**, not application journals or financial tables. Its physical write scope is broader than the approved index-only trial and requires a new decision. Logical preservation must be proved by the saved baseline and subsequent reads; documentation alone is not execution evidence.

## Proposed bounded manual batch

1. **Prepare/test before providing a command.** Audit exact installed zerofill options, matching source, diagnostic output, write scope and no-force/no-retry behavior. Use a new helper/probe/test set and marker, retaining every earlier file/marker/artifact. No combined recover/repair/force/extend/safe-recover option or SQL REPAIR fallback. No raw header patch or warning suppression.
2. **Preserve and establish the baseline.** Verify all14 existing database trees, both upload trees, original export, complete saved baseline and pinned source/tools. Use the immutable offline rebuilt treeAEFD94F9... as the fresh-copy source, not the working data or failed-validation tree. Start one fresh preflight copy with existing isolation/identity/path guards, read_only=1, events/automatic repairs off. Require two complete direct/index-independent grant scans and full application/object/permission snapshots matching the original saved baseline, then one graceful shutdown. No CHECK before this proof and no zerofill if it fails.
3. **One offline target-only normalization on another complete copy.** Clone the stopped preflight into a separate fresh offline destination and verify complete equality. Preserve target frm/MAI/MAD before-images outside its datadir. With all servers stopped, run exactly one pinned, bounded installed `aria_chk --zerofill` operation with reviewed contained datadir/log/tmp/control handling. No force, second operation, fallback or retry. Exact options must pass installed help/source review during preparation. A timeout affects only the new utility; retain the partial copy without starting it.
4. **Enforce the new physical boundary.** Only copied `mysql/tables_priv.MAI` and `mysql/tables_priv.MAD` may change. Its frm, every other database file, copied control/log files and all protected originals must remain byte-identical across the offline operation. No added/removed database files. Confirm the target metadata reaches the expected normalization state without flags indicating damage; keep before/after counts/hashes/output private. Unknown writes, warnings, errors, dropped/skipped/duplicate records or unexpected metadata stop the batch. This explicitly supersedes the target-MAD-unchanged rule **only for this new operation on its fresh disposable copy**.
5. **Fresh strict validation and separate application restore.** Preserve the normalized offline build, start a fresh validation clone, and require the exact original full logical baseline before/after all application/mysql checks, foreign-key, accounting/advance/correction/evidence and protected-file checks. Every warning/error remains a stop. If passed, gracefully stop and create a separate fresh application-export restore copy; replace only its disposable application schema from the original pinned export and require exact logical/evidence/permission comparisons, full checks and graceful stop. No further repair is allowed in these server roles.
6. **Retain evidence and stop.** Rehash all protected inputs, baseline, source/tools and completed intermediate artifacts. Leave XAMPP and both database ports stopped. Report scoped success only after full validation/restore; working cutover/login/acceptance, original handshake diagnosis, migration009 and U3-A integration remain separate.

The direct reader is not assumed transaction-safe by itself; quiescent isolated copies, writer exclusion and exact repeated fingerprints remain essential. No new account, password, grant, application setting, feature flag or migration changes are proposed. Preparation uses offline doubles/help only; actual runtime checks occur only in the user's separately identified one-shot manual batch. Any new issue returns to review without automatic escalation.

## Suggested authorization

> Authorize preparation/testing of Phase 3-B and its single guarded manual batch as proposed: preserve all14 existing database sources/uploads/export/baseline, use only new disposable copies, permit one target-only offline recovery-metadata normalization of mysql.tables_priv, allowing only its MAI/MAD physical bytes to change while requiring exact logical grant/application preservation. Continue to full validation and application restore only if all gates pass. No fallback, forced stop, working change/restart, migration or U3 integration.

The user subsequently selected this scope by instructing preparation of the helper and then saying to proceed. The wording above is retained as the bounded scope description, not a second approval request.

## Prepared implementation and verification

New files only for the operational/test implementation:

- `scripts/database_phase3b_normalize.ps1`: new switch/one-shot marker, all14 protected database trees and both upload trees, original export/baseline/tool/source pins, exact previous stopped-result gate, fresh capture from the immutable offline rebuilt source, two full baseline captures compared to the original, one offline normalization, physical/header gates, then conditional fresh validation and separate application restore. Prior helpers and attempt markers remain unchanged. Existing process/path/effective-variable guards and one authenticated graceful stop per server role are reused as pinned function definitions only.
- `scripts/database_phase3b_probe.php`: reuses the pinned complete direct reader/strict validation, plus an offline read-only header decoder. It checks the reviewed 10.4.32 layout, closed count, record/deleted counts, checksum/UUID fingerprints, cleared recovery/damage state and reset create transaction reference. No raw header mutation exists. Header outputs stay in the fresh normalization role's private logs.
- `scripts/test_database_phase3b_helpers.ps1` and `scripts/test_database_phase3b_probe.php`: synthetic guards, actual saved predecessor validation, immutable-baseline mismatch rejection, header/output/physical-boundary failures, and exact installed-option help compatibility. No operational main runs in these tests.

**262 preparation assertions passed against final helper/probe bytes:** 70 new PowerShell helper checks, 46 new PHP metadata checks, 59 existing direct-reader checks, 44 shared helper checks and 43 shared snapshot/integrity/evidence probe checks. PHP lint and PowerShell AST parsing also pass. This is preparation evidence; no successful live normalization, table validation or restore is claimed.

An additional native-call double caught PowerShell joining the before-header path and row-count option into one argument. Explicit expression grouping corrected it before handoff; the final helper checks verify separate before/after argument boundaries, row count, pinned before-image and rejection of unknown stages/tampered metadata. Earlier failed synthetic artifacts are retained; no real normalization/server operation occurred during that failure.

Private execution evidence: `.migration-private/phase3b-ps-tests-be0af22b07/results.json`, `.migration-private/phase3b-shared-tests-4a98da8b2e/results.json`, `.migration-private/phase3a-corrected-php-tests-98af4857b3`, and `.migration-private/phase2b-php-tests-b77bad0645`. Source/preservation/offline certificates use the `.migration-private/phase3b-preparation-*` prefix. The read-only inventory has no relevant processes or3306/3307 listeners. The complete existing protected-tree/hash inventory is rechecked during preparation and again before and after the manual batch; no protected source is opened by a database server during preparation.

### Audited normalization invocation

The entire argument set is tested with the installed binary in help mode first:

```text
aria_chk.exe --no-defaults --zerofill --require-control-file --skip-transaction-log
  --datadir=<fresh-normalization>/data --logdir=<fresh-normalization>/data
  --tmpdir=<fresh-normalization>/tmp <fresh-normalization>/data/mysql/tables_priv.MAI
```

This is a scope description, not a standalone command to run manually. The helper controls all paths, process checks, evidence, one-shot markers and sequencing. No recover/quick/force/keep-LSN/silent/ignore-control-file option is used. Help confirms control-file handling, disabled transaction logging and the contained directories. Matching [maria_chk.c](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/maria_chk.c#L158-L193) places its retry/fallback branches behind the force flag, which is absent. Matching [ma_check.c](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_check.c#L3148-L3399) defines the expected two zerofill progress lines and recovery-state reset; all other output, stderr or nonzero exit stops. Only target MAI/MAD bytes may differ, with unchanged lengths, schema/control/log/non-target bytes and no added/removed database files. Logical equality is subsequently required; header verification alone does not establish row preservation or integrity.

## Original manual handoff: consumed, do not rerun

The user has already executed this one-shot command. Retained as dated handoff evidence; **do not run it again**. Keep XAMPP **MySQL and Apache stopped**.

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase3b_normalize.ps1' -NormalizeIsolatedRecoveryMetadata
```

The batch performs its own fresh-copy preflight, single normalization and conditional validation/restore. Do not start another server or copy/change database files while it runs. Send only the terminal result afterward; retain private exports/logs locally. A stop result must be reviewed without rerunning the batch or attempting another repair. A success result establishes only the isolated candidate/restore scope; working startup/cutover/login/acceptance are later steps.

## Execution outcome: normalization and database checks passed; attachment paths stop

The user ran the batch at `phase2b-20261021-012006-549712af`; its saved result is stopped-for-review, not full acceptance. Capture and validation each completed an authenticated graceful shutdown with server/admin exit0. A fresh read-only process/port inventory is clear. No forced stop, second repair, working modification, migration, cutover or U3 integration ran.

Independent read-only review confirms:

- The new complete stable capture and fresh validation's pre-check snapshot exactly equal the original saved full application/schema/object/permission baseline. No comparison was reduced to totals or row counts.
- The single offline normalization completed exit0 with empty stderr and expected progress. Before/after manifests have the same2,767 files; **only `mysql/tables_priv.MAI` changed**, with the MAD, frm, control/log and all non-target bytes unchanged. The header moved/not-movable/not-zerofilled flags cleared; remaining changed40 is ordinary statistics state, with closed count0, row count1, unchanged checksum/UUID and create_trid0.
- **All65 application/mysql table/view checks passed:34 application and31 mysql.** The previous tables_priv failure is cleared in this isolated candidate.
- **All70 foreign-key orphan checks returned0**, and the accounting/advance/correction/evidence-association integrity reader returned an empty problem list.
- The inspection then stopped in the protected attachment-file check: `Missing or escaping preserved attachment.` The post-check complete-baseline comparison, completed attachment verification and application restore did not run. Full financial/recovery acceptance remains incomplete.

An offline parser of the original pinned application export, followed by the same file-hash/size/MIME checks used by the probe, identifies **three missing receipt filenames** in both the working uploads and the original preserved uploads. All four receipt paths use supported contained syntax. One of four receipt files is present at its recorded path. All four Board attachment references and all five nonempty external-email attachment references are present. The absence therefore predates this normalized candidate and occurs in the already-preserved file inventory; this review does not establish when or why the names became missing.

For each of the three missing receipt paths, there are preserved files under other filenames that match **every recorded SHA-256 hash, expected byte length and MIME type**. All three references have recorded hashes. Matching contents are recoverable without guessing or creating substitute images. No file has been copied/recreated, no database reference was edited, and no check was waived. Raw paths, receipt identities, hashes and candidate filenames stay private.

Private read-only evidence: `.migration-private/phase3b-result-baseline-review.json`, `phase3b-result-attachment-review.json`, `phase3b-result-attachment-candidates.json`, `phase3b-result-attachment-candidate-check.json`, `phase3b-result-offline.json` and `phase3b-result-preservation.json`. The current helper/probe and preceding pinned sources/configuration/U3-A application files still match the preparation certificate. Fresh independent hashing verifies all19 existing source/artifact/upload trees unchanged. New capture/normalization/validation trees remain preserved alongside earlier inputs; future work must protect all17 database trees plus both upload trees.

## Proposed completion: recover verified filenames in rehearsal storage, then finish validation/restore

**Subsequently authorized and prepared; manual execution pending.** The user explicitly approved preparing/testing this guarded continuation after the three exact-byte receipt candidates were identified. The detailed preparation and new command below supersede the result review's proposal-only status. This is the remaining Phase3-B validation/restore work, with no new index/zerofill attempt. No real receipt filename has yet been restored and no continuation server/SQL/import has run.

1. Prepare/test a new one-shot continuation helper/probe/marker, preserving the consumed batch and all17 existing database trees, both upload trees, original export/baselines, source/tool pins and the exact saved failure/graceful stops. Use the completed immutable normalized offline tree as the new database-copy source. No `aria_chk`, SQL REPAIR, migration, password/grant change or working restart is included.
2. Create one fresh rehearsal upload tree from the verified preserved uploads. Recover exactly the three recorded missing receipt filenames **only inside that new rehearsal tree**, using preserved source files whose bytes pass all recorded hash/size/MIME checks. Reject traversal, reparse points, existing destinations, disagreement among recorded hashes or any other file/record change. Preserve the originals and their existing missing-path state. Save a private before/after manifest proving exactly those3 additions and unchanged prior files.
3. Start one fresh isolated validation database copy under the existing authenticated native/process/path/read-only guards. Compare its full logical baseline to the original; verify the database-derived receipt metadata agrees with the offline candidate mapping; then perform the complete original table/foreign-key/accounting/evidence and before/after equality checks against the new pinned rehearsal upload tree. The new probe needs an explicitly guarded rehearsal-upload role; do not relax the existing preserved-root checks or edit the consumed probe.
4. Only if all validation passes, gracefully stop and create a separate fresh application-export restoration copy. Import only the original pinned application export there; repeat strict original full-baseline, complete file/evidence and system-permission comparisons, then gracefully stop. No repair fallback or additional normalization is allowed.
5. Reverify all protected inputs/new completed artifacts and stop with XAMPP left off. Report isolated recovery/restore success only if every check passes. Working data-directory replacement/startup/login and user acceptance require their later concrete cutover plan; U3-A remains unchanged.

The new scope adds three files in **fresh rehearsal upload storage**; the consumed batch only read the immutable preserved upload tree. The user has now authorized this bounded file-recovery/validation continuation. The existing working files, preserved files and original database rows remain immutable; only the separate disposable restore schema may be replaced from the pinned original export.

## Authorized completion prepared: verification and manual handoff

New files:

- `scripts/database_phase3b_complete.ps1`: pinned predecessor result/check/stop gates;19 protected trees (17 databases and2 original uploads); new one-shot completion marker; fresh rehearsal uploads with exactly3 verified file additions; fresh normalized-source database validation; conditional independent original-export restoration; exact logical/evidence comparisons and one graceful stop per server role. Earlier helpers/markers are unchanged. It contains no repair utility or SQL REPAIR invocation.
- `scripts/database_phase3b_complete_probe.php`: pinned private inputs, exact original-export receipt-metadata binding, contained/exclusive-create file recovery using all recorded hashes/size/MIME, a strictly guarded fresh rehearsal-upload role, and the same complete direct logical reader/table/foreign-key/accounting/evidence checks before/after inspection. It does not weaken the old preserved-root probe. File recovery and DB inspection are separate modes.
- `scripts/test_database_phase3b_complete_helpers.ps1` and `scripts/test_database_phase3b_complete_probe.php`: synthetic file recovery and PDO/native-argument doubles, metadata/path/hash/size/MIME/overwrite/source-change failures, exact file-delta boundaries, saved predecessor gates and final-report requirements.

**264 preparation assertions passed:**55 new PowerShell helper checks,63 new PHP synthetic file/metadata checks,59 reused direct-reader checks,44 shared PowerShell helper checks and43 shared snapshot/integrity/evidence checks. Both new PHP files pass lint; both PowerShell files pass AST parsing. Test files were synthetic and stayed inside new private workspace directories; no real receipt filename, database startup, SQL or import was executed. The synthetic PowerShell fixture's initial property-indexing error was corrected before the final55 passing checks; no operational main was invoked by that test failure.

Private evidence: `.migration-private/phase3d-php-tests-bb6c3ac5f5/results.json`, `phase3d-ps-tests-8f98aa6b21/results.json`, `phase3d-shared-tests-3d421475c6/results.json`, `phase3a-corrected-php-tests-e558e69360` and `phase2b-php-tests-4460c8869c`. Preparation source/preservation/offline certificates use `.migration-private/phase3b-completion-preparation-*`. The immutable `.migration-private/phase3b-completion-inputs.json` holds private reference paths/metadata, source/export/baseline/tool-related pins and protected-tree hashes, with its own SHA-256 pinned by the helper. Keep it out of public reviews/commits. No credential value, export content or receipt image is embedded in the public helper.

The read-only process/port inventory is clear. Fresh full hashing verifies all19 protected database/upload trees unchanged. Earlier source/configuration/U3-A files, original export/baseline and the new private manifest/probe pins are verified; the completion attempt marker remains absent. Before/after full tree verification is mandatory again in the manual batch. All recovery copies remain preserved; the working installation has not been restarted or changed. Passing preparation is not passing recovery/restore execution.

### New manual continuation: run once

Keep XAMPP **MySQL and Apache stopped**. In Administrator PowerShell run only this new command once:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase3b_complete.ps1' -CompleteIsolatedValidationRestore
```

It recovers the3 filenames only in fresh rehearsal uploads and attempts the remaining strict validation/restoration. It never repeats table repair or normalization. Send the terminal result afterward; do not rerun on a review stop. Leave XAMPP stopped. No working cutover/login, migration, grant/password change or U3-A work is included, even if the isolated restoration succeeds.

## Completion execution passed: isolated validation and restoration

The user ran the completion once. Attempt `phase2b-20261021-021041-339e0626` finished with `PHASE3B_VALIDATION_RESTORE_COMPLETE`; its saved result has no halt/shutdown issue and records exact application restoration matched, both candidates stopped and protected inputs unchanged.

- Fresh rehearsal uploads contain exactly3 additional recorded receipt filenames, recovered from preserved bytes verified against recorded hash/size/MIME. All50 existing files and existing directory entries remain unchanged; all53 resulting file hashes were independently rechecked during result review. No original preserved or working upload was changed.
- Both the normalized-source validation copy and separate original-export restoration copy passed65 table/view checks,70 zero-orphan foreign-key checks, empty accounting/advance/correction integrity findings, exact receipt-metadata binding and complete attachment checks.
- Each candidate's complete logical baseline before and after inspection equals the original pinned baseline. Application/schema/object/permission and evidence snapshots match across validation and restoration. The original pinned export imported successfully into the disposable restoration schema (exit0, no timeout or stderr).
- Both servers shut down gracefully (server and authenticated shutdown client exit0). Fresh read-only process/listener inventory finds no MySQL/Apache/recovery clients or listeners on3306/3307.
- The batch rehashed all17 protected database trees and2 original upload trees at completion and reports them unchanged. Independent result review checked saved logical reports, table/foreign-key outcomes, file deltas/hashes, import/shutdown results and export/baseline pins; it did not repeat the full19-tree rehash.

Private evidence remains under the completion attempt's `results.json`, root/role logs and shutdown reports. The aggregate independent-review certificate is `.migration-private/phase3b-completion-result-review.json`; its read-only inspection script is `.migration-private/phase3b_completion_review.py`. Keep exports, receipt paths/images and permission contents private.

This establishes successful isolated recovery and existing-application-export restoration. It does not establish working cutover, normal-port working startup/login, Atikha acceptance, a proven original handshake root cause, migration009 routine-registration resolution or U3-A integrated verification. No further repair, migration, working configuration/feature change or U3 application change ran during completion or review.

**Next:** prepare the concrete working-cutover/acceptance plan using validated recovery artifacts and verified rehearsal files, preserving the original installation and rollback path. Execution requires authorization before working replacement/startup. Keep XAMPP stopped; never rerun the consumed completion helper.
