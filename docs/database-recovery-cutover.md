# Database recovery cutover: database promoted, scoped smoke complete

## Remaining smoke completed — October 12, 2026

The user returned to system work and authorized continuing the remaining recovery checks. Apache and MariaDB were already running; no server was started, stopped or reconfigured in this continuation. The recovered MariaDB 10.4.32 instance was verified on port 3306 at `C:\xampp\mysql\data`, with its SQL PID file matching the listener owner. Windows withheld the running process's executable path and command line, so this continuation does not claim a fresh OS-level binary/option-file provenance inspection. The installed expected binary and active `my.ini` still match the pinned October 8 hashes; effective data paths and relevant settings also match. No 3307 listener was present.

Before browser access, fresh read-only fingerprints confirmed that all 32 non-authentication application tables and user credential/identity fields still match the verified recovery baseline. Counts remain users 3, incoming funds 0, expenses 0, journal entries 2, journal lines 4 and advances 0. The schema matches after normalizing only the expected `audit_logs` AUTO_INCREMENT difference. Three permissions-view queries succeeded. Recursive fingerprints of the original archive, recovery source, prerepair backup and first failed staging folder still match their preservation records; the financial SQL export is unchanged. Audit ID 55 was the fresh pre-smoke cutoff; intervening application authentication activity was not mistaken for a financial discrepancy.

Only private verification helpers were added. The original failed smoke script and its evidence were preserved. The new helper explicitly permits GET `accounting_actions.php?action=documents` alongside GET `draft`/`drafts`; mutation actions and non-authentication POSTs remain blocked. Its narrower route checks passed 15 focused self-tests before the single browser attempt. Forecasting and external browser requests remain blocked. No production PHP/JS, application configuration or permission was changed.

The user completed normal System Administrator login and MFA in the guarded Edge window. All seven scoped browser checks passed: login page, completed login/MFA, owner draft list, GET documents lookup, existing draft resume without edits, visible existing document and request boundaries. The document returned HTTP 200, decoded visibly, and its bytes matched the stored SHA256, MIME type and size. The resumed-draft screenshot was visually inspected. No save, review, discard, upload, extraction or financial posting occurred; there were no unexpected requests. One forecast request and one external browser request were blocked. The dedicated browser closed normally after success; no credentials or MFA values were collected by the helper.

Post-smoke fingerprints confirmed unchanged non-authentication tables and unchanged user fields apart from the permitted MFA fields. Exactly one new authentication audit was present, ID 56 / LOGIN. The only schema difference remains the expected audit counter advancement; no DDL or counter reset was performed. Three further permissions queries succeeded, and all preserved offline sources/configuration/export fingerprints still match.

Private evidence: `.migration-private/recovery-smoke-20261012-01/`, including `before.json`, `remaining-browser.json`, `document-integrity.json`, `existing-draft-resumed.png`, `after.json` and `authentication-audits.tsv`. The initial baseline helper's empty-audit count metadata was corrected from one to zero; data fingerprints and its pre-smoke cutoff were unchanged. Keep these artifacts private. No automatic browser retry was performed.

**Scoped database recovery and cutover smoke are complete**, combining the October 8 journal/phpMyAdmin checks with this October 12 remaining draft/document continuation. The earlier full 34-application/30-system table checks were not repeated solely to increase the check count. This is not full working-system, cross-browser, physical-device or Atikha acceptance. Apache remains running on 80/443 and one recovered MariaDB process remains on 3306, as found at the start; no recovery listener remains. Optional phpMyAdmin storage is still deferred. No migrations, imports, repairs, seeding, cleanup, financial mutations, folder operations, configuration/permission changes, commit or push occurred.

Next: receive the reviewers' responses to the corrected U2-A report and 24 screenshots, then obtain the separate U2-B implementation instruction. The user supplied the original U2-A delivery reviews and requested corrections earlier; revised-delivery reviews have not yet been supplied. U2-B, U2-C, later usability/completion work and U-PERF-01 remain pending. The October 8 paused status below is dated history, superseded by this completed continuation.

October 8, 2026. **Database cutover and normal-server validation succeeded. Full scoped browser completion remains pending.** MySQL is running normally on port 3306 with the clean recovered data at `C:\xampp\mysql\data`. Apache was stopped by the user through XAMPP and its exit was verified. The known-corrupt original is preserved offline in the dated archive; do not restore or start it.

## Authorized continuation and completed database gates

