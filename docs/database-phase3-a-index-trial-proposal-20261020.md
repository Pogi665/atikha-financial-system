# Phase 3-A proposal: preserve grants, trial one copied Aria index repair

October 20, 2026. **The user explicitly authorized this narrow Phase 3-A batch. The user ran its prepared manual helper once; execution stopped during the pre-repair scan-plan gate and the candidate shut down gracefully. No repair or import was attempted. The preparation account below is dated evidence; see the execution outcome at the end.** This follows the [Phase 2-B continuation findings](database-phase2-b-validation-restore-20261020.md), not a general database-repair authorization. Keep working XAMPP MySQL/Apache stopped.

## Evidence and narrow target

The user-run continuation completed clean standard checks for all 34 application tables and 22 mysql system tables, including mysql.proc. It stopped at mysql.tables_priv with `Wrong base information on indexpage at page: 2` and `Corrupt`. This is an index-structure error reported by SQL CHECK TABLE on the isolated copy, replacing the earlier open-count-warning-only diagnostic. The candidate was gracefully stopped; no restore or manual repair ran.

[mysql.tables_priv](https://mariadb.com/docs/server/reference/system-tables/the-mysql-database-tables/mysql-tables_priv-table) stores table-level database permissions. Preserve its current records and effective grants exactly; do not recreate it as empty, replace the mysql folder, reset users or infer that this error proves corruption of journal records. All 34 application table checks were OK, but financial/evidence invariants and restoration remain unrun. The original handshake failure and migration 009 registration error remain separate unresolved issues.

Read-only file hashes show tables_priv.frm and tables_priv.MAD unchanged across the original preservation, Phase 2-A, first Phase 2-B and stopped continuation copies. The pre-check tables_priv.MAI hash is also identical across the first three; it changed during the failed continuation check. These observations support a trial confined to the target index; they do not prove recoverability or record-level integrity by themselves.

## Approved scope

One guarded manual batch, prepared/tested before the user runs it, consisting of private logical baseline capture, one offline index-only trial on a fresh copy, and validation/application restore comparison if every preceding gate passes. Three serial isolated startup roles may be needed (capture, repaired validation, application restore); no parallel server or working restart. Preserve all partial results. No migration, production code/configuration/flags, financial workflow postings or U3-A tests.

1. **Revalidate preservation and offline state.** Pin the eight prior database trees plus the latest cleanly stopped continuation copy as a ninth protected tree, both upload trees, original export, tool/source hashes and previous stopped outcomes. The latest copy's recorded hash is `55C7EF745BAF543B6E38615126BD0D7FF3CC69E2781B5755B5F880E91C881FB8`, 2,767 files. Refuse occupied 3306/3307, unexpected processes/clients, links, drift, reused destinations/markers and insufficient space. No original is ever launched or repaired.
2. **Capture a logical baseline before repair.** Make a new complete disposable capture copy from the first cleanly stopped Phase 2-B source (`4B3AA2146106026AC60F8368B52AACCE251570E44694232ABA308A4426B385E4`). Its tables_priv index matches the preserved pre-check bytes and its ordinary startup was demonstrated by the continuation. Avoid launching the latest copy whose failed CHECK may have marked the table crashed. Use the established native/SQL/path/loopback3307 isolation gates, read_only=ON, normal recovery only, no automatic repair/events/providers and bounded clients. **No new CHECK TABLE before baseline capture.** Persist all application schema/data/object fingerprints and row counts/hashes for permission tables/current grants, especially tables_priv; keep raw sensitive rows/metadata private if captured. Fully enumerate table rows using an inspected scan path, not an assumed usable damaged index. If full baseline capture, stable repeated reads, identity or graceful shutdown fails, stop **without repair**. The earlier probe reached checks after its initial in-memory snapshot, suggesting reads may work; this is not a saved baseline or a guarantee.
3. **Make a fresh complete offline trial copy.** After the capture server is gracefully stopped and ports clear, hash and preserve the capture tree. Make a separate complete repair destination from it, verify equality, and additionally preserve the target frm/MAI/MAD before-images outside the repair datadir. The capture copy remains untouched. Record one target-specific attempt marker.
4. **One index-only repair trial, while every server is stopped.** Candidate method: installed `aria_chk` with `--no-defaults --recover --quick`, exactly one quick flag, absolute target `mysql/tables_priv.MAI`, required matching copied control context and attempt-contained temporary/log paths. Validate the exact installed options/help and write behavior during helper preparation. [MariaDB documents](https://mariadb.com/docs/server/clients-and-utilities/aria-clients-and-utilities/aria_chk) one quick repair as retaining the data file; a second quick flag can change that behavior. Reject that second flag, force, zerofill, salvage/extend-recovery, safe-recover fallback, ignore-control-file, grant bypass and all other table targets. This is one trial, not an automatic escalation sequence. Bound the utility; a timeout leaves the disposable partial result for review. Require unchanged target MAD/frm bytes and no unexpected non-target database-file changes before any server start. A documented expected utility-side control/state write must be reviewed explicitly during preparation; do not silently allow it after execution. Any dropped/duplicate/skipped-row report, data-file change, nonzero result, unexplained warning or scope drift stops without another repair attempt.
5. **Verify logical preservation and full integrity on the trial.** Start only the trial copy once under the established isolation guards. Require exact pre/post tables_priv row count/hash and other privilege/account/current-grant fingerprints, plus unchanged application rows/schema/objects. Require target CHECK TABLE and all remaining application/system checks, then existing exact-centavo journal, foreign-key, advance/correction/evidence and protected-file validations. Keep strict warning/error rejection. No GRANT/REVOKE/CREATE USER, password reset, application login or financial posting. Gracefully stop; a new error ends the batch.
6. **Finish the original application restore rehearsal only if all gates pass.** Create one additional fresh disposable copy from the verified repaired stopped state; apply the same guarded replacement of **only its application schema** from the original pinned Phase 2-A export, without force/SQL rewriting/migrations. Require source/restored application data/schema/objects/evidence and copied permission fingerprints to match, full checks and graceful stop. No original/working folder promotion.
7. **Retain evidence and stop.** Rehash every protected source, uploads, export and pinned inputs; preserve pre/post trial/capture/restore manifests, private counts/hashes, utility output and process/shutdown evidence. Report scoped success only when all gates pass. Leave XAMPP/3306/3307 stopped. Working cutover, clean working restart/login smoke and U3-A integration require their own later decisions.

## Preparation and failure requirements

Before providing an operational command, implement the new helper and focused synthetic gates, validate exact installed utility options in help mode, verify process/path controls and write boundaries, and retain all earlier helpers/markers unchanged. Use separate durable markers for capture, repair and each launch/import/shutdown. Existing time bounds remain: readiness60s, ordinary SQL10s, bounded probe180s, import120s, graceful-request15s/exit60s; propose a reviewed bounded offline repair duration during preparation. Cancellation may affect only the newly launched client/repair utility, never a server. A partial repair is never retried automatically.

The user subsequently authorized this scope explicitly. This document alone does not authorize broader work. A failure, record/grant mismatch, required data rewrite, unavailable baseline or broader damage must return to review. Do not accept successful utility exit alone as successful recovery. Never change a warning/error classifier merely to reach the restore step.

## Authorization wording presented before approval

> Authorize Phase 3-A only as proposed: prepare/test the new guarded manual batch, preserve all nine existing database sources/uploads/export, capture a private exact logical baseline first, and attempt one index-only repair of mysql.tables_priv only in a fresh complete disposable copy with all servers stopped. Continue to full isolated validation and application restore comparison only if bytes, row counts and grant fingerprints are preserved. Stop on any new issue, with no fallback repair, force-stop, working changes, migration or cutover.

## Authorized preparation and manual handoff

The user answered "yes i authorize" to the proposed Phase 3-A trial. This supersedes the earlier pending-approval handoff without extending the scope to the working installation. New files are `scripts/database_phase3a_index_trial.ps1`, `scripts/database_phase3a_probe.php` and their two focused `test_database_phase3a_*` files. Earlier recovery helpers/probe/source manifest, attempt markers and protected inputs are retained unchanged. No application, configuration, flag, migration or U3 test change belongs to this preparation.

The new helper imports only pinned function definitions from the successful corrected Phase 2-A helper and original Phase 2-B helper, never their operational mains. New batch folders deliberately retain the `phase2b-<timestamp>-<random>` naming accepted by the existing strict restore guard; their distinct `phase3a-index-trial-attempt.json` marker and `capture`/`index-trial` roles identify this trial. The source is the first cleanly stopped Phase 2-B tree (4B3AA214...), while the later failed-check tree (55C7EF74...) is protected only.

Before CHECK or repair, the new PHP probe requires the installed PRIMARY/Grantor index inventory, explicitly ignores both indexes for SELECT, and requires EXPLAIN to show a full scan with no selected index. It compares the complete row multiset/count with the ordinary permission snapshot, immediately persists the first private baseline, then requires a second identical complete application/object/permission snapshot. Unavailable, unstable or unexpectedly indexed reads stop before any repair. Capture is gracefully stopped and preserved. Only a separate complete offline clone can become the repair target.

### Exact offline operation and write boundary

The installed `aria_chk` binary and matching MariaDB 10.4.32 primary source were inspected during preparation. The bounded operation uses precisely one `--recover` and one `--quick`, with `--no-defaults --require-control-file --skip-transaction-log`, copied datadir/logdir, attempt-local tmpdir, and the absolute copied `mysql/tables_priv.MAI` target. Matching source references: [maria_chk.c](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/maria_chk.c), [ma_check.c](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_check.c), [ma_control_file.c](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_control_file.c). Installed full-option help must pass before the repair and must change no database bytes. The repair has a 60-second utility deadline, no force flag, second quick flag, salvage, fallback or retry.

The complete before/after offline tree comparison permits **only tables_priv.MAI to change**. All MAD/frm/control/log and other database files must be byte-identical; added/removed files also fail. No control-file write exception is permitted. Exit success alone is insufficient: diagnostics must match the reviewed recovery shape, report exactly the captured record count, and contain no error/warning/duplicate/skipped/lost/retry indication. A timeout may terminate only the newly launched utility; its partial disposable result is retained and not started or retried.

After this gate, a fresh validation clone is started, leaving the offline repaired tree intact. The new probe requires the original capture baseline before and after all unchanged strict table, orphan, accounting/advance/correction/evidence checks. A separate restore clone then receives only the original application export through the existing guarded import and must pass the same baseline/checks plus exact source/restored comparisons. Each server has one guarded graceful shutdown; failure stops the batch. There is no working promotion, working login, migration or U3 integration.

### Preparation evidence and limits

**173 preparation assertions passed:**

- 58 new PowerShell/helper assertions: exact saved corruption/graceful-stop gates, target/path restrictions (including a line-break case caught during preparation), single-quick operation, installed full-option help, output/record-count rejection, complete index-only byte-delta enforcement, and baseline completeness. Private evidence: `.migration-private/phase3a-helper-tests-ee5f7286ae94/results.json`.
- 28 new offline PHP assertions: forced-scan SQL/index inventory, full-scan EXPLAIN requirement, row multiset/null/binary handling, baseline and role guards, and unchanged strict warning rejection. Private evidence: `.migration-private/phase3a-probe-tests-d158213ba7/results.json`.
- 44 unchanged shared helper assertions freshly passed, including isolated identity/path/import guards and harmless bounded child handling. Private evidence: `.migration-private/phase3a-shared-tests-e37b2593c7/results.json`.
- 43 unchanged probe assertions freshly passed. Private evidence: `.migration-private/phase2b-php-tests-c3992e5ac9/results.json`.

PHP syntax and PowerShell parsing pass. Only function definitions, installed help, synthetic fixtures and read-only local evidence were used; no operational main, server startup, database connection, CHECK TABLE, repair or import ran. No execution-policy bypass/change was used. Private final source, preservation and offline-inventory certificates are retained under `.migration-private/phase3a-preparation-*`. These checks establish helper preparation, not repair success or financial/database acceptance.

Fresh read-only full-tree hashing verified all nine database trees and both upload trees unchanged, with the original export still matching its pinned SHA-256 and no Phase 3-A attempt marker present. A separate Windows inventory found zero relevant processes and zero listeners on 3306/3307. Private certificates: `.migration-private/phase3a-preparation-preservation.json`, `phase3a-preparation-offline.json` and `phase3a-preparation-source.json`. Final helper SHA-256: `560C8EC2BE21E8DCA399435D15E6DFC46044716A5C95BF74F7E23A72D92749E2`; probe SHA-256: `B2AF34FAA83A80391044D29720847143B411653197346ED29CC8E70D1AC0081E`. Earlier helper/probe/manifest pins still match. Git diff/new-file whitespace checks pass; existing LF/CRLF notices on unrelated U3 files remain informational.

### Manual command - run once

Keep XAMPP MySQL and Apache stopped. In Administrator PowerShell run once:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase3a_index_trial.ps1' -RunIsolatedIndexTrial
```

Keep the window open while running, then send the terminal result. Success is `PHASE3A_INDEX_TRIAL_COMPLETE`; any error or `PHASE3A_STOPPED_FOR_REVIEW` means stop and report, with no rerun, fallback, forced stop or XAMPP startup. If a candidate remains running, do not touch its files. Keep all private exports, permission baselines, logs and receipts local. Operational execution and its result remain pending. Original handshake diagnosis, migration 009 error 1304, working restart/cutover/acceptance and U3-A integration remain unresolved/separate.

## Execution outcome - capture plan gate stopped before repair

The user ran the manual command once. The new private attempt is `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-192336-6a4bea4a`. Saved `results.json` at19:25:17 +08 records Passed=false, CaptureCompleted=false, RepairAttempted=false, no validation/restore, no shutdown issue, all servers stopped, and protected nine database trees/uploads/export unchanged. No working change, forced stop, migration, cutover or U3 test occurred. Prepared recovery source hashes still match their manifest.

The capture probe exited1 without timeout. Its private report gives the precise failure: **"Permission baseline does not prove a full table scan."** Source inspection places this at `p3a_scan_plan()`, after SHOW INDEX and EXPLAIN but before the explicit SELECT row scan, complete application/permission snapshot, CHECK TABLE or offline repair. The gate requires exactly one EXPLAIN row, type ALL and an explicitly null selected key. The actual EXPLAIN rows were not persisted before the exception. This is a diagnostic-evidence omission in the helper; the saved report does not establish which predicate failed or whether the actual plan was unsafe, optimized differently or otherwise unexpected. Do not guess that the table is empty, relax the gate or infer new financial damage from this result.

There is no `baseline-first.json`, successful logical baseline or index-trial/validation/restore directory. The durable attempt marker remains consumed. No repair command ran, and no pre/post-repair row/grant preservation claim is possible.

Server log: ready19:24:03 on127.0.0.1:3307; authenticated normal shutdown19:24:06; shutdown complete19:24:07. Saved server/admin exit0, client timeout=false. Fresh read-only Windows inventory independently finds zero relevant servers/clients/checkers and zero3306/3307 listeners; private certificate `.migration-private/phase3a-result-offline.json`. No server or SQL was launched during this review.

### Next diagnostic decision - no operational retry authorized

The next proposed step is one fresh-copy diagnostic-only startup under the existing native/SQL/path/loopback guards, persisting the exact fixed scan SQL, SHOW INDEX metadata and complete EXPLAIN result before evaluating the gate, then one graceful shutdown and preservation verification. It would not scan application financial rows, perform CHECK TABLE, repair or import, or change the ALL/no-index requirement. Its evidence must be reviewed before selecting any correction or renewed repair batch. Keep all original copies and the latest stopped capture immutable; do not reuse the consumed marker or rerun the repair helper.

This new startup is pending the user's decision under the approved stop-on-new-failure boundary. No diagnostic continuation helper/startup has been implemented/executed in this result review. Working XAMPP remains stopped. Phase 3-A and the full Phase 2-B integrity/restore gate remain incomplete; original handshake cause, migration009 error1304, working acceptance and U3-A integration remain unresolved.

Fresh independent full-tree hashing in this review confirms all nine prior database trees, both upload trees and the original export still match the preparation pins. The stopped capture also matches its saved graceful-stop manifest: 2,767 files, tree SHA-256 `B850F25EFFA63989D09898835FFF1CF62312BEC319472750BE04CA5230ED3010`. Retain it as a tenth protected database tree in any future approved diagnostic. Private certificate: `.migration-private/phase3a-result-preservation.json`. This read-only review performed no new startup, SQL, repair or import; documentation-only changes record the outcome.

## Authorized diagnostic-only continuation - prepared, execution pending

After the reported capture failure, the user explicitly answered "yes, i authorize" to one diagnostic-only startup on a fresh disposable copy, saving the actual query plan and then shutting down gracefully. This authorization does not permit a repair retry, weakening the ALL/no-index gate, CHECK TABLE, import, working restart or U3 integration.

Prepared four new files: `scripts/database_phase3a_plan_diagnostic.ps1`, `scripts/database_phase3a_plan_probe.php`, `scripts/test_database_phase3a_plan_helpers.ps1` and `scripts/test_database_phase3a_plan_probe.php`. All earlier recovery helpers/probes/tests/manifest remain unchanged. The new helper uses pinned function definitions from the earlier corrected startup/Phase 2-B helpers; it never executes their operational mains or invokes their repair/import/financial inspection paths. Its unique marker is `evidence/phase3a-plan-diagnostic-attempt.json`; prior consumed markers remain untouched. New folders use `phase3a-plan-<timestamp>-<random>/diagnostic` with an exact role guard before launch and within the probe.

The first cleanly stopped Phase 2-B tree (4B3AA214...) supplies the fresh complete candidate. All ten existing database trees, both uploads and the original export are pinned before/after. The latest stopped capture (B850F25E..., 2,767 files) is protected, not reused/launched. Existing native held-image/creation identity, sole process/listener ownership, authenticated SQL identity and effective path/settings checks remain mandatory. The new candidate uses only127.0.0.1:3307, normal recovery, read_only=1, automatic repairs/events/external calls disabled, and separate local configuration/log/tmp paths. It performs one startup and one guarded graceful shutdown; no automatic retry or force-stop.

### What the diagnostic captures

After identity, the probe performs fixed `SHOW INDEX FROM mysql.tables_priv`, immediately saves `indexes.json`, constructs the exact already-reviewed ignore-both-indexes scan SQL, executes **EXPLAIN only**, and immediately saves the complete SQL/plan as `query-plan.json`. It does not execute that SELECT, fetch permission rows or read application financial rows. The unchanged original classifier is then evaluated only to annotate the private diagnostic report with its acceptance/rejection and reason. A rejected or empty plan is still saved as evidence; it cannot authorize a row scan or repair. An unsupported index set, failed SQL/evidence write or new server warning stops without further action while retaining earlier observations. `PHASE3A_PLAN_DIAGNOSTIC_COMPLETE` means metadata was captured and shutdown/preservation passed, not that the baseline gate passed or the database is repaired.

The PHP client is bounded to45 seconds, individual diagnostic SQL to10 seconds, readiness60 seconds and existing graceful-request15s/exit60s. A client timeout affects only the newly launched client, never the server. No schema import, migration, credentials/grants changes, financial posting, working configuration/flag edit or application login is involved. Normal server recovery writes remain confined to the disposable copy.

### Preparation evidence

**109 preparation assertions passed against the final new source:**

- 26 offline PHP assertions, PDO doubles only: exact metadata/EXPLAIN-only sequence; evidence saved before interpretation; complete rejected/empty/successful plans retained; unchanged classifier rejection; no row SELECT; index/evidence/EXPLAIN failures stop; unsafe roles rejected. Private evidence: `.migration-private/phase3a-plan-php-tests-9876689b6b/results.json`.
- 39 PowerShell assertions: actual prior failure/graceful-stop evidence accepted; changed protection/progression/outcomes rejected; exact fresh role/traversal/newline checks; complete diagnostic reports with either original classifier verdict accepted; forbidden actions/incomplete evidence rejected; no repair/import/financial inspection calls. Private evidence: `.migration-private/phase3a-plan-ps-tests-bf996d2ee7/results.json`.
- 44 shared helper assertions freshly passed, including installed server/client help and bounded harmless child behavior. Private evidence: `.migration-private/phase3a-plan-shared-tests-679506c92e/results.json`.

New PHP syntax and PowerShell parsing pass. All previous recovery source pins remain exact. No operational helper main, server startup, real database connection, CHECK, repair or import ran in preparation; no execution-policy changes/bypasses were used. Source hashes and private preservation/offline inventory certificates are `.migration-private/phase3a-plan-preparation-*`. Final new helper SHA-256 `DC06F58BE3285B8E6B6E3473D86B8F58A8E24B51213514EC4F07D99DAE6D793A`; new probe SHA-256 `DEFDA95ABC052E9CC502E529B7CD78028D3BB5A587F354AF925CB60A4E3B6629`.

### New manual command - diagnostic only

Keep XAMPP MySQL and Apache stopped. In Administrator PowerShell run **once**:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase3a_plan_diagnostic.ps1' -CaptureIsolatedPlan
```

Keep the window open and send the terminal result after completion. Success is `PHASE3A_PLAN_DIAGNOSTIC_COMPLETE`; an error or `PHASE3A_PLAN_DIAGNOSTIC_STOPPED_FOR_REVIEW` requires reporting without rerun, force-stop, repair or XAMPP startup. If a candidate remains running, do not touch its files. Keep private exports/logs/permission metadata local. Execution is pending; the next decision follows inspection of the actual plan, not an automatic repair continuation. Full integrity/restore acceptance, original handshake cause, migration009 error1304 and U3-A integration remain pending.

Final read-only preparation verification confirms all ten protected database trees and both upload trees unchanged (12 tree fingerprints), the original export unchanged, and no new diagnostic marker present. Windows inventory finds zero relevant processes and zero3306/3307 listeners. Certificates: `.migration-private/phase3a-plan-preparation-preservation.json`, `phase3a-plan-preparation-offline.json`, `phase3a-plan-preparation-source.json`. Git diff and new-file whitespace checks pass. These are preparation/preservation observations; no operational diagnostic result has yet been obtained.

## Diagnostic execution complete - actual system plan identified

The user ran the diagnostic once. Private attempt: `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase3a-plan-20261020-195003-f2761503`. Saved `results.json` at19:52:09 +08 reports Passed=true, DiagnosticCompleted=true, no halt/shutdown issue, protected ten database trees/uploads/export unchanged, and all servers stopped. It also records no CHECK/repair/import/financial row query/working changes/force/migration/cutover/U3. New diagnostic and earlier recovery source hashes remain unchanged.

### Observed plan and what it establishes

The exact fixed `SELECT * FROM mysql.tables_priv IGNORE INDEX (PRIMARY,Grantor)` was explained, not executed as a row query. Saved `query-plan.json` and `plan-diagnostic.json` contain one row: id1, select_type SIMPLE, table tables_priv, **type system**, possible_keys/key/key_len/ref all null, rows1 and empty Extra. The index inventory is the expected four-part PRIMARY (Host, Db, User, Table_name) plus Grantor. The unchanged original ALL/no-index classifier rejects this plan, so the diagnostic reproduced the classifier mismatch precisely. It is not an SQL query failure, timeout, unexpected index set or new corruption report.

[MariaDB EXPLAIN documentation](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain) defines system as the zero/one-row optimization. Matching [MariaDB10.4.32 sql_select.cc](https://github.com/MariaDB/server/blob/mariadb-10.4.32/sql/sql_select.cc) selects this path from exact engine row statistics and reads the constant table during optimization (around4830-4840 and4929-4950). Thus the immediate capture stop is explained by the helper's ALL-only assumption. The reported rows1 is **not a privately captured row count/hash baseline** and must not be promoted to one. The original indexpage2/Corrupt finding is not repaired or invalidated by a successful EXPLAIN.

Do not simply whitelist system/key-null and claim that all data-file rows were scanned. The matching server's `join_read_system()` calls `ha_read_first_row()`; [handler.cc](https://github.com/MariaDB/server/blob/mariadb-10.4.32/sql/handler.cc) around2917-2948 may choose a data scan or primary-key first-row path depending on deleted-row statistics. A null EXPLAIN key alone therefore is insufficient proof that a damaged index was bypassed or that full enumeration reached end-of-data. No reader/guard source has been changed in this review.

### Shutdown and independent preservation review

Candidate ready19:50:31 on127.0.0.1:3307; authenticated normal shutdown19:50:34; shutdown complete19:50:35. Server/admin exit0, timeout=false. Fresh independent Windows inventory finds zero relevant server/client/checker processes and zero3306/3307 listeners. Certificate: `.migration-private/phase3a-plan-result-offline.json`.

Independent full-tree hashing confirms all ten prior database trees and both upload trees unchanged, plus the new stopped diagnostic copy matches its saved after-manifest: 2,767 files, SHA-256 `B850F25EFFA63989D09898835FFF1CF62312BEC319472750BE04CA5230ED3010`. Its bytes happen to match the earlier stopped capture; retain both separate roots and markers. The new copy becomes an eleventh protected database tree for any later approved work. The original export still matches its pinned SHA-256. Certificate: `.migration-private/phase3a-plan-result-preservation.json` (13 database/upload roots total). No startup/SQL/check/repair/import was run during this result review; documentation changes only.

### Recommended next scope - preparation requires a new decision

Prepare/test a revised baseline reader that proves complete index-independent row enumeration rather than inferring it from the ALL label; preserve actual metadata/access-path evidence, row multiplicity/null/binary hashes, repeated stable captures and comparison with the complete application/permission snapshot. Audit the exact installed-version read path and fail closed on unsupported access, incomplete enumeration, new warnings or count/hash drift. Include zero/one/multiple-row and index/error negative coverage. Preserve the old helpers/probes/markers and all eleven existing database trees/uploads/export.

Only a complete verified baseline could permit the same already-described single-quick offline target-index trial on a separate fresh complete copy, followed by strict index-only bytes/record/grant checks and conditional full validation/application restore. No broader repair, bypass, financial mutation, working restart, migration or U3 integration is proposed. No revised repair helper, guard relaxation, new startup or operational repair command has been implemented or executed in this review. The latest authorization was diagnostic-only and explicitly excluded a repair continuation; obtain the user's decision for this corrected trial preparation/batch. Keep XAMPP stopped and do not rerun completed commands. The initial handshake root cause and migration009 error1304 remain unresolved.

## Corrected Phase 3-A trial authorized and prepared

The user explicitly answered "yes, i authorize" to preparing the corrected baseline reader and the same guarded Phase 3-A trial. This supersedes the diagnostic-only pending-decision handoff. Four new files are prepared: `scripts/database_phase3a_corrected_probe.php`, `scripts/database_phase3a_corrected_trial.ps1` and their two `test_database_phase3a_corrected_*` tests. All prior recovery helpers/probes/tests/manifest and consumed markers remain byte-identical. No application, configuration, feature, migration or U3 work is included.

### Corrected proof of complete row enumeration

The new reader does **not** whitelist system or rely on an optimizer estimate. It records the actual index inventory/EXPLAIN for context, verifies the eight expected visible grant-table columns, and uses fixed `HANDLER mysql.tables_priv OPEN AS p3c_grant_rows`, followed by `HANDLER p3c_grant_rows READ FIRST LIMIT 128` and index-unnamed `READ NEXT LIMIT 128` until an explicit empty result. A short batch does not terminate enumeration. It always closes the handle; read/warning/evidence/close errors or resource ceilings prevent a baseline. Limits are100,000 rows/800 batches and the existing180-second client deadline, with20-second SQL bounds. Values are hashed with the unchanged binary/null/duplicate-preserving framing; raw grants are never written to scan evidence.

The operation is supported by the matched [MariaDB10.4.32 Aria engine flags](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ha_maria.cc) around948-957. Inspection of [sql_handler.cc](https://github.com/MariaDB/server/blob/mariadb-10.4.32/sql/sql_handler.cc) around895-913 confirms that FIRST/NEXT without a key name use the engine's sequential row interface, rather than index-first/next. The general documentation's system-table limitation is not treated as proof of support: matched-version source rejects INFORMATION_SCHEMA/schema tables and unsupported engine flags; the preserved mysql.tables_priv is an ordinary Aria table. [sql_parse.cc](https://github.com/MariaDB/server/blob/mariadb-10.4.32/sql/sql_parse.cc) around5378-5401 preserves SELECT authorization at OPEN. No grant bypass, engine change, copied table recreation or data rewrite is used.

Before/after session counters additionally require no increase in Handler_read_first/key/last/next/prev/rnd, no counter reset/missing/invalid inventory, and enough Handler_read_rnd_next calls for all returned rows plus end-of-data. Actual counter evidence is saved before judging it. Explicit EOF and this proof are both mandatory. Unsupported runtime behavior stops before repair; it never selects a fallback reader.

HANDLER reads are not assumed to provide transactional snapshot consistency by themselves. The candidate has no application/other client writers, read_only=1, events/automatic repairs off; two complete captures must still match exactly. Every direct grant-table count/hash is compared with the full ordinary permission/application snapshot, and the first complete baseline is saved immediately. The same direct proof and original baseline equality are required before/after repaired validation and after the original export restore. SQL EXPLAIN remains contextual evidence only; the original ALL-only classifier and earlier source are unchanged.

### Trial scope and preparation checks

The new manual helper protects all eleven existing database trees, both uploads and original export, including the two separate B850F25E... stopped roots. The fresh capture source remains the first cleanly stopped Phase 2-B tree4B3AA214.... It validates the saved diagnostic/no-prior-repair outcomes, uses a new durable `phase3a-corrected-index-trial-attempt.json` marker and new timestamp/random destinations, and retains every previous marker. Existing strict native/SQL/effective-path/copy/shutdown/import gates remain. Only complete capture permits one offline single-quick target-index trial on its separate complete clone. Unchanged MAD/frm/control/all other bytes, same records/grants, full strict checks/invariants/evidence and separate application-export restore comparison remain required. No force, fallback, repeated repair, working restart/cutover or migration is added.

**223 preparation assertions passed against the final new files:**

- 59 offline PHP/PDO-double checks: empty/one/duplicate/multiple batches, binary/null fingerprints, real system-plan shape retained, no row SELECT, explicit EOF even after a partial batch, page ceiling, repeated-baseline drift, index/counter/reset/overflow rejection, column/row-shape/warning/read/evidence/close errors and handle cleanup, and rejection of optimizer-only/missing physical proof. Private evidence `.migration-private/phase3a-corrected-php-tests-249f6ebeee/results.json`.
- 77 PowerShell/helper checks: retained exact original corruption outcome, installed full repair-option help and one-quick argument restrictions, path/byte-delta/diagnostic/count/output gates, new EOF/counter baseline requirements and actual successful diagnostic/no-prior-repair evidence with negative cases. Private evidence `.migration-private/phase3a-corrected-ps-tests-4f6d76faf3/results.json`.
- 44 shared helper checks freshly passed, including installed server/client help and harmless bounded-child behavior: `.migration-private/phase3a-corrected-shared-tests-02fef4b734/results.json`.
- 43 original PHP probe checks freshly passed: `.migration-private/phase2b-php-tests-aca610e502/results.json`.

PHP syntax, PowerShell parsing, probe self-pin and whitespace checks pass. Earlier recovery source hashes still match. No operational main, database startup/connection, real HANDLER statement, CHECK TABLE, repair or import ran during preparation. Runtime direct-reader compatibility and the full database trial remain unverified until the user's manual capture; any mismatch blocks repair. No execution-policy changes/bypasses were used. Private source, offline-inventory and preservation certificates are `.migration-private/phase3a-corrected-preparation-*`.

Final helper SHA-256 `1F13AA983FD17BADD1D2ADE0AEA3993E5C50E213C2558883AA7B3C95F1964596`; probe SHA-256 `DE401BE3AFA080707390D41F1040433FD78C7F5FF720CE0E59C56D3EDCF122B4`.

### Corrected manual command - run once

Keep XAMPP MySQL and Apache stopped. In Administrator PowerShell run once:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase3a_corrected_trial.ps1' -RunCorrectedIndexTrial
```

Keep the window open and send the terminal result. Success is `PHASE3A_CORRECTED_INDEX_TRIAL_COMPLETE`; any error or `PHASE3A_CORRECTED_STOPPED_FOR_REVIEW` requires stopping without rerun, fallback, force-stop or XAMPP startup. If a candidate remains running, do not touch its files. Keep private baseline/grants/exports/logs local. User execution is pending; no repair success, integrity/restore completion, working acceptance, handshake root cause or migration009 repair is claimed. U3-A integration remains pending.

Final independent preparation hashing verified all eleven protected database trees and both upload trees unchanged (13 fingerprints), the original export unchanged, and the corrected attempt marker absent. Fresh read-only process/listener inventory found no relevant servers/clients/checkers or3306/3307 listeners. Certificates: `.migration-private/phase3a-corrected-preparation-preservation.json`, `phase3a-corrected-preparation-offline.json`, `phase3a-corrected-preparation-source.json`. Earlier source hashes and final probe self-pin remain exact; git diff/new-file whitespace checks pass. No operational trial has run during preparation.

## Corrected trial outcome - index rebuild ran; moved-state validation stop

October 21, 2026. The user ran the corrected command once. Private attempt: `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261021-001938-c6362668`. Saved00:26:11 +08 results record a complete stable capture, one offline repair attempt, passed index-only byte scope, then failed validation and no restore. The prior eleven database trees/uploads/export and completed capture/offline artifacts are unchanged; all servers stopped, no shutdown issue, no working changes/force/migration/cutover/U3.

### Completed scope and exact stop

The new direct reader succeeded at runtime: one grant row, explicit EOF, zero index/positioned-access counter increases, sufficient sequential reads, and two identical complete application/object/permission captures. The isolated offline utility ran once with sort, reported one record, fixed both indexes and exited0 without timeout or stderr. Independent before/after tree comparison confirms **only mysql/tables_priv.MAI changed**; target MAD/frm and all control/non-target database bytes are unchanged. The rebuilt index hash is `12940BF09BF0DD7D4F28DE36B6F0D647EE9BFCDFC5B952FC7113FF5FE0271D6E`.

The validation clone's full pre-check application/schema/object/permission snapshot and direct grant count/hash match the original capture exactly. Independently comparing saved capture baseline against validation's baseline-first.json confirms that equality, not merely the report boolean. The original baseline file SHA-256 is `005F5D9D0C484F9C01E106380288BCB62C62D36C094971553AFB7A86F6145714`. No raw grant values are published. Certificate `.migration-private/phase3a-corrected-result-baseline-review.json` also verifies target data/schema bytes still match original pins and no restore folder exists.

Serial checks returned clean OK for34 application and22 mysql tables. The57th result, mysql.tables_priv, now reports **a moved-system/zerofill-required error plus Corrupt**, instead of the earlier wrong-indexpage error. The original probe wording "no repair attempted" refers to its inspection-only function; the overall batch **did perform the separately authorized offline repair before this inspection**. Financial/orphan/advance/correction/evidence checks, remaining system-table checks, post-check full equality and restoration did not run. No successful full integrity or working acceptance claim follows from the pre-check equality.

### Read-only moved-state diagnosis

Matched [ha_maria.cc](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ha_maria.cc) around1202-1206 emits this error when the table carries STATE_MOVED; it returns before the normal key/data checks. Therefore the new result does not independently certify that all index pages are good. Read-only header decoding used the exact10.4.32 layouts/constants from maria_def.h, ma_open.c, myisampack.h and ma_control_file.c, with control-file CRCs and row count cross-checked against the direct scan. No header bytes were edited.

Observed flags: before offline repair432 (moved=false); offline result936 (moved=true); stopped validation936 (moved=true). UUIDs match the copied control file at every point; no standard crashed flags are set. create_trid changed2 to0 during the utility, then227 in validation, within the respective control maxima223/257. Thus STATE_MOVED was introduced/persisted by the offline operation; the error is not evidence that someone actually substituted a different control file. The exact tool-internal origin of that flag is not fully reproduced, and this does not diagnose the original working handshake or migration009 failure. Certificate `.migration-private/phase3a-corrected-result-header-review.json` retains numeric comparisons without exposing raw UUIDs.

The earlier helper's no-force/no-fallback bounds remain binding. A bare server-side REPAIR QUICK is not automatically acceptable: matched ha_maria.cc around1350-1375 can retry without QUICK or switch repair method after an error. No second repair, automatic fallback, zerofill, raw flag patch, grant reset or control/log deletion was performed in this review.

### Shutdown and preservation

Both capture and validation completed normal authenticated graceful shutdowns with server/admin exit0 and no timeout. Validation shutdown completed00:23:43 +08. Fresh read-only Windows inventory confirms no relevant processes and no3306/3307 listeners; certificate `.migration-private/phase3a-corrected-result-offline.json`.

Independent full-tree hashing confirms all eleven prior database trees and both upload trees unchanged, and the three new completed artifacts match their saved manifests. Retain these as three additional protected trees for future work (14 database trees plus2 uploads):

- Capture: F02D73C1A94E7EE9F47AD7E4C4BF81A73C29CA25EF792A89977C71E7359DC8EA, 2,767 files.
- Offline index trial: AEFD94F91CAD86A7CDE9AC26D5AA717EC102A5BB6F6E5134C58CBDC0FFE0BA13, 2,767 files.
- Stopped validation: FF90764111681D0F0540A47C4E9AA415A87000983E428772568578DF3B633AC8, 2,767 files.

The original export still matches its pin. Certificate `.migration-private/phase3a-corrected-result-preservation.json` covers all16 database/upload roots and the one-file offline delta. Recovery source hashes still match. This result review used read-only files/source/process checks and documentation updates; it ran no new server, SQL, checker, repair or import and changed no application/configuration/feature/migration/test source.

### Next decision

[Proposed Phase 3-B target-only recovery normalization](database-phase3-b-normalization-proposal-20261021.md) would use fresh copies, the now-saved complete baseline and a supported single offline zerofill operation, then the same full validation/restore gates. Unlike the index-only scope, it may change **only this target's MAI and MAD** low-level metadata/unused bytes while requiring unchanged grant/application records and all non-target database bytes. It needs explicit authorization; it is not a permitted fallback under the consumed Phase3-A marker. No Phase3-B operational helper or mutation has been implemented/run. Keep XAMPP stopped and do not rerun the corrected trial.
