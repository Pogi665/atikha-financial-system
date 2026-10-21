# Phase 2-B: isolated inspection and application restore rehearsal

October 20, 2026. **The authorized fresh-copy continuation passed mysql.proc but stopped at an index-structure error in mysql.tables_priv. Its candidate shut down gracefully; all protected sources/uploads/export are unchanged. Financial invariants and restore remain incomplete.** A separately approved targeted repair trial is now proposed; no repair or further startup is authorized. Earlier preparation/attempt records below remain dated evidence. Keep working XAMPP MySQL and Apache stopped.

## Inputs and protected state

[Phase 2-A](database-phase2-a-startup-proposal-20261020.md) completed authenticated identity/effective-path verification, logical application capture and graceful shutdown. Its stopped candidate is the source for new disposable copies; the original working directory is never launched. Every earlier database tree and the original Phase 2-A export remain protected.

- Five original/preservation/examination/log-review trees: SHA-256 `B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD`, 2,768 files each.
- Earlier stopped candidate: `647FBDA5FFA69F1B756B87BD6BB0AB496F43BD822DDD65E205562B1AD3BE10A6`, 2,767 files.
- Successful corrected Phase 2-A candidate data: `D0DA68893E42300F3E6CB6B1384887FF8C76D745C7FB5B753EB59A4E71FD680E`, 2,767 files. The private `candidate-after.json` records this post-recovery state; it is deliberately different from the abrupt-stop original.
- Application export: 123,247 bytes, SHA-256 `E6C603BAFF6038977A177AD97D2A10F4F0D5D55A21381372B22E287E80DBE22A`.
- Both current and preserved uploads: `FF2E87E9C44B4ADF9385EB8748AAAB56B569738FBADD6D148C84A967FFC328BE`.

The helper rejects source/input/tool drift, reparse points, external tablespace links, active clients/servers, occupied 3306/3307, existing attempt markers/destinations and insufficient capacity. Seven database trees, both upload trees, export and pinned tools/read-only sources are verified again at completion. Working configuration is pinned and untouched. All copies/partial results remain private and retained.

## One manual batch

Prepared `scripts/database_phase2b_validate_restore.ps1 -ValidateAndRehearseIsolatedRestore` and `scripts/database_phase2b_probe.php`. The wrapper imports only function AST definitions from the exact successful Phase 2-A helper, pinned at `E78CFCAA9979E091B1D0E28E6974D27F496937FAAAE420D233456AD31145067B`; it does not execute that helper's main. `scripts/database_phase2b_source_manifest.json` pins the probe, 46 includes and PHP executable; its own hash is pinned by the wrapper.