The user directly authorized one continuation: test the corrected configuration replacement on disposable files, recheck preserved inputs and validated staging, promote once, and continue validation only while gates pass. No additional staging copy or database repair was performed.

The disposable non-database test reproduced PowerShell 5.1's failure when passing `$null` as the third `File.Replace` argument. The explicit unique-backup call succeeded: destination bytes matched the proposed contents, backup bytes matched the previous destination, and the replacement source was consumed. Its private proof was copied into the continuation evidence.

Before promotion, fresh recursive manifests matched the original, clean recovery, untouched offline backup, first failed staging copy and already validated offline retry stage. Export/binary/configuration hashes matched their pins. Promotion used guarded native PowerShell renames and a unique explicit configuration-backup filename. The active `my.ini` contains exactly the two approved changes: `ibdata1:12M:autoextend` and a 48 MiB redo-log size. The atomic backup matched the original configuration hash.

- Original archive: `C:\xampp\mysql\data-original-archive-20261008-095004-7eb14ffc`.
- Active recovered data: `C:\xampp\mysql\data`.
- Preserved recovery source: `C:\xampp\mysql\atikha-recovery-clean`.
- Preserved prerepair backup: `C:\xampp\mysql\data-before-db-repair-20261008-044531`.
- Preserved first failed stage: `C:\xampp\mysql\data-cutover-staging`.
- The validated retry stage was promoted; its former path is intentionally absent. Its validation evidence and both prior failed-attempt records remain preserved.

MariaDB was started through XAMPP's existing `mysql_start.bat`, using the expected binary and existing `bin\my.ini` provenance. At 09:52:16 +08, before Apache, all normal-server checks passed: port 3306/data-directory/version/root identity, required effective settings, data/log/tmp/undo/buffer-pool paths, 34 application and 30 mysql base tables, 24 triggers, repeated stable `mysql.user` queries returning five accounts, no orphan journal lines, and positive balanced journal totals. An unknown account was rejected with 1045; no grant bypass or permission change was used. Canonical data and schema hashes matched the recovery baseline exactly before application access.

Apache subsequently started through XAMPP's existing startup entry point. No application source, application configuration, feature flags or phpMyAdmin configuration changed. No migration, import, seed, cleanup, repair, financial posting or unrelated regression suite ran.

## Browser and phpMyAdmin evidence

The guarded Edge context blocked external browser calls, dashboard forecasting and every non-authentication POST. The user signed in normally and completed MFA. Passed checks were:

- Login page, normal login/MFA and authenticated Dashboard access.
- Financial Records across all dates: exactly two existing journals and four lines.
- Exact journals #1 and #2: two lines each with complete positive balanced totals.
- Owner-private draft list.
- GET-only phpMyAdmin application-database structure, showing the expected recovered tables. Its optional `pma`/configuration-storage warning was expected and accepted under the selected deferred-storage policy. No optional storage or accounts were created.

**Remaining browser checks are paused, not passed.** On resuming the existing payment draft, the smoke harness allowed the draft lookup but accidentally rejected `GET accounting_actions.php?action=documents`. Current source shows that this lookup only selects the owner's available unposted images for the document picker. The draft GET returned HTTP 200 and loaded; blocking its subsequent picker lookup produced the harness's failed-resume assertion. This is a smoke-guard defect, not evidence of corrupt recovered financial rows. No retry was made. Existing image #4 was not reached by this smoke run.

Post-smoke verification confirmed all 32 non-authentication application tables still match the baseline and user identity/password/role/active-status fields remain unchanged. Exactly one normal LOGIN audit was added: audit count 50 to 51. The full data hash therefore legitimately differs from the pre-login hash. A strict post-login schema-dump comparison initially flagged a difference; read-only investigation proved its only difference was the expected `audit_logs` next AUTO_INCREMENT value, 51 to 52. Substituting only that prior counter in memory reproduced the exact pinned schema hash. No DDL or counter reset was performed.

## Pause and current operational state

The smoke failure triggered the explicit no-automatic-retry boundary. Apache's CLI shutdown command then reported that no `Apache2.4` service is installed; this XAMPP process runs in console mode. It did not stop Apache. No forced shutdown or additional automatic shutdown method was attempted. The user then clicked Stop beside Apache in XAMPP; process exit and clear web ports were verified and recorded.

