# U3-A delivery: source checkpoint approved

## Final external approval — October 21, 2026

The user supplied ChatGPT's final exact-source approval with no remaining blocker. **U3-A source implementation, integrated verification and correction are complete; the source checkpoint is closed.** The [approval record](usability-u3-a-approval.md) records this documentation-only closure. Reviewed ZIPs/manifests remain unchanged; no new tests, database/server operations, commits or pushes ran. Working/Atikha acceptance remains separate. Next: the user commits/pushes U3-A, then separately requests U3-B planning and plan review. U3-B/C/D remain unstarted and unauthorized. Pending-review statements in the dated results below are superseded by this approval.

## Dated correction result — before final external approval

**U3-A review correction verified, October 21, 2026:** initial reconciliation now uses neutral loading/unavailable states and offers full details only after verification; failed refreshes retain the last successful snapshot. **43 database-free +91 authenticated focused checks passed** against corrected bytes, with no uncaught JS errors and unchanged working-data/config fingerprints. [Correction report](usability-u3-a-revision.md), [corrected exact-source ZIP](usability-u3-a-revision-review-package.zip), [revision manifest](usability-u3-a-revision-manifest.json) and [30 new screenshots](images/usability-u3-a-revision/index.html) are the current handoff. The earlier269-check/36-screenshot delivery is dated pre-correction evidence. External final U3-A approval and working/Atikha acceptance remain pending; U3-B/C/D are unstarted. No accounting/server rules, migrations, configuration, feature flags, working data, commits or pushes changed. Other suites retain their earlier results and were not rerun for this scoped presentation correction.

## Original integrated result — October 21, 2026, before the review correction

The user resumed **U3-A only** after the separate database recovery/startup/login checks. The prepared connected advance register/detail source is unchanged. Its previously blocked integrated verification is now complete: **269 fresh Edge browser/HTTP assertions passed** across the focused suite, evidence-bearing readback and affected Stage 2/3 regressions. This checkpoint is ready for exact-source external review, not yet externally approved or accepted by Atikha. U3-B/C/D have not started.

Review artifacts:

- [Exact-source review ZIP](usability-u3-a-review-package.zip), including the three application files, application patch, test tools, plan, report, reviewer notes, manifest and synthetic screenshots.
- [Application patch against U2-C](usability-u3-a-application.patch), [tracked regression-test patch](usability-u3-a-regression-tests.patch), [SHA-256/source manifest](usability-u3-a-source-manifest.json), and [reviewer notes](usability-u3-a-reviewer-notes.md).
- [36 screenshots and gallery](images/usability-u3-a/index.html): 26 main-suite captures plus 10 readback captures. Native viewport images are labeled `viewport`; other images show full scrolling pages or emulated print media. The gallery identifies response doubles explicitly.

### Final source and scope

The three production files remain byte-identical to the prepared October 20 implementation:

- `cash_advances.php` — SHA-256 `80EB6A34C75AFAB6A252A668FB63B2205663DE0EB951F93F61297E98AC87DA7A`.
- `assets/js/cash_advances.js` — SHA-256 `228E6A7F28F03B52539BFF259155A6E676F4531ACC92AA476963B91A1C1236CD`.
- `assets/css/accounting_workspace.css` — SHA-256 `7F09CB2A49C6ABC074DAE7E81A1E01FE683B1D93027E451BCF18815C70178461`.

They emphasize purpose/project, outstanding balance, separate settlement/deadline state, dated historical context, original/replacement lineage, eligible linked actions and complete operation journals. Reconciliation remains independent of display filters and prominent on discrepancies; its detail, journals and deadline history are secondary disclosures. Failed/malformed/stale register responses retain the successful applied snapshot. Print keeps current-page scope and all-matching totals distinct and expands the full financial details.

No accounting service/writer, endpoint contract, evidence/privacy rule, migration, configuration, feature flag, ordinary/correction editor or advance-entry authoring form changed in this resumption. CSS additions are scoped under `.advance-register`; no Tailwind rebuild was needed. Edited XAMPP source can serve on refresh, but this task did not perform working financial actions, deploy, commit or push.

### Fresh verification results

Commands use each suite's own new guarded synthetic clone and private copied application, sessions and evidence:

