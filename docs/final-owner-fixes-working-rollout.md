# Final owner corrections and working rollout

Completed October 29, 2026 under the explicit owner authorization to replace working financial/domain data with the fictional ATIKHA-DEMO-V2-FULL dataset. No commit or push.

## Final status

- The normal XAMPP application at `http://127.0.0.1/Financial%20Management%20System/` is running on **`atikha_finance`**, now populated with V2 fictional presentation data.
- Apache is running normally; MySQL was kept running throughout. The pre-rollout rollback backup remains intact and was restoration-tested.
- All three existing Users, password hashes, IDs, active states, MFA fields, identity records and password-reset records were preserved exactly. No synthetic login replaced an existing account; no additional Management account was needed.
- **Primary Administrator is initialized: UserID 1 — System Administrator**, explicitly selected by the owner on October 29. Designation actor is UserID 1. Ordinary Admin 9 remains an Admin but cannot modify the protected Primary account. No credential or financial data changed during finalization.
- No real email, MFA delivery or Gemini call was performed. Existing provider configuration and authentication source were preserved. This is rollout verification, not provider/login acceptance or Atikha/client acceptance.

## Source corrections

The actual local workspace is authoritative. Branch `main`, HEAD and cached `origin/main` remain `1bb6a074f99c42569c88bf73a3d2a54dd3f532d2`. The 102 pre-existing modified/new files were retained and extended only within the approved owner correction scope.

### Planning

- Default is next month; current month is labelled Current Month Outlook. Live October 29 planning excludes incomplete October and describes completed history through September.
- Recommended Allocations contains positive eligible estimates; Needs Manual Planning separates exceptions; Other Detailed Estimates is collapsed. Calculation methods are secondary.
- Calculated zero cannot prefill an explicit budget zero. Existing deliberate zero allocations remain valid.
- Selection count/total is separate from independent organization forecast and effective approved total. Confirmation identifies month, count, selected total and new/revision intent.
- With approval, the private draft copies the effective approved revision and overlays only selected positive recommendations. Unselected allocations are retained. No automatic submission/approval.
- Existing budget writer gained an optional new-create guard under its actor/shared-coordination/month-root locks. Identical committed retry recovery precedes the guard; stale/tampered requests are rejected. No estimation mathematics changed.

### Reports

- Export menus are viewport-clamped above content/navigation at expanded/collapsed 1366, 1920 and 390 widths, with Escape/focus recovery.
- Normal report printing fetches the existing validated print output into a temporary same-page print surface. Print dismissal restores the normal page and opener. No normal new-tab workflow; technical print endpoints remain.
- Selected report print omits the setup appendix. The complete package prints eight components; an explicit Print Options choice includes the appendix.
- Complete Report Package is described as four financial statements plus four Supporting Accounting Schedules. Frozen copies open at the top with revision/state/friendly timestamp, frozen explanation and Back to Live Report.
- Default live report mathematics now uses today for the current month, month-end for past months, and rejects future actual periods. Trusted explicit historical cutoffs remain available to internal deterministic checks; no request clock override was added.
- Frozen payloads keep their captured cutoff. Native export metadata also states the actual cutoff. Two existing mojibake separators in the touched print presentation were corrected.

### Budgets and deliberate non-change

- Current actuals end at the accounting date, past actuals at month-end. Future budgets show zero actual activity and no claimed actual cutoff. Dates are friendly in the normal page.
- Blank versus explicit zero, recognized-expense actuals, effective approval and immutable revisions remain authoritative.
- Advanced Accounting Entry remains hub-only and was not added to sidebar children.

### Changed files in this owner batch