Current state: **one healthy MariaDB process on 3306; no 3307 listener; Apache stopped.** The application browser runner is closed. Fresh preservation hashes confirmed the archived original, clean recovery source, prerepair backup, first failed stage and SQL export unchanged. The active `my.ini` still matches the exact approved replacement. There is no reason to restart the known-corrupt original for this harness-only finding; the verified recovered database is retained in its normal location with its permitted authentication audit preserved.

Private continuation evidence: `C:\xampp\mysql\recovery-exports\cutover-continuation-20261008-095004-7eb14ffc`. Important files are `disposable-replacement-test.json`, `baseline.json`, `offline-sources.json`, `promoted.json`, `my.ini.before-cutover`, the unique `my.ini.atomic-replace-backup-*`, `grant-enforcement-3306.json`, `table-checks-3306.tsv`, `xampp-verified.json`, `apache-started.json`, `browser-smoke.json`, `phpmyadmin-smoke.json`, `post-smoke-data.json`, `post-auth-schema-check.json`, `post-smoke-preservation.json`, `apache-graceful-shutdown.log` and `apache-paused.json`. Journal/Financial Records screenshots are private. Do not commit or publish these artifacts, configuration backups, session data or SQL exports.

## Next narrowly scoped action, pending authorization

No further cutover, database copy, migration or repair is needed for the reported issue. Before restarting Apache for a further smoke attempt, obtain direct authorization because the approved one-attempt continuation stopped on a new failure.

The concrete remaining task is to allow **only GET `documents`** alongside the existing GET `draft`/`drafts` in the private browser guard, retaining the prohibition on financial POSTs and blocked forecasting/external calls. Run only the remaining normal-login/owner draft-resume/document checks and verify authentication-only changes afterward. Keep each attempt's evidence separate. Do not change production PHP/JS, create fixtures, edit/save/review/discard drafts, upload/extract receipts or post financial records. Full working-system acceptance, usability U2-B and later stages remain separate.

The earlier reports below are dated evidence, superseded by the current status above.

## Previous attempt: validated staging, promotion stopped

October 8, 2026, updated after the authorized corrected attempt. **Cutover is still incomplete. All servers are stopped. Do not start XAMPP MySQL: its `data` folder again contains the known-corrupt original after verified rollback.**

## Latest authorized attempt and result

The user directly authorized one corrected staging attempt, followed by the remaining cutover only if every gate passed. A fresh offline copy was created at `C:\xampp\mysql\data-cutover-staging-retry-20261008-093456-3d8ebd15`; the first failed copy remains unchanged at `C:\xampp\mysql\data-cutover-staging`.

Before launch, recursive source hashes matched the previously recorded original/recovery/offline-backup manifests, and the export, binary and XAMPP configuration matched their pins. The fresh copy matched the entire offline recovery tree byte-for-byte. The installed MariaDB binary checked the corrected launch arguments through `--verbose --help` and returned zero. The unsupported shared-memory option was omitted; the buffer-pool filename and its validation now use the relative `ib_buffer_pool` resolved inside staging.

Staging started successfully on 127.0.0.1:3307 at approximately 09:35:42 +08. Validation passed:

- Expected XAMPP binary, option-file provenance, server version, port, authenticated root identity, data directory and all redirected writable paths.
- Recovery tablespace geometry: `ibdata1:12M:autoextend`, two 48 MiB redo logs; normal page-size/case settings, no force recovery, read-only staging and no binary/general/slow/event work.
- All 34 application tables and 30 mysql base tables returned `CHECK TABLE ... status OK`; counts remained users 3, funds 0, expenses 0, journals 2, journal lines 4, advances 0. There were 24 application triggers, no orphan lines and balanced journal totals.
- All 34 application tables' canonical data and schema fingerprints matched the pinned recovery baseline exactly. Dumps remained private, with UTC timestamp normalization.
- `mysql.user` returned the expected five server accounts repeatedly. MariaDB 10.4 does not expose the planned `skip_grant_tables` SQL variable: effective options and process arguments showed no bypass, and a randomly named nonexistent account was denied with error 1045. This expected denial explains the sole access-denied warning in the staging log; no permissions were changed.
- Graceful authenticated shutdown completed at 09:36:24 +08, with process exit and both ports clear. The validated offline staging tree was fingerprinted before promotion.

The successful staged validation is in `C:\xampp\mysql\recovery-exports\cutover-retry-20261008-093456-3d8ebd15`: `baseline.json`, `offline-sources.json`, `offline-copy.json`/log, `option-compatibility.json`, `compatible-staging-help.txt`, `staging-settings.json`, `table-checks-3307.tsv`, `grant-enforcement-3307.json` and `staging-validated.json`. Keep these private.

