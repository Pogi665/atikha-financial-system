# U2-C exact-source review notes

October 15, 2026. Read `usability-u2-c-delivery.md` first, then the manifest, patch and exact application/test source. The user handles Git commits/pushing.

The ZIP includes five final application files, the changed CLI test helper and three new test files. The tracked-file patch is against U2-B HEAD `7674261e8aa999c580ae6ed6365c3603e19c1bae`; new tests are complete files, not hidden behind hashes. Screenshots/gallery are under `docs/images/usability-u2-c/`. Verify their SHA-256 values against the manifest.

Review the trusted target operation/source-book precedence in correction navigation, especially reverse-only and changed replacement books. Confirm existing role/schema/flag helpers and server authorization remain authoritative. Check direct-child anchors, escaped labels, `aria-current`, headings, long-label wrapping, focus and internal scrolling.

Reported evidence: 368 navigation, 124 payment, 199 receipt/GJ/transfer, 68 Stage 1, 56 Stage 2, 48 CP4 and 54 CP5 browser/HTTP assertions; 487 backend, 90 adapter, 16 reader and 8 offline guard assertions. Counts are Codex execution evidence unless independently reproduced.

Important limit: fresh fixture setup encountered unexplained stored-procedure registration error 1304. The backend passed using explicit disposable-only anonymous-block routine execution. Read the report's mechanism/guard/limitations section and inspect `scripts/cli_common.php`. No database repair or successful normal migration-deployment validation is claimed.

All 124 application hashes matched each of the seven successful browser application copies. Browser runtimes/configuration and mutating regression fixtures are private and omitted. Never reproduce database-backed tests against the working database. The offline adapter/guard suites can run without a database; backend/browser suites require new guarded disposable fixtures and isolated provider/session/evidence boundaries.

U2-C source scope is delivered. Working/client acceptance, U-PERF-01, cross-browser/physical-device validation and later stages remain pending.
