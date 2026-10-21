# MariaDB startup crash: preservation-first root-cause plan

October 21, 2026. **Planning only; execution is not authorized by this document. Stop for external review.**

The current incident handoff is [the October 21 shutdown diagnostic](database-shutdown-diagnostic-20261021.md). This plan does not replace its findings or rerun the earlier recovery process. The user supplied ChatGPT's review recommending offline dump analysis first, with fresh preservation before any separately authorized isolated reproduction. Only planning/handoff documentation changes in this task.

## 1. Scope, current state and non-assumptions

Find the failure path that precedes MariaDB's runtime breakpoint, then report evidence sufficient to propose a targeted next action. Do not select or perform a repair merely because the process crashes.

Confirmed incident evidence, collected earlier on October 21:

- XAMPP MariaDB 10.4.32 repeatedly exits during startup. The final eight logged attempts reach InnoDB initialization and socket creation without a confirmed ready-for-connections state.
- Windows Application Error records and the preserved dump agree on exception `0x80000003` at `mysqld.exe + 0x643842`. Offline image inspection identifies `my_parameter_handler + 2` and its breakpoint instruction. This is the immediate crash mechanism, not the initiating cause.
- The executable and main `bin/my.ini` match their pinned earlier-recovery hashes. Other effective settings, dependencies and database integrity are not thereby certified.
- At the last diagnostic completion, MySQL and Apache were stopped and the relevant listeners were absent. This is a dated observation; recheck before any future execution.
- Earlier successful recovery/SQL/login checks do not establish present stability. The old copies must not overwrite today's installation or legitimate activity recorded after their capture.

Do not assume table corruption, InnoDB/Aria damage, U3-A causation, migration-009 causation or a particular engine defect. Do not treat a successful copied startup as proof of financial integrity or a working-system repair.

**Correct application status:** U3-A is approved/pushed. The B-D plan was approved and implementation authorized. Release source and shared helpers were partially prepared before the pause; B's backend/browser gate has not passed. C/D are not enabled and their gates have not run. Thus B-D is **paused after partial B preparation**, not "paused before execution." Preserve all current uncommitted source/tests and approved U3-A artifacts. No B-D edits or fixtures belong to this diagnostic plan.

## 2. Authorization boundaries

Review this whole plan first. The recommended initial execution authorization is **Stage A only: offline acquisition/identity verification and analysis of the existing dump**. It permits no database-server launch or data-directory change. Any required debugger installation or sandbox/network access must use its applicable approval; obtaining a debugger does not authorize installing/upgrading MariaDB.

If A is insufficient, report why and stop. Stage B preservation and Stage C's exact single-launch manifest require their own explicit applicable authorization after review. Approval of this plan or of A must not silently activate B/C. Repair, promotion/restoration, a server-version change, normal working restart, SQL acceptance checks and resuming U3 are separate later decisions.

## 3. Stage A: existing dump, offline analysis first

### A1. Protect and identify the evidence

Use the existing private collection `.migration-private/shutdown-diagnostic-40c0e923b1`. Verify its collection certificate and file hashes. Work on a new private analysis copy, leaving the preserved dump and original logs unchanged. Record hashes, tool versions and provenance; keep outputs outside Git and outside public application routes. Recheck that no server has unexpectedly appeared; do not stop a process automatically if one is found.

Recorded identifiers to revalidate, not substitute for a fresh identity check:

- Dump: `mysqld.dmp`, 146,907 bytes; SHA-256 `5d70597175fa643948da40893cf0d27057b5b21c0eaf7a36e5f50aa766c75d73`.
- Installed image: `C:\xampp\mysql\bin\mysqld.exe`; SHA-256 `ee6380db7277e0a4ebe0ff255edd846eb3b4ee604804cf5db3bdde1be44b7e8a`.
- Main configuration: `C:\xampp\mysql\bin\my.ini`; SHA-256 `268b38bba89e1fd30e3dc9edbb5aa70ba30a97912d98cafc2d5283119d06e3a0`.
- Dump CodeView target: `mysqld.pdb`, GUID **`D0435555-C131-4E46-9091-FC529819BE77`**, age **1**. These values are decoded from the preserved diagnostic's recorded RSDS identifier. Re-extract/compare the dump and matching executable identities during execution.

Copy the exact image and only needed dependencies into private analysis storage without running them. Check each dependency against its own dump identity; a currently installed Windows DLL is not automatically the historical matching image. Use the dump's module metadata and report unresolved modules explicitly.

### A2. Obtain and verify tools and symbols

Use a trusted Microsoft x64 dump debugger, preferably an already available WinDbg/CDB. Earlier checks did not find it in the inspected standard locations; this was not an exhaustive machine-wide inventory. If unavailable, propose the exact official Microsoft acquisition method, destination, version and required permissions before installing anything. Prefer private/portable analysis tooling where officially supported; no global PATH, registry or system-debugger changes are implied.

