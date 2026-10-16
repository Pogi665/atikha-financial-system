# U2-C delivery: task navigation and integrated U2 verification

**External exact-source review and documentation closure, October 17, 2026:** the user supplied ChatGPT's approval of U2-C application implementation with no application-code blocker. U2-A, U2-B and U2-C source scope are complete; this documentation handoff closes the U2 source checkpoint. ChatGPT reported checking the exact changed source, patch/blobs, syntax, all 30 screenshot hashes and independently passing eight offline routine-helper guards. Those are supplied reviewer findings, not new Codex execution or independent verification of GitHub state; ChatGPT did not rerun the private database-backed browser suites.

Working-system and Atikha acceptance remain separate. U-PERF-01, U3-U5 and original Stages 4-7 remain outstanding; U3 is not authorized. Migration 009's MariaDB stored-procedure registration error 1304 remains an unresolved, separate diagnostic issue before any future fresh deployment or recovery rehearsal needing those migrations. The anonymous-block compatibility path was test-only and does not establish normal stored-procedure migration/deployment success or a database repair. Do not rerun old migrations against the working database to investigate it.

Only this documentation handoff changed in the October 17 task. No application code, tests, migrations, database contents, configuration or feature flags changed, and no application/database tests, working smoke, diagnostics, commits or pushes were run. Next: the user can checkpoint/commit/push U2-C; choose and authorize any subsequent task separately. The October 15 exact-source ZIP and its manifest/screenshots remain unchanged as the reviewed evidence package. The local README, current status, U2 overview and delivery documents carry this later handoff.

October 15, 2026; execution began October 14. **U2-C source implementation and scoped isolated verification are complete.** U2-A/U2-B remain accepted checkpoints. This completes the source scope of U2, not working-system acceptance, Atikha observation or later completion stages.

The user authorized implementation after approving the [clarified U2-C plan](usability-u2-c-plan.md). Baseline HEAD remains `7674261e8aa999c580ae6ed6365c3603e19c1bae` (`U2-B`). No commit or push was performed; the user retains that work.

## Review package

- [30 inspected screenshots and local gallery](images/usability-u2-c/index.html), also individually available in `docs/images/usability-u2-c/`.
- [Final source/test/screenshot SHA-256 manifest](usability-u2-c-source-manifest.json).
- [Tracked application/helper diff against U2-B](usability-u2-c-source-review.patch). New test files are supplied as complete source, because ordinary `git diff` does not include untracked files.
- [Review ZIP](usability-u2-c-review-package.zip): exact five changed application files, changed/new test tools, approved plan, this report, manifest, patch, reviewer notes and screenshots. It contains no configuration, sessions, receipt fixtures or SQL exports.
- [Reviewer notes](usability-u2-c-review-notes.md).

## Resulting behavior

The sidebar now separates Dashboard, Transactions, Cash advances, Books & reports, Communications and Administration. Headings organize direct links; they do not open hub pages or accordions. Admin has distinct **Record a payment**, **Record money received**, **Other journal entry (General Journal)**, **Scan receipt** and **My drafts** tasks when their existing gates permit them. Management has existing read destinations, including discoverable CRB/CDB book links, with no accounting-entry or administration links. Server authorization is unchanged.

Payment/receipt tasks highlight independently from their read-only books. My Drafts highlights itself; exact journal views and posted correction comparisons use Find a transaction. Advance register, detail and authoring stay under Cash advances.

Correction authoring follows the trusted original target. Ordinary CRB corrections highlight the receipts book; ordinary CDB corrections highlight the payments book; original GJ or legacy/missing source-book context highlights Find a transaction. Any trusted advance operation selects Cash advances. These rules apply to reverse-only and reverse-and-replace, even when reverse-only generates GJ or has no advance presentation configuration. Changing a permitted replacement book does not change the original navigation context.

Every displayed current destination has `aria-current="page"`. The sidebar keeps links as direct `<nav>` children, exposes a main-navigation landmark and group headings, wraps long labels, prevents icons from shrinking and provides visible keyboard focus. Its links scroll inside the sidebar; scrollbars are no longer explicitly hidden. Existing role colors, fixed desktop shell and print hiding remain.

