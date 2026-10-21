# MariaDB repeated startup crashes: read-only investigation

October 21, 2026. This is a separate database diagnostic. U3-B-D application work remains paused.

## Finding

The current failure is a confirmed crash of XAMPP's MariaDB process, rather than only XAMPP's generic shutdown warning. Windows Application Error records and the existing minidump agree on exception `0x80000003` at `mysqld.exe + 0x643842`. Offline examination of the matching executable maps that address to **`my_parameter_handler + 2`**, the breakpoint instruction in MariaDB's Windows runtime error handler.

This identifies the crash mechanism, **not its initiating cause**. It does not identify a damaged table, demonstrate financial integrity, prove a particular MariaDB bug, or justify a specific repair. It also does not establish a connection to migration-009's earlier routine-registration error 1304.

## Evidence obtained

- Read-only process and listener checks found no `mysqld.exe` or Apache process and no listeners on 3306, 3307, 80 or 443. The separately installed MySQL80 service is stopped. This is current-state evidence, not proof about every earlier instant.
- The MariaDB log contains eight new startup attempts between 20:23:49 and 20:26:22. Each reaches InnoDB initialization and socket creation; those segments contain no readiness or normal shutdown message. A log message saying InnoDB started does not establish authenticated SQL availability.
- Windows records two Application Error events at 20:26:13 and 20:26:41, with the same executable, exception and module-relative fault offset. Four associated Windows Error Reporting records were also captured. The final crash PID matches the final startup PID. These times are recorded local event times; client context is UTC+08:00.
- The 146,907-byte existing minidump independently contains the same exception and relative address. Its module timestamp and image size match the installed executable. The exact exported handler address and `INT3` opcode are verified directly; no server executable was launched to obtain this result.
- Installed `mysqld.exe` and `mysql/bin/my.ini` SHA-256 values match their pinned values from the earlier successful recovery. This excludes drift in these two files; it does not establish unchanged database files, every dependency or all effective settings.
- The inspected C: drive has approximately 272 GB free. No current competing database listener or explicit bind failure was observed. Neither fact alone rules out all storage or configuration failures.
- The other `.err` files inspected contain dated October 8 records; their old recovery/readiness messages are not current crash evidence. Earlier October 21 authenticated recovery/login results remain dated successes, not proof of present stability.

Private evidence is under `.migration-private/shutdown-diagnostic-40c0e923b1`: hash-verified copies of the existing MariaDB log, XAMPP log and minidump; selected Windows crash records; recovery-pin comparison; current application/configuration hashes; collection metadata; and a bounded offline exception/export inspection script and its result. The three copied artifacts matched their source hashes before and after copying. Keep the minidump and raw logs private; do not attach them to public issues or review packages.

## Interpretation and limits

The exact MariaDB 10.4.32 [source for this handler](https://github.com/MariaDB/server/blob/mariadb-10.4.32/mysys/my_init.c#L259-L264) calls `__debugbreak()`. Microsoft's [exception documentation](https://learn.microsoft.com/en-us/windows-hardware/drivers/debugger/specific-exceptions) identifies the breakpoint exception. Together with the local binary and dump, this supports an internal runtime trap as the immediate crash mechanism. The handler can be reached through different failure paths; its name alone cannot distinguish invalid arguments, an upstream abort/assertion, corruption or another defect.

No matching PDB was found beside the server executable, and the checked standard Windows debugger locations were absent. A raw stack-address scan was exploratory only and is **not** a resolved call stack; nearest-export names for distant addresses are not reliable function identifications. One inefficient exploratory symbol scan was cancelled and replaced by the bounded exact-handler inspection. No root-cause conclusion relies on that scan or on superficially similar public bug reports.

No authenticated SQL, table checker, repair, migration, import, restore, configuration edit, server start/stop or financial write ran. No complete new database preservation copy was created. Current logical consistency and application/evidence totals have not been reverified. Existing recovery copies remain intact but must not automatically replace today's data.

## Recommended next diagnostic step

Analyze the **saved dump offline with matching debug symbols** before choosing a repair. MariaDB's [stack-trace guidance](https://mariadb.com/docs/server/reference/product-development/mariadb-fault-finding/how-to-produce-a-full-stack-trace-for-mariadbd) explains why an informative call stack is needed. Use the exact binary/PDB identity from the dump, keep analysis private, and do not install or replace the database server as part of obtaining diagnostic tools.

If the saved dump cannot identify the trigger, prepare a bounded isolated reproduction proposal first:

1. Reverify both servers/listeners are absent. Preserve the entire **current** stopped data directory, current configuration, logs, executable/dependency identities and protected uploads in fresh storage; hash and verify them. Do not reuse an older logical baseline as today's truth.
2. Create a separate examination copy. Prepare audited options that redirect every writable path, listen on a distinct loopback port, and disable provider/application activity and automatic repair. Do not mix MySQL80 binaries with the MariaDB directory.
3. Allow one specifically authorized diagnostic launch under an appropriate debugger/console, with bounded readiness and captured exit/exception data. Do not connect the working application, post financial entries, run table repairs, alter permissions or use recovery modes speculatively.
4. Stop on unexpected identity, path, write, warning or shutdown behavior. No retry/fallback repair, deletion of InnoDB/Aria files, old migration, directory replacement or forced stop is implied. Report the exact failing stack before proposing a targeted change.

Tool acquisition and any copied-server launch require the applicable execution/sandbox approval and a concrete reviewed setup. None was performed in this read-only investigation. The earlier recovery commands must not be rerun. Leave MySQL and Apache stopped. U3-B's backend/browser gate, C/D implementation and final combined regressions remain pending.
