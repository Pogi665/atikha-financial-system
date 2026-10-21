# Current-state logical recovery: database restored; normal login pending

October 21, 2026. **The proven-source database reconstruction, working cutover and normal MariaDB SQL verification passed.** MySQL and Apache are stopped. The user is not available for normal login/MFA, so bounded authenticated application acceptance remains pending and U3-B-D has not resumed. No commit/push occurred.

This supersedes the operational hard stop in the earlier [incident outcome](database-incident-outcome-20261021.md); that report remains dated evidence. The [table-level manifest](database-logical-recovery-manifest-20261021.json) classifies every one of the34 application and31 required system objects without publishing records, credentials, exports or private evidence.

## Authorization and continuity

After the previous envelope stopped, the user explicitly authorized expanded table-by-table current salvage, proven-source clean reconstruction, full validation, conditional working cutover/read-only acceptance and automatic U3-B, C and D resumption only after environment acceptance. Routine successful gates need no further approval; invented records, silent audit gaps, working historical migrations, version changes and speculative broad repairs remain excluded.

The user directly confirmed that after the last verified working snapshot at15:21:02 +08 on October21 there was no additional working application or MariaDB user/grant activity outside Codex checks and XAMPP startup attempts. The freshly copied and hashed Apache access log independently ends at09:48:13 +08. This continuity evidence is explicit; an older backup was not simply declared current.

## Damage assessed and trustworthy sources recovered

A fresh isolated reader used only the already demonstrated preload mitigation and attempted each object independently. It recovered complete current exports for32 of34 application tables and29 of31 system objects. Every readable application table's complete logical fingerprint and SQL schema matched the latest verified working snapshot. The affected objects were:

- **budgets:** the known invalid bitmap page prevents normal engine access. Its schema and every physical byte outside that bitmap match the prior validated file, including all data-bearing pages. The120 recovered logical rows match the latest verified fingerprint. No seeded defaults or spending-derived budget values were substituted.
- **audit_logs:** the current tablespace cannot open and has overwritten/non-audit page identities. A bounded page-aligned search across1147 current preserved InnoDB-related files,474316800 bytes, found no surviving expected audit index page. This is not a claim to exhaust every forensic technique.
- **mysql.func:** marked crashed, but its current data/schema bytes equal the prior validated empty UDF table. The clean table was verified empty; no configured UDF was discarded.
- **mysql.proxies_priv:** normal reads fail with an Aria checksum error; the data file contains log-like text rather than usable table records. The prior validated four-row grant projection matches the last verified permission fingerprint. Its index keys remain intact, with only four index-file bytes differing; seven other readable permission projections match. Together with the direct no-grant-change statement, this supports restoring the proven four rows without resetting accounts or granting new privileges.

The prior source was itself checked against its saved full physical manifest before/copy/after, read only through a fresh isolated copy, and gracefully stopped. Both historical and current readers retain their separate source identities; the reader's generic CURRENT-READABLE output for a historical copy is not treated as proof of currency.

### Exact audit reconstruction

The prior validated database yielded58 audit rows exactly matching the recorded pre-login fingerprint. The earlier normal-login recovery check proves one additional LOGIN/Auth record, ID59, with prior rows unchanged. Preserved MFA/logger source and verified user identities fix its payload structure; the recorded before/after login interval bounds its UTC timestamp and the guarded local browser bounds its address.

Offline enumeration of2616 bounded candidates yielded **one unique complete59-row SHA-256 match** to both the independent post-login and latest verified working snapshots. A native PHP/SQL reread after restoration reproduced that exact fingerprint. No guessed event, changed timestamp or invented audit narrative was accepted. User/HTTP continuity establishes no later legitimate working activity. All59 rows are preserved in the reconstructed target.

## Clean reconstruction and validation

The same preserved MariaDB10.4.32 build's native initializer created a new isolated data directory. Its [version-specific official source](https://raw.githubusercontent.com/MariaDB/server/mariadb-10.4.32/sql/mysql_install_db.cc) was checked for bootstrap/default-file behavior; no Windows service was installed. No old InnoDB/Aria data or logs were carried into this clean directory. All34 application tables,24 triggers and required original system data/grants were restored from the table-level proven sources.

Foreign-key checking was disabled only during bulk loading of the new isolated schema, then enabled before explicit relationship/integrity validation. No working SQL constraint bypass occurred. Persistent optimizer statistics are derived caches and were regenerated. Historical atikha_test_* fixture databases remain preserved in the damaged directory/archive and are omitted from the active production reconstruction; test/tmp had no table-definition files.

The initial comparison stopped at24 trigger ACTION_ORDER values serialized as strings rather than integers by the private PDO reader. Their numeric values and every other snapshot value were equal. Native numeric rereads corrected that reporting boundary; no application/schema/data correction or restore retry was needed. Physical .frm hashes also differ after logical creation; actual SQL definitions were compared rather than mistaking physical file identity for schema identity.

Fresh completed evidence:

