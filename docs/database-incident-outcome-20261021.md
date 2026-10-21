# Database incident execution outcome: preservation and diagnosis passed; recovery stopped

October 21, 2026. Executed under the user's full [coordinated envelope](database-incident-u3-execution-envelope-20261021.md). **A required current-data validation gate failed. No repair, working cutover or U3 resumption occurred.**

## 1. Crash cause established within the available evidence

The signature-verified official MariaDB symbol package contains a PDB exactly matching the preserved XAMPP dump: GUID `D0435555-C131-4E46-9091-FC529819BE77`, age1. Microsoft's existing signed Windows debugger engine resolved an actual unwound stack from the copied dump, with no debugger installation or MariaDB launch during offline analysis.

The pre-handler path is:

`buf_load` -> background page read -> change-buffer merge/bitmap access -> `buf_page_get_low` -> `ib::fatal` -> `abort`/`raise` -> breakpoint.

The saved bitmap caller has `DB_PAGE_CORRUPTED`, and the failing read exhausted100 retries. Tablespace9 maps to the current `atikha_finance/budgets.ibd`. Read-only checksum inspection of a separate verified copy confirms **page1, the change-buffer bitmap page, is invalid**. Separately checked pages2/3/4 pass. No matching redundant space9/page1 page was found in today's preserved `ibdata1`.

This explains the observed startup abort during buffer-pool preload. It does not establish how or when the bytes became damaged or attribute the damage to U3-A, migration009, hardware or a particular software defect. Exact symbols name the shared breakpoint address `my_sigabrt_handler`; the earlier nearest exported `my_parameter_handler` label did not identify the initiating failure. No conclusion relies on an exploratory raw-stack scan.

## 2. Exact correction performed

**None.** No page checksum/header was rewritten, table/index rebuilt, migration run, account/grant changed or data restored. Disabling startup buffer-pool preload was used only on an isolated capture copy to attempt reading today's logical data. It is a diagnostic mitigation, not a corruption repair or a working configuration change.

## 3. Preservation and rollback status

Fresh restricted private archive:

`C:\xampp\mysql\recovery-exports\incident-20261021-093316cb4b`

It preserves the full current database (3,609 files,495,241,246 bytes), current application working tree including Git/uncommitted B source and dependencies, all53 protected upload files, MariaDB binaries/share/configuration, crash evidence, helper inputs and existing dump-referenced dependencies. Historical `.migration-private` fixture caches were excluded from the application copy; current incident evidence and private helper inputs were preserved separately. There were no hard links or writable junctions. Source-before/copy/source-after manifests matched.

Final verification rehashed both original and preserved data/uploads/bin/share and confirmed equality with Phase0. The preserved application is complete;3,877 current non-documentation application files are unchanged. All database/Apache/client processes and relevant listeners are absent. Only handoff documentation and private diagnostic tooling changed.

The original working directory remains `C:\xampp\mysql\data`. It was not replaced or restarted. Older recovery archives remain untouched. No cutover happened, so no rollback operation was needed. Fresh preservation must not be confused with an older baseline or discarded after this stop.

## 4. Isolated capture and failed validation gate

One fresh **unrepaired capture candidate** was started on loopback3307 with copied MariaDB binaries, isolated paths, events/replication start disabled, read-only mode and the diagnosed startup preload disabled. No force-recovery or grant bypass was used. Authenticated SQL confirmed the expected private datadir, version10.4.32 and force recovery0. It reached readiness; this was not a passing full validation or repair.

The capture then attempted the existing read-only full application/schema/permission fingerprint. It failed at `SHOW CREATE TABLE atikha_finance.audit_logs` with **error1932: table does not exist in engine**. The candidate error log reports **expected tablespace ID7, found4294967294** for `audit_logs.ibd`. Startup also reports the system `mysql.func` table marked crashed. These are additional structural failures; journal/evidence/accounting totals were not fully validated.

Consequently, a complete trustworthy **current pre-correction logical baseline could not be established**. A candidate that merely starts with preload disabled cannot pass the envelope's financial/history-preservation gate. No old logical snapshot was substituted, and no post-repair state was mislabeled as "before." The log's suggestion to recreate an object or run an upgrade was not followed.

The owned candidate was gracefully shut down after revalidating its PID, exact image,3307 ownership and authenticated identity. No force stop or retry launch followed. The generic reproduction stage was unnecessary because the existing dump already exposed the crash path.

## 5. Working normal startup and application acceptance

**Not run.** Working MySQL/Apache remained stopped. No working SQL, login/MFA, provider request or financial/draft/evidence mutation was performed. Normal startup, role/privacy acceptance and full current financial integrity remain unverified. Recoverability of the affected records has not been determined; this report does not declare them lost.

## 6. U3-B result

Partial release/shared presentation source remains preserved and uncommitted. Earlier52 pure adapter assertions remain dated preparation evidence. B's backend/browser/real-post-recovery gate is incomplete; no financial fixture ran during this incident.

## 7. U3-C result

Not enabled or verified. No liquidation implementation gate was advanced.

## 8. U3-D result

Not enabled or verified. No return/proof-confirmation implementation gate was advanced.

## 9. Combined final regression and review package

Not run or produced. Earlier U2/U3-A results remain dated evidence. The full U3-B-D exact-source ZIP cannot truthfully be delivered as complete while prerequisite environment/data gates fail. Approved U3-A packages were not changed. No commit/push occurred.

## 10. Execution limits, retained evidence and next action

Private manifests, source snapshots, signature/key/checksum verification, dump stream/thread inventory, debugger transcripts, read-only page probes, candidate manifests/identity/capture error and graceful-shutdown logs are retained beneath the archive. A sanitized [identity/provenance manifest](database-incident-manifest-20261021.json) contains identifiers only, not dump memory, credentials, receipts or database exports.

Preparation tool issues are retained separately: Python cached directory-entry link-count metadata, an overly broad archive-path guard, a checksum filename-prefix parser, Git GPG path formatting, an initial debugger API method map and MariaDB boolean-variable JSON formatting. The first two stopped before server launch; the corrected API map resolved exact symbols and the identity continuation revalidated/stopped the same candidate without a new launch. None is counted as a successful application test or used to waive a data-integrity failure.

**Hard stop:** current audit/history data cannot be fully validated, and the observed failures extend beyond the single startup symptom. Do not promote, mask checksums, drop/recreate financial/audit tables or resume U3. A further current-state damage/recoverability assessment must establish which intact authoritative data can be recovered and whether the necessary operation fits a separately defined recovery scope. Any use of retained earlier material requires proven identity and reconciliation with today's legitimate data, not automatic restoration. This is not a proposal to restart the old repair sequence or rerun historical migrations.

U4/U5, U-PERF-01, original Stages4-7, full Atikha acceptance and independent migration009 diagnosis remain outstanding and outside this execution.