## New failure and verified rollback

Promotion attempted the two guarded renames and then reached the approved configuration replacement. Windows PowerShell 5.1 rejected `[IO.File]::Replace($temporary,$Ini,$null)` with **“The path is not of a legal form.”** Both real file paths existed and were valid; the null optional backup argument is the suspected PowerShell binding issue to correct and test before another attempt. The preserved temporary file contains exactly the two approved InnoDB changes, but it is not the active configuration.

The promotion handler rolled the folder names back and restored `my.ini` from the verified backup. A fresh preservation check at 09:38:11 +08 confirmed:

- No database process or Apache process; neither 3306 nor 3307 listening.
- Original `data` folder, clean recovery source, untouched offline backup, export, MariaDB binary and active `my.ini` unchanged.
- First failed stage unchanged; the successfully validated retry stage still exactly matches its offline validated fingerprint.
- Attempted archive destination absent after rollback.

The failure and rollback proof are `stopped.json` and `promotion-failure-preservation.json` in the retry evidence folder. The unused `C:\xampp\mysql\bin\my.ini.cutover-20261008-093456-3d8ebd15.tmp` is preserved; it must not be loaded or overwritten by an automatic retry. No normal 3306 startup, Apache startup, application requests, authentication writes, migrations, imports, repairs, seeding or permission changes occurred.

## Concrete next step, pending renewed authorization

This new failure exhausts the one-attempt authorization. The user-approved instruction was to **stop and report any new failure without automatically retrying or performing additional repairs**. No second promotion or another staged launch was attempted.

A further authorized attempt can reuse this already validated offline stage after rechecking every pinned input, source and stage hash and confirming all servers stopped. It does not need another staging startup solely to repeat already passed checks.

The proposed narrow command correction is to give `File.Replace` a unique, explicit third backup-file path, instead of `$null`, then verify that extra backup matches the original configuration hash. For example, inside the existing guarded promotion/rollback block, with a new unique execution stamp and a verified temporary file:

```powershell
$replacementBackup = Join-Path $Evidence "my.ini.atomic-replace-backup-$Stamp"
if (Test-Path -LiteralPath $replacementBackup) {
    throw 'Atomic replacement backup destination already exists.'
}
[IO.File]::Replace($iniTemporary, $Ini, $replacementBackup)
if ((Get-FileHash -LiteralPath $replacementBackup -Algorithm SHA256).Hash -ne $ExpectedIniHash) {
    throw 'Atomic replacement backup did not preserve original configuration.'
}
```

This is a proposed correction, **not executed or independently tested yet**. Before another real promotion, validate the replacement call with non-database temporary files, retain exact two-line changes and existing folder/configuration rollback guards, then proceed only under renewed direct user authorization.

Normal XAMPP startup/validation on 3306 and scoped browser smoke remain pending. The previous policies remain: no corrupt-original startup, no forced shutdown, no deletion of InnoDB files, no old grant-table restoration; phpMyAdmin optional storage remains deferred; block dashboard forecasting before normal login. U2-B remains unstarted.

The earlier report below is dated evidence of the first attempt, superseded by this current handoff.

## Earlier attempt: stopped at staged startup

October 8, 2026. **Cutover is not complete. Original data has not been promoted, renamed or restarted.** The user authorized the guarded cutover plan; its explicit staging-failure stop condition halted execution before promotion.

## Current operational state

- Apache is stopped. No `mysqld.exe` or `mariadbd.exe` process remains, and neither port 3306 nor 3307 is listening.
- The healthy manual recovery instance on 3307 was shut down gracefully using `mysqladmin shutdown`; process exit was confirmed before copying.
- `C:\xampp\mysql\data` remains the original, known-corrupt installation. **Do not start MySQL in XAMPP against it.**
- The validated recovery source remains offline at `C:\xampp\mysql\atikha-recovery-clean`.
- Failed staging is preserved at `C:\xampp\mysql\data-cutover-staging`.
- No folder promotion or archive rename occurred. No XAMPP, application or phpMyAdmin configuration was changed. No permission changes, migrations, imports, repairs, seeding or application requests occurred.

## Completed evidence

Fresh identity verified MariaDB 10.4.32, port 3307, the recovery data directory and the expected root account. Apache and the original server were stopped, with no other non-daemon database clients. The export, XAMPP binary and existing `my.ini` matched the planning hashes.