Existing Stage 1/2/schema helpers and independent OCR gating still determine visibility. Admin's scalar General Journal fallback remains with Stage 1 off. Installed ordinary corrections can remain available with the Stage 2 UI flag off; advance correction starts retain their own gate. No gate, permission, financial calculation or evidence rule was relaxed.

## Exact scope

Five application files changed:

- `includes/nav.php`: grouped destination catalog, existing role/gate branches and accessible current links.
- `assets/css/sidebar.css`: focus, label wrapping, icon sizing, heading styling and internal scrolling.
- `includes/accounting_entry_page.php`: active key based on ordinary task or trusted correction/advance provenance.
- `accounting_drafts.php`: My Drafts active key.
- `journal_corrections.php`: posted-comparison active key.

Shared accounting JavaScript, workspace CSS, posting/read services, migrations, working configuration and feature flags remain unchanged. No Tailwind rebuild was needed. Source hashes across all **124 application files** match each of the seven successful browser test copies; exactly the five files above differ from accepted U2-B.

Verification tools changed separately: `scripts/cli_common.php` gained the opt-in isolated routine mechanism described below; `scripts/test_cli_inline_routines.php`, `scripts/test_usability_u2_c_browser.py` and `scripts/test_usability_u2_c_records_fixture.php` were added. Existing U2-A/U2-B and Stage 1/2/3 regression scripts were not edited. Final test hashes are in the manifest; new test files created later are not claimed to have existed in earlier browser copies.

## Executed checks

All listed successful runs used the final application source. Each browser run used its own guarded disposable database/application/session/evidence copy, with synthetic accounts/data and external-call boundaries. Recorded browser version was Edge **155.0.4283.45**. Counts represent assertions, not independent client tasks or test participants.

- **368 U2-C browser/HTTP assertions**: final `.migration-private/u2c-browser-810bb7ed40/results.json`; no uncaught page errors; native exit 0.
- **124 U2-A/payment assertions**: `.migration-private/u2a-browser-2369aedf5e/results.json`; no uncaught page errors; native exit 0.
- **199 U2-B receipt/GJ/transfer assertions**: `.migration-private/u2b-browser-b951e8f6dd/results.json`; no uncaught page errors; native exit 0.
- **68 Stage 1**, **56 Stage 2**, **48 Stage 3 CP4**, **54 Stage 3 CP5** browser assertions: private logs `u2c-stage1.log`, `u2c-stage2.log`, `u2c-cp4.log`, `u2c-cp5.log`; native exits 0 and suites reported no uncaught JS errors. Artifact directories are recorded in the manifest.
- **487 integrated backend assertions**, native exit 0: 71 Stage 1, 96 Stage 2, 48 CP1, 59 CP2, 53 CP3, 64 CP4, 96 CP5. `.migration-private/u2c-backend-final.json` and separate stdout/stderr logs record the controlled process result. This run used the explicit test-only routine mechanism below.
- **90 adapter assertions**, **16 exact-reader assertions**, **8 offline routine-helper guard assertions**.
- PHP lint passed the four changed application PHP files and three changed/new PHP test tools; Python syntax passed the new U2-C runner. `git diff --check` passed.

U2-C checks cover all 32 Admin/Management × Stage 1/2/3/OCR flag combinations, intended destinations/groups/labels, direct authorization and every normal permitted sidebar route. Separate disposable partial-schema cases remove Stage 1, Stage 2 or OCR prerequisites and confirm fail-closed visibility. Anonymous, inactive and unsupported cached sessions cannot gain access.

Current-state checks cover ordinary entry/book/draft/setup/read/comparison locations and release/liquidation/return contexts. Both correction modes were exercised for ordinary CRB/CDB/GJ and advance-linked release/liquidation/return targets, including saved/reopened drafts, changed replacement books and legacy original context. Page reads are fingerprinted separately from intentional private draft/audit saves.

All 34 application tables retained identical row fingerprints across normal navigation/flag/read passes and correction page reads. Records setup deliberately posted 28 balanced synthetic journals through the ordinary save/review/post contracts, only in the new test database. Subsequent fingerprinted read checks verify CRB/CDB whole-entry account/search filtering, split lines, totals across pagination, corrected originals/reversals/replacements and History's separate line filtering. Failed/stale filter responses retain the appropriate successful data. Fixture preparation is not counted as read-only activity.

