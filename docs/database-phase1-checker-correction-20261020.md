# Phase 1 Aria checker review and proposed correction

October 20, 2026. **One corrected offline-check batch is now authorized; the manual helper is prepared but not executed. Repairs/server starts remain unauthorized.** This reviews the user's manual Phase 1 result under the [preservation/recovery plan](database-preservation-recovery-plan-20261020.md). The original proposal below remains the batch scope.

## Observed result

The user ran `database_phase1_check_copy.ps1 -CheckExaminationCopy`. Saved evidence at `C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\offline-aria-checks` records:

- Start: **04:06:24 +08**; stopped after **2 of 24** tables.
- `mysql\column_stats.MAI`: exit **0**, no stderr, zero data records reported.
- `mysql\columns_priv.MAI`: exit **1**, not a timeout, zero data records reported. The diagnostic was:

```text
Table create_trd (3) > current max_transaction id (0).
Table needs to be repaired or zerofilled to be usable
```

The final tool summary calls the table usable but recommends fixing it. Do not execute those recommendations automatically.

The helper stopped at that first issue and **04:06:45 +08** post-check evidence confirms the original, preservation copy and examination copy still share the pinned tree hash **B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD**. A subsequent process/listener query found no relevant server/client/checker process or 3306/3307 listener. No repair, zerofill, warning suppression or server restart occurred.

## Checker setup defect

The supplied command used `--ignore-control-file` while retaining transaction-ID checks. MariaDB 10.4.32's source explicitly initializes the comparison maximum to zero when no control file is open. The checker then emits the observed warning if the table creation ID exceeds that value. Therefore this invocation cannot establish a real mismatch with the installation's actual control-file context. This was a diagnostic setup mistake, not evidence that the user ran the command incorrectly. [10.4.32 check initialization/status and maximum-ID logic](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_check.c#L121).

A separate read-only Python inspection of **preserved\data\aria_log_control**, based on the pinned version's documented-in-source field offsets, found:

- **52 bytes**, format version **1**.
- Fixed section **30 bytes**, variable section **22 bytes**.
- Both stored CRCs match independently computed CRC32 values.
- Stored maximum Aria transaction ID: **145**, rather than the ignored-file comparison value zero.

No UUID, receipt contents, credentials, grants or row values were displayed, and no file was written by this inspection. The file structure and native checksum checks are in the version-pinned [control-file implementation](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_control_file.c#L245).

**Conclusion:** the specific `3 > 0` warning is explained by omitting the control-file context. It does not establish damaged indexes/rows in `columns_priv`, and does not establish the cause of the server's original handshake failure. It also does not prove every table healthy: only two were checked, and InnoDB has not been validated. The stored control maximum is updated at clean shutdown; after an abrupt stop, later transaction information can exist in logs. Even a future warning with the correct control context must be interpreted with that limitation, rather than treated automatically as corruption. [Control maximum lifecycle](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_control_file.c#L97).

## One corrected attempt proposed for approval

Preserve the failed script/logs and all existing original/backup/examination trees. Do not remove the old attempt marker or rerun the old helper. Proposed new batch:

1. Reverify all servers/clients/checkers stopped, binary hashes and the original/preserved/existing examination tree hashes.
2. Make **one fresh complete copy** from the verified preserved data into a unique `check-context-<timestamp-guid>\data` directory under the same private diagnostic root. Refuse an existing destination and verify full tree equality. Keep temporary files/logs outside this new data tree.
3. Validate installed option compatibility without checking tables. Require the existing copied `aria_log_control`; if it cannot be read, fail closed instead of silently using zero.
4. Run a corrected check of `mysql\columns_priv.MAI` first, with this command shape and fully resolved new-copy paths:

```powershell
& 'C:\xampp\mysql\bin\aria_chk.exe' --no-defaults `
    '--datadir=<fresh complete data copy>' `
    '--logdir=<fresh complete data copy>' --require-control-file `
    --check --read-only --skip-update-state `
    '--tmpdir=<new attempt temporary directory>' `
    '<fresh complete data copy>\mysql\columns_priv.MAI'
```

5. If that result is clean, check the other 23 indexes once under the same control context. Stop at the first warning, nonzero exit, timeout or path/hash issue. Keep serial 30-second checker bounds and private per-table stdout/stderr/exit records.
6. Rehash the active original, preserved source, old examination tree and fresh check tree. Require unchanged bytes, including the copied control file. The control-file open path uses read/write access internally even for this check; that is another reason to target a fresh disposable copy and verify bytes afterward, rather than opening originals. [Native control-file open](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/ma_control_file.c#L254), [checker opens an existing control file and can require it](https://github.com/MariaDB/server/blob/mariadb-10.4.32/storage/maria/maria_chk.c#L128).
7. Report results and leave both servers stopped. No automatic repair, zerofill, transaction-ID warning suppression, table replacement, migration, candidate server launch or cutover.

This changes only the diagnostic context, not database values to force a pass. It is **one new, corrected offline-check batch**, with all prior evidence preserved. The earlier Phase 1 stop-on-new-failure instruction remains binding, so this proposal needs the user's narrow continuation authorization before execution. No new helper/command should be executed until that authorization is provided.

## Subsequent authorization and helper preparation

After clarifying that neither repairs nor server starts are part of the next step, the user explicitly replied **"then i authorize"**. This authorizes the one corrected offline-check batch above. Prepared manual command:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase1_check_context.ps1' -CheckFreshContext
```

The new helper preserves the prior checker/source/logs, pins the original data hash and checker binary, requires offline state and prior preservation evidence, reads the copied control format/checksums in memory, creates a unique complete fresh data copy, and checks `columns_priv` first. It uses that copy's required control file without suppressing transaction warnings, bounds each child, records exact arguments/results, checks index/data/control bytes after each table and all four data trees afterward. A durable fixed one-batch marker prevents another execution. Temporary/checker files stay outside database trees. No table check, actual database copy, repair or server launch has been executed by the agent in this preparation.

Verification: PowerShell parsing, compiled read-only control parser and **34 synthetic guard/compatibility checks passed**. Coverage includes complete synthetic copy/hash, overwrite/overlap/escape rejection, required/forbidden argument flags, control CRC/signature/layout/length rejection, native argument round trips (spaces/quotes/trailing slash/empty argument), nonzero exit capture and bounded child cancellation. The installed Aria executable parsed the full corrected options in `--help` mode only, with no control file present in the synthetic target and no target-file changes; it performed no table check. An initial synthetic test exposed a PowerShell ancestor-object typing issue; it was corrected and all 34 checks then passed before handoff. Final private test evidence: `.migration-private/context-checker-test-5b9366d691/results.json`.

Expected manual result is `CONTEXT_ARIA_CHECKS_COMPLETE` or `CONTEXT_ARIA_CHECKS_STOPPED_FOR_REVIEW`. Any error stops/reporting without a second attempt. Keep MySQL and Apache stopped; no later recovery phase is authorized by this continuation.

## Corrected execution result

The user manually executed the prepared corrected helper. `columns_priv` and `column_stats` passed, then `mysql.db` stopped the batch at 3/24 with a creation-ID 147 versus copied control maximum 145 warning. All four trees were verified unchanged at 04:31:41 +08. No repairs or restart occurred. The [new findings and copied-log review proposal](database-phase1-aria-log-review-20261020.md) now carry the next action; it requires narrow authorization. Do not rerun this helper or infer structural corruption from that transaction-state warning alone. Earlier pending-execution wording is superseded by this result.
