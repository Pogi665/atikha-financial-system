# MariaDB preservation and recovery plan — October 20, 2026

**Prepared for approval; no shutdown, database copy, checker run, server launch or repair has been executed.** This follows the [read-only diagnostic](database-diagnostic-20261020.md). The user requested addressing the database blocker before completing U3-A. This document proposes bounded phases; approval of preservation is not permission to perform every possible repair or cut over a replacement server.

## Decision

Preserve the **current October 20 dataset**, investigate copies, then select a repair based on evidence. Do not restore October 8 automatically. Current financial counts and integrity remain unknown. A working TCP listener with no greeting does not identify a corrupt table, and the earlier migration-009 error 1304 does not establish today's cause.

Recommended first execution authorization: **Phase 1 only**, with the shutdown branch explicitly selected below. It ends after an offline verified backup, checks on a separate examination copy, and a findings report. No server is started and no table is repaired in Phase 1. Subsequent phases require a concrete proposal based on those findings.

## Fresh planning observations

Read-only observations during plan preparation:

- PID **20024** remains the sole observed MariaDB server and owns port **3306**. Its creation time matches the October 20 01:11:26 log start; parent PID **23432** is XAMPP Control Panel. `data\mysql.pid` contains **20024**. No Apache process or port-3307 listener was observed.
- Although CIM withholds path/command line, Windows `QueryFullProcessImageName` with limited query rights independently returned **C:\xampp\mysql\bin\mysqld.exe**. The loaded option file and effective SQL datadir still cannot be confirmed from this result.
- The installed binary is 10.4.32.0, SHA-256 **EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A**. `bin\my.ini` is SHA-256 **268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0**. Recheck both at execution; stop on drift.
- The active directory inventory is approximately **412,226,547 bytes / 2,768 files**, including the working database, system databases and disposable test databases. This is a live inventory, not a consistent backup. No reparse points or `.isl` external tablespace links were found. There are **24 `.MAI` files** and no `.MYI` files in this inventory.
- `data\my.ini` is a retained 88-byte file specifying the old `atikha-recovery-clean` directory and port 3307. Its existence does **not** prove the current process loaded it. Preserve it, but use an explicit option file for any future diagnostic launch.
- The installed `mysql_stop.bat` calls a `killprocess.bat` helper and contains PID-file deletion. Do not use it as a graceful shutdown method. Do not close server consoles or click an unverified XAMPP stop action for MariaDB.
- `data\DESKTOP-75HSME5.err` is an **October 8** initialization log; `cutover-stage.err` is also historical. They do not describe today's startup. `data\mysqld.dmp` is dated **October 13 at 23:41:25**, matching the older Windows crash. Only metadata was inspected; no private process memory was opened or published.
- Installed `aria_chk.exe --no-defaults --help` confirms `--check`, `--read-only`, `--skip-update-state` and `--ignore-control-file`. `--update-state` is enabled by default, and `--force` can invoke recovery. Those defaults must not be mistaken for read-only checking.

## Phase 1 — stop, preserve, inspect copies

### 1. Revalidate identity and allocate private destinations

Keep Apache stopped and close application/database clients. Record current process ID, creation time, parent image, process image, listener owner, PID-file value, server/INI hashes and recent log bytes privately. Revalidate immediately before any shutdown/termination; never trust a cached PID alone. If a different process owns the port, another database process exists, or identity cannot be established, **stop**. Do not stop MySQL80 or unrelated servers.

Use a new timestamp plus GUID, for example `<stamp> = yyyyMMdd-HHmmss-<8 GUID characters>`. Refuse existing destinations. Proposed paths:

```text
C:\xampp\mysql\recovery-exports\diagnostic-<stamp>\
  evidence\                 process records, command results, hashes, logs
  preserved\data\           entire offline October 20 data tree
  preserved\configuration\  bin\my.ini, data\my.ini, relevant XAMPP files
  preserved\application\    private app configuration/source preservation
  preserved\storage\        complete configured protected upload trees
  examine\data\             second verified copy for offline checking
```

Preservation must include **all** current database directories and shared files, including test databases, system tables, `ibdata1`, both redo logs, Aria logs/control, `.ibd` files, old logs and the crash dump. Selectively copying only `atikha_finance` would omit shared InnoDB state. No cleanup or exclusion of test databases in this phase.

Record uncommitted U3-A source hashes/patch and private configuration hashes without printing configuration contents. Preserve `config.php`, `db_connect.php`, application source needed for recovery, and the complete `uploads` tree. Inspect configured storage overrides privately and include any additional real evidence roots. Do not execute PHP includes merely to discover storage paths, as they can initialize services. Do not omit evidence because its SQL row is private. Keep exports, config backups, crash dumps and receipts outside served folders and Git.

### 2. One bounded graceful shutdown attempt

After the identity gate, try authenticated shutdown once using the installed client, with no default client configuration:

```powershell
& 'C:\xampp\mysql\bin\mysqladmin.exe' --no-defaults `
    --protocol=TCP --host=127.0.0.1 --port=3306 --user=root `
    --connect-timeout=5 --shutdown-timeout=30 shutdown
```