The recovery application-data fingerprint matched the previously verified 34-table dataset. Ordered complete inserts and UTC timestamp handling were used; SQL rows and authentication values were captured privately in memory rather than printed.

- Data fingerprint: `5F1F1B25AD97CC791EDD06338CE1F4C65D7ADD999E51876A09277E2E2E331C95`.
- Schema fingerprint: `AFE94CD1F23A21690A6C8E7E877B1DDB1EF371F81367317812F94287734CEEC0`.
- Original export: `C:\xampp\mysql\recovery-exports\atikha_finance-20261008-050644.sql`, SHA-256 `78D36D609FEAACA96CEA264D2DF493563345987CB6DC1CFD1D06643AE93F64C8`.

The entire offline recovery folder was copied to a newly created staging directory. Robocopy returned 1; a recursive file/directory manifest with file SHA-256 hashes proved byte equivalence before staged startup. Copied source bytes totaled 187,815,156. No InnoDB files were manually deleted or selectively copied.

The recovery, original data and untouched offline backup were fingerprinted. After staged startup failed, fresh verification confirmed all three preserved trees were unchanged, as were the SQL export and XAMPP configuration. The preserved offline backup remains `C:\xampp\mysql\data-before-db-repair-20261008-044531`.

Private evidence is in `C:\xampp\mysql\recovery-exports\cutover-20261008-091005-3c2474cd`: `baseline.json`, `my.ini.before-cutover`, `offline-sources.json`, `offline-copy.json`, `offline-copy.log`, `stage.stdout.log`, `stage.stderr.log`, and `staging-startup-failure.json`. Do not publish private configuration, exports or future browser captures.

## Failure and required launch corrections

The initial baseline attempt exposed a planning-command defect: this bundled `mysqldump.exe` does not accept `--connect-timeout=5`. That client option was removed before the successful baseline. No server/folder mutations occurred during that failed baseline attempt.

Staged launch at approximately 09:17 +08 failed with `unknown option '--skip-shared-memory'`. It also reported an invalid buffer-pool filename because MariaDB prepended the staging data directory to the supplied absolute `innodb_buffer_pool_filename`, producing a doubled path. These were command defects in the supplied plan, not evidence that recovered financial rows are corrupt. The staged process aborted and exited; no forced termination was performed.

Corrective launch changes proposed for a separately authorized retry:

- Omit `--skip-shared-memory`, which this binary rejects; do not substitute an unverified option or change permissions.
- Use `--innodb-buffer-pool-filename=ib_buffer_pool`. Verify its effective filename is relative and its resolved location is within the staged data directory.
- Keep `ibdata1:12M:autoextend`, two 48 MiB redo logs, and the redirected staging data/log/tmp/Aria/undo paths.
- Check all validation options against this build before launching. Where a setting is not exposed as a MariaDB 10.4 system variable, do not claim it passed or simply remove the safety requirement; establish the appropriate supported check.
- Preserve the first failed stage and logs. Test a new, guarded copy of the unchanged offline recovery source; refuse occupied destinations.

The approved plan says, "Preserve staging and its logs for investigation; do not delete files or automatically retry." Accordingly no retry or promotion was performed. A corrected staging attempt requires user authorization.

## Pending work

Staged table/system/account checks and final equality verification have not run. The original archive rename, normal XAMPP startup on 3306, two backed-up InnoDB setting changes, Apache startup and browser smoke checks all remain pending. No browser smoke helper was run, and no login/MFA/audit writes occurred.

The Computer Use native pipe was unavailable, so XAMPP UI control could not be used. XAMPP's existing startup batch entry points were inspected but were not executed. Any later use must retain their actual option-file provenance and verify the resulting binary, port and data directory.

The retained policy is to defer phpMyAdmin's optional control storage: its configured `pma` account and `phpmyadmin` database are absent from clean recovery. Preserve the old databases and grants offline; do not restore old grant tables into clean recovery. The fresh recovery `test` database is part of the whole-folder copy. Existing application users remain separate from fresh MariaDB server accounts.

After a successful cutover, scoped smoke checks must block the automatic `forecast_ai.php` request, permit normal login/MFA and associated audit changes, and prohibit financial mutations. Full data/schema equality is required before Apache; afterward only expected authentication changes are allowed. No unrelated regression suite is authorized by this recovery task.

Usability U2-A review corrections remain complete at source level; U2-B is still unstarted. Resume system work only after the recovery cutover has a verified operational outcome.
