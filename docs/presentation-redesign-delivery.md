## Bounded external-review correction - October 24, 2026

The external R8 reviewer found no redesign/accounting/security blocker but withheld source approval for two presentation corrections. Final sign-off remains pending on this refreshed package.

Only new_transaction.php and includes/dashboard_reporting.php differ in production from the prior reviewed ZIP (SHA256 b0742cc83a7473117acc9ad948cc5c3c2e6ec4c329d3697cdda443d7e4cb4df7). The first now says exactly:

- Record a Payment: Record cash paid to a supplier, partner, or other payee.
- Record Money Received: Record cash received from a donor, partner, or other source.

Task labels, destinations, forms and accounting behavior remain unchanged. The second replaces only the malformed separator with U+00B7, the intended middle dot; the legacy helper remains dormant.

Affected PHP syntax passes. A pure helper-rendering assertion confirms the UTF-8 output without a database/provider call. All43 changed production files decode strictly as UTF-8 and show zero findings for U+00C2/U+00C3/U+FFFD, malformed smart-punctuation sequences or C1 controls. Exact-byte comparison proves the production delta is limited to the three requested strings in two files.

The refreshed focused shell browser run passes50 checks at1366x768 and1920x1080, including exact task labels/routes/descriptions, visibility, role separation and no JavaScript errors. Both full-size Admin screenshots were visually inspected. Every synthetic database table is unchanged by the read-only run; no working database connection, financial writer, provider call or working-data/configuration mutation occurred in this correction. No broad financial suite was rerun: previously passing financial evidence is preserved and explicitly predates this bounded text update.

The new package regenerates final source hashes and forward/reverse reconstruction against db15b542..., with prior run source pins retained. New correction checks/captures are labelled After bounded text correction; previous runs/captures are labelled Initial R8 and remain dated evidence. The initial package counts in the original delivery below are historical; current counts are in evidence/package-build.json and the final verifier output. The prior ZIP is retained privately unchanged. No commit or push. External final sign-off is next; rollout/human acceptance boundaries remain unchanged.

# Refined Client Experience - implementation and review delivery

October 24, 2026. R0-R8 source implementation and the external-review handoff are complete within the verification boundaries below. External source/browser review is pending. No commit, push, working rollout/configuration, real provider activation or actual human walkthrough occurred.

The user authorized the amended presentation-redesign-plan.md as one coordinated execution. Its explicit U5 Save and close decision, report-route compatibility, role/privacy/accounting boundaries and approved design precedence govern this result. The older pending-authorization paragraphs in the plan are retained dated history, superseded by the recorded implementation instruction.

The handoff contains43 changed production files,47 changed/new test/tools and10 documentation files against the baseline;381 source/test/migration files are supplied for review. The package has314 labelled before/after captures and958 manifest-tracked members. Both extracted-directory and ZIP checks pass, including exact-byte forward and reverse reconstruction of all100 patch-scope files. The ZIP checksum is in presentation-redesign-review-package.zip.sha256; it is deliberately outside the self-contained manifest to avoid a circular hash.

## Gate outcomes

- R0 passed: main/local HEAD/cached origin and read-only live remote matched db15b542a45c3c6e88ee9ccab78e8adc9c700f4c. No unrelated production drift. Approved PDF/ZIP hashes matched,14 package members and16 declared source blobs verified.188 existing local files inventoried/preserved. Fresh working read-only baseline and guarded synthetic clone established. Reports uses report_bundles.php?component=income_statement; omitted/default/JSON/print and reports.php?snapshot_id contracts remain. Transactions/Setup/Administration use groups; only New Transaction is a new hub.
- R1 passed: shared tokens/styles/helpers, role-specific shell/navigation, accessible controls/disclosures/tabs and custom-shell integration. Both desktop sizes and both roles captured. Existing feature gates retained. No framework or Tailwind rebuild.
- R2 passed: six scoped role-relevant KPIs, secondary basis help, Needs Attention and Admin Quick Actions. Recognized results remain distinct from cash-side gross book activity. No normal forecasting panels/requests. Board routes are authorized GET archives; server rejects new uploads/records/reviews before application storage. Email retains existing backend and delivery recovery; final panes keep Send visible. Missing SMTP is explicit and Send visibly disabled.
- R3 passed: guided Details/Review/Recorded, progressive optional documents/options and visible required controls. Single Save and close preserves acknowledged private persistence, failure retention and separate financial confirmation. Scan Receipt ends Continue to General Journal. Complete balanced journals/evidence remain in Review. Corrections tell Original Transaction/Reversal/Corrected Transaction while preserving preview/posting state.
- R4 passed: readable cash-book/History/account-ledger tables, truthful matching-export/full-period-print scopes, complete-period balances before search/page. Advance register has nine familiar columns; detail retains operation timeline, totals, current/historical state, eligibility and visible reconciliation blockers. Management remains read only.
- R5 passed: one primary Reports destination, four primary report tabs and secondary Accounting Details. Working Figures/Submitted Copy, complete review and Close/Reopen stay distinct. Budget draft/review preserves blank versus explicit zero, effective approval and signed actuals. Review Queue opens exact submitted financial items with verified metadata; no blind decisions or Board items.
- R6 passed: focused Accounting Setup tabs/editors, Reporting Setup status/steps with navigable read-only Review, grouped Accounts with row actions, simplified Users/reset area, secondary advanced audit tools and sign-in/reset/MFA presentation. Existing account/auth restrictions remain.
- R7 passed within stated limits: financial/security/role regressions, affected UI/browser checks, performance and unchanged working preservation. Actual screen-reader and physical zoom checks remain explicitly unperformed; this is not accessibility certification.
- R8 delivered: exact source/changed-file inventory, reconstructed forward/reverse baseline patch, before/after full-page gallery, financial/security/browser evidence, design truth, manifest/verifier, limitations, fresh human protocol and separately unperformed rollout procedure.