- `assets/css/atikha_ui.css`
- `assets/css/planning.css`
- `assets/js/atikha_ui.js`
- `assets/js/planning.js`
- `assets/js/report_bundles.js`
- `budgets.php`
- `docs/current-state.md`
- `docs/decisions/011-reporting-and-period-controls.md`
- `docs/decisions/012-budget-revisions-and-actuals.md`
- `docs/decisions/013-deterministic-planning-and-audit-advisories.md`
- `docs/final-owner-fixes-working-rollout.md`
- `financial_export.php`
- `financial_statements.php`
- `includes/budget_actuals.php`
- `includes/budget_revisions.php`
- `includes/financial_export.php`
- `includes/financial_report_query.php`
- `includes/planning_budget.php`
- `includes/planning_query.php`
- `includes/presentation.php`
- `includes/report_snapshots.php`
- `includes/report_workspace.php`
- `includes/reporting_common.php`
- `planning.php`
- `planning_actions.php`
- `report_bundles.php`
- `reports.php`
- `scripts/test_owner_review_contracts.php`

Private one-time backup/rebuild/seed/verification/recovery commands and evidence are untracked under `.migration-private/final-owner-rollout-20261029/`. Existing frozen deliveries/packages were not regenerated.

## Backup and recovery

Database: `atikha_finance`. Backup timestamp: `20261029-081411`. Full SQL includes all original schema/data/triggers and requested routines/events (none were present).

- Full database backup: [atikha-finance-before-20261029-081411.sql](../.migration-private/final-owner-rollout-20261029/rollback/atikha-finance-before-20261029-081411.sql); 126,885 bytes; SHA-256 `0f6e1c1d248ea160c8e1fe164ca1421efbe2ba8665a09def9727ddcb0bc95584`.
- All-upload archive: [uploads-before-20261029-081411.zip](../.migration-private/final-owner-rollout-20261029/rollback/uploads-before-20261029-081411.zip); 4,243,118 bytes; SHA-256 `197dbf0723580ee483d6b50565cdf35d66c8ca8edad6e4aba20f02d79fe99f0e`.
- Exact authentication restore data: [authentication.sql](../.migration-private/final-owner-rollout-20261029/rollback/authentication.sql); 3,320 bytes; SHA-256 `219dbd61ebbb7b755861d9955c27b27f6e66c7ffcf0ae994096292761c52f082`.
- Verified predecessor schema: [verified-predecessor-schema.sql](../.migration-private/final-owner-rollout-20261029/rollback/verified-predecessor-schema.sql); 124,965 bytes; SHA-256 `d556547fd2b10c00d4b8a3387851c4c555e61abb2fbfed29de31c5a65e7e8a57`.

- The SQL backup was restored into a guarded temporary verification database, matched all 34 table row/hash fingerprints and 24 triggers, and that temporary target was removed afterward. No new long-lived preview was created.
- All 53 original upload files were archived, read back/hash-checked and retained unchanged in place. New watermarked V2 evidence uses new filenames; no original evidence file was deleted.
- Recovery command (prepared, **not executed**): first stop Apache and confirm no working connections, then `python .migration-private/final-owner-rollout-20261029/restore_pre_rollout.py --confirm=atikha_finance --restore-pre-rollout`.
- The guarded command verifies backup hashes and the exact database, restores all original tables, restores original uploads and quarantines extra V2 files. Apache remains stopped until read-only validation and explicit restart. Do not run seed/rebuild commands blindly after interruption; inspect their start/complete markers first.

## Schema and user preservation