1. Verify stopped state and all protected inputs. Record one durable `phase2b-validation-restore-attempt.json` marker. Allocate a new `phase2b-<timestamp>-<guid>` root under the established private diagnostic directory.
2. Make a complete fresh `validation/data` copy of the successful stopped Phase 2-A candidate. Validate the generated option file using installed server help before copying/launching; require full copy-hash equality. No database directory is excluded.
3. Start that candidate once on loopback 3307. Retain the held native executable/creation-time identity, conditional available-CIM token checks, PID file and sole listener ownership. Require authenticated SQL identity and all 30 effective runtime variables before inspection. Preserve InnoDB geometry, grant checks, read_only=ON, force_recovery=0, disabled automatic repairs/events/replication/logging and confined writable paths. Apache/3306 remain closed.
4. Run standard serial `CHECK TABLE` for every existing application and mysql base table/view. Empty, warning, error, unsupported or non-OK responses stop the batch; no repair, analyze, optimize or EXTENDED check is attempted. Capture each result privately before continuing. Non-InnoDB system tables are inspected on the disposable copy only. Other test databases are retained in the complete copy but not treated as application acceptance fixtures.
5. Inspect all application foreign keys for orphans, respecting composite nullable-key semantics. Use the existing flag-independent Stage 1/2/3 schema and `correction_integrity()` readers for posted exact-centavo balance/line integrity, advance reconciliation, correction provenance and evidence/reservation/allocation consistency. Load those definitions under `ATIKHA_ISOLATED_TEST=true`, suppressing working configuration; do not invoke writers, sessions, authentication, migrations or providers. Financial/invariant reads run in a read-only transaction. Outbound PHP URL/curl/socket/mail functions are disabled in this invocation. No application/PHP HTTP endpoint is served.
6. Verify every referenced receipt against preserved file existence/size/MIME/stored hashes where recorded. Preserve the distinction for legacy receipts lacking stored hashes; do not fabricate them. Resolve Board/external attachment paths within the preserved upload tree and hash referenced bytes. Unsafe/missing paths or mismatches stop, without modifying evidence. Full current/preserved upload trees also retain their prior hashes.
7. Capture deterministic fingerprints of **every application column and row**, including null/binary values, duplicate multiplicity, credentials/MFA/history/drafts/evidence/snapshots and generated values; sort row hashes independently of storage order. Compare full table/view definitions and column metadata, database charset/collation, triggers, routine definitions/parameters and event definitions/settings. Include account/privilege table row fingerprints and current-connection grant fingerprints; raw credentials/grants are not printed. Require application/object/permission snapshots to stay identical before/after checks. Creation/last-execution metadata for stored objects is not represented as application definition equality.
8. Gracefully stop the validation candidate once. Require exit code 0, no remaining clients/servers and clear ports. Recheck protected database trees before any second candidate. If anything fails, do not proceed to restore.
9. Create a **second complete fresh copy**, `restore/data`, from the same protected Phase 2-A source. Start it once with the same isolation and identity gates. Only inside this second disposable copy, after native/listener/SQL/datadir/PID verification, execute `DROP DATABASE atikha_finance` and verify the schema is absent. Import the exact pinned export through the no-defaults/no-force TCP client with binary batch mode and reconnect disabled. This schema replacement is part of the authorized isolated restore rehearsal; it never targets the working directory/server or protected source. No grant changes, account resets, migration files or SQL compatibility rewrite are used.
10. Run the same checks and snapshots against the restored application. Require exact source/restored application schemas, row counts/hashes, objects, account/grant fingerprints and referenced evidence fingerprints. No normalization of differences or ignored missing rows. Gracefully stop and preserve this candidate too, then verify all original inputs/stopped state and write the final report.

Both candidates reuse 3307 **serially**, never simultaneously. Each has a unique generated INI, held process identity, before/after data manifest, SQL/check/import evidence and one graceful-shutdown marker. Normal engine startup/recovery and check-state writes are confined to the new candidates. Restore DDL/data writes are confined to the second copy's application schema. No financial workflow posting is used as a test.

## Bounds and stop handling

Readiness: 60 seconds. Ordinary identity/metadata SQL client calls: 10 seconds. Probe: 180 seconds total, with session max_statement_time=20 seconds (not assumed to cancel every maintenance operation). Import: 120 seconds. Shutdown request client: 15 seconds, server exit: 60 seconds. A timed-out client/probe may be cancelled; **the server is never force-terminated**. A maintenance operation may outlive its disconnected client; a failed/slow graceful stop is reported and left for a separate decision.

At the first failure, stop further inspection/import work, retain partial evidence and attempt only one safely guarded graceful shutdown if it has not already been requested. No launch/import/shutdown retry, force-stop, manual repair, log deletion, recovery-level change or files reset. If identity cannot be verified or a server remains running, report its PID and leave its files untouched. Do not start XAMPP or close an active server console. All earlier failure evidence remains intact.

## Preparation verification

