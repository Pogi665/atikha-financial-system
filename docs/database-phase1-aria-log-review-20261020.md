# Corrected Aria check findings and proposed log review

October 20, 2026. **The authorized copied-log display/check completed successfully; no repair, replay or server launch ran. The next proposed diagnostic stage is not yet authorized.** This follows the [control-context correction](database-phase1-checker-correction-20261020.md) and [preservation plan](database-preservation-recovery-plan-20261020.md). Both servers remain stopped; no repair or server launch has occurred.

## Corrected check result

The user manually ran `database_phase1_check_context.ps1 -CheckFreshContext`. Private evidence:

```text
C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\check-context-20261020-043052-9c47421f
```

Saved results at **04:31:17 +08** confirm the corrected options used this fresh copy's `--datadir`, `--logdir` and required control file, with read-only/update-state-disabled checking and no warning suppression:

- **1/24, columns_priv:** exit 0, no stderr, passed. The earlier `3 > 0` warning did not recur with valid context.
- **2/24, column_stats:** exit 0, no stderr, passed.
- **3/24, db:** exit 1, not a timeout; the checker reported table creation transaction ID **147** above the copied control maximum **145**. It reported two data records and ran file-size, key-chain/index-reference and record-link checks; the saved output contains no additional index/record error. The transaction-state warning remains unresolved.
- The batch stopped; **21 tables remain untested in this batch**. Do not treat the first two passes as full system-table health.

At **04:31:41 +08**, the post-check certificate confirms the active original, preserved data, old examination data and fresh corrected-check data all remain byte-identical to tree hash **B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD**. Copied control-file size/version/CRCs/max145 are recorded. Fresh process/listener inspection found zero relevant processes and zero 3306/3307 listeners. No repair, zerofill, table replacement, grant edit or restart occurred.

## Interpretation and limits

This result uses real copied control context; it is different from the first helper's zero-context warning. However, the version-pinned MariaDB implementation states that the persisted maximum is updated at clean shutdown and logs can contain newer information after a crash. Since the current snapshot follows an explicitly authorized abrupt stop, a stale persisted transaction boundary is plausible. That is an **inference**, not an established explanation for every earlier crash or the original handshake failure. [MariaDB 10.4.32 control-file lifecycle](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_control_file.c#L97).

The `147 > 145` warning by itself is insufficient grounds to repair or zerofill `mysql.db`. Do not change transaction IDs/control bytes or suppress the warning to obtain a pass. Current SQL financial counts, InnoDB integrity, routine registration and server readiness remain unverified. No recovery action is selected from this warning alone.

## Next diagnostic proposal: one display-only copied-log inspection

Before deciding on a repair or candidate server startup, inspect the copied Aria transaction logs for readable record/transaction history consistent with the warning. This proposal required narrow continuation approval because the corrected batch's stop condition had been reached; the user has now given that approval. It is not an automatic table-check retry.

1. Keep every database server, Apache and checker stopped. Verify binary/source preservation pins and all four existing database trees. Preserve every earlier script/log/attempt marker.
2. Create **one new complete copy** of the verified preserved data in a unique private `log-review-<timestamp-guid>\data` folder under the current diagnostic root. Refuse existing destinations; verify full tree equality. Use separate temporary/log output directories outside that data tree.
3. Use only the installed reader, whose observed SHA-256 is **FC9121B314E0E7C6778A33DFDEF9E59A74236ECF3E5BDC7B64141DA2E77B32AA**. Check installed options in help mode first, with explicit `display-only=TRUE`, `check=TRUE`, `apply=FALSE`, `undo=FALSE`.
4. Proposed actual invocation shape, with placeholders resolved by a guarded helper:

```powershell
& 'C:\xampp\mysql\bin\aria_read_log.exe' --no-defaults `
    --display-only --check --skip-apply --skip-undo `
    '--aria-log-dir-path=<fresh complete copied data>' `
    '--tmpdir=<new attempt temporary directory>'