The official [MariaDB 10.4.32 Windows listing](https://dlm.mariadb.com/browse/mariadb_server/76/1911/winx64-packages/) includes `mariadb-10.4.32-winx64-debugsymbols.zip` (approximately 129.7 MB), its detached signature and checksum files. This is a **candidate**, not a verified match for the XAMPP build. The archive directory was not accessible through the planning browser; do not invent a successful download or substitute an arbitrary mirror.

After authorization, download only from the official publisher or its explicitly identified distribution route into a fresh private cache. Record final URLs, sizes and hashes; validate published SHA-256 and the detached signature using an independently verified official signing-key fingerprint. If verification material/tools cannot be obtained, report the limitation and stop acquisition rather than marking provenance verified. Extract only needed symbols with path/reparse-point and overwrite protections. Never extract into XAMPP, the application tree's runtime directories or the current data directory.

Independently read the candidate PDB's GUID/age and require an exact match to the dump/image. Microsoft's [PDB terminology](https://github.com/microsoft/pdb-rs/blob/main/docs/terminology.md) describes this binding key. A same-version, same-name, different-build PDB fails the gate. Do not force mismatched symbols with ignore-mismatch options or substitute a recompiled/debug server binary. If the official package mismatches, one bounded publisher/source search may seek the exact build; log the candidates and stop as unavailable if no exact artifact can be established.

Microsoft's [public symbol server](https://learn.microsoft.com/en-us/windows-hardware/drivers/debugger/microsoft-public-symbols) may supply matching Windows/CRT symbols. Use a private cache and process-local symbol paths only. Do not assume it hosts MariaDB's own PDB. Do not upload the dump or logs. No untrusted debugger extensions, source-server scripts or auto-executed source retrieval are permitted.

### A3. Resolve the crash context

Open the **copied user-mode dump** as a dump target, never by launching/attaching to `mysqld.exe`. Configure image/symbol paths to private copies and verify each loaded module's symbol status. Follow Microsoft's [user-mode dump workflow](https://learn.microsoft.com/en-us/windows-hardware/drivers/debugger/analyzing-a-user-mode-dump-file) and [symbol verification guidance](https://learn.microsoft.com/en-us/windows-hardware/drivers/debugger/verifying-symbols).

The reviewed command list should be limited to analysis such as `.sympath`, `!sym noisy`, `.reload /f mysqld.exe`, `lmv m mysqld`, `.exr -1`, `.ecxr`, `kv`, `~* k` and `!analyze -v`, with private logging. Exact invocation paths will be prepared only for execution review. Do not use forced mismatches, live-target launch/attach commands, automated triage uploads or unreviewed scripts. Set an outer analysis time bound; on a hang, close only the owned offline debugger, never a database process. Use bounded tool waits with progress reporting.

Record exception thread/context, validated frame addresses, function/source locations where available, relevant assertion/error text and the frames before the handler. Separate a correctly unwound stack from raw stack candidates. Do not treat the debugger's automated bucket or a distant nearest-export label as a proven cause. Examine other captured threads if relevant, without publishing private memory contents.

The small minidump may lack required memory even with exact symbols. Missing pages/locals, optimized frames or unresolved symbols must be reported as limits; do not fill gaps with guesses.

### A4. Decision and deliverable

Produce a sanitized offline-analysis report, exact identity/provenance manifest and private full transcript. State one of:

1. An informative, validated failure path was recovered: describe observations and confidence, then propose a separately reviewed targeted diagnostic or correction. No repair follows automatically.
2. Exact symbols could not be obtained: record concrete acquisition/match failures.
3. Symbols matched but the dump was insufficient: identify missing context and the precise evidence needed from a reproduction.

Reverify preserved-artifact hashes and frozen application/configuration hashes. No full database-copy or financial-health claim is made. A successful Stage A means the analysis is trustworthy, not that MariaDB is fixed.

## 4. Conditional Stage B: fresh preservation before reproduction

Only if A is insufficient and preservation is separately authorized:

1. Verify absence of MariaDB/MySQL variants, Apache, diagnostic children and relevant listeners/services. Verify exact canonical source paths and reject unexpected junctions/reparse points. If any process or uncertain ownership exists, stop without terminating it.
2. Inventory space and permissions against measured current data/upload sizes, allowing the verified preservation copy, separate examination copy, logs and potential larger dump. Select new uniquely named private storage outside the served application/data paths. Reject existing destinations and overwrites. Protect copies/configuration as sensitive local evidence.
3. Hash and copy the **entire current stopped** `C:\xampp\mysql\data`, not selected tables. Include InnoDB/Aria files, system schemas, application/test schemas, control files, existing logs/dumps and copied directory-local options. Preserve current effective-option inputs, main configuration, binary/plugin/dependency identities and protected uploads in their entirety. Resolve upload roots from current configuration privately; do not assume one receipt folder is the whole protected store. Preserve current application source/diff as a separate private snapshot, including the partial B work. Do not expose credentials or sessions in public outputs.
4. Independently verify source-before, copy and source-after file manifests; reject missing/extra files, changed contents or copy errors. Existing recovery archives remain untouched. Hash equality establishes byte preservation, not logical/database health.
5. Make a separate examination copy from the newly verified preservation. It must not use hard links or writable junctions back to original/preserved files. Verify hashes again. Keep the preservation immutable during the trial.

Stop for review with a preservation certificate and prepared launch manifest. **B starts no server, checker, SQL client, repair, import or migration.** If any mismatch occurs, retain evidence and stop; no repeated copy/repair fallback is implied.

## 5. Conditional Stage C: at most one copied diagnostic launch

Prepare and review this setup after B, then obtain explicit authorization for its **exact** one-attempt manifest. Do not reuse earlier failed candidates or consumed recovery commands.

- Use the matching MariaDB 10.4.32 executable/dependencies copied into the new trial area, never MySQL80 or an upgraded/debug replacement. DLL resolution, plugin paths and any default search must be audited. No symlink or default option may point writable state back to working/preserved storage.
- Use an explicit option file and audited process-local environment. Inventory all effective option sources; redirect every possible writable location to trial storage: datadir; InnoDB system/redo/undo paths; Aria logs/control; temporary files; error/general/slow/binary/relay logs; replication/master metadata; PID/socket paths; buffer-pool dumps; crash dumps; debugger logs and Windows error-report output where applicable. Reject absolute paths in copied metadata/configuration that would escape the trial. Use copied/read-only message/plugin assets. If a path cannot be proven isolated, stop before launch.
- Bind only to `127.0.0.1` on a verified free distinct port (proposed 3307, subject to fresh ownership checks); keep working 3306 unused. No working application, Apache, SQL client or browser connects. Disable event execution, automatic replica start and automatic table repair using options verified for this exact binary. Do not add `skip-grant-tables`, force-recovery settings or other speculative changes that bypass the suspected startup path.
- A normal engine startup can perform crash recovery and alter **examination-copy bytes**. That expected behavior must be stated; it is not a no-write trial. No explicit table repair/check, financial statement, grant change, old migration or SQL restore is permitted. Log each option difference and its potential to change the failure; disabling automatic repair may itself change reproduction behavior.
- Launch once under the approved debugger/console, without automatic restarts. Stop at the relevant fatal exception and capture the true pre-handler stack, assertion/output and exact process exit status. Define debugger handling of routine first-chance events in advance so they are not mistaken for the target failure.
- Proposed readiness/failure observation limit: 60 seconds, excluding reviewed debugger capture time; capture itself is limited to five minutes. Readiness is observed through captured process/log state only, not application traffic. If the server remains alive or paused, the manifest must specify how the owned candidate exits safely. No forced termination is implied. If graceful exit cannot be established under the separately authorized procedure, stop/report its PID and leave its files untouched; do not restart XAMPP, copy/move an active directory or spend the launch budget on another attempt.
- Before launch, review synthetic/function-only guard tests for path escapes, reparse/hard-link hazards, incorrect PID/executable/start-time identity, port conflicts, malformed/mismatched symbols, timeouts and cleanup ownership. Do not test guards by launching against working data. After the attempt, verify originals/preservation/uploads/source pins unchanged and retain the altered examination copy separately.

One launch is the entire reproduction budget. Failure to reproduce, successful startup, missing symbols, a new error or a timeout leads to a report, not another launch, alternate repair, version trial or cutover. If C cannot be safely isolated with concrete reviewed controls, do not execute it.

## 6. Explicit stop conditions and exclusions

Stop immediately for evidence/hash drift, unverified publisher/tool, PDB mismatch, missing required dump context, unexpected process/listener, ambiguous identity, insufficient disk space, unsafe copy/path/permission, effective-option uncertainty, external/application activity, unexpected writes outside the trial, new table warnings, unresolved shutdown or exceeded time/attempt budget. Retain evidence and state what did and did not execute. Do not bypass the guard to obtain a passing result.

Across this plan, never repeatedly press XAMPP Start; delete/regenerate InnoDB or Aria files; copy XAMPP's backup/system tables over data; run repair tools; reinstall/replace MariaDB; edit working `my.ini`; change working ports/grants/flags; run old migrations; overwrite current data with an older recovery; start Apache; perform application/financial writes; run U3 fixtures; commit or push. Migration-009 error 1304 remains separate unless direct evidence establishes a link.

No diagnosis implies permission to repair. A later fix requires its own concrete scope, current-data preservation, isolated verification, acceptance criteria and authorization. U3 resumes only after the environment is separately shown stable and the user resumes that checkpoint; existing prepared source is retained throughout.

## 7. Planning handoff and next action

This task researched official documentation/package listings, read the current incident/evidence metadata and decoded the already recorded PDB identity. It downloaded no symbol/tool archive, installed/executed no debugger, ran no dump-analysis session, copied no database, started no service, issued no SQL and changed no application/test/configuration file. These are planning results, not new crash-analysis or database-health results.

**Next: external review of this plan.** After review, the recommended first authorization is A only. No operational command is provided for immediate execution. The current incident diagnostic and existing private evidence remain unchanged.
