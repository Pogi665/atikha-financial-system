# Coordinated database incident and U3-B-D execution handoff

**Execution stopped at the envelope's current-data validation gate.** The [consolidated outcome](database-incident-outcome-20261021.md) and [sanitized manifest](database-incident-manifest-20261021.json) supersede the in-progress entries below. Preservation and informative offline analysis passed, but current audit/history data could not be fully read/validated on the isolated capture copy. No repair, cutover or U3 resumption occurred. Final original/preservation/application hash checks passed and all servers are stopped. Keep all private evidence and partial B source; no further operational retry is implied.

**Phase 1 obtained an informative validated failure path; Phase 3 baseline capture is in progress.** The official MariaDB symbol archive and signed SHA-256 list verified against publisher fingerprint177F4010FE56CA3336300305F1656F24C74CD1D8. Its PDB exactly matches dump GUID D0435555-C131-4E46-9091-FC529819BE77, age1. The existing Microsoft-signed Windows debugging engine resolved the copied dump without installation or server execution. A first API-adapter method-count error occurred before the dump opened; the corrected method map includes variadic methods and was checked against Microsoft's header. Both outputs remain private.

The validated stack is buffer-pool preload -> background page read -> change-buffer merge/bitmap read -> buf_page_get_low after100 retries -> ib::fatal -> abort/raise -> breakpoint. PDB symbols call the shared breakpoint address my_sigabrt_handler; the earlier nearest exported my_parameter_handler name is not evidence of an invalid-parameter initiating cause. The caller error is DB_PAGE_CORRUPTED. Tablespace9 maps to current atikha_finance/budgets.ibd. Read-only checksum inspection on a separate byte-identical copy confirms page1 is invalid; separately checked pages2/3/4 pass. A page0 option uses the utility's zero sentinel and did not isolate page0, so it is not evidence of a second bad page. No matching space9/page1 redundant page was found in today's preserved system tablespace. No checksum was rewritten and no repair selected merely to hide the mismatch. How the page became damaged remains unproven; no attribution to U3-A or migration009 is made.

Offline Stage A therefore supplied a concrete failure path; a generic isolated reproduction is unnecessary. One fresh unrepaired capture candidate is being prepared from current preservation, with every runtime/data path isolated on3307. Its only crash-path mitigation is disabling the now-proven failing startup preload temporarily, plus isolation/event/replication/read-only guards. It must capture a complete trustworthy current pre-correction logical snapshot before any target repair can be considered, then shut down gracefully. This is not a passing correction, promotion or current-integrity result. Working MySQL/Apache remain stopped and untouched.


**Phase 0 passed; Phase 1 in progress, October 21, 2026.** Fresh private preservation is at `C:\xampp\mysql\recovery-exports\incident-20261021-093316cb4b`. The entire current database, application working tree (including Git/uncommitted source and dependencies, excluding historical `.migration-private` caches), all53 uploads, MariaDB bin/share, current incident evidence, configuration/helper inputs and existing dump-referenced dependencies were preserved. Source-before/copy/source-after manifests match. No hard links/junctions were used; the fresh archive is restricted to the current account and SYSTEM. No SQL, server or repair ran.

The first attempt `incident-20261021-73807e6728` stopped before database copying because cached Python DirEntry metadata reports link-count0 on this Windows filesystem. Read-only `fsutil` and native handle metadata confirmed one link; fresh path stat also reports1. The helper was corrected to use fresh metadata, with its prior version retained, and a new unique attempt passed. This was a guard implementation issue, not observed file corruption or a bypass of an actual hard link.

The preserved dump has flags0, exception/thread/module/memory streams,33 threads and38 modules. Its exception thread11620 has1,232 bytes of context and22,728 bytes of captured stack; the saved stack pointer lies within that captured range. This supports attempting a stack walk but does not guarantee all required memory is present. Exact symbols are not yet acquired/matched. Microsoft's existing signed Windows `dbgeng.dll` and `dbghelp.dll` were found, offering an offline API route without installing a debugger. Official metadata acquisition is in progress; no server launch or candidate correction is selected.


October 21, 2026. **The user explicitly authorized the full [execution envelope](database-incident-u3-execution-envelope-20261021.md).** Successful internal gates require no further routine external review. Scope exclusions, sandbox/tool permissions and hard stops remain binding. No commit/push is authorized.

## Current execution state

- Phase 0 current-state preservation: preparing a new unique private archive; no completion claimed yet.
- Phase 1 offline dump analysis: pending preservation gate.
- Phase 2 isolated reproduction: conditional, not run.
- Phase 3 targeted candidate correction: conditional, no defect-specific operation selected.
- Phase 4 candidate validation: not run.
- Phase 5 working cutover/normal start: not run.
- Phase 6 bounded authentication/read-only application acceptance: not run.
- Phase 7 U3-B/C/D: paused pending environment acceptance. Existing partial B source/tests are retained; B gate incomplete, C/D not enabled.
- Phase 8 final review package: not prepared.

Current read-only inventory found no relevant database/Apache process or listeners. The complete current data directory inventory is approximately 495 MB across 3,609 files; all 53 working upload files total approximately 4.7 MB. These are filesystem inventory figures, not logical financial baselines or integrity results. Current code, configuration and protected upload definitions were inspected without executing application configuration. No SQL or server launch ran during this preparation.

Next: complete verified current-state preservation, inspect the preserved dump's type/stream/thread-stack coverage and obtain trusted exact-match diagnostic material. Record every gate here as execution progresses. Keep raw evidence, sessions, configuration and exports private.