- Before: 34 tables, 24 triggers; Stage 1–3 complete; reporting configuration, approved budget revisions, period controls and 026 absent, not partially installed; zero cross-schema foreign keys.
- Used authorized Option B: schema-only extraction from the verified complete V2 schema, with original working authentication table definitions substituted verbatim; restored exact Users/user_identities/password_resets rows. No working financial rows were used as fixtures.
- The schema replacement used connection-local FK suppression solely to replace backed-up cyclic table structures. FK checks were restored immediately; no global configuration was changed. Complete predecessor guards and user hashes passed before 026.
- Applied `migrations/026_primary_administrator.sql` once, after proving absence and complete predecessors. No 015/019/020/021 or already-represented migration was replayed. Later components came from the verified schema structure, not historical migration execution.
- Final: 51 tables and 52 triggers; full schema guards pass and zero cross-schema foreign keys.
- Automatic selection was correctly withheld at rollout because two active Admins existed. The owner subsequently selected UserID 1 explicitly; working initialization and protection verification are now completed as recorded below. Prior isolated guard/race evidence retains its original dated scope.
- Executed the prepared guarded command: `php .migration-private/final-owner-rollout-20261029/initialize_working_primary.php --confirm=atikha_finance --user-id=1 --actor-id=1`. It uses the reviewed service transaction, locks and audit. The test initializer was not loosened and migration 026 was not replayed.
- V2 workflow actors map to existing active finance Admin 9 and Management 8. Mapping dataset actor keys is not Primary designation. Existing identities and credentials were not changed.
- Locked Composer install completed with network disabled: nothing to install/update/remove; autoload regenerated. PHP/platform checks passed on PHP 8.2.12; minimum remains >=8.0. Native Dompdf/SimpleXLSXGen dependencies are available to the actual application.

## V2 dataset and results

Authoritative bundle: `docs/atikha-demo-dataset-v2-full.zip`, SHA-256 `dc9d71c038f0e5416355c5f8aa40e11e9dab3256e1c492a06822f0f01004f444`. The guarded one-time private working adapter uses the same inspected V2 domain-service seed mechanism, with explicit `--confirm=atikha_finance`, backup/schema gates and preserved actor mapping. It refuses blind re-entry after its start marker.

- 120 incoming-money business events are CRB journals; 360 final recognized-expense events include legitimate liquidations/corrected replacements; 24 General Journal reclassifications have zero organization-wide net recognized-expense effect. Retired legacy incoming/expense tables were not populated as duplicate records.
- Recognition history covers November 2025–October 2026, with explicitly designated October 2025 opening funds. All 12 income/expense/opening-cash/closing-cash/approved-budget oracles matched independently.
- At the October 29 rollout verification, October: income PHP 530,000; recognized expenses PHP 307,250; opening cash PHP 3,465,000; closing cash PHP 3,673,550; approved budget PHP 339,900. Actual cutoff is October 29.
- November approved budget PHP 330,000 plus a distinct submitted proposal. Approved, superseded, returned and pending budget/report examples remain represented.
- Trusted November 1 forecast benchmarks: Last Month PHP 307,250; MA3 PHP 298,433.33; weighted MA3 PHP 301,366.67; trend PHP 315,486.67. All four match. This trusted clock does not change the live October 29 cutoff.
- FAM-YTH remains insufficient at granular level (7 insufficient rows under the trusted November clock).
- Advance scenarios include partial/full liquidation, unused-cash return, settled, outstanding and overdue/due-soon states. Corrections retain original -> reversal -> replacement. Evidence/manual review is fictional and watermarked; no successful provider extraction was fabricated.

Current working counts at the October 30 post-defense checkpoint (read-only observation):

- `journal_entries`: 572.
- `journal_entry_lines`: 1144.
- `Receipts`: 294.
- `evidence_item_reviews`: 277.
- `cash_advances`: 22.
- `journal_corrections`: 15.
- `budget_revisions`: 17.
- `budget_revision_allocations`: 294.
- `complete_report_bundles`: 14.
- `complete_report_reviews`: 13.
- `Notifications`: 60.
- `audit_logs`: 3024.
- `projects`: 6.
- `parties`: 64.
- `Categories`: 18.

The initial rollout notification count was 60 because existing working identities include one active Management recipient rather than two synthetic Management identities. Domain journal/budget/report states and monthly financial oracles are unchanged by actor mapping.

### Preserved V2 interpretation/deviations