## Architecture and actual source

Shared includes/presentation.php supplies cached asset loading and small safe presentation helpers. assets/css/atikha_ui.css defines shared tokens/shell/components; atikha_workspaces.css and atikha_dashboard.css hold scoped workflow adapters. assets/js/atikha_ui.js manages presentation interactions only. No financial write is delegated to the shared UI layer. Existing focused-workflow request IDs/serialization/save/post recovery remain.

includes/presentation_queries.php adds authenticated, bounded read-only dashboard projections using canonical financial models and owner-private drafts. It selects cash-side gross book movements rather than entire-entry debit totals, preserves recognized result basis and current effective approval, and shows missing/unavailable states. Budget queue submitter metadata comes from captured/hash-checked submitted payloads; complete-report metadata is reused. No schema or client-policy default was added.

includes/report_workspace.php and assets/js/report_workspace.js unify report presentation around existing services. report_bundles.js preserves the selected presentation component on acknowledged navigation. Historical Trial Balance snapshot URLs remain exact. The old report routes stay available; consolidation is navigation/presentation, not calculation replacement.

Board routes now call includes/legacy_communications_view.php after existing role guards. Admin reads only owned history; Management retains historical access. board_attachment.php and historical records/files/audits/notifications stay preserved. review_actions.php rejects Board mutations while retaining financial branches.

The machine-readable evidence/changed-files.json is the authoritative actual file inventory. Evidence/unchanged-baseline-files.json identifies unchanged protected dependencies. New split CSS files and report_workspace.js are small shared presentation dependencies under the approved architecture. No new migration, setup hub, administration hub or integration was created.

## Navigation mapping

Admin Dashboard remains Dashboard. Payment/receipt/GJ/Scan Receipt/My Drafts are reached through New Transaction and the current task's child navigation. Journal History/Cash Receipts Book/Cash Disbursements Book/General Ledger are grouped under Transactions. Cash Advances, Budgets, Reports and Email are primary. Accounting Setup/Reporting Setup/Chart of Accounts are under Setup; Users/Audit under Administration. Only the current group expands initially. CRB/CDB abbreviations are not primary navigation labels.

Management has Dashboard, Review Queue, Reports, Budgets, Cash Advances and Email. No Admin authoring/setup actions. Message the Board/Board Inbox become secondary Legacy Communications Archive access from Email; financial decisions remain in Review Queue/exact submitted readers.

## Financial and security evidence

The accepted ordinary regression union covers17 fresh-clone suites: Stage1, integrated U5, exact reader, performance reader, U2 payment/receipt/GJ, Stage2, CP2/3/4/5, U3 register/advance readers/backend and U4 find/correction/evidence. Original Stage2/CP3 hidden-disclosure selectors failed and are retained as failures; only these affected suites were corrected/retested. The final union passes with matching run source pins. No financial expected value was weakened.

Separate fresh component runs cover Stage5 configuration/core/edge cases, Stage6 budgets/schema/read-model/integrity, complete-report/month controls/flag-off/negative/races, and Stage7 evidence/export/negative cases. Browser runs pass Stage4 ledger35, Stage5 reports65, Stage6 budgets57, complete-period52 and final Stage7 manual evidence/export69. Relevant injected failures, durable retry and concurrent writer/closing checks are retained in logs. These are dated component runs, not a claim every historical suite used byte-for-byte final CSS.

Final focused evidence includes171 workspace checks,33 accessibility/print checks,14 setup/auth presentation checks,19 archive/security/account/user checks and the final Email role/delivery/visibility sweep. Fake Email backend31 and HTTP85 plus MFA helpers pass. No real SMTP/Gemini call, production login or actual MFA delivery is claimed. Board POST/review410 tests prove no new application upload or historical mutation, including valid-CSRF attempts; owner/Management downloads and other-owner denial remain.