Dirty-sidebar checks retain edits on Stay and on failed Save-and-leave; successful Save-and-leave waits for one acknowledged private draft. Current-task clicks are inert. Affected payment/receipt regressions preserve unknown-result navigation blocking and the original durable request, with recovery producing one posting. Advance/correction and Management exact-record evidence restrictions also pass with entry flags off. Existing scalar OCR handoff is covered without live extraction.

## Fixture bootstrap issue and verification limit

The first new fixture bootstrap stopped during migration 009 with MariaDB error **1304, `PROCEDURE migrate_identity_foreign_keys already exists`**, before operational assertions. A minimal probe reproduced it against a second disposable target, even after its routine DROP check. The cause is **unknown**. Failed databases/logs are preserved; no server repair, restart, permission change or working migration was attempted.

To continue isolated operational verification, `ATIKHA_TEST_INLINE_ROUTINES=1` enables a guarded compatibility path in the CLI test helper. It requires both `ATIKHA_ISOLATED_TEST === true` and an already selected `atikha_test_*` database, then pins that target before every fixture statement. Zero-argument procedure bodies are retained in memory and executed as `BEGIN NOT ATOMIC ... END` at their corresponding CALL; registration/DROP statements are omitted. Unsupported routine shapes and unknown calls fail closed. Without explicit opt-in, normal CLI behavior remains unchanged. Migrations 009/019 themselves were not edited.

MariaDB documents anonymous compound blocks outside stored programs. This supports the chosen test mechanism; it does not diagnose the registration failure. [MariaDB BEGIN/END](https://mariadb.com/docs/server/reference/sql-statements/programmatic-compound-statements/begin-end), [compound statements outside stored programs](https://mariadb.com/kb/en/using-compound-statements-outside-of-stored-programs/).

Eight offline guard assertions cover preserved body/order, omitted registration/live USE, working-target rejection, unknown/unsupported routines and unchanged normal SQL behavior. The final integrated run executed the same fixture migration bodies and passed 487 operational assertions with native exit 0. Its stderr includes the deliberate negative audit-failure test; that expected message is not an unexplained server failure. An earlier PowerShell wrapper formatted that stderr as an error despite completed assertions, so the final launcher captured native exit code and streams separately.

**Normal stored-procedure registration and deployment through the unmodified migrations were not validated successfully this turn.** Diagnose that environment behavior separately before any future deployment requiring routine registration. Passing U2-C tests must not be represented as a repair or a working-database health certification.

## Screenshots and remaining boundaries

Thirty published PNGs were visually inspected: 15 paired states at 1366×768 and 1920×1080, including grouped entry/book navigation, My Drafts, keyboard reach to Audit, scalar fallback, ordinary/advance reverse-only location, whole-entry search, History filters, Management read/print, dirty leave and payment uncertain/recovered states. Full-page captures can be taller than the viewport. Print images use browser print-media emulation, not a physical print test.

My Drafts initially captured its loading state; the published pair was recaptured after the list settled on the same isolated application copy. All application-row fingerprints remained unchanged. The four payment uncertain/recovered images come from the successful 124-check payment regression; all application hashes match U2-C. Original private captures and failed development runs remain preserved.

No working financial data, drafts, receipts, accounts, configuration, flags or permissions were changed or used as test fixtures. Test servers were isolated PHP processes; the working XAMPP/database servers were not restarted. No live OCR/SMTP/forecast/anomaly call, normal-login working smoke, other-browser/device compatibility test, Atikha observation, restoration rehearsal or new performance measurement is claimed.

The application files are in the existing XAMPP source folder, so enabled routes can serve the new PHP/CSS on refresh. That source-serving effect is separate from working acceptance. **U-PERF-01, U3–U5 and original Stages 4–7 remain outstanding.** Optional U2-B currency/transfer wording polish was not added here.

Current next action after the October 17 external approval: the user handles checkpointing/committing/pushing. The exact-source review and documentation handoff are complete. Any separate diagnostic, working acceptance run or U3 planning/implementation requires its own scope and authorization; no next stage started.