- Evidence uses truthful locally generated watermarked manual-review images; no successful OCR/provider extraction is fabricated.
- Repeated summary budget scenario rows map to the same actual revision, with explicit historical supersession/return preserved.
- Future November forecast benchmarks require a November 1 oracle; current live date still excludes incomplete October.
- AUD-DEMO-03 automatic-provider failures and AUD-DEMO-05 successful sign-in IP history are not fabricated in presentation data; manual/unconfigured OCR is excluded and new-IP rules have separate technical tests.
- AUD-DEMO-04 monetary-change monitoring is disabled because no reviewed threshold is configured.
- CA-DEMO-012 has November 13/16 settlement events but a contradictory Settled-as-of-October label. Future events are not posted before their dates; current status remains Outstanding. The authoritative October monthly totals are preserved.

## Verification and limitations

- 11 focused pure owner contracts; 29 deterministic advisory checks; 16 isolated service/API checks; 29 isolated browser checks before working mutation.
- 42 independent read-only working checks, including twelve monthly oracles, four forecast benchmarks and all fourteen captured frozen cutoffs; 47 normal-XAMPP browser/HTTP checks.
- Browser viewports: 1366x768 expanded/collapsed, 1920x1080, 390x844. Inspected menu/financial layouts and Planning; no horizontal page overflow in checked report states.
- Working Admin Dashboard, hub/advanced entry, Transactions, General Ledger, advances, Budgets, Reports, Planning, User Management, Audit Trail and Audit Alerts passed. Management Dashboard, readers, Planning and Review Queue passed; Admin navigation/access and private evidence remain restricted.
- PDF parsed successfully (2 pages), rendered/inspected with the current cutoff and expense total; Excel ZIP/XML had 76 cells and no formulas; CSV contained the expected cutoff/total. All three were downloaded from the normal working application.
- Print lifecycle tests used a stubbed browser print call plus afterprint to verify cleanup, no new tab and appendix selection. Physical print-dialog interaction/printer output is not claimed.
- Invalid CSRF was rejected without a draft write. Verification did not change financial counts. Two temporary verification sessions were removed; normal login/MFA was not exercised or weakened.
- Configuration/server pins remained identical. Provider functions and URL wrappers were disabled in CLI seeding/verification, and browser checks exercised read-only routes (apart from a rejected invalid-CSRF request). Real provider acceptance remains unperformed.
- Both protected participant/defense databases matched all 100 table fingerprints. All 1,912 protected runtime/application/session/evidence files matched their previous hashes. Original 59912 preview and defense mailbox/ports/credentials were not replaced. Historical participant A01 remains paused/incomplete; no human task was fabricated.
- No broad unrelated financial suite was rerun merely for assertion count. The previous pre-defense package remains dated evidence, not evidence of these later source fixes.

### Tooling corrections / remaining blocker

- Backup preflight first detected authentication DDL column-order/auto-increment differences between schema sources. Original working authentication DDL was retained exactly before rollout; no credential conversion was needed.
- The first post-rebuild verifier had a duplicate PHP include. Only the verifier was corrected; the completed schema rebuild was not replayed. Complete predecessor/auth guards then passed before the single 026 application.
- The first working privacy test expected 403, while the existing endpoint intentionally conceals restricted evidence with 404. The assertion was corrected and verified; no image bytes were disclosed and no production fix was needed.
- Primary Administrator finalization is complete following explicit owner selection. No designation blocker remains. Working credentials, V2 financial state and the existing rollback backup were preserved.
- Accessibility certification, physical screen-reader/zoom/device acceptance, real provider/login/MFA acceptance and Atikha/client acceptance remain separate and unclaimed.

## Primary Administrator finalization — October 29, 2026

