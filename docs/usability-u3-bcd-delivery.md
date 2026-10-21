# U3-B-D final delivery and consolidated incident handoff

October 22, 2026. **Implemented and verified; ready for external exact-source review. No commit or push.** Baseline: approved/pushed U3-A `797d47cbbd2f0b8f5ea91e2bd583a8196285ec38`. The user authorized the coordinated incident recovery and automatic B -> C -> D resumption. All three internal gates and final affected suites passed. External source sign-off and full working/Atikha acceptance remain separate.

## Database incident closure

Exact-symbol dump analysis identified the InnoDB fatal path from the invalid `budgets.ibd` change-buffer bitmap page. Separate salvage found an unusable audit tablespace, crashed `mysql.func`, and overwritten permission-table data. The initiating mechanism is known; how those files were overwritten is not proven. Neither U3-A nor migration009 is established as its cause.

The current incident preservation retains 3,609 database files (495,241,246 bytes), the complete application including partial B, binary/configuration evidence and all 53 uploads. Proven-source logical reconstruction restored all 34 application tables,24 triggers,59 historical audit rows and120 budget rows, with table-level provenance. Current readable data took precedence; budget rows and the recovered single login audit were established by documented independent equality proofs. Required system-table data/grants were restored from proven current/prior sources. A clean same-version MariaDB 10.4.32 candidate was built; corrupt physical tablespaces were not promoted.65 table/view checks,70 zero-orphan checks, accounting/evidence validation and two normal working startup/shutdown cycles passed. See [recovery report](database-logical-recovery-20261021.md) and [sanitized provenance](database-logical-recovery-manifest-20261021.json).

Untouched rollback evidence remains at `C:\xampp\mysql\recovery-exports\incident-20261021-093316cb4b\preserved\data`; the damaged pre-cutover directory remains `C:\xampp\mysql\data-damaged-20261021-516017986f`. Do not rerun consumed recovery/cutover helpers or substitute older whole databases.

On October 22 the user completed normal Administrator and Management login/MFA.25 actual browser/read checks,35 browser-guard assertions and6 server route-rejection probes passed. The earlier interrupted guard attempt added one normal Administrator LOGIN; the successful final role run added two more. All59 prior audit rows remain; final audit count 62. Working financial/evidence/permission fingerprints remain unchanged. The working dataset had no eligible advance-detail/evidence-download sample, so those specific working reads were not exercised; synthetic evidence-bearing role checks below cover the source contracts.

After U3 testing, a fresh read-only comparison again matched all 34 working application tables, objects, grants/permissions,62 audit rows, all 53 upload files, working config, MariaDB binary/INI and persistent Apache config. Normal SQL identifies MariaDB 10.4.32 on 3306, the intended working datadir, recovery0 and normal preload1. Apache is stopped; MySQL remains running normally. This is bounded verification, not a promise about future hardware/engine stability or full client acceptance.

## Production scope

Exactly six production files differ from U3-A:

- `cash_advance_entry.php`: explicit dedicated advance presentation opt-in; route/role/schema gates retained.
- `includes/accounting_entry_page.php`: advance progress, review/result/recovery/leave controls and conditional pure module.
- `assets/js/accounting_workspace.js`: advance-specific state path, visible linked errors, advisory races, liquidation cost/liability presentation, bound proof display and durable recording recovery.
- `assets/js/advance_presentation.js`: new pure effect/identity/proof/display helpers; no posting or browser financial storage.
- `assets/css/accounting_workspace.css`: scoped advance layout, validation, state and print styles.
- `includes/cash_advance.php`: only additive `advance_effect` from the already computed lifecycle result, outside canonical input/fingerprint/token generation.

Accounting writers, lifecycle calculations, evidence/privacy rules, endpoints, migrations, feature flags, permissions and persistent configuration are unchanged. Corrections remain on their version 4 branch; ordinary Quick/Split/Advanced and scalar OCR retain their separate paths. Edited local source can serve on refresh when Apache is later started; this report does not authorize operational financial actions.

## B - release gate

Explicit employee/control/cash/project/date/due/amount selection; no automatic control choice. Release is clearly an asset, not expense; a CA identity appears only after verified recording. Optional proof and staff-recorded external approval remain distinct from system/Management approval. The advisory uses only the existing authorized employee-filtered register read, committed selection and request generation; all-page count/totals remain truthful beyond25 rows. It neither blocks another permitted release nor replaces editor/recovery status.

Initial internal gate:12 backend and39 browser assertions. Final-source gate:12 backend and56 browser assertions. Final browser evidence additionally verifies A -> B -> A stale-response protection, invalid review-effect refusal, optional proof/external metadata, draft print, failed Save-and-leave, posted-draft resume, and both actual lost-response and missing-returned-advance-ID recovery. Earlier preparation counts are dated gate evidence and are not added to final totals.