```

5. Set the working directory to the fresh-copy attempt area, capture all output privately, and bound this single reader process to 30 seconds. On timeout, cancel only that captured reader, then stop/report. Never launch `mysqld`, request log apply/undo or run another table checker.
6. Rehash the original and every copy, including the new log-review tree. Require unchanged bytes. Stop/report on missing logs, warning/error, changed files, unexpected process or timeout. Preserve all evidence and do not retry automatically.
7. Review readable record types/LSNs/transaction context privately, then prepare the evidence-based next plan. A successful log scan does not establish commit durability, restore current SQL access, prove all tables healthy or authorize replay. Any later crash-recovery/startup proposal must remain confined to a disposable copy and obtain its own authorization.

Installed help confirms apply mode modifies tables, undo defaults to on, and the explicit `--skip-apply --skip-undo` flags disable both. A non-operational compatibility call with the proposed mode flags plus `--help` returned exit 0 and printed `apply FALSE`, `undo FALSE`, `display-only TRUE`, `check TRUE`. No actual transaction log was scanned in preparing this proposal. The source dispatches display/check separately from apply. [Official reader documentation](https://mariadb.com/docs/server/clients-and-utilities/aria-clients-and-utilities/aria_read_log), [10.4.32 implementation](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/maria_read_log.c#L85).

**Approved scope:** one copied-log display/check batch only, with no apply/undo, repairs, table-check retries, server startup, configuration/permission changes, migrations or cutover. The user continues the established manual-command workflow after a guarded helper is prepared and tested. U3-A remains incomplete and later U3 checkpoints remain unstarted.

## Authorized manual helper and verification

Prepared `scripts/database_phase1_read_logs.ps1`. It rejects missing opt-in, an existing attempt marker/destination, a running server/client/checker/listener, changed preservation evidence, binary/configuration drift, nonordinary path ancestors, overlapping paths, missing copied logs or changed database hashes. The new copy comes from the preserved full data, not an earlier examination attempt. Required copied control context is CRC-validated with stored maximum 145. Temporary and output files remain outside the copied data tree. There is no server-launch, SQL, repair or apply/undo branch.

Help compatibility checks the complete proposed option set and requires the four effective mode values. The single actual reader call has a 30-second bound. Exit zero alone is insufficient: the classifier also requires the display-only banner and explicit `aria_read_log: SUCCESS`, rejects stderr/warnings/`DOUBTFUL`/failure, and sends empty or incomplete output for review. This follows the version-pinned reader's result behavior: some unsuccessful/inconclusive paths can exit zero. Final full-tree hashing checks the original, preserved copy, first examination copy, corrected-check copy and new log-review copy; changed bytes are preserved for investigation, never reset to manufacture equality.

**Preparation evidence:** PowerShell parsing, C# control-parser compilation and **40 utility assertions passed**. Coverage includes fixed safe arguments, data/temp overlap rejection, help effective values, zero-exit doubtful/failure/missing-completion/empty-log handling, stderr/nonzero/timeout handling, checksummed synthetic control parsing and CRC rejection, complete synthetic copying and unchanged-source verification, changed-byte detection, evidence overwrite rejection, native argument quoting, output/exit capture and cancellation of a harmless synthetic child. The installed reader ran **help only** against a nonexistent synthetic data path and created no data directory. Private final test evidence: `.migration-private/phase1-log-review-test-4116385b45/results.json`. An earlier synthetic test attempt had a too-specific expected failure classification; the expectation was corrected and the complete suite rerun on fresh synthetic files. No real Aria log/table scan, operational recovery copy or server startup occurred during preparation.

Run once in the same Administrator PowerShell, with MySQL and Apache stopped:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase1_read_logs.ps1' -InspectCopiedLogs
```

Expected terminal marker: `COPIED_ARIA_LOG_REVIEW_COMPLETE` or `COPIED_ARIA_LOG_REVIEW_STOPPED_FOR_REVIEW`; a thrown error also means stop. Send the terminal result, leave servers stopped, and do not rerun or share private log output. Completion means a display/check run finished, not that recovery, table integrity, financial durability or normal startup has been established. Interpretation and the next recovery decision follow separately.

## Execution result and interpretation - October 20, 2026

The user manually ran the helper and supplied `COPIED_ARIA_LOG_REVIEW_COMPLETE`. Independently inspected private evidence is under `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\log-review-20261020-044727-5f056f22`.

- `reader-attempt.json` records the pinned reader, fresh copied log path and explicit display/check with apply/undo disabled.
- `results.json` at **04:47:52 +08** records native exit **0**, no timeout, `completed-display-check` and no halt reason. Stderr is **empty**; stdout ends with EOF and an explicit reader success message, without a reported warning/failure.
- Stdout contains **148 sequentially numbered record headers**, from LSN `(1,0x2007)` to `(1,0x7317)`, including 12 long-transaction-ID headers, 12 commit headers and 22 checkpoint headers. Indented `Rec#0` lines redisplay grouped records and are not additional sequential records. These are Aria engine records, not counts of financial journals or proof that every financial operation committed.
- `preservation-after.json` at **04:48:22 +08** records unchanged original, preserved, first-examination, corrected-check and new log-review data trees, all at the pinned `B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD` hash. No repair/replay/start ran. Fresh process/listener inspection also finds no relevant server/client/checker and no 3306/3307 listener. A subsequent independent read-only rehash confirmed all five trees still match the pinned hash, each with 2,768 files.

The printed maximum-long-ID value **0** is not evidence that the log contains no transactions or that the true maximum is zero. In this version, check mode scans without executing the transaction-ID recovery hooks that accumulate that value; the final output separately reports persisted control maximum **145**. The short IDs in headers are a different identifier and must not be compared directly with table creation ID 147. See [version-pinned scan/apply separation](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_recovery.c#L2594) and [long-ID accumulation](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_recovery.c#L561).

**Conclusion:** the requested non-applying log inspection passed its gates. It did not resolve `mysql.db` creation-ID **147 > 145**, identify the handshake failure's cause, establish InnoDB/application-table integrity or prove that normal crash recovery will succeed. Historical repair/incomplete-log record types describe saved history; they are not new repairs performed by this inspection. Do not repair/zerofill tables, edit control IDs, delete logs or restore October 8 data from this result.

**Next recommendation:** review [Phase 2-A: one isolated candidate startup and logical preservation](database-phase2-a-startup-proposal-20261020.md). This is a proposal only. It permits normal startup recovery writes solely on a fresh disposable copy if separately authorized, while preserving all five existing trees. Keep MySQL and Apache stopped meanwhile; U3-A remains blocked/incomplete.
