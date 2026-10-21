# MariaDB read-only diagnostic — October 20, 2026

**Outcome: TCP is reachable, but SQL readiness and database integrity are unverified. Root cause remains unknown.** The user authorized investigation only, with no repairs. U3-A integration is blocked; its [incomplete handoff](usability-u3-a-delivery.md) is separate.

## Observed connection failure

After the user started MySQL through XAMPP and left Apache stopped, a bounded native `mysql.exe` connection outside the sandbox failed with exit code 1:

```text
ERROR 2013 (HY000): Lost connection to MySQL server at 'handshake: reading initial communication packet', system error: 0
```

The client used `--no-defaults --protocol=TCP --host=127.0.0.1 --port=3306 --user=root --connect-timeout=5`, with a ten-second subprocess bound. The intended read-only identity SELECT returned nothing; no authenticated server identity or SQL result was obtained.

Separate raw TCP probes connected successfully to both `127.0.0.1:3306` and `[::1]:3306`, but neither received the four-byte initial packet header within a three-second receive timeout. They sent no SQL or credentials and recorded no authentication packet contents. This is evidence of a failure before the normal client authentication exchange, rather than an application query error. MariaDB's documented sequence is socket creation, server greeting, client authentication response and server result. A listening socket alone does not establish SQL readiness. [MariaDB connection protocol](https://mariadb.com/docs/server/reference/clientserver-protocol/1-connecting/connection).

## Process, configuration and log evidence

- Listener: address `::`, port **3306**, owned by **mysqld.exe PID 20024**. No port-3307 listener was observed. Its parent was **xampp-control.exe PID 23432**. The separate MySQL80 service was stopped. Apache was absent at the final process check.
- PID 20024 started **October 20 at approximately 01:11:25, UTC+8**. It remained alive at the final observation. Windows reported 29 threads and about 0.39 CPU seconds; these are one-time observations, not a stack diagnosis. `Responding=true` does not establish MariaDB readiness.
- CIM did not expose this process's executable path. Its parent and log align with XAMPP, but the exact running executable, loaded option file, effective datadir/settings and authenticated identity were not independently established.
- Selected nonsecret lines in `C:\xampp\mysql\bin\my.ini` specify port **3306**, basedir `C:/xampp/mysql`, data/InnoDB home/log directories `C:/xampp/mysql/data`, temporary directory `C:/xampp/tmp`, `ibdata1:12M:autoextend`, and redo size **48M**. These are configured values, not SQL-verified effective values. No configuration was edited.
- `C:\xampp\mysql\data\mysql_error.log` contains three October 20 starts: **01:10:59 (PID 1132), 01:11:10 (PID 10476), and 01:11:26 (PID 20024)**. The agent did not initiate these starts or stop/restart a server.
- The latest startup reports InnoDB crash recovery from checkpoint **LSN 64290368**, active rollback segments, temporary tablespace recreation, InnoDB started at **LSN 64290377 / transaction 20302**, and socket creation on `::`. The final line is at **01:11:26**. No subsequent ready-for-connections message appears in the observed file.
- The complete observed log was **13,953 bytes**. A search for `[ERROR]`, assertion/exception/stack-trace indicators, ready-for-connections and shutdown-complete found only the **October 8, 09:51:09** readiness message. No explicit current fatal cause was found. Missing messages do not establish what happened outside this log.
- C: had approximately **251 GiB available** at observation. The failure is not explained by an observed full C: drive.