Final export retesting rebuilt a fresh disposable Stage7 scenario and passed evidence/export/negative/browser checks. This follows the narrow encoder optimization below. Manual evidence suggestions do not post or become financial authority. Management privacy remains tested across HTML/JSON/print/download/export and direct authoring requests.

## Narrow serialization performance correction

The first valid serial500/5000 workload timing found the full5000-row CSV path above the2s target (approximately4.3s Admin/3.08s Management), while normal pages passed. Each text cell used a disk temporary file solely for native fputcsv quoting. includes/financial_export.php now uses php://memory for that one-cell buffer. The complete validated-result disk spool, canonical models, validation, formula-protection, precision rules, privacy, limits and failure-before-success behavior remain unchanged. This additional dependency is explicitly identified rather than hidden as an accounting-engine change.

1017 adversarial/generated cells are byte-identical to the baseline native encoder. Fresh Stage7 full-result/negative/browser retests pass. Final serial performance: maximum measured HTTP median1.173s and visible-navigation median1.281s across both roles and500/5000 selected-account movements;5000-row CSV medians0.447s/0.494s. Six observations per case, first discarded as warm-up. Declared workload size is asserted against actual selected account movements. Earlier invalid workload-reuse and slow-encoder attempts are retained privately and described, not accepted as passing evidence. These are controlled local synthetic engineering results, not an Atikha production SLA.

## Visual, interaction and adoption checks

See design-qa.md and the labelled gallery/source pins. Approved sage/forest/warm-canvas tokens, restrained hierarchy and familiar worksheets are implemented. Full-page contact sheets and representative originals include lower content. Both desktop sizes are tested; financial tables scroll instead of shrinking. Keyboard tab controls, linked validation/focus, dialog Escape/restoration, disclosures, horizontal scrolling, reduced motion and five print paths pass. The18 rendered print pages were contact-inspected, with selected full-size pages reopened; this is not a claim every screenshot was individually inspected at1:1.

Internal adoption gate observations:

- Primary task: role navigation/hub, guided form stage, Reports tabs, exact queue Open and focused Setup Add/Edit are visible.
- Plain language: no daily engine hashes/epochs/schema-version controls; basis explanations use help, while errors/blockers remain visible.
- Progressive detail: optional documents/options, advanced filters, supporting accounting and audit tools are secondary; required proof/coverage/selection and validation reveal their controls.
- Familiar financial columns: books/ledger/advance/report/budget grids retain tested scope/totals; posted values are read only.
- Draft versus posted: editable budget/task controls are distinct from review tables, exact submitted copies and effective approval. Blank versus zero remains explicit.
- Important actions: Save and close/Review/Record, eligible advance actions, queue Open and Email Send are discoverable; final normal/hover/focus labels were checked after fixing legacy contrast.
- Lifecycle: saved/private, unposted Review, Recorded, Working Figures, Submitted Copy, review state, period controls and approved budget identity remain distinguishable.
- Errors: linked field summaries, visible unavailable/configuration/reconciliation and uncertain-write/send guidance tell the user what to address. No automatic uncertain replay/resend.

These are internal implementation observations, not actual-human ease-of-use or client acceptance. Earlier selector/helper mistakes, a custom-shell missing helper, print navigation, fixed-width advance conflict, action contrast/hover and Email long-list height defects were corrected and affected checks rerun. Honest failed attempts remain described.

## Preservation and limits

Fresh final read-only comparison equals R0:34 application tables,24 triggers,62 audit rows,53 protected uploads, permissions/object state and pinned configuration. Snapshot SHA256552354448852feebff3e56db9a05c56be32b5ba62e3b1c1934e5b17866f02fbc. No working financial action, migration, client mapping/budget/opening balance, provider activation, recovery, account/grant/global-config change, server restart, commit or push. Preexisting local screenshot/planning files and frozen consultation packages are preserved; updated documentation retains prior text and the R0 copy.

Known unperformed: actual screen-reader speech/browse-mode, physical browser zoom, cross-browser/device/printer checks, real SMTP/MFA/provider acceptance, working rollout and fresh human walkthrough. Reflow at683 CSS pixels and ARIA snapshots are explicitly limited proxies. Browser evidence uses installed headless Edge/Windows and isolated synthetic sessions. Source-pinned component timing/differences are disclosed in the package.

The previous Stage4-7 G7 remains paused/incomplete. Do not resume it on the old interface. After external source/browser review, use presentation-redesign-human-protocol.md to prepare a new guarded session. Working Stage4-7 migration/configuration status must be independently established under separate authorization; presentation source completion does not establish deployment or Atikha acceptance.

Next: external exact-source/browser review of presentation-redesign-review-package.zip. No implementation authorization or rollout request is pending within this handoff.