The execution wrapper must impose a **15-second client-process bound**, record stdout/stderr/exit code privately, and cancel only that verified client if it stalls. Cancellation of the client is not server shutdown. If shutdown was accepted, poll the original server identity for up to **60 seconds**. Native exit zero alone is insufficient: require process exit, no database processes and clear ports 3306/3307. If shutdown is in progress beyond that bound, stop/report rather than immediately killing it.

MariaDB documents `mysqladmin`/`mariadb-admin shutdown` as an authenticated server shutdown request. [Administrative client](https://mariadb.com/docs/server/clients-and-utilities/administrative-tools/mariadb-admin).

### 3. Explicit shutdown branch if authentication is still unavailable

**Default branch: stop and report; do not force anything.** Phase 1 cannot proceed to an offline copy while the process remains alive. This respects the prior recovery rule prohibiting forced termination.

**Optional exceptional branch, requiring the user's explicit approval:** if the graceful request could not connect/authenticate, allow **one controlled forced termination of the reverified XAMPP server process only**. This is a proposed exception to the earlier no-force rule, not an implied part of routine implementation approval. The executable, process creation time, parent, PID-file value and listener ownership must still match immediately before acting. Use a captured Windows process handle where practical to avoid PID-reuse races; otherwise recheck the same identity and stop on uncertainty. Do not terminate by image name, process-tree kill, service-wide command or broad `taskkill /IM`.

Abrupt termination can interrupt flushing and require crash recovery; no consistent current logical backup exists yet. The subsequent offline copy preserves the resulting state and cannot retroactively guarantee that every in-flight write was durable. Existing recovery sources are retained, but they do not eliminate this risk. Do not call this a graceful shutdown or a repair. If termination fails or any process/listener remains, **stop without copying**. No automatic restart.

The execution authorization must say whether this exceptional branch is permitted. Without that explicit exception, the default stop condition applies. No forced-termination command will be executed under the current planning/read-only request.

### 4. Make and verify the offline preservation copy

Once every database process is absent, both database ports are clear and Apache remains stopped:

1. Resolve source/destination absolute paths, reject reparse points and destinations nested in the source, and recheck external tablespace links. Do not rename the source.
2. Require free space for the full preserved copy, examination copy, possible later candidate and protected storage, plus at least **2 GiB headroom**. Measure again after stopping; the live 412 MB inventory is not a capacity pin.
3. Fingerprint every source file using relative name, length and SHA-256, plus directory entries. Copy the complete tree with native PowerShell/Robocopy; no `/MIR`, purge, move or recursive deletion. Capture its exit code; investigate unexpected codes or skipped files.
4. Require destination tree equality to the offline source manifest. Rehash the source after copying and require equality to the first offline manifest. Stop on any difference or unexpected server restart.
5. Preserve configuration, configured upload roots and source state; verify their copied bytes. Reject a storage override/reparse point until its complete target is included explicitly.
6. Copy **preserved\data** into **examine\data** and require complete equality. Never launch a server against the preservation copy. Keep the active original directory at its existing name and unmodified by this procedure.

A backup on the same disk protects against procedural mistakes, not disk failure. A separately verified off-device copy is desirable before later destructive recovery; none is assumed to exist.

### 5. Check only the offline examination copy

Verify again that no server is running. Enumerate `.MAI` files from **examine\data**, reject any resolved path outside that root, and check serially using the installed version. For each file, the command shape is:

```powershell
& 'C:\xampp\mysql\bin\aria_chk.exe' --no-defaults `
    --check --read-only --skip-update-state --ignore-control-file `
    '--tmpdir=<private examination temp directory>' '<one examination .MAI path>'
```

Resolve the placeholders before use; do not paste them literally. Use an examination working directory and per-table private output/exit-code records. Impose a bounded per-table runtime; if it hangs, cancel only the checker and stop the phase. `--ignore-control-file` is appropriate only after confirming this examination tree is not in use. It avoids opening a shared control file; the per-table index/data hashes must remain unchanged.

Do **not** use `--force`, `--recover`, `--safe-recover`, `--update-state`, `--analyze`, zerofill or automatic repair. Hash examination table files before and after checking and stop if they change. Do not check InnoDB `.ibd` files with Aria/MyISAM tools. If unexpected `.MYI` files appear, stop to extend the checker inventory rather than using Aria against them. Report damaged-table findings as findings, without invoking repairs.

MariaDB warns against running `aria_chk` on tables in use, and documents its check/update/repair distinctions. [Aria checker](https://mariadb.com/docs/server/clients-and-utilities/aria-clients-and-utilities/aria_chk). The installed 10.4.32 help output, rather than current documentation alone, confirms the options above.

Preserve the October 13 dump for possible private stack analysis. Do not upload it publicly or print memory contents. Lack of a matching-symbol stack is an explicit diagnostic limit.

**End of Phase 1:** report shutdown method, validated backup paths/manifests, individual checker results and remaining unknowns. Leave all servers stopped. No repair, candidate launch, folder switch, account edit or migration is included.

## Phase 2 — propose one instrumented startup of a disposable copy

Prepare this exact launch only after Phase 1 findings. If a specific table is damaged, propose its trial repair first as a separate Phase 3 variant; do not improvise it while booting.

For a normal-start diagnostic, create another complete fresh copy of **preserved\data** as a unique candidate. Use the same MariaDB binary, loopback **127.0.0.1:3307**, and an **explicit** candidate option file. Keep 3306 closed and Apache stopped. Audit all loaded options/includes and writable destinations before launch. Redirect data/InnoDB data/log/undo directories, Aria logs, temporary files, slave-load temporary files, PID, socket, error/general/slow log destinations and relative tablespaces into the candidate/private attempt tree. Reject external paths that have not been copied or safely redirected. Never rely on copied `data\my.ini` or working-directory default discovery.

Retain **ibdata1:12M:autoextend, two 48 MiB redo logs**, page/case settings and full original InnoDB files. Use relative `innodb_buffer_pool_filename=ib_buffer_pool`; the October 8 attempt already established that this binary mishandles the proposed absolute filename. Do not reuse unsupported `--skip-shared-memory`. Validate the complete option set against this installed binary before any launch.

Disable event execution, replication autostart, binary/general/slow logging, network binding beyond loopback, named-pipe/shared-memory transports where supported, automatic Aria/MyISAM table repair and application access. Keep grant checks enabled, `innodb_force_recovery=0`, and candidate `read_only=ON`. Read-only mode does not prevent startup/crash-recovery writes; those are allowed **only in the disposable candidate**. No application test endpoints, routine registration, seeds or migration/bootstrap scripts.

Capture stdout/stderr and the complete candidate log. Allow **one** startup attempt with a 60-second readiness deadline, including an authenticated identity response matching port, candidate datadir, version and SQL PID-file/listener owner. A listener alone is not success. A failure or timeout is a stop condition; do not automatically retry, delete logs or increase force-recovery levels. Define and approve the candidate shutdown/failure-handling branch before launch, so a hung candidate is not left without a safe handling plan.

If it becomes ready, capture a fresh working-database inventory/export privately **before higher-risk table checks**. Use ordered UTC-normalized data/schema manifests; include users, drafts, documents, correction links, advance tables, audit/identity history, triggers, views and routines. Treat `atikha_test_*` databases separately from `atikha_finance`. Compare with dated October 12 preservation evidence where available, allowing only explained changes. The old six-table counts are comparison evidence, not mandatory values to enforce by deleting newer rows.

Then validate system/application tables and views serially on the candidate, balance/orphan/evidence invariants, stable permissions queries and all configured paths. A check can itself expose a server crash; stop on the first failure and preserve evidence. Rehash untouched originals/preserved sources. A candidate that merely starts is not approved for promotion.

## Phase 3 — evidence-based trial repair proposal, if needed

Choose only after identifying a failure. Examples of possible later proposals, not authorized actions:

- A diagnosed Aria index problem: one specific-table repair on a **fresh trial copy**, preserving its pre-repair files and reported row counts, with post-repair export/record comparisons. Do not replace all `mysql` tables with an old/fresh folder or accept a silent row-count drop.
- A diagnosed stored-routine table problem: preserve current routine definitions/definers/grants and app triggers separately; determine the minimum repair. Do not attribute all trouble to migration 009 or rerun it against working data.
- A diagnosed InnoDB problem: propose extraction on disposable copies using the lowest justified recovery setting, followed by an independently initialized/restored instance and exact validation. Do not delete redo logs or system tablespaces. Recovery mode is not a repair. [InnoDB recovery modes](https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-troubleshooting/innodb-recovery-modes).
- A configuration/path/process problem: show the precise mismatch and test the smallest correction on a copy. The stale copied `my.ini` is a hypothesis to control, not a proven cause to fix blindly.

Stop and obtain a revised plan if a repair loses rows, breaks associations/identities, needs grants bypass or additional tools, or cannot preserve the current working dataset.

## Phase 4 — separately approved cutover and U3-A resumption

**October 21 continuation:** isolated normalization, complete validation and original-export restoration have now passed. After the user instructed proceeding with working recovery, the [concrete Phase 4 helper/rollback/manual handoff](database-phase4-working-cutover-20261021.md) was prepared with202 passing preparation assertions. It preserves the original working directory, promotes a whole verified cleanly stopped copy, recovers exactly3 verified working filenames and verifies3306 before graceful shutdown. Preparation has made no working/server/SQL change. Use only its new one-shot command; normal XAMPP startup/login and U3-A integration remain subsequent bounded work. Earlier pending cutover statements in this dated plan are superseded only by that concrete scope.

Only after the candidate's current application data, schema, protected files, system permissions and clean stop/restart pass: prepare a new guarded folder/configuration cutover with preservation and rollback. Do not promote the old October 8 recovery directory by name. A rollback preserves files and leaves known-broken originals stopped; it must not automatically restart them.

Verify the promoted server on 3306 before Apache, then perform separately scoped application reads/login checks with provider side effects blocked. Do not create financial transactions to prove availability. A full restore must preserve current evidence bytes and user/identity/audit history, not just the older two journals.

Once working health is independently established, resume U3-A using **fresh disposable synthetic databases** and the prepared integration suites. Keep normal routine-registration diagnosis distinct from compatibility-mode fixture results. U3-B/C/D, U-PERF-01 and later stages remain outside this recovery plan.

## Approval boundary

No recovery execution has occurred. Recommended authorization text for the first batch:

> Execute Phase 1 only. Attempt bounded authenticated graceful shutdown first. [Choose explicitly: stop if it fails / allow the one controlled forced termination described in Phase 1 only if the graceful request cannot connect.] Verify complete server exit, preserve and hash the entire current database/configuration/protected storage, and run non-mutating offline checks on a separate examination copy. Make no repairs, launch no database server, change no active configuration or permissions, and stop/report on any new failure. Preserve all older backups and U3-A work.

The forced-stop choice is necessary because the prior no-force rule remains in effect and no authenticated shutdown has been demonstrated available. A generic approval that does not select that exception leaves the default stop-on-failure branch in force.

## Execution update — graceful-only gate, October 20 at 03:27 +08

The user authorized Phase 1 without selecting the forced-stop exception, then manually ran `scripts/database_phase1_shutdown_gate.ps1` in Administrator PowerShell. Its identity gate passed and the one graceful shutdown request failed during the initial connection handshake. The helper stopped as designed. Saved identity/stderr are at `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\evidence`; there is no successful-stop marker. A subsequent read-only inspection confirms the same PID 20024/start time/parent and port-3306 ownership, with no Apache or shutdown client running. No database copy, offline checker, repair, server restart or forced termination has executed. The next operation requires the user's explicit decision on the exceptional shutdown branch; do not rerun the graceful helper automatically.

## Subsequent authorization — controlled-stop exception

The user then explicitly stated, "yes i authorize the controlled forced stop," and instructed proceeding after clarification that MySQL must remain running until the controlled action and database files must not be copied first. This selects the exceptional Phase 1 branch for the one reverified server only. The manual helper `scripts/database_phase1_controlled_stop.ps1 -ExecuteAuthorizedStop` is prepared, with a held process handle, exact creation-time/image comparison, independent ownership/hash guards, durable one-attempt marker and complete exit checks. It has not been executed by the agent. Its successful result is `CONTROLLED_STOP_VERIFIED`; any error is a stop/report condition, without retry, copying, repair or restart. Current authority does not include launching a candidate or later recovery phases.

Helper verification: PowerShell parsing and native C# compilation passed; three safe native identity checks passed against the test PowerShell process only (matching identity, rejection of wrong creation time, rejection of wrong image). No termination method was invoked and no database process was opened by these checks. A separate attempt to test the script's no-switch rejection was blocked by the tool host's execution policy before the script ran; that runtime guard is source-inspected, not claimed as an executed assertion. No policy was changed or bypassed. These checks validate guard preparation, not a completed server stop.

## Execution update — controlled stop completed; preservation pending

The user manually ran the explicitly authorized controlled-stop helper. Private `controlled-stop-attempt.json` and `stopped.json` record the one controlled termination and verified exit at **October 20, 03:35:07 +08**. A fresh read-only process/listener check independently found no database, Apache or copy-client processes and no 3306/3307 listener. No forced-stop retry, repair or restart occurred. This supersedes earlier pending-stop state; no further server termination is authorized or necessary.

Prepared `scripts/database_phase1_preserve.ps1 -CreateOfflineCopies` for the next manual gate. It preserves all current database files, source/private configuration and the complete known `uploads` tree, then creates a separate examination database copy. All five inspected configuration/storage helper files still match the pre-U3-A hashes; the working configuration has no inspected storage override and the default receipt/Board/external trees are inside uploads. Any drift/reparse point/external tablespace link is a stop condition. Application-only exclusions are `.git`, `.migration-private`, `node_modules`, `.codex`, `.agents`, `.aws`, and uploads copied separately; no database directories are excluded. Private source manifests, Git HEAD/status/patch and the pre-U3-A preservation index are included in evidence. Both original/current copy hashes and stopped state must match at completion. No checker, repair or server launch is executed by this helper.

PowerShell parsing and **eight synthetic utility checks** passed: include/exclude/empty-directory manifest, copy hash equality, unchanged source, existing-destination rejection, nested-destination rejection, changed-byte detection, JSON round trip and JSON overwrite rejection. Only extracted utility functions ran on new private synthetic workspace files; the operational helper/main has not been executed and no actual database files have been copied. Success marker: `OFFLINE_PRESERVATION_VERIFIED`. Any failure stops without deletion or automatic retry. Offline checker execution remains the subsequent Phase 1 gate, after preservation is independently verified.

## Execution update — offline preservation completed; checker gate pending

The user manually completed the offline preservation helper and supplied its success output. The private completion certificate records **October 20, 03:58:35 +08**, **2,768 files / 412,226,547 bytes**, database tree SHA-256 **B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD**, application/source/configuration hash **EE8FE4A217A12AAB15611BC275F24144B8B2A8A8DF6B39C2B9D349817671E282** and uploads hash **FF2E87E9C44B4ADF9385EB8748AAAB56B569738FBADD6D148C84A967FFC328BE**. Data/source/storage copies verified, and originals were unchanged through copying. Saved manifests agree, and a fresh independent read-only rehash confirmed the active original, preservation data and examination data still match. Both servers remain stopped. This is verified byte preservation of the stopped state, not proof of table integrity or durability of every prior in-flight write.

Prepared manual `scripts/database_phase1_check_copy.ps1 -CheckExaminationCopy`. It pins the preservation certificate/hash and installed checker hash, requires all relevant processes/listeners stopped, rejects unexpected index/link inventory and paths, and runs each of the **24** examination-only `.MAI` files once with `--no-defaults --check --read-only --skip-update-state --ignore-control-file`, a separate temporary directory and private stdout/stderr/exit records. Each checker is bounded to 30 seconds; only that checker can be cancelled on timeout. The first warning, nonzero exit, timeout or orchestration error stops further checks. It saves findings and rehashes the original/preservation/examination data before marking the no-byte-change result. No repair options or database server startup are present. A clean Aria result would not establish InnoDB/current financial integrity or authorize Phase 2.

PowerShell syntax and **15 synthetic utility/child-process tests** passed, covering manifest stability/empty directories, valid/escaping/wrong-type paths, changed-byte detection, result/warning/timeout classification, evidence overwrite rejection, native output/nonzero exit capture and bounded child cancellation. Tests used synthetic placeholder files and harmless cmd/PowerShell children only; no Aria table checker or database server was invoked. Private test evidence: `.migration-private/phase1-checker-test-c1ec085fb4/results.json`. The operational checker helper has not yet run. Expected manual result is `OFFLINE_ARIA_CHECKS_COMPLETE` or `OFFLINE_ARIA_CHECKS_STOPPED_FOR_REVIEW`; either requires reporting before further recovery action. Do not rerun on an issue.

## Execution update — checker halted; omitted control context identified

The user ran the checker. It checked 2/24 indexes, passed `column_stats`, and stopped on `columns_priv` exit 1 with a creation-ID 3 versus comparison-maximum 0 warning. All three database trees were rehashed unchanged at 04:06:45 +08. Read-only review found the supplied `--ignore-control-file` caused a missing-context comparison; the preserved control file has valid header/variable CRCs and stored maximum 145. This is a checker setup defect, not an established corruption diagnosis. See the [exact findings and corrected-attempt proposal](database-phase1-checker-correction-20261020.md). The old command's ignore-control instruction is superseded for future checks; do not silently amend/rerun it or repair/zerofill a table from this warning. One fresh-copy, required-control-context batch is proposed but not authorized/executed. Both servers remain stopped and original/backup evidence is preserved. Original startup cause, remaining tables and financial/InnoDB integrity remain unknown.

The user subsequently explicitly authorized that one corrected offline batch after clarification that no repair/server start is included. The new `database_phase1_check_context.ps1 -CheckFreshContext` manual helper and its 34 passing synthetic/installed-help checks are documented in the [correction handoff](database-phase1-checker-correction-20261020.md). It is prepared, not operationally executed; the earlier pending-authorization sentence is superseded by this approval. Old attempts/sources remain preserved. The default stop-on-any-new-issue rule continues to apply.

The user then executed the corrected batch: two tables passed and `mysql.db` stopped it at 3/24 with creation-ID 147 above persisted control maximum 145. All four database trees remain unchanged, with no repair or server start. See [current findings and proposed display-only copied-log inspection](database-phase1-aria-log-review-20261020.md). This further read-only operational batch is proposed, not authorized/executed; no retries or automatic log apply/undo are permitted.

The user subsequently explicitly authorized **one display-only copied-log inspection**. This supersedes the preceding pending-authorization statement. The guarded manual helper `scripts/database_phase1_read_logs.ps1 -InspectCopiedLogs` is ready, with 40 passing synthetic/installed-help utility assertions and PowerShell parsing. It uses one new complete copy, explicit apply/undo disabling, a durable single-attempt marker, bounded reader execution and original/all-copy preservation checks. Actual log inspection is pending user execution; no operational recovery copy, repair, replay or server start ran during helper preparation. See the [manual command and verification boundary](database-phase1-aria-log-review-20261020.md). Any new issue stops for review; later recovery/server startup is not authorized by this approval.

## Execution update - copied-log inspection completed

The user manually completed the one authorized display/check batch. Saved evidence records exit 0, empty stderr, EOF/success and 148 sequential record headers; the 04:48:22 +08 certificate verifies all five database trees unchanged. No apply/undo, repair or server startup ran. [Interpretation and limits](database-phase1-aria-log-review-20261020.md) retain the unresolved creation-ID warning and startup/financial-integrity unknowns. A narrower [Phase 2-A startup/export proposal](database-phase2-a-startup-proposal-20261020.md) is now prepared for separate approval; it does not authorize Phase 3 repairs or Phase 4 promotion. Leave both XAMPP servers stopped.

## Authorization update - Phase 2-A manual helper ready

The user explicitly instructed starting Phase 2-A. The narrowed [startup/export handoff](database-phase2-a-startup-proposal-20261020.md) records the guarded manual helper, 69 passing synthetic/installed-help preparation assertions and compatibility details. No operational candidate copy/server launch/SQL/export ran during preparation. The next action is the user's one manual `database_phase2a_candidate.ps1 -StartIsolatedCandidate` command. Keep XAMPP MySQL and Apache stopped. Normal recovery writes are allowed only in the new candidate; force-stop/manual repairs/retries/working changes/migrations/cutover remain excluded. Earlier pending-Phase-2-A authorization statements are superseded by this explicit approval, not by the successful Phase 1 log inspection alone.

## Execution update - Phase 2-A identity guard stop; candidate active

The user executed the single authorized candidate launch. The candidate log reached ready on127.0.0.1:3307, but the literal CIM image/command guard failed before authenticated verification and also prevented its graceful-shutdown call. PID9436 remains active; XAMPP MySQL/Apache are stopped. All five protected trees were verified unchanged after the attempt; no SQL/export, forced stop, manual repair or cutover ran. [Detailed handoff](database-phase2-a-startup-proposal-20261020.md) records native read-only image/creation-time verification and null CIM metadata in the agent inspection, with the exact original failing predicate still unrecorded. A tested shutdown-only continuation awaits explicit approval under the stop-on-new-failure boundary. Do not rerun startup, force-stop, close the PowerShell window or move/copy/reset candidate files.

## Subsequent authorization - shutdown-only continuation

The user explicitly authorized the one table-free candidate identity query plus one graceful shutdown. The tested manual `database_phase2a_shutdown_candidate.ps1 -GracefullyStopVerifiedCandidate` command and boundaries are recorded in the [Phase2-A handoff](database-phase2-a-startup-proposal-20261020.md). A fresh read-only complete target guard passed; no SQL/shutdown request has run during preparation. The next action is the user's one manual command, not a startup/export retry. No forced stop, repair, migration, working change or cutover is included; any failure stops again. Earlier pending-shutdown-approval statements are superseded.

## Execution update - candidate authenticated and gracefully stopped

The user completed the authorized shutdown-only continuation. Saved table-free SQL proves the exact candidate identity on3307 with read_only1/force_recovery0; the server logged normal root-requested shutdown and exited successfully. The17:54:30 +08 preservation certificate verifies the five protected original/earlier trees unchanged; fresh process/listener inspection confirms all servers/clients stopped and both database ports clear. No forced stop, new startup/export retry, repair or cutover occurred. The [Phase2-A handoff](database-phase2-a-startup-proposal-20261020.md) records limits: no application export/full runtime-path/object/account/financial validation has completed, and the initial helper guard failure is retained. Correct/review that guard and runtime compatibility before a separately authorized attempt to finish export; no new operational attempt is authorized by this successful cleanup. Keep XAMPP MySQL/Apache stopped; U3-A remains incomplete/blocked.

## Subsequent authorization - one corrected Phase2-A attempt

The user instructed proceeding after successful candidate shutdown. A new corrected helper/manual command is documented in the [Phase2-A handoff](database-phase2-a-startup-proposal-20261020.md), retaining native identity plus listener/SQL checks and conditional available-CIM argument validation.81 synthetic/installed-help checks passed;30 runtime variables were audited against pinned source. The stopped earlier candidate is additionally fingerprinted/protected; the original five sources remain pinned unchanged. No operational startup/export was executed during preparation. The user will run the new helper once, with XAMPP stopped. Repairs, forced stops, automatic retries, working changes, migrations, promotion and U3 integration remain excluded.

## Execution update - corrected Phase 2-A completed

The user's corrected isolated attempt completed authenticated identity/effective-path verification, current application logical export and metadata capture, then graceful shutdown at 18:12:10 +08. The export is 123,247 bytes with independently matching SHA-256 `E6C603BAFF6038977A177AD97D2A10F4F0D5D55A21381372B22E287E80DBE22A`; dump exit 0 and empty stderr. The 18:12:53 +08 certificate confirms all six protected original/earlier database trees unchanged. Fresh process/listener inspection finds all servers/clients stopped and ports 3306/3307 clear. Normal recovery writes occurred only in the new candidate; no manual repair, forced stop, migration, working change or cutover ran. See [detailed execution evidence](database-phase2-a-startup-proposal-20261020.md).

Phase 2-A is complete within its bounded scope. Export restoration, table/system integrity, financial/evidence reconciliation and working acceptance remain pending; original handshake cause and migration 009 error 1304 remain unresolved. Next recommendation is a separately authorized isolated validation/restore rehearsal, not working promotion or automatic U3-A resumption. No next operational phase is authorized by this completion. Leave XAMPP MySQL and Apache stopped; retain all copies/exports and do not rerun completed commands. Earlier pending-execution statements are superseded by this result.

## Subsequent authorization - Phase 2-B manual inspection/restore batch prepared

The user instructed proceeding after Phase 2-A. The [Phase 2-B handoff](database-phase2-b-validation-restore-20261020.md) specifies serial validation and separate application export restore on two fresh complete copies, seven protected database trees, original export and both upload trees, native/SQL path identity, strict table/invariant/evidence/restore comparisons and one graceful shutdown per candidate. Schema replacement/import occurs only in the second disposable copy. 43 offline PHP and 44 PowerShell/helper assertions, syntax and installed-help checks pass; no operational startup/SQL/import ran in preparation. The manual helper is ready for the user to run once. Working repair, restart, cutover, migrations, normal routine-registration diagnosis and U3 integration remain outside this batch. Earlier pending-authorization wording is superseded by the user's instruction; successful Phase 2-A alone did not authorize it.

## Execution update - Phase 2-B stopped at mysql.proc status warning

The user-run batch passed standard checks for all 34 application tables and 16 mysql tables, then stopped at mysql.proc warning plus OK. The candidate was gracefully stopped with exit 0; the seven protected database trees, both uploads and original export are unchanged. No restore, financial/evidence invariants, repair, force-stop, working change or U3 integration completed. [Exact findings and source interpretation](database-phase2-b-validation-restore-20261020.md) record the Aria open-count state warning and unchanged proc schema/data bytes, without diagnosing record corruption or migration 009's cause. A new continuation from a fresh complete copy of this cleanly stopped post-check candidate is proposed, retaining strict warning rejection and adding it as an eighth protected tree. That continuation requires separate approval under the stop-on-new-failure contract; no new operational command has run or been prepared. Keep XAMPP stopped and retain all attempts/markers/copies.

## Subsequent authorization - one strict fresh-copy Phase 2-B continuation

The user explicitly authorized the proposed continuation after the mysql.proc warning review. The [new manual helper and handoff](database-phase2-b-validation-restore-20261020.md) preserve the cleanly stopped post-check candidate as an eighth pinned tree and create fresh validation/restore copies with a separate single-attempt marker. The old helpers/probe/source manifest/markers are unchanged; every new warning still stops. Exact previous-failure/graceful-stop gates and all copy/identity/export/equality/shutdown boundaries remain. 36 continuation, 44 shared helper and 43 probe preparation checks pass; no operational startup/SQL/check/import ran. Next is the user's one manual command with XAMPP stopped. No repair, force, retry, migration, working restart/cutover or U3 resumption is authorized. The previous pending-approval statement is superseded by this instruction; successful continuation is not yet established.

## Execution update - continuation found a tables_priv index-structure error

The authorized fresh-copy continuation passed34 application and22 mysql checks, including mysql.proc, then stopped at mysql.tables_priv indexpage2/Corrupt errors. It gracefully stopped without restore or repair; the eight prior database trees/uploads/export remain unchanged. The latest stopped copy is retained as a ninth potential protected source. [Exact results](database-phase2-b-validation-restore-20261020.md) distinguish this index error from the earlier resolved status warning and retain incomplete financial/evidence/restore acceptance. [Phase3-A](database-phase3-a-index-trial-proposal-20261020.md) now proposes logical baseline capture, one target-index-only offline trial on a new complete copy, strict byte/row/grant preservation and conditional isolated validation/restore. It requires separate approval; no new repair helper, startup, repair, working change or U3 test has run. Keep XAMPP stopped and do not rerun completed helpers.

## Subsequent authorization - Phase 3-A copied-index trial prepared

The user explicitly approved the proposed narrow Phase 3-A scope. The [authorized handoff and new manual command](database-phase3-a-index-trial-proposal-20261020.md#authorized-preparation-and-manual-handoff) retain nine protected database trees, both uploads, original export and all previous helpers/markers. A stable saved logical/permission baseline with an explicitly verified full tables_priv scan is mandatory before any CHECK/repair. One offline single-quick index-only trial runs solely in a separate fresh complete copy; only its target MAI may change, with unchanged data/schema/control/other bytes and exact row/grant preservation. Fresh validation and application-export restore copies follow only if every gate passes. Strict warning rejection and one guarded graceful shutdown per server remain; no fallback/force/retry/working restart is included.

173 preparation assertions, syntax and installed-option help checks pass; preparation ran no operational main, database server/connection, CHECK, repair or import. User manual execution is the next action, not established repair success. Keep XAMPP stopped. No application/configuration/feature/migration/U3 change belongs to this batch. Original handshake cause, migration 009 error 1304 and working acceptance remain unresolved. The prior pending-approval entry is superseded by this explicit authorization.

## Execution update - Phase 3-A capture guard stopped before repair

The user-run Phase 3-A attempt stopped at the full-scan EXPLAIN guard before SELECT baseline, CHECK TABLE, repair or import. The candidate shut down normally with server/admin exit0; saved results report nine protected database trees/uploads/export unchanged and no working changes/force/migration/cutover/U3. Fresh read-only process/port inventory is clear. [Exact findings](database-phase3-a-index-trial-proposal-20261020.md#execution-outcome---capture-plan-gate-stopped-before-repair) identify that the actual query plan was not persisted, so its failed predicate/root cause is unknown. Do not loosen the guard, presume empty data, reuse the marker or rerun the helper. The next proposed separately approved step is diagnostic-only metadata/EXPLAIN capture on one fresh copy, then graceful shutdown/review; no repair, CHECK, restore or working restart. No new operational continuation was implemented or run in this review.

## Subsequent authorization - diagnostic-only plan capture prepared

The user approved one fresh-copy startup to capture actual index/EXPLAIN metadata, followed by graceful shutdown and review. [Separate helper/command and evidence](database-phase3-a-index-trial-proposal-20261020.md#authorized-diagnostic-only-continuation---prepared-execution-pending) preserve all ten existing database trees, uploads/export, earlier sources and consumed markers. The new diagnostic stores observations before interpretation and cannot perform row SELECT/financial inspection, CHECK, repair or import. The original ALL/no-index classifier is unchanged. 109 offline/helper assertions, syntax and installed-help checks pass; no operational main/startup/database connection ran in preparation. Next is the user's one manual diagnostic command with XAMPP stopped. No working restart/cutover, repair retry, migration, forced stop or U3 test is included; the actual plan must be reviewed before any further decision. The prior pending-approval note is superseded only by this explicit authorization.

## Execution update - plan diagnostic succeeded; ALL-only mismatch identified

The user's metadata-only diagnostic saved the expected index inventory and actual system/key-null/rows1 plan, then shut down gracefully with server/admin exit0. [Detailed interpretation](database-phase3-a-index-trial-proposal-20261020.md#diagnostic-execution-complete---actual-system-plan-identified) identifies MariaDB's zero/one-row optimizer path as the immediate ALL-only guard mismatch. It does not establish a captured baseline or erase the prior index corruption. Matching-version first-row source can use a primary key internally, so a corrected reader must prove complete index-independent enumeration instead of simply allowing system. Saved results and independent hashes verify ten prior database trees/uploads/export unchanged; the stopped diagnostic is retained as an eleventh database tree. Fresh process/listener inventory is clear. No CHECK/repair/import, application/configuration change, new startup or SQL ran in result review. Next is a user decision on corrected baseline-reader/trial preparation under all existing preservation/index-only/grant/validation/restore boundaries; diagnostic-only authorization did not grant a repair continuation. Keep XAMPP stopped; U3 integration remains incomplete.

## Subsequent authorization - corrected direct baseline and copied-index trial prepared

The user authorized preparing the corrected Phase 3-A trial. [New source/handoff/manual command](database-phase3-a-index-trial-proposal-20261020.md#corrected-phase-3-a-trial-authorized-and-prepared) retain earlier files/markers and eleven protected database trees/uploads/export. The new source-audited reader directly enumerates grant rows without an index name, continues through explicit EOF, checks no-index session counters and exact stable full snapshots; it does not whitelist system plans. Only complete baseline permits the same one-off offline target-index repair and conditional strict byte/row/grant/invariant/evidence/application restore checks on fresh copies. 223 preparation assertions, syntax/installed-help/source checks pass; no operational main/server/database connection/HANDLER/CHECK/repair/import ran. User execution is pending; runtime incompatibility or any issue stops before further work without fallback/retry/force. Working restart/cutover, migrations/configuration/application/flags and U3 remain outside scope. The earlier pending-decision handoff is superseded by this explicit authorization, without claiming the actual repair or root-cause resolution.

## October 21 execution - copied index rebuild completed, moved-state stop

The corrected user-run batch captured two stable complete baselines, completed one offline index-only repair and verified a fresh validation copy's logical baseline still matched. Its57th table check then stopped at tables_priv moved-system/zerofill-required, after56 clean checks. Remaining checks/invariants/evidence and restore did not run. Both servers shut down normally; all prior sources/uploads/export and new artifacts are preserved. [Detailed outcome](database-phase3-a-index-trial-proposal-20261020.md#corrected-trial-outcome---index-rebuild-ran-moved-state-validation-stop) records read-only evidence that the offline repair persisted STATE_MOVED despite matching UUIDs; no additional mutation was run. [Proposed Phase3-B](database-phase3-b-normalization-proposal-20261021.md) would normalize only the copied target's recovery metadata, potentially changing its MAI/MAD bytes under a new explicit scope, then require complete logical preservation and strict validation/restore. It remains pending authorization. Keep XAMPP stopped; no Phase3-A retry, working restart/migration/cutover or U3 work is included.

## October 21 execution - Phase 3-B passed database checks; file restoration remains

The user subsequently authorized and ran Phase3-B's single copied recovery normalization. [Execution details and proposed remaining work](database-phase3-b-normalization-proposal-20261021.md#execution-outcome-normalization-and-database-checks-passed-attachment-paths-stop) record exit0/empty stderr, target-MAI-only byte changes, preserved full pre-check baseline,65 clean table/view checks,70 zero-orphan foreign-key checks and no accounting-integrity findings. The next file check stopped at3 missing receipt filenames that are absent from both protected preserved and working uploads. Read-only original-export inspection found preserved files under other names that match all recorded hashes, sizes and MIME types for all3 references. No file was restored or metadata/record changed. Both candidates shut down gracefully; all17 database and2 upload trees remain unchanged and offline inventory is clear. Full attachment checks/post-check equality and application restore remain unperformed. The proposed bounded completion would recover only these3 names in fresh rehearsal upload storage and finish validation/restore without any further database repair. That new file-copy scope needs authorization; no new helper/startup/copy ran during result review. Keep XAMPP stopped; no working cutover or U3 integration is included.

## Subsequent authorization - complete filename recovery and validation/restore

The user explicitly approved the bounded completion. [Prepared helper/probe, verification and new one-shot command](database-phase3-b-normalization-proposal-20261021.md#authorized-completion-prepared-verification-and-manual-handoff) bind the3 additions to original-export receipt metadata and recorded hash/size/MIME, only inside fresh rehearsal uploads. Full database/table/foreign-key/accounting/evidence before/after checks and a separate original-export restore remain mandatory; existing sources, protected uploads and earlier helpers are immutable.264 preparation checks and syntax/pin/argument checks pass with synthetic files/PDO doubles only. No real receipt recovery, database startup, SQL or import ran during preparation. Manual execution is pending; no repair, working restart/cutover, migration, grant change or U3 task is authorized by this continuation.