**43 offline PHP probe assertions passed** against final probe bytes. Coverage: binary/null/framed row hashing, duplicate/order preservation, strict restore mismatch detection, serial table-check success/fail-fast behavior, composite FK orphan/NULL semantics, safe/missing/traversal/URL attachment paths, new-only JSON evidence, private hashed permissions, object coverage, and loading existing integrity readers without config/database connection/session. Exact-centavo handling is retained. Private evidence: `.migration-private/phase2b-php-tests-ba1fe246b3/results.json`.

**44 offline PowerShell/helper assertions passed** against the prepared helpers. Coverage: pinned source/input checks, protected-tree drift/external links, incomplete/failed/write/working-access probe results, row/schema/null restore mismatch, wrong working/validation/outside/traversal restore roots, fixed no-force/reconnect-disabled import options, installed client/server option compatibility in help mode, no help-time datadir initialization, absent-CIM/listener ownership cases, unchanged recovery/grant/repair/event settings, and the actual stdin-client wrapper with harmless children (exact input, unchanged bytes, new-only logs, nonzero exit and bounded timeout). Private evidence: `.migration-private/phase2b-helper-tests-e92da981e7/results.json`.

PHP syntax and both PowerShell files parse. Only function AST definitions and the explicit preparation-test function were invoked for PowerShell testing; no execution-policy bypass or operational recovery main was used. Earlier Phase 2-A utility/native/shutdown tests remain dated evidence for unchanged pinned functions, not a new live Phase 2-B result. The first PHP test run emitted an expected file-exists warning in the negative overwrite test; the writer now suppresses that native warning while retaining explicit failure, and all 43 tests were rerun successfully on final bytes.

A fresh independent preparation rehash confirmed all seven protected database trees and both 50-file upload trees at their pinned hashes; the operational attempt marker is absent. Private evidence: `.migration-private/phase2b-preparation-protected-trees.json`.

These are preparation checks, **not** a completed CHECK TABLE, live financial invariant pass, restore or working acceptance. No new database server/connection/import was started by the agent. No production application source/configuration/flags, working data, migrations or U3 tests were changed/run for this batch.

## Manual command

Leave XAMPP MySQL and Apache stopped. In **Administrator PowerShell**, run once:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase2b_validate_restore.ps1' -ValidateAndRehearseIsolatedRestore
```

Keep the window open while it runs. Send the terminal result. Do not share private exports/logs/receipts.

- `PHASE2B_VALIDATION_RESTORE_COMPLETE`: the scoped isolated inspection/application restore comparison passed and servers stopped. It is not permission to start the working system.
- `PHASE2B_STOPPED_FOR_REVIEW` or any error: stop and report; do not rerun, repair or force-stop. If a candidate remains running, leave it and its files untouched.

## Acceptance boundaries and next decision

This rehearses the application export **inside a complete physical copy carrying existing system tables/accounts/grants**. It is not a separately initialized/bare-metal full server restore, repair of the working original, proof of normal stored-procedure migration registration, application login/privacy/browser acceptance or U3-A integration completion. The original handshake cause and migration 009 error 1304 remain unresolved. Other test databases are not individually validated. A future clean restart/cutover proposal must use the actual findings and retains separate authorization; no working promotion, Apache start or U3 resumption is included.

## Execution findings - stopped at mysql.proc table-status warning

The user ran the helper once. Private attempt: `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-183602-8bee3d3b`. `results.json` at 18:37:35 +08 records failure at the inspection probe, graceful shutdown without issue, all seven protected database trees and both upload trees unchanged, and no restore/cutover/manual repair/forced stop. The original export's independently recomputed SHA-256 still matches.

`validation/logs/table-checks.jsonl` contains 51 serial results:

- All **34 application tables** returned only status `OK`.
- The first **16 mysql system tables** also returned only status `OK`.
- The next table, **mysql.proc**, returned warning `1 client is using or hasn't closed the table properly`, followed by status `OK`. The helper deliberately rejects any warning, so it stopped here. It did not silently accept the warning or continue to remaining tables.