## C - liquidation gate

Costs/assets and liability credits have separate presentation; explicit debit/credit fields remain available, with unsupported/malformed values kept visible. Employee/originating context is fixed; allocation projects remain independent. Evidence targets eligible cost/asset debits, never generated control or liability credits. Review displays authoritative gross costs, gross reviewed support, liability credits, net advance reduction and current-balance before/after basis; the entry date is separately labelled.

Initial internal gate:24 backend and36 browser assertions. Final-source gate:24 backend and35 browser assertions (the combined runner's single final no-JS-error assertion belongs to D rather than being counted again in C). Verified examples include10000 ->8000 supported costs ->2000 remaining and4500 gross /500 liability /4000 reduction. Net-only support is rejected. Backdated multi-project assets, saved/resumed identities, partial/excluded evidence and actual committed lost-response replay pass.

## D - unused-cash return gate

Return proof confirmation remains a separate deliberate server-owned step before accounting Review. Visible confirmation binds the advance, amount, receiving account, date and reviewed documents; relevant changes stale it, unrelated wording preserves the existing server contract. Return proof has no expenditure coverage denominator or invented expiry. Recorded/recovered results retain both original advance and exact journal identities.

Internal and final backend gate:26 assertions. Initial/final D browser gate:37 assertions. The real synthetic10000 release /8000 liquidation /2000 return sequence settles to zero. Tests cover missing/unconfirmed proof, bound changes, partial/multiple returns before liquidation, expired-token same-owner cross-session replay and actual lost-response recovery without additional writes.

## Final-source integration and regressions

All results below concern the final six production-file hashes in the manifest. Independent financial suites used new guarded synthetic databases, copied applications, private sessions/evidence and blocked external browser/server transports. The synthetic base was restored from a SHA-verified previously generated synthetic SQL fixture, then cloned; no working drafts/receipts were used and no historical migrations ran.

- Advance pure adapter:58 assertions; ordinary U2 adapter:90.
- Focused backend: B12 / C24 / D26; extended integration:9. The latter proves actual competing settlements, inactive original references, unsupported/excess/cash/liability rejection, private ownership, forged Management-role rejection, full control reconciliation and exact journal balance. Total71 backend assertions, with no duplicate gate-preparation counts.
- Focused connected B-D Edge/HTTP:128 assertions (B56 / C35 / D37), zero uncaught JavaScript errors.
- U2 payment:124; receipt/GJ/transfer:199; navigation/role/flag/schema matrix:368; exact transaction reader:16.
- Stage2 browser:56; Stage3 CP4:48; Stage3 CP5:54.
- U3-A register/detail:91; evidence/privacy readback:27, including Management HTML/JSON/print/download restrictions and relevant flags.
- PHP/JS/Python syntax and changed-file whitespace checks pass.

Runners are supplied with the review source. Commands use `--fixture=<fresh-private-synthetic-fixture>`; the manifest records runner/exit identities and the sanitized verification log preserves individual assertions. Private fixture databases, SQL exports, sessions, configuration and receipts are deliberately excluded. `npm test` is not used as evidence. No487 historical backend result or old checkpoint result is represented as a new pass.

During verification, obsolete assertions were updated to test the dedicated advance progress branch, open the optional-proof disclosure before checking its visible content, compare the new exact gross-support summary, and inspect the explicit proof-confirmation state. CP4 was rerun with its CP4 metadata after an initial CP3-metadata selection error. New negative fixtures were corrected to use linked employee/full owner-scoped drafts and a nonreserved line ID. These failures are retained privately; financial/authorization assertions were not waived, and the final passing commands are identified separately.

## Evidence, review and remaining work

The exact-source ZIP includes the six application files, changed tests, relevant regression runners, baseline application/test patches, SHA-256/Git blob manifest, approved plan, reports, verification log and synthetic full-shell screenshots. No normal login/MFA screenshots, credentials, sessions, working receipt bytes or database dumps are included. Screenshot response doubles are labelled by scenario (including malformed-ID recovery); normal workflow images use actual isolated service results. Edge155.0.4283.62 was exercised at 1366x768 and1920x1080. These are not other-browser, physical-device, exported-PDF pagination or uncoached Atikha acceptance results.

U3-A remains approved/pushed. B/C/D implementation and isolated integration are complete; external exact-source approval is pending. No commit/push was performed. Full working/Atikha acceptance, retained U2 follow-up before U5, U-PERF-01, U4/U5 and original Stages4-7 remain outstanding. Migration009 error 1304 remains a separate unresolved diagnostic; neither cloning nor the older test-only anonymous-block path proves normal migration/deployment success. Do not start these later tasks from this handoff.

Next: send `docs/usability-u3-bcd-review-package.zip` for external exact-source review, then address only any actual in-scope findings. The user retains commit/push authority.