- `python scripts/test_usability_u3_a_browser.py --fixture=<new-U3-A-fixture>` — **84 checks passed**. Includes 26 real normal-API releases, real two-page filtered pagination (25 + 1), exact all-page PHP 12,500.00 totals, safe long labels, applied/draft filters, actual no-match queries, current/historical navigation and all five lifecycle states, exact journal links, independent reconciliation, print expansion/restoration, role gates, successful deadline/history update, retained failed inputs and stale-revision rejection. All 34 application tables were unchanged across its read/navigation/print phase; deliberate synthetic preparation and deadline writes were separate.
- `python scripts/test_usability_u3_a_readback.py --fixture=<completed-U3-A-fixture>` — **27 checks passed**. Confirms the privacy case actually has associated synthetic image evidence and authorized Admin links. Management HTML/JSON/print excludes private properties/URLs; complete journals remain printable; direct image downloads return 404 with Stage 1/2 enabled, Stage 2 off, and both off. Also verifies actual historical restatement of a settled advance to PHP 100.00 outstanding, the earlier deadline before a current extension, native viewports and keyboard opening of the deadline disclosure. All 34 application tables remained unchanged.
- `python scripts/test_stage2_browser.py --fixture=<new-Stage-2-fixture>` — **56 checks passed**, including real release/liquidation/return, retained print/filter behavior, same-request liquidation recovery, owner privacy and Management download/flag-off privacy.
- `python scripts/test_stage3_checkpoint4_browser.py --fixture=<new-checkpoint-4-fixture>` — **48 checks passed**, including lost-response release correction recovery, distinct original/replacement identities, corrected settlement balances/timeline, proof invalidation, exactly three two-journal correction bundles, retained register reconciliation and Management privacy.
- `python scripts/test_stage3_checkpoint5_browser.py --fixture=<new-checkpoint-5-fixture>` — **54 checks passed**, including ordinary corrections with Stage 2 off, paused advance actions without mutation, same-request recovery and flag-off financial privacy.

All five final browser runs report no uncaught JavaScript errors. They used **Edge 155.0.4283.62**, with 1366×768 and 1920×1080 viewports. Representative native, full-page, historical, discrepancy, failed-extension and evidence-bearing print screenshots were visually inspected. Long pages scroll; the native captures establish the actual fixed sidebar position. Full-page captures made after scrolling can place the fixed sidebar at the current scroll offset in the stitched image. Print screenshots use browser print media, not physical printing or a paginated PDF acceptance test.

PHP lint passed `cash_advances.php` and the synthetic fixture preparer. JavaScript syntax passed `assets/js/cash_advances.js`. Python compilation passed the three U3-A Python runners and affected Stage 2/CP4 runners (the PHP fixture is checked by PHP, not Python). No new U2, backend-487, adapter or reader run is claimed: the changed surface is the scoped register/detail; Stage 2/3 browser suites exercised its connected server workflows. The earlier **32 database-free presentation assertions** remain dated supporting evidence and are not included in the 269 fresh checks.

### Fixture, preservation and attempt boundaries

Each fresh database was cloned from the previously validated synthetic full-schema U2-C fixture, whose prior 487 backend checks are dated provenance only. Its source schema and synthetic user identities were rechecked before cloning. No migration/bootstrap ran, and the old disposable anonymous-routine compatibility helper was not enabled in this task. This does **not** resolve or retest migration-009's separate normal stored-procedure registration error 1304.

Authentication uses the existing guarded synthetic-session factory; real PHP authentication, role and ownership guards then serve HTTP pages/actions. These tests do not perform a new human login/MFA acceptance flow. Working credentials, sessions, drafts, receipts and financial records are never fixtures.

Provider endpoints and external browser requests were blocked. Child-only PHP settings additionally disabled URL streams, cURL execution, socket transports and mail; working PHP/application configuration remained unchanged. Successful final app copies were hash-compared to the three production files. Before/after **read-only** fingerprints of the working application tables/schema/objects and permission records match exactly, and the working `config.php`/`db_connect.php` hashes are unchanged. No working financial data, audit, users or grants were written; no working server was started/stopped by this task.

The first private preflight stopped on a duplicate test-helper include, before cloning; `require_once` corrected that orchestration. The first U3-A browser attempt reached a successful extension POST but its same-URL navigation wait queried the unloading DOM. The test now waits for the actual navigation; a fresh fixture passed the entire suite. CP4 first stopped on a wrong metadata-file selection before financial actions; subsequent preserved attempts exposed old case/currency expectations and a hidden-disclosure `inner_text()` assumption. Its assertions now inspect the actual status badge, exact formatted outstanding amount and reconciliation content plus visible health. These are test adaptations; no application correction was needed. Every financial rerun used a fresh clone, and earlier logs/copies were retained.