- 65 table/view checks passed on the isolated reconstruction.
- 70 foreign-key/orphan checks returned zero.
- All34 application table schemas/data,24 triggers and original permission projections match the proven target exactly.
- Existing accounting, journal, correction, advance and evidence integrity readers returned zero findings.
- All13 referenced supporting-evidence files passed metadata/path/hash comparison, and all53 protected upload files remain byte-identical.
- Two normal isolated startup/authenticated-connection/validation/graceful-shutdown cycles passed with preload enabled, force recovery0 and normal writable mode. Each included30 seconds of running observation.

## Working cutover and normal SQL checks passed

The verified clean files were staged and compared while offline. The damaged working directory was moved intact to:

`C:\xampp\mysql\data-damaged-20261021-516017986f`

The untouched fresh preservation remains:

`C:\xampp\mysql\recovery-exports\incident-20261021-093316cb4b\preserved\data`

Detailed private execution evidence/provenance is under that incident archive's `logical-7c75115910` directory. Neither damaged source nor preservation was overwritten or repaired. After the move, both were rehashed against the original manifest. The promoted directory initially matched the clean source manifest exactly; its working ACL rules match the previous directory.

The installed binary then passed **two normal working startup/authenticated SQL/graceful-shutdown cycles on3306 using the unchanged XAMPP configuration**, with no recovery mode or preload bypass. Read-only working probes confirmed the complete target snapshot,70 zero-orphan relationships, accounting integrity and evidence equality. All31 system objects' actual SQL definitions also match their preserved definitions, including the five regenerated statistics tables. Each working cycle included30 seconds of bounded observation.

No working application login, financial posting, draft/evidence mutation, account/grant reset, migration or feature-flag change occurred. The bulk restoration ran only on the isolated candidate before promotion. The1799 current non-documentation/non-Git source/runtime/dependency files match preservation; the full original4217-file application copy remains intact. Two vanished Codex-generated Git references were classified as transient tool metadata, retained in preservation and excluded from runtime-source comparison.

## Remaining gate and safe resume

The user answered that they are **not available now** for normal login/MFA. Apache was not started, and working MySQL was gracefully stopped. The database-level recovery passed; bounded normal application acceptance and full working/Atikha acceptance are not claimed.

When the user is available, resume with a fresh current pre-login fingerprint and normal MySQL startup, prepare the guarded normal-authentication/read-only browser check, then perform normal login/MFA and allowed dashboard/records/exact-journal/draft/advance/evidence and role/privacy reads. Verify unchanged financial/evidence/permission data and prior audit history, documenting only inherent authentication effects. Do not bypass authentication or use working records as synthetic financial fixtures.

Only after that gate passes, automatically resume the already approved U3-B -> C -> D sequence and final affected regressions on fresh guarded disposable databases. B remains partial preserved source; its integrated gate has not passed. C/D and the final review ZIP remain incomplete. Earlier52 pure adapter assertions are dated preparation evidence, not new recovery/U3 results.

Do not rerun consumed initialization, restoration or cutover helpers. The old original-state verifier expects damaged data at the old working path and is superseded after this authorized move; future comparisons must use the archived damaged path and new working baseline. Private resume pointers are `.migration-private/logical-working-state.json` and `.migration-private/logical-current.json`.

The initiating budgets-page failure is established; how the physical overwrites arose remains unproven. Bounded observations do not establish long-term engine stability. Migration009 error1304 remains a separate unresolved issue; neither this native system bootstrap nor the older test-only anonymous-block path proves historical migration/deployment success. U4/U5, U-PERF-01, original Stages4-7 and full client acceptance remain outside this work.


## October22 normal authentication and application acceptance

**Normal recovery acceptance passed, October22,2026:** the user completed Administrator and Management login/MFA in guarded Edge.25 browser/read checks passed, with zero unexpected requests or uncaught JavaScript errors; server route guards passed6 checks and browser guard checks passed35. Financial/evidence/permission fingerprints and all prior audit history are unchanged. The restored59 audit rows now have3 additional normal LOGIN records: one Administrator login during the earlier interrupted guard attempt, plus the final Administrator and Management logins. Working data had no eligible advance-detail/evidence-download sample, so those specific working reads were not exercised; synthetic role/evidence regressions remain required. Apache stopped normally; normal MariaDB remains running on3306 for authorized fresh disposable tests. Database incident recovery is complete within this bounded acceptance scope; full Atikha acceptance remains separate. The approved U3-B -> C -> D implementation is resuming; no commit/push or later stage is authorized. Do not rerun consumed recovery/cutover helpers.

Private execution identity: `acceptance-20261022-ac77e60e61`. Both per-role native comparisons and the completion certificate passed. No auth secrets, MFA values, session material or private exports are included here. The current attempt required two Management credential submissions and one successful MFA submission; no authentication was bypassed.35 browser guard assertions and6 server rejection probes are preparation checks, separate from25 actual role/read assertions. No working financial posting, upload, deadline extension, migration or grant change occurred.