The probe exited 1 without timeout. Inspection stopped before foreign-key orphan, financial/advance/correction/evidence invariants, file-byte verification or final before/after logical comparison. Its initial snapshot was computed in memory but not written before the table-check stop; do not claim a completed source fingerprint comparison from it. No restore directory/import was created. No new U3-A database test ran.

The server log records readiness at 18:36:31, normal authenticated shutdown at 18:36:35 and shutdown complete at 18:36:36; server and admin exit codes are 0. Fresh independent process/listener inspection found no relevant database/Apache/client processes and no 3306/3307 listeners. The stopped validation copy is retained at post-check tree hash `4B3AA2146106026AC60F8368B52AACCE251570E44694232ABA308A4426B385E4`, 2,767 files. Keep every earlier copy/attempt/marker intact.

### Read-only interpretation, not a repair diagnosis

The exact [MariaDB 10.4.32 Aria status checker](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_check.c#L150-L188) emits this message when its stored open count disagrees with its internal changed-state expectation. This is distinct from its separate crashed-table and transaction-ID checks. The matching [SQL CHECK handler](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ha_maria.cc#L1170-L1285) proceeds with checks and, on success, can update table statistics/open-count state before returning `OK`. SQL global read_only does not mean these internal state bytes cannot change on the disposable copy. The batch had authorized candidate-only normal engine/check-state writes; no manual REPAIR statement or offline repair tool was invoked.

Read-only file comparison confirms `mysql/proc.frm` and `mysql/proc.MAD` have identical hashes in the preserved source, successful Phase 2-A source and stopped Phase 2-B copy. The 16,384-byte `proc.MAI` differs by six bytes in the Phase 2-B copy. This is consistent with status/statistics maintenance, but individual changed fields have not been decoded; file equality does not establish logical routine-registration health. Private evidence: `.migration-private/phase2b-stopped-proc-file-comparison.json`. This warning plus `OK` does not establish damaged table records or justify REPAIR, migration reruns, grant bypass, log removal or working cutover. Its connection to the original handshake failure or earlier migration 009 error 1304 remains unproven.

### Proposed bounded continuation - approval pending

Recommend preserving this cleanly stopped post-check candidate as an eighth protected tree, then using a **new complete fresh copy of it** for one separately authorized continuation. Retain the existing strict warning/error rejection; do not add a blanket or mysql.proc warning exception. CHECK TABLE may have normalized status metadata already, so a fresh startup/check from the cleanly stopped copy is a reasonable next diagnostic step, not a guaranteed cure. A repeated/new warning must stop again without repair or automatic retry.

Before a new manual command, prepare/test a separate continuation helper and marker that pins all eight database trees, both upload trees, the original export and source/tools; never change/rerun the completed helper or erase its marker. If the complete validation passes, perform the same separate disposable application restore/compare and graceful stops already specified above. The current working server/data/configuration and original preservation sources remain untouched. No normal routine-registration test, repair, working restart/cutover or U3-A integration is included. **No continuation has been prepared or executed under this result review; a new attempt requires approval under the agreed stop-on-new-failure contract.** Keep XAMPP MySQL and Apache stopped.

## Continuation authorized - separate manual helper ready

The user explicitly replied **"yes, i authorize"** to the proposed fresh-copy continuation after reviewing the mysql.proc warning, clean shutdown and preservation findings. This supersedes the preceding pending-approval state. It authorizes one new bounded validation/restore batch through the same manual Administrator PowerShell workflow, not repair, warning suppression, automatic retry, working restart or U3-A integration.

Prepared `scripts/database_phase2b_continue.ps1 -ContinueIsolatedValidationRestore`. It imports **function AST definitions only** from the unchanged successful Phase 2-A helper (SHA-256 `E78CFCAA9979E091B1D0E28E6974D27F496937FAAAE420D233456AD31145067B`) and original Phase 2-B helper (`F6FB16745C53E618ECE316F75BF1520AA98B1DBBBD7BDDEEE54DCC4E7D9D3744`). Their operational mains do not run. Those function sources are re-pinned before launches and at completion. The original probe (`C827303D62808315FE808BD269555733C29BC54A8D4AD17A363FB6E706375A63`) and source manifest (`32924EB58FD5242BDCD14A36E2FEF22CD70F0A504EF924352FF0E4BB05877D51`) remain byte-for-byte unchanged. **Every new CHECK TABLE warning still stops the batch**, including recurrence of the known mysql.proc message.

The additional continuation gate requires the saved previous failure/probe reason, exactly 34+16 clean checks and the reviewed mysql.proc warning plus OK, verified graceful server/admin exit, no prior restore, unchanged seven sources/uploads, and the exact before/after full-tree manifests. Any different failure/progression/timeout/source drift is rejected. It does not reinterpret the previous failed probe as passed.

The fresh source is the complete cleanly stopped `phase2b-20261020-183602-8bee3d3b/validation/data`, pinned at `4B3AA2146106026AC60F8368B52AACCE251570E44694232ABA308A4426B385E4`, 2,767 files. This is now the **eighth protected database tree**. New validation and, only if that passes, restore copies are created serially under another unique `phase2b-<timestamp>-<guid>` root. Both start from that protected post-check source. The original Phase 2-A source remains protected separately; its original logical export remains the restore input. A distinct `evidence/phase2b-continuation-attempt.json` marker prevents repetition. The previous Phase 2-B marker/results/copy are not changed, removed or reused as a destination.

All existing transport/identity/runtime-path, standard serial table checks, exact-centavo/schema/foreign-key/correction/advance/evidence readers, private file verification, source/restored snapshot equality, client/probe bounds and one graceful stop per candidate remain unchanged. The only application-schema replacement/import is in the new second disposable copy. Completion now records `ProtectedEightTreesAndUploadsUnchanged`. Full working acceptance, a fresh-initialized/bare-metal restore, migration 009 registration diagnosis and clean working cutover remain separate.

Preparation checks on final source:

- **36 continuation assertions passed:** exact valid saved-state acceptance; failed/missing stop/preservation/no-repair protections; unexpected previous progression/restore; wrong export or source hashes/count; duplicate/missing/wrong table inventory; other warnings or missing final OK; and the same gate against the actual saved stopped-attempt evidence. Earlier helper/probe/manifest hashes remain exact. Private evidence: `.migration-private/phase2b-continuation-tests-77d6c4e059/results.json`.
- **44 shared helper assertions freshly passed:** synthetic input/tree/report/restore guards, installed server/client help compatibility, process/listener predicates and harmless stdin/nonzero/timeout child handling. Private evidence: `.migration-private/phase2b-continuation-shared-tests-8ac0ad84de/results.json`.
- **43 unchanged probe assertions freshly passed**, including rejection of warning responses, row/schema comparison, orphan/path handling and no working configuration/session load. Private evidence: `.migration-private/phase2b-php-tests-38e1b9249c/results.json`.
- PHP syntax and all helper/test PowerShell parsing pass. No database server, SQL, CHECK TABLE or import was run by preparation. No execution-policy changes or bypasses were used. No application/configuration/migration/feature changes or U3 tests occurred.

Fresh read-only preparation hashing confirms all eight protected database trees and both upload trees still match the pinned values; the continuation attempt marker is absent. Private evidence: `.migration-private/phase2b-continuation-protected-trees.json`. Final source fingerprints are retained in `.migration-private/phase2b-continuation-preparation-source.json`.

The prepared continuation helper SHA-256 is `CFE4F5C09D3DBFFF5E0FCF766024E4F62ED48328AD0586852779EAE112AF6FD6`. These **123 preparation assertions** are not a completed live continuation or proof that the warning will not recur.

### New manual command - do not rerun the earlier helper

Keep XAMPP MySQL and Apache stopped. In Administrator PowerShell run **once**:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase2b_continue.ps1' -ContinueIsolatedValidationRestore
```

Keep the window open while running, then send the terminal result. Success is `PHASE2B_CONTINUATION_COMPLETE`; a repeated/new warning, error or `PHASE2B_CONTINUATION_STOPPED_FOR_REVIEW` means stop and report again. Do not rerun, repair, force-stop or start XAMPP. If a candidate remains running, do not touch its files. Keep private exports/grants/logs/receipts local. The agent has not executed this operational main or created its attempt marker/candidates.

## Continuation outcome - mysql.proc clean; tables_priv index error

The user ran the separate continuation once. Private evidence: `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-185120-15e57a02`. Saved `results.json` at18:53:19 +08 records the probe stop, protected eight database trees/uploads unchanged, all servers stopped, and no repair/force/working changes/migration/restore/cutover. The original Phase 2-A export SHA-256 independently still matches. Fresh process/listener inventory finds zero relevant servers/clients and no3306/3307 listeners.

The serial log now contains **57** results: all34 application tables and22 mysql tables returned clean OK, including **mysql.proc** with no repeated warning. The next table, **mysql.tables_priv**, reported `Wrong base information on indexpage at page: 2` and `Corrupt`, both error rows. This is a different, structurally significant finding; the helper correctly stopped. No remaining table, financial/orphan/evidence/file validation or restore/import ran. The initial logical snapshot was only in memory and was not persisted before the stop, so no complete saved row/grant baseline is claimed.

Server log: ready18:51:47; authenticated normal shutdown18:51:52; shutdown complete18:51:52. Server/admin exit0, no shutdown issue. The latest stopped candidate is retained at tree hash `55C7EF745BAF543B6E38615126BD0D7FF3CC69E2781B5755B5F880E91C881FB8`, 2,767 files. It becomes a ninth protected source for any future authorized work, never a repair target itself.

Read-only comparison: tables_priv.frm (2,978 bytes) and tables_priv.MAD (16,384 bytes) match the preserved, Phase2-A, first Phase2-B and latest-copy hashes. tables_priv.MAI (24,576 bytes) matched across the first three, then changed during the latest failed check. These are preservation observations, not proof of all permission rows or a successful repair. Private evidence: `.migration-private/phase2b-continuation-tables-priv-comparison.json`. No index rebuild, REPAIR, privilege reset or new operational check was run in this result review. Fresh independent full-tree hashing confirmed all eight prior database trees and both upload trees still match preparation, and the latest stopped candidate matches its saved after-manifest. Private evidence: `.migration-private/phase2b-continuation-stopped-preservation.json`.

[Phase 3-A proposal](database-phase3-a-index-trial-proposal-20261020.md) requests a private baseline capture first, then one offline index-only trial on a **new complete copy**, exact row/grant/data-file preservation and full validation/restore comparison if successful. This needs separate approval because the existing authorization excluded repairs and required stopping on new issues. No new helper has been implemented or operational command provided. The original handshake cause, migration009 error1304, working acceptance and U3-A integration remain unresolved. Keep XAMPP stopped; do not rerun either Phase2-B helper.

## Subsequent Phase 3-A authorization and preparation

The user subsequently authorized the [baseline-first copied-index trial](database-phase3-a-index-trial-proposal-20261020.md#authorized-preparation-and-manual-handoff). Its separate helper/probe/tests are prepared with 173 passing preparation assertions; earlier Phase 2-B helpers/probe/manifest and strict classifiers remain unchanged. It protects all nine existing database trees and may repair only a fresh complete offline clone after stable private row/grant capture. Validation/restore are conditional on index-only byte scope and exact logical preservation. No operational server, repair or import has yet run for Phase 3-A. User manual execution is pending; no working restart, migration, force/fallback or U3 integration is included. The preceding pending-approval statement is historical and superseded by this authorization, not by the failed Phase 2-B checks themselves.