- Explicit owner-selected Primary: **UserID 1 — System Administrator**; designation actor **UserID 1**. Stored role remains `Admin`, with no third role.
- Initialization timestamp: **2026-10-29 00:49:08 UTC** (October 29, 2026, 08:49:08 Asia/Manila). Exactly one singleton designation exists.
- Preflight confirmed active database `atikha_finance`, complete/consistent already-installed 026, no prior designation, active Admin UserID 1 and intact recorded rollback hashes. No rebuild, reseed, migration, user replacement or provider call occurred.
- Used the existing guarded working initializer and `primary_administrator_initialize()` with normal transaction/locking/audit. An identical retry returned duplicate recovery without changing the original timestamp or adding a second designation audit. A conflicting designation was refused.
- **47 focused working-service assertions passed.** Admin 9 was denied disable, demotion and credential-change attempts. Unsupported delete requests were rejected. Primary self-disable/demotion were rejected; a permitted same-value self-edit succeeded without changing identity/password/MFA data.
- Actual singleton/FK constraint probes were rejected inside transactions that were always rolled back. The initialized designation remained intact. No SQL bypass was used for initialization.
- **18 normal-XAMPP browser/HTTP checks passed**, covering Primary and ordinary Admin views at 1366x768 and 1920x1080. The Primary badge is visible; Primary self-edit is available with the role protected; ordinary Admin edit/status controls are hidden and the reset action is disabled. Real HTTP attempts to disable/demote/change credentials were refused. The actual reset writer rejected Admin 9 against the existing pending request before any password write; that request remains pending and unchanged.
- Full before/after table fingerprints match everywhere except `system_primary_administrator` and `audit_logs`. All Users, identity/password-reset records, password hashes and MFA fields match exactly. All V2 financial/domain tables are unchanged.
- Audit delta is exactly **two**: one designation CREATE and one permitted same-value Users EDIT used to verify self-edit. Rejected requests and initialization retries produced no duplicate designation/audit records.
- Existing rollback files and recorded hashes remain intact. Apache is running normally. Two temporary verification sessions were removed; ordinary login sessions were not changed. Authentication/MFA/provider acceptance was not rerun.
- Only this report and `docs/current-state.md` changed in the repository during finalization; production PHP/JS/CSS, migration files, configuration and financial data were not changed.
- Private evidence: `.migration-private/final-owner-rollout-20261029/primary-finalization-20261029/` — `preflight.private.json`, `service-checks.json`, `browser-checks.json`, `final-verification.json`, browser screenshots and session-cleanup record. Earlier rollout evidence is retained unchanged.
- Tooling notes: the first browser selector matched the common role label rather than the selected account; it was corrected before mutation checks. The final fingerprint verifier's Windows newline serialization was corrected; canonical row hashes then matched. Neither issue required a production/data change.

## Post-defense documentation reconciliation — October 30, 2026

The owner reported a successful capstone defense and identified the current local source and populated `atikha_finance` as the demonstrated system. A read-only checkpoint query confirmed Primary Administrator UserID 1 remains initialized, with 51 tables and 52 triggers. The current audit count is **3,024**, superseding the initial seed count of 2,995 and the 2,997 count immediately after Primary finalization. No data was altered to reconcile these figures.

There are 294 receipt records: 292 retained files verify against their recorded hashes; two unposted records are intentionally Discarded and have no file, matching the existing discard workflow. The private current-state backup preserves the database tombstones and all 345 files currently under uploads. It includes users/authentication, Primary designation and financial/V2 state. Verification used complete dump structure/completion, stable before/after table fingerprints and archive/evidence hash readback; no database restore, fixture, financial write or migration was run for this checkpoint.

Prior rollout/Primary test results above remain dated evidence. Capstone defense success is the owner's report; it is not a new claim of provider acceptance, accessibility certification or Atikha/client acceptance.

## Evidence locations

- Private manifests, sanitized summaries, original tool results, rollback artifacts and commands: `.migration-private/final-owner-rollout-20261029/`.
- `working-verification.json`, `working-browser-checks.json`, `working-binary-readback.json`, `preservation-checks.json`, `source-validation.json`, `schema-rebuild-complete.json`, `final-working-state.json`.
- Screenshots: `screens/` (isolated focused checks) and `working-screens/` (actual XAMPP readers); working exports and PDF render are alongside them.
- Nothing was staged, committed or pushed. The next action is the short owner smoke/acceptance check, followed by a separately instructed Git checkpoint.