Private execution evidence is in `.migration-private/u3a-resume-7cb666f349` and `.migration-private/u3a-verified-ec81350bfe`; the latter contains final suite logs, execution JSON and `final-preservation-historical.json`. Final application copies/screenshots are in `u3a-browser-7e1abada46`, `u3a-readback-8907ab3c61`, `stage2-browser-2a034e06ce`, `stage3-cp4-browser-2b60e13614` and `stage3-cp5-browser-be3b7f28bd` below `.migration-private`. Private exports, sessions, config, raw receipts and database fingerprints are excluded from the public review package.

### Next gate

Send the exact-source ZIP for external review of U3-A. Do not start U3-B/C/D until the relevant review and authorization. Full working/Atikha acceptance, physical-device/other-browser testing, U2 follow-up before U5, U-PERF-01, U4/U5, original Stages 4–7 and migration-009 diagnosis remain separate outstanding work.

## Dated original handoff — superseded where the current result above differs

**October 21 recovery completion:** [working recovery, normal XAMPP startup and scoped Administrator login/read checks passed](database-phase4-working-cutover-20261021.md#scoped-normal-login-and-application-reads-completed). Post-login checks preserve financial/evidence/permission data and previous audit history, with one expected LOGIN audit. MySQL and Apache remain running normally; the guarded browser is closed. All three prepared U3-A application files remain unchanged. The9 recovery browser checks are not U3-A integration/regression evidence. **Resume boundary:** fresh guarded disposable fixtures, pending U3-A authenticated register/detail/pagination/as-of/deadline/privacy checks and affected Stage2/3 regressions, final-source full-shell screenshots, then exact-source review. U3-A remains incomplete; U3-B/C/D have not started. The older database-blocked/stopped-server statements below are dated evidence, superseded by this recovery result. Migration009's separate routine-registration diagnostic and full working/Atikha acceptance remain outstanding.

**October 21 earlier follow-up:** the user supplied ChatGPT's partial U3-A delivery review: no requested rollback, but approval remains pending authenticated/database/regression evidence and exact local source review. The user then selected database recovery before further U3-A work. [Phase 3-B recovery preparation/manual handoff](database-phase3-b-normalization-proposal-20261021.md) leaves all three U3-A application files unchanged; no U3 integration run or later U3 checkpoint occurred. Recovery preparation checks are not U3-A results. The original diagnostic/stopped-server descriptions below are dated events; consult current project status for later authorized recovery steps.

October 20, 2026. **Implementation prepared; checkpoint not complete or accepted.** The user authorized U3-A only: connected cash-advance register/detail presentation. U3-B release, U3-C liquidation and U3-D return redesigns have not started. The [U3 plan](usability-u3-plan.md) remains the scope contract.

## Implemented source

Three application files changed from U2-C HEAD ab653a1b59431d6c1ef0fad080f583da64961b8f:

- `cash_advances.php`: connected register/detail layout; purpose/project labels; prominent outstanding and separate posted balances; explicit current/historical date basis; original/replacement/correction context; linked eligible settlement actions; exact operation-journal links; secondary complete journals, deadline history/extension and independent reconciliation.
- `assets/js/cash_advances.js`: exact decimal-string formatting, complete-view preparation before replacing applied results, visible unfiltered reconciliation health/discrepancies, preserved failed/stale result handling, separate empty/no-match guidance, accessible reconciliation disclosure and print disclosure expansion/restoration. Deadline submission retains the existing endpoint, CSRF, advance ID and revision; failed responses retain entered values.
- Scoped `.advance-register` rules in `assets/css/accounting_workspace.css`: readable cards/rows, long-label wrapping, visible focus, secondary sections and print layout. Ordinary/correction workspace selectors remain unchanged.

The existing catalog's already computed reconciliation supplies control-account names; no new comparison/all-history reader query was added. No accounting services/writers, validation, evidence/privacy rules, migrations, configuration, feature flags or advance entry forms changed. The U3-B review-balance response projection was not implemented here.

Test infrastructure added: `scripts/test_usability_u3_a_fixture.php`, `scripts/test_usability_u3_a_browser.py` and `scripts/test_usability_u3_a_presentation.py`. The Stage 2 browser suite's control-label expectation now follows the selected account name instead of the old Account # label; its substantive checks remain. The new fixture preparer is guarded to a new private U3-A disposable clone and would create synthetic masters and 26 normal-API releases; it **has not run successfully** because database copying never completed.

## Completed verification

- PHP lint passed the changed application PHP and new synthetic fixture preparer.
- JavaScript syntax passed the changed register script; Python syntax passed the new runners and modified Stage 2 runner.
- **32 database-free Edge presentation assertions passed**, browser 155.0.4283.62, with no uncaught JavaScript errors. Final private evidence: `.migration-private/u3a-presentation-ca446d6bb2/results.json` and `.migration-private/u3a-presentation-utf8.log`.
- The presentation runner renders only the actual PHP view portion against explicit synthetic arrays and intercepts every browser request. It covers exact formatting, safe text, reconciliation warning/disclosure, print scope/full-journal disclosure/restoration, empty/no-match messages, historical actions and conditional Management controls. It does not execute application authentication or financial/database services.
- Native desktop/laptop component screenshots were inspected for layout and punctuation. They omit the authenticated application shell and are not working-application, authorization, financial or cross-browser/device acceptance evidence. Earlier renderer attempts exposed test-only missing-asset, print-event timing and Windows default-encoding problems; these were corrected in the harness. Production source retained UTF-8.
- Source-preservation checks confirm the working `config.php` and `db_connect.php` hashes are unchanged. All application changes are limited to the three files above; unrelated prior U3 planning edits remain preserved.

## Database blocker and stopped checks

The first disposable-clone attempt failed with connection refusal on 127.0.0.1:3306 while MySQL was stopped. The user then confirmed starting MySQL through XAMPP and leaving Apache stopped. A second copy attempt stalled with a zero-byte disposable export, before a successful clone or fixture preparation.

A bounded connection outside the sandbox returned **ERROR 2013 (HY000): Lost connection to MySQL server at 'handshake: reading initial communication packet'**. No authenticated identity result was obtained. A listener was observed on port 3306, and the startup log ended around socket creation without an observed ready-for-connections message in the inspected tail. These facts do not establish root cause or new financial-data corruption.

The verified stalled export client and its private copy worker were cancelled. MariaDB itself was not stopped, force-terminated, restarted, repaired or reconfigured by the agent. Failed private export folders/logs are preserved. No working financial records, drafts or receipts were used as fixtures; no migrations, permission changes or working configuration edits occurred.

**Not run/passed this checkpoint:** real PHP/auth/database U3-A browser checks; successful synthetic preparation; all-34-table read fingerprints; real pagination/historical/lifecycle data cases; actual deadline-extension success/stale-revision checks; Management HTML/JSON/download authorization and affected Stage 2/3 regressions. Earlier passing U2/Stage counts are dated evidence, not U3-A results.

After preserving this handoff, the user explicitly authorized a **separate read-only database investigation, with no repairs**. Its findings must remain separate from U3-A application verification. Migration-009 error 1304 is an older unresolved issue; a causal connection to this handshake failure has not been established. Do not rerun old working migrations or treat anonymous test blocks as a server repair.

The [completed read-only diagnostic](database-diagnostic-20261020.md) confirms local IPv4/IPv6 TCP connections received no initial greeting within the bounded probes. The current startup log ends at socket creation; it gives no explicit fatal cause. An October 13 Windows crash is older evidence, not a proven explanation. Current financial integrity and effective SQL identity remain unverified. MariaDB was left running and Apache stopped; no repairs or server changes were made. The next database step needs a separately reviewed preservation/startup-diagnosis plan, not another automatic clone attempt.

## Resume and scope boundaries

Once MariaDB is independently shown usable, create fresh guarded clones from a validated synthetic full-schema fixture and run the new U3-A browser suite plus affected Stage 2/3 checks against final application bytes. Use normal isolated auth/role guards, private sessions/evidence and blocked browser/server-side provider endpoints. Do not reuse a failed or partially prepared target without checking its state. Inspect full authenticated-shell screenshots and update this handoff with exact results before calling U3-A complete.

The changed application files are in the XAMPP source folder and can serve on refresh when Apache runs. Apache was left stopped by the user for testing; no working smoke was performed. No live provider calls, commits/pushes, later U3 checkpoints, U-PERF-01 work or database repairs occurred.

The user selected a separate [U2 follow-up before U5](usability-u2-followup-plan.md) for action-count evidence and optional currency/transfer wording refinements. Working/Atikha acceptance, restoration, performance diagnosis and remaining stages stay outstanding.
