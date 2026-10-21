# Phase 4: promote the verified recovery and check working-port availability

**Current outcome: database recovery, normal XAMPP startup and scoped Administrator login/read checks passed.** The cutover command is consumed. [Final smoke evidence](#scoped-normal-login-and-application-reads-completed) supersedes pending-login statements below; full working/Atikha acceptance and U3-A integration remain separate.

**Current outcome: the user-run working cutover passed; the manual command is consumed and must not be rerun.** Normal XAMPP startup/login and application acceptance remain unverified. [Execution result](#execution-result-working-recovery-verified) supersedes the preparation-only wording below.

October 21, 2026. The user instructed proceeding with database recovery after the [successful Phase 3-B completion](database-phase3-b-normalization-proposal-20261021.md#completion-execution-passed-isolated-validation-and-restoration). This prepares the next concrete one-shot manual operation. Preparation has not moved a working folder, recovered a working filename, started a database server or executed SQL. U3-A source remains unchanged.

## Concrete change

Use the **cleanly stopped validation data directory** from completion attempt `phase2b-20261021-021041-339e0626`, not an old October 8 recovery folder or a partially failed target. Its full tree SHA-256 is `8C636E79682862010BACE969DC9CE69DFB35D142123A6FFF1C6C956A183192BB` (2,767 files). Its complete logical baseline matched the original before/after65 table/view checks,70 zero-orphan checks, accounting-integrity and evidence verification. The separate original-export restoration passed with the same logical/evidence/permission results.

The new helper will:

1. Pin the private specification, completed result/reports/clean stops/manifests, original SQL export/full baseline, installed executables, working configuration, accounting readers, prior recovery helpers and all three prepared U3-A application files. Reject unexpected database-client environment settings. Require MySQL, Apache, recovery tools and ports3306/3307 stopped/clear.
2. Rehash22 protected trees: the17 earlier database trees and2 original upload trees, plus the completion's validation/restore databases and recovered rehearsal uploads. Reject reparse points/external tablespace links, drift, existing attempt markers or staging/archive destinations. Require enough free space for staging plus headroom. These checks can take several minutes.
3. Allocate a fresh private `phase4-<stamp>` evidence directory and one-shot `evidence/phase4-cutover-attempt.json` marker under the existing recovery base. Copy the **whole** validated data directory to `C:\xampp\mysql\data-recovered-stage-<stamp>` and require byte equality. No table repair, initialization, SQL import or migration is performed.
4. Check installed option compatibility in help mode. Help's datadir is explicitly overridden to a new empty private directory so it cannot inspect the original working mysql.plugin table. Audit that override and every other expected option. The actual launch omits this help-only override.
5. Recover exactly the same3 receipt filenames in **working uploads**, using original preserved bytes and every recorded hash/size/MIME. Preflight all destinations, exclusively create each new file, reject overwrite/traversal/reparse points, preserve all50 prior entries and prove exactly3 additions. No receipt row, recorded path, evidence association or financial amount changes. Partial failure stops and retains the exact files/evidence for review; no automatic removal/retry.
6. Reverify offline state, exact absolute paths, staging/original hashes and absent archive. Rename `C:\xampp\mysql\data` to `C:\xampp\mysql\data-before-recovery-<stamp>`, then rename the verified staging directory to `C:\xampp\mysql\data`. Both destinations are resolved and restricted to the named mysql parent before moves; no recursive deletion, overwrite or shell cross-composition is used. Verify the archived original still matches its original pinned hash and the promoted directory matches the validated source before startup. Record each completed move separately.
7. Start the promoted directory **once on127.0.0.1:3306**, with Apache off. Use a new explicit private option file, pinned binary and captured native process handle/creation identity. All writable runtime paths point to the promoted data or fresh private evidence/tmp/export directories. Require ordinary grant authentication, `read_only=1`, events/logging/replication start/automatic Aria/MyISAM repair disabled and force recovery0. The existing `bin/my.ini`, application configuration and feature flags are unchanged; the temporary launch settings do not alter future XAMPP defaults. Copied data/my.ini is retained but is not selected by this explicit launch.
8. Require readiness within60 seconds, the exact loopback listener owner, authenticated SQL datadir/PID/version/safety settings and effective paths. Reject any server warning/error. Perform65 strict table/view checks,70 zero-orphan checks, accounting/advance/correction integrity, receipt metadata and every attachment check. Compare complete logical baselines before/after to the original and evidence to the passing rehearsal. SQL is limited to existing inspection/session/transaction commands; read_only does not make root incapable of writing, so exact pre/post logical comparisons remain required.
9. Request one authenticated graceful shutdown of the held, reverified working server; no forced stop or retry. Require server/client exit0 and no remaining server/listener. Rehash the archived original and all other protected sources, verify the recovered uploads/source/configuration pins again and save the final working-tree manifest. Leave both MySQL and Apache **stopped** for result review.

The private specification `.migration-private/phase4-cutover-inputs.json` binds the exact report/file/source pins and private receipt mapping. Its SHA-256 is embedded in the new helper. Do not publish it, database exports, session data, receipt names/images or permission contents.

## Stop conditions and rollback

Any unexpected file, table warning, integrity difference, source/configuration drift, competing process, unknown listener or uncertain shutdown stops this batch. No fallback repair, repeated launch, SQL restore, forced termination or automatic folder rollback occurs. If a server remains running, do not move/copy its files or start XAMPP.

The original directory is preserved, not deleted, under its exact saved archive path. If promotion fails between renames, the archive and staged directory remain intact and the move certificates identify what happened. Do not start the known-broken archived original. A later rollback is an **offline, separately reviewed** folder operation: first verify no server/listener, preserve the promoted directory under another fresh name, verify the archived original's hash and restore its original name only if needed. Leave that original stopped. Never overwrite new legitimate application activity with an old baseline. The3 added verified filenames and their saved manifest remain preserved; there is no automatic file deletion.

## Verification during preparation

New files: `scripts/database_phase4_cutover.ps1`, `scripts/database_phase4_probe.php`, `scripts/test_database_phase4_helpers.ps1` and `scripts/test_database_phase4_probe.php`.

**202 preparation assertions passed:**66 new function-only PowerShell checks,18 new PHP checks,55 reused completion-helper checks and63 reused file-recovery/metadata checks. They cover completed-predecessor requirements, path boundaries, SQL/PID/listener identity and wrong-port/owner rejection, settings/argument separation, provider blocking, exact recovery metadata and complete report rejection. Tests use synthetic private files and saved reports; they invoke neither operational main nor SQL, folder promotion or real receipt recovery. PHP lint and PowerShell AST parsing passed. Installed MariaDB help mode accepted the exact temporary launch options with the audited empty-datadir override; this is option compatibility, not a server startup or working acceptance test.

Reused file-recovery and inspection functions remain pinned. Preparation evidence stays under `.migration-private/phase4-*`. The actual working-port inspection and promotion have not run; only the manual command below can establish those results.

Fresh read-only hashing separately verified the current original working directory (2,768 files), validated source (2,767 files) and recovered rehearsal upload tree against their existing pins. The process/listener inventory is clear, all39 pinned inputs match, all22 protected roots are distinct and the new marker is absent. The full22-tree preservation scan runs again in the manual helper before working mutations; preparation did not claim a fresh full22-tree rehash. No working filename or directory was changed during these checks.

## Manual command: run once

Keep XAMPP **MySQL and Apache stopped** and avoid other application/database clients. In Administrator PowerShell:

```powershell
& 'C:\xampp\htdocs\Financial Management System\scripts\database_phase4_cutover.ps1' -PromoteVerifiedRecovery
```

This command changes the working data-directory placement and adds the3 verified working receipt files, while retaining the originals. It then verifies the recovered database on3306 and shuts it down. It can take several minutes. Avoid console text-selection mode; if the title says **Select**, press Esc once. Do not interrupt or repeat the command.

Send the terminal result and leave XAMPP stopped. Expected success is `PHASE4_WORKING_RECOVERY_VERIFIED`. On a review stop, no restart/repair/retry is authorized by these instructions.

## Remaining boundary

Success means the recovered working directory passed a guarded3306 startup/full inspection/graceful stop. **Normal XAMPP startup, browser login/MFA and application acceptance are the next bounded check**, with provider effects controlled and no synthetic financial postings. They are not claimed by this helper. Atikha acceptance and U3-A integrated verification remain separate. The original handshake root cause and migration009 error1304 remain unresolved; no old migration is rerun or certified by this existing-schema recovery.


## Execution result: working recovery verified

The user ran the command once. Attempt `phase4-20261021-085934-caebe39c` completed with `PHASE4_WORKING_RECOVERY_VERIFIED`; saved results show no halt or shutdown issue.

- The whole validated directory was promoted to `C:\xampp\mysql\data`. The original remains at `C:\xampp\mysql\data-before-recovery-20261021-085934-caebe39c`, with its original pinned hash preserved by the batch's final verification. The staging directory no longer exists. Move certificates record both transitions.
- Exactly3 receipt filenames were recovered in working uploads; all50 prior file/directory entries are unchanged. Independent read-only review rehashed all53 working files and confirmed the resulting manifest equals the passing rehearsal upload tree.
- The guarded server authenticated on127.0.0.1:3306 with the expected working datadir, held PID, read-only setting, force recovery0 and disabled events/automatic repairs. All65 table/view checks passed; all70 foreign-key orphan counts were0 and accounting/advance/correction integrity returned no problems.
- Full logical snapshots before/after equal the pinned original, including application data, schema/objects and permissions. Evidence equals the passing rehearsal. The probe reported no SQL data writes. Server and authenticated shutdown client exited0 with no timeout; fresh read-only process/listener inventory is clear.
- The batch's final rehash reports the archived original and other protected sources unchanged. Independent result review verified saved baselines/outcomes, working file hashes and all source/configuration/tool pins, without repeating the full protected physical-tree rehash. The stopped recovered working tree's saved hash is `C292BDC4A86DFC32B24BE685903569FB4B0A74ED97DDD469A8E8AC7C015C3F25` (2,767 files); this is expected to differ physically from the pre-start copy after normal engine startup/shutdown.

Private evidence: attempt `results.json`, move certificates, `logs/inspection.json`, table/scan/file manifests, effective identity/variables, shutdown result and final working manifest. Independent-review certificate `.migration-private/phase4-result-review.json` and inventory `.migration-private/phase4-result-offline.json` retain the review boundaries. No new server startup or SQL was performed during result review; no application or prepared U3-A source changed.

**Next bounded check:** start **MySQL only** through XAMPP, leave Apache stopped, and report status. Confirm normal configuration/SQL identity and absence of new startup warnings before controlled browser login/MFA and read-only application checks. If normal startup fails, stop/report without repeated starts or repairs. Do not start the archived original or rerun the cutover command. Normal XAMPP configuration has not yet been exercised by this temporary-option-file startup. Working/Atikha acceptance, U3-A integrated verification and migration009's independent routine-registration diagnostic remain separate.


## Normal XAMPP startup: authenticated connection passed

The user started MySQL through XAMPP normally. The running server's native executable identity, creation time, PID file and3306 listener ownership were verified; Apache remains stopped and no3307 listener is present. One bounded authenticated TCP query returned the recovered working datadir, version10.4.32, normal read_only0 and force recovery0. The client exited0 with no timeout/stderr; the current startup log segment contains no warnings/errors. All39 pinned source/tool/configuration inputs remain unchanged. Historical red XAMPP messages were not treated as new failures.

MySQL is left running as the user started it. This review started/stopped no server, modified no configuration and performed only a system-variable SELECT. The earlier failed-initial-greeting symptom did not recur in this authenticated check. This is not a demonstrated original root cause, long-duration stability test, fresh migration bootstrap or application login result. Do not hash/copy an active data directory as an offline preservation check.

Private evidence: `.migration-private/phase4-normal-start-c47b92b997` (held identity/inventory and bounded client outputs) and `phase4-normal-start-result.json`. Next: guarded System Administrator login/MFA with before/after application fingerprints and browser request restrictions. Dashboard source automatically POSTs to forecast_ai.php, so block that request before opening the dashboard; allow normal authentication/MFA without collecting credentials/codes. Apache stays stopped until the browser check is prepared. No synthetic financial postings, working drafts as fixtures, migrations, repairs or U3-A changes belong to this check.


## Scoped normal login and application reads completed

The user confirmed availability, started Apache through XAMPP and completed System Administrator login/MFA once in a fresh guarded Edge session. The agent started/stopped no server. Pre-login full application/schema/object/permission and evidence snapshots matched the original recovered baseline.34 request-guard classifications passed before opening the browser.

All9 browser checks passed: login HTML, successful normal login/MFA, exactly one auth/MFA POST sequence, Financial Records HTML, posted journal lines loaded, existing exact-journal table, owner draft list, no unexpected requests and forecast blocking. One automatic forecast request and2 external requests were blocked. Only normal authentication could POST; all financial/draft mutation, OCR and external communications routes were denied. No credentials, MFA values, HAR, session cookies or traces were collected. Financial Records and exact-journal screenshots were visually inspected. No synthetic transaction/draft was created and no existing draft was resumed or edited.

The initial post-login private helper failed because it passed a scalar SHA256 to an array-only comparator. This was a verification-tool type error, not an observed data mismatch. Original source, client exit1 and stderr are preserved. A new private continuation changed only the scalar fingerprint comparison to strict validated hash equality.13 comparison tests passed against synthetic in-memory deltas, including the scalar regression. Its bounded read-only after snapshot then passed, exit0/no timeout/no stderr; the browser/login was not repeated.

Final snapshots confirm all non-authentication tables unchanged; non-MFA user fields, schema/objects/permissions and attachment evidence unchanged; previous audit rows unchanged; and exactly one expected Admin LOGIN/Auth audit added. Only the audit AUTO_INCREMENT advancement was allowed in schema comparison. All53 working upload files and39 pinned code/tool/configuration inputs were reverified. No repair, migration, SQL import, grants change, feature/configuration edit, financial mutation or U3 source change occurred.

Private evidence: `.migration-private/phase4-login-8a7013b8f2/` contains before/after snapshots, browser outcomes, private screenshots, initial failed-comparison output, correction provenance/tests, successful continued comparison, final process/listener inventory and `completion.json`. Keep these artifacts private. The guarded browser closed normally; MySQL remains running on3306 and user-started Apache on80/443, with no recovery3307 listener. No long-duration stability, Management privacy, cross-browser/device, full working or Atikha acceptance claim is made by this scoped recovery check. Migration009's independent fresh-bootstrap routine-registration error remains unresolved; do not rerun old migrations against working data.

**Next:** resume only unfinished U3-A disposable authenticated/integration/privacy/regression verification against the preserved prepared source, then exact-source external review. U3-B/C/D stay unstarted. The successful recovery removes the observed database-startup/handshake blocker; further recovery-command retries are unnecessary.