The observed error-log bytes are preserved privately at `.migration-private/database-diagnostic-20261020/mysql_error.observed.log`, SHA-256 **335DFF070D5A59D56706E0F4F28A9273C59DC66DBF5295E659B77AB918D7B87E**. This is a log copy, not a database backup. The original log was not changed by the investigator. MariaDB identifies the error log as a primary startup diagnostic source; this log alone does not identify the stalled code path. [MariaDB startup troubleshooting](https://mariadb.com/docs/server/server-management/starting-and-stopping-mariadb/what-to-do-if-mariadb-doesnt-start).

**Inference:** incomplete/stalled startup is consistent with these observations. A particular damaged table, lock, routine, permission fault or InnoDB defect has not been demonstrated.

## Older crash and system-table observations

Windows Application Error event 1000 and Windows Error Reporting event 1001 record an earlier **October 13, 2026, 23:41:25–23:41:28, UTC+8** crash. They identify `C:\xampp\mysql\bin\mysqld.exe`, version 10.4.32.0, exception **0x80000003**, offset **0x643842**, and report ID `d13ce74f-be3b-4c0c-a625-dd07b1eadfab`. The old process was **0x4FC4 / 20420**, matching the October 13 23:41:24 startup in the log; a subsequent start is logged at 23:41:35. This is not a current-PID crash record. No matching October 20 crash appeared in the queried recent Application Error/WER subset. The exception/offset without a stack does not establish cause or a connection to today's symptom.

Read-only file metadata was inspected for selected `mysql` system-table files in the active and retained recovery directories. Some sizes/timestamps differ, including `mysql.proc` data/index files. Ordinary use and test routines can change those files; differences do not prove corruption. No live Aria/offline checker, table repair, grant-table replacement or system-table SQL check was run.

Migration 009's previously reported **error 1304 / procedure already exists** remains separately unresolved. No causal link to this handshake failure is established. The isolated anonymous-block compatibility helper did not repair MariaDB and does not prove normal routine registration works.

## Preserved recovery material

The following paths were observed, without restoring or opening a server against them:

- Active directory: `C:\xampp\mysql\data`.
- Retained recovery source: `C:\xampp\mysql\atikha-recovery-clean`.
- Untouched pre-repair backup: `C:\xampp\mysql\data-before-db-repair-20261008-044531`.
- Original archive: `C:\xampp\mysql\data-original-archive-20261008-095004-7eb14ffc`.
- Financial export: `C:\xampp\mysql\recovery-exports\atikha_finance-20261008-050644.sql`, **122,416 bytes**. No new restore or equivalence check was performed.
- October 8 cutover evidence directories: `cutover-20261008-091005-3c2474cd`, `cutover-retry-20261008-093456-3d8ebd15`, and `cutover-continuation-20261008-095004-7eb14ffc` under `recovery-exports`.
- A retained `C:\xampp\mysql\data-cutover-staging` directory also exists. Its name is not evidence of current validation or suitability for promotion.

These October 8 materials are historical recovery references. They are not established backups of changes made since then, and must not automatically replace the current dataset or grant tables. The failed U3-A disposable exports are not usable backups.

## What remains unknown

No authenticated connection means no current financial counts, table integrity checks, schema fingerprint, permissions stability check, process list, lock diagnosis, effective settings or fresh logical backup. The previously validated October 8 counts cannot be presented as today's counts. Neither new corruption nor current financial-data loss has been established or ruled out. Process-stack/wait information sufficient to identify the stalled startup path was not obtained.

## Actions and next step

Only verified stalled **test clients** were cancelled: mysqldump PID 16228, private copy worker PID 23192 and its wrapper PID 17940. They were absent at the final process check. **MariaDB PID 20024 remained running; Apache remained stopped.** No server was shut down, killed, restarted, initialized or reconfigured by the agent. No migrations, imports, seeds, repairs, financial mutations, permission edits or database-file moves/deletions were performed. The server can still perform its own background writes; read-only investigation is not a claim that live file bytes are frozen.

Do not resume integration or repeatedly launch exports against this instance. The next task should be a **separately reviewed, guarded preservation and startup-diagnosis plan**, including an approved way to stop a nonresponsive server, verification that it has exited, a complete offline copy of the current data/configuration, and diagnosis on copies while preserving all originals. An authenticated graceful shutdown has not been demonstrated available, so force termination, XAMPP stop scripts and live-file copying are not substitutes authorized by this report. Do not execute that next plan under the present read-only authorization.

Keep the current recovery references and U3-A evidence. Resume U3-A only after server usability is independently established and fresh guarded disposable fixtures can be prepared. U3-B/C/D remain unstarted.

## Subsequent planning inspection — October 20

The [preservation/recovery plan](database-preservation-recovery-plan-20261020.md) records additional read-only observations after the diagnostic above. Windows limited process-image query independently resolved PID 20024 to `C:\xampp\mysql\bin\mysqld.exe`; CIM still withheld its command line, and the loaded option file/effective SQL datadir remain unverified. The active `mysql.pid` contains the same PID. A retained `data\my.ini` points to the old recovery directory/3307, but there is no proof that it was loaded. Alternate `.err` logs are October 8 history; the existing dump is dated October 13. No server action, backup/checker execution or repair was performed during plan preparation. Phase 1 approval, including an explicit shutdown-failure branch selection, is pending.
