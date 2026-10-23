**Execution supersession, October 24, 2026:** the user subsequently authorized this amended plan as one R0-R8 implementation. The dated pending-authorization text below is retained planning history; it is superseded by that explicit authorization and the [execution delivery](presentation-redesign-delivery.md). No working rollout, provider activation, commit or push was authorized.

# Atikha Finance - Refined Client Experience

## Governing plan and execution contract

**Amended approval recorded: October 24, 2026. Implementation authorization: pending.**

The user approved the repository-grounded presentation-redesign plan subject to four final amendments and explicitly instructed that they be incorporated before implementation. This document consolidates the approved scope and amendments. It supersedes the earlier chat proposal wherever that proposal conflicted with these amendments; it does not reopen the visual direction, accounting contracts or require a new broad planning cycle. Stop after this documentation update. A later explicit implementation instruction is required to begin R0-R8 execution.

### Binding amendments and precedence

1. **Preserve the actual-human U5 Save and close selection.** Focused ordinary, advance and correction entry retain a single explicit Save and close action, not stay-on-page Save Draft. Save and close persists the unfinished owner-private draft, requires an acknowledged valid saved result, then returns to the appropriate private My Drafts experience. Failed or uncertain saving retains the populated form, does not navigate away and does not automatically replay an uncertain write. Review may perform its existing internal save; Review does not post. Confirm/Record remains separate. The existing U5 decision takes precedence over an incidental Save Draft label in a visual concept and over the superseded save behavior in the original redesign proposal.
2. **No empty hubs for symmetry.** New Transaction is an approved task hub. A Transactions hub is optional only if it improves orientation/access. Setup and Administration default to accessible expandable sidebar groups; do not automatically create setup.php or administration.php. A dedicated hub needs meaningful status/orientation/task value beyond repeating child links. Avoid duplicating a hub's choices in an expanded child list.
3. **Reports UX is authoritative; filename is not.** Deliver one obvious Reports experience without forcing reports.php into a risky dual-purpose controller. Preserve existing GET, JSON, print, snapshot and deep-link contracts. Existing reports.php?snapshot_id=... must still open the exact frozen Trial Balance. Choose the lowest-risk route/shared-renderer arrangement at R0; no financial calculation change is authorized to consolidate navigation.
4. **Dashboard accounting-basis explanations are secondary.** The approved scoped KPI contracts remain. Present numbers, states, attention items and actions first. Put gross book basis, configured-cash limitations and related explanations in concise accessible info/help treatment rather than dashboard paragraphs. A blocking unavailable/error state must still remain visible; help is not a place to conceal a blocker.
5. Everything else in the approved plan remains in force, including shared components, role separation, forecasting removal, Board retirement/history preservation, Email, decision-oriented reviews, worksheets, safeguards, no unnecessary migration, R0-R8 and a fresh post-redesign human walkthrough only after external source/browser review.

Authority order is the current user amendments/request, explicit repository human decisions, approved written design/control rules, approved visual concepts and existing verified behavior where no change is authorized. Documents and mockup text do not independently authorize implementation, deployment or new financial authority. This is user-approved product design, not a new Atikha accounting policy or client acceptance.

## 1. Verified source baseline and preserved local work

The October 24 planning inspection verified main, local HEAD, cached origin/main and live GitHub main at db15b542a45c3c6e88ee9ccab78e8adc9c700f4c, subject `stages 4-7`. Live identity was established with read-only git ls-remote, not by resetting/fetching over local work. During amendment incorporation local HEAD/cached origin/main remain that commit; the earlier live observation remains dated evidence and must be refreshed at R0.

Pre-existing local changes are the screenshot-handoff additions to docs/project-status.md and docs/stages4-7-delivery.md, the untracked visual handoff Markdown/ZIP/checksum/PDF, output/, and scripts/__pycache__/stages4_7_browser.cpython-312.pyc. The two previously modified tracked documentation files had six added lines. There was no tracked production-source drift from the expected baseline. Preserve all these files and the new planning documentation; do not reset, stash, rebase, clean or overwrite them.

Approved design attachments:

- Atikha-Design-Extension.pdf: SHA256 a5b237f6fe46fc7c4315ddbabd7bd6bf32f34c666ac0b0b234e498c5c5af0a4b.
- Atikha-Design-Extension-Package.zip: SHA256 3bffe67924b80c30a9c7871f70593823c838e4771bf169b2c40c13d3a31e47e5.
- All 14 manifest-listed members matched; the standalone and packaged PDF match; all 16 design-package recorded production blobs match this baseline.
- The five boards and written component/control rules were inspected. They are canonical concepts rather than an exhaustive running state inventory.

The original planning inspection covered README, current status, client context, decisions, relevant Stages4-7 plan/delivery boundaries, shared layout/nav/header, dashboard/query/rendering paths, accounting entry/presentation, books/ledger, advances, reports/budgets/reviews, evidence/corrections, setup, Accounts/Users/Audit, Email and Board/archive/attachment routes. Relevant existing tests were inventoried, not executed. Amendment incorporation additionally verified the U5 explicit decision, aw-save acknowledged-save navigation and Budget/Reporting Setup save handlers.

No current working schema/health/configuration or client policy is inferred from source identities. No application/database test, server startup, migration, provider call or working financial action is part of planning.

## 2. Outcome and invariants

Simple on the outside, powerful underneath: guided forms perform accounting tasks; familiar worksheet-style tables review financial information. Use the approved refined visual direction without generating alternatives or rewriting the framework.

Preserve double entry, exact centavos, journals, posting formulas, book semantics, General Ledger balances, reversal/replacement rules, advance accounting, signed budget actuals, report formulas, exact frozen identities, close/reopen dependencies, writer locks, chronology, durable duplicate recovery, CSRF/MFA, roles, audit atomicity, privacy, feature gates and immutable records/evidence. Posted cells never become editable inputs.

Resolve incidental mockup defects using the written rules: projects stay optional where Organization Operations is valid; advance accounts remain explicitly selected eligible noncash Debit-normal Assets; drafts have no submitted version; corrections leave originals Posted until success; reversals are not cash returns; no unavailable/null value is fabricated as zero. The user's Email/Board and Scan Receipt wording override generic mockup labels.

## 3. Shared presentation architecture

Remain within PHP/JavaScript/Tailwind. Add small shared render/helpers and scoped CSS rather than duplicating each page or introducing a framework rewrite.

New shared files proposed:

- includes/presentation.php: headings, breadcrumbs, action groups, state badges, feedback, disclosures and table framing.
- includes/presentation_queries.php: bounded authenticated read-only dashboard projections.
- includes/report_workspace.php: common presentation of Working Figures/Submitted Copy and exact review context, without replacing financial models.
- includes/legacy_communications_view.php: archive-only Board rendering.
- assets/css/atikha_ui.css: approved tokens and shared components.
- assets/js/atikha_ui.js: navigation disclosure, menus/dialogs/drawers, focus restoration and error/disclosure behavior.
- assets/js/dashboard.js: Admin dashboard presentation without forecasting.

Apply the approved light-sage sidebar, white header/surfaces, warm canvas, forest-green brand, meaningful state colors, Inter/system fallback and tabular figures. Use the supplied 28px title, 18px section heading, 16px body, 14px label/table/help sizes, spacing scale, 44px controls and visible focus treatment. Ordinary expenses are neutral; negative net result retains its sign and deficit treatment. No decorative hero or animation.

Reuse accessible buttons, navigation links styled as buttons, state badges, inputs, feedback, financial tables, draft grids, disclosures, tabs and overlays. Links navigate; buttons act/expand. One dominant stage action, secondary Save and close/Back where appropriate, one row Actions menu and no hover-only controls. No success before persistence is verified.

Refactor old CSS into module-specific extensions, with explicit loading order and no blanket override wall. Accounts/Users/Audit custom shells must adopt the same components; changing layout_begin alone is insufficient. No Tailwind rebuild is initially planned; if new generated utilities become necessary, justify and inspect that output while excluding private analysis/test trees from scanning. No new font download/provider activation is required; the approved system fallback is acceptable.

## 4. Information architecture

Admin primary: Dashboard; New Transaction; Transactions; Cash Advances; Budgets; Reports; Email. Separate secondary groups: Setup; Administration. Use Title Case and full Cash Receipts Book/Cash Disbursements Book labels.

- New Transaction: Record a Payment, Record Money Received, General Journal Entry, Scan Receipt, My Drafts. new_transaction.php is the approved task-hub route.
- Transactions: Find a Transaction, Cash Receipts Book, Cash Disbursements Book, General Ledger. Prefer the simpler accessible group if sufficient; transactions.php is conditional on genuine orientation value.
- Setup: Accounting Setup, Reporting Setup, Chart of Accounts. Expandable group by default, with no empty setup.php route.
- Administration: User Management, Audit Trail. Expandable group by default, with no empty administration.php route.

Expand only the current group where practical. A hub's central task list must not be unnecessarily repeated in an expanded sidebar. Preserve valid feature availability and exact return context: selected draft, account, period, filters, tab and relevant scroll.

Management primary: Dashboard; Review Queue; Reports; Budgets; Cash Advances; Email. No authoring/setup/admin actions. Existing permitted books/ledger readers remain available through Reports Accounting Details and valid direct URLs; removing primary navigation does not remove or widen server permission.

## 5. Dashboards and read-side contracts

Use clear period/as-of scope. Financial KPIs follow that scope; current operational counts such as private drafts are explicitly distinguished from period totals. Use accessible info/help for basis limitations; do not restore paragraph-heavy dashboard panels. Critical errors/unavailable states stay visible.

Admin: Cash Balance, Money Received, Payments, Outstanding Cash Advances, Drafts to Finish and Report Items needing action. Management: Cash Balance, Income, Expenses, Net Result, Budget Utilization and Pending Reviews. Use compact KPIs, a grouped Needs Attention list and useful executive visuals only. Payment and Money Received are the primary Admin quick tasks.

Approved KPI contracts:

- Cash Balance uses existing configured-cash-account balances and integrity/availability checks. Accessible information: Recorded balance of configured cash/bank accounts; not a live bank-feed balance. Do not imply unrestricted spendability or a live feed.
- Money Received/Payments use cash-side originating cash-book activity for the selected period, with accessible gross-book basis. Do not reuse complete-entry debit totals where noncash lines make them differ. Corrected originals/replacements retain gross originating-book treatment; GJ offsets do not silently make these net bank movements.
- Income, Expenses and Net Result use existing recognized-result calculations, not receipts/payments.
- Budget Utilization uses the effective approved revision and signed recognized expense actuals. Missing approval/allocation remains unavailable, not an invented 0%.
- Draft counts are owner-private; review/attention items use authorized submission data and exclude retired Board messages.

Remove Predictive Forecast, Forecast Findings, controls, prose and automatic dashboard requests. Preserve forecasting services as dormant dependencies; no engine/model/provider redesign is included. Missing/loading/error/zero remain different states. Preserve a dated last successful snapshot on failed refresh where supported.

## 6. Daily transaction, evidence and correction workflows

Use Details -> Review -> Recorded for focused payments, receipts, journal and advance operations. Show required business fields first; progressively disclose references, split/advanced controls and optional documents. Required/unresolved controls and complex saved content open automatically with visible attention cues. Mode/disclosure changes never trim lines, discard evidence or silently repair amounts.

**Focused save contract:** retain one aw-save Save and close action for ordinary/advance/correction entry. After acknowledged valid draft persistence it leaves for the appropriate private My Drafts. A failed, malformed or uncertain save retains fields and does not leave/replay automatically. Review retains internal save and authoritative validation without posting. Confirm/Record stays separate. Preserve unsaved-leave handling and original durable identities.

Budget Draft and Reporting Setup preserve their own existing approved save semantics instead of inheriting a generic replacement. At this source baseline both visibly use Save and close and navigate only after save; the redesign does not convert either to stay-on-page Save Draft. Their existing internal save/review/submit mechanisms remain distinct from financial posting/approval. Nonfocused legacy save behavior remains as currently authorized.

Review retains complete authoritative balanced journals and evidence results. Accounting detail can disclose progressively but stays available before confirmation. Expired/stale review, changed evidence/balance and uncertain recording use existing fresh-review/result-recovery paths.

Scan Receipt: Upload -> Review Details -> Continue; final CTA Continue to General Journal. Preserve real file constraints, original private images, manual fallback and existing handoff. OCR suggestions differ visibly from user-confirmed details. Item review remains optional where currently optional, printed quantities are notes, and document confirmation is not posting or monetary coverage.

Corrections tell Original Transaction -> Reversal -> Corrected Transaction, preserving reverse-and-replace/reverse-only and fixed-mode draft restrictions. The original remains Posted in preview. Only successful atomic posting changes its correction state. Preserve exact reversal, replacement, advance restrictions and evidence association/re-review rules. Do not describe reversal as actual cash returned.

## 7. Books, ledger and advances

Apply coherent financial-table language without changing semantics. History remains line-oriented; CRB/CDB remain complete-entry/gross activity readers. General Ledger shows account/period, opening, chronological movements, debit/credit, running and full-period ending balance. Literal search filters visible rows without recalculating the underlying period basis. Preserve matching-row export versus full-period print labels and full-result scopes. Posted values remain read-only text.

Cash Advances register prioritizes employee, purpose, project, released/liquidated/returned/outstanding, due date and status. Detail uses the employee/purpose/project/status header, four amounts, posted-operation timeline, View Transaction and secondary Accounting Details/deadline history.

Reconciliation detail is secondary, but discrepancy/unavailable/action blockers, last verification and historical context stay visible. Settlement status and overdue condition are separate; Partial and Overdue can coexist. Reversed/Replaced must not be misrepresented as Settled. Historical views offer Open Current Advance rather than initiating current settlement.

Release keeps explicit eligible advance-account selection open until valid; no inferred default. Liquidation retains multiple costs, gross eligible debits, liability credits/required notes, net reduction and complete monetary coverage for each eligible debit. Return retains reviewed informational proof and explicit return-proof confirmation. Management stays read only; Admin actions use existing server eligibility.

## 8. Reports, budgets and reviews

One Reports navigation experience, independent of filename. **Lowest-risk starting architecture for R0 validation:** use report_bundles.php's existing complete-report workspace and shared renderer, with an explicit primary navigation target such as report_bundles.php?component=income_statement. It already carries all eight reports, submitted-copy identity, review and period controls. Do not force reports.php to acquire another controller responsibility.

R0 must check compatibility before confirming this arrangement. A lower-risk presentation-route choice may be documented within the same approved scope; the UX requirements and existing contracts do not change. Existing omitted/default query behavior must not be silently changed merely to make the new navigation open Income & Expenses.

Primary report tabs: Income & Expenses, Financial Position, Cash Flow, Budget vs Actual. Accounting Details: Monthly Worksheet, Trial Balance, Project Activity, Advance Aging and permitted books/ledger links. Working Figures is separate from exact immutable Submitted Copy. Switching view/period/tab must not silently replace a decision target.

Preserve financial_statements.php's seven-report GET/JSON/print interface, report_bundles.php's complete-report/review/month-control interfaces, and reports.php's live/frozen Trial Balance and snapshot_id interface. HTML may share renderers or use validated context-preserving links/adapters; JSON/print/deep-link behavior remains compatible. reports.php?snapshot_id=... must open that exact copy. Supported separate Trial Balance copy reviews remain a secondary financial-review/history area and never qualify as complete bundles for closing.

Review, submit and Close/Reopen remain separate operations. Surface stale source, earlier period-cycle, incomplete-report, required reason and affected-month blockers. Keep exact submitted identity and relevant setup summary, with hashes/epochs/fingerprints internal to normal use. No financial calculations are changed for navigation consolidation.

Budgets distinguish effective approval from private Draft, Submitted, Returned and superseded Approved. Editable draft grids preserve blank versus explicit zero and use actual existing save semantics. Review Budget displays proposed allocations against current approved context; Submit for Approval uses the existing immutable revision service. No official submitted version before submission. Actuals remain signed journal-recognized expense. Management opens the exact submitted revision; stale approval basis blocks approval and retains the existing return path.

Review Queue shows actionable authorized financial items: submitted budgets, complete reports and still-supported secondary Trial Balance copies. Use verified type/period/submitter/date/state/Open metadata. No blind decisions from queue rows. Missing/corrupt metadata is Unavailable, not guessed. Remove Board items and retired fund/expense sections from the primary current experience; preserve required legacy history through existing readers/audit.

## 9. Email and Board retirement

Use the existing external_email.php backend and current Admin/Management security/access scope. Present New Email and Sent first; rename Send Issues to secondary Delivery Issues, with count when attention is needed. Keep Sending, Failed and Unknown/Delivery Unconfirmed distinct. Preserve existing intent/outcome persistence, duplicate recovery, attachment rules and uncertain-delivery guidance. No blind/automatic resend, incoming mailbox, IMAP, in-app replies or email-based financial decisions.

Retirement is enforced at the server, not just in navigation:

- Remove Message the Board/Board Inbox from active navigation, Review Queue and actionable dashboard counts.
- Convert board_messages.php and board_inbox.php to archive-only GET surfaces; block new Board POSTs before application attachment storage/record creation.
- Reject Board review mutation in review_actions.php while preserving its financial branches and security checks.
- Preserve Board_Communications, original attachments, status/audit history and old notification records/links. Do not bulk mark Requested messages Reviewed or delete pending historical rows.
- Admin archive access remains owner-only; Management retains its current historical access; board_attachment.php keeps existing owner-or-Management authorization.
- Existing notification links resolve to archive views. Historical Requested status is explained as historical, not a current review action.
- Put narrow Legacy Communications Archive access in Email's secondary area, with bounded authorized readers. Shared upload/attachment helpers remain preserved, including dependencies used by external email.

## 10. Setup, administration and authentication surfaces

Accounting Setup: Projects / Parties / Advance Accounts sections, one relevant Add action, focused drawer/modal/editor and preserved protection/reason rules. Reporting Setup: Cash Accounts / Statement Mappings / Cash Flow Classifications / Exceptions status first, then focused editing/review; no invented mapping or accounting policy. Its own draft/save/submit semantics remain unchanged.

Chart of Accounts uses grouped types, search/filter, Add Account and one Actions menu, preserving used/protected-account rules. Users retain current Admin/Management roles, simplified table and secondary Password Reset Requests. Preserve self/last-admin/identity restrictions. Audit Trail retains history/filter/detail/export; advanced tools do not dominate ordinary reading.

Apply shared typography, controls and feedback to sign-in/reset-panel/MFA presentation without changing authentication, expiry/resend, CSRF or account protection. No extra standalone Setup/Administration route unless meaningful value beyond navigation is established and documented.

## 11. Expected production-file inventory

This is the approved expected inventory, not a claim that every file must change. Record and justify a newly discovered presentation dependency before editing it; a financial/schema/service expansion is not implied.

Shared shell: includes/layout.php; includes/nav.php; includes/header_bar.php; includes/review_ui.php.

Dashboard/read projections: dashboard.php; includes/management_dashboard.php; includes/dashboard_reporting.php; includes/budget_revisions.php (read-only queue metadata projection only).

Transactions/evidence: includes/accounting_entry_page.php; accounting_drafts.php; general_journal.php; cash_advances.php; journal_transaction.php; journal_correction.php; journal_corrections.php; ocr_expense.php; evidence_item_review.php.

Books/reports/budgets: financial_records.php; general_ledger.php; financial_statements.php; reports.php; report_bundles.php; budgets.php; management_reviews.php; includes/report_component_view.php; financial_export.php (HTML/print presentation only).

Setup/admin/auth views: accounting_setup.php; report_setup.php; admin_accounts.php; admin_users.php; audit_trail.php; login.php; mfa_verify.php.

Communication/retirement: external_email.php; board_messages.php; board_inbox.php; review_actions.php (Board retirement only; preserve financial writers).

Existing JavaScript: assets/js/notifications.js; assets/js/management-dashboard.js; assets/js/accounting_workspace.js; assets/js/payment_presentation.js; assets/js/advance_presentation.js; assets/js/correction_presentation.js; assets/js/accounting_drafts.js; assets/js/accounting_setup.js; assets/js/cash_advances.js; assets/js/financial_records.js; assets/js/general_ledger.js; assets/js/general_journal.js; assets/js/budget_workspace.js; assets/js/report_bundles.js; assets/js/report_setup.js; assets/js/review_actions.js; assets/js/ocr_expense.js; assets/js/evidence_item_review.js; assets/js/external_email.js; assets/js/admin_accounts.js; assets/js/admin_users.js.

Existing CSS: assets/css/sidebar.css; assets/css/executive.css; assets/css/management-dashboard.css; assets/css/accounting_workspace.css; assets/css/general_journal.css; assets/css/financial_records.css; assets/css/general_ledger.css; assets/css/financial_reports.css; assets/css/report_bundles.css; assets/css/reports.css; assets/css/budget_workspace.css; assets/css/evidence_item_review.css; assets/css/ocr_expense.css; assets/css/admin_accounts.css; assets/css/admin_users.css; assets/css/financial_export.css.

New production files: the seven shared files in section3 and new_transaction.php. transactions.php is conditional on demonstrated orientation value. setup.php and administration.php are not planned by default. Thin payment/receipt/advance wrappers are expected unchanged when shared entry rendering supplies their presentation.

Preserved/retired boundaries: board_messages.php and board_inbox.php become archive-only; Board attachments/helpers remain; old report/snapshot routes remain compatible; forecasting services remain preserved/dormant outside normal dashboards. Do not delete historical modules/data merely because normal navigation changes. Posting, period, evidence-authority, SMTP, authentication and schema services remain protected dependencies, not rewrite targets. No migration is currently required.

## 12. Narrow read-model changes

Only scoped dashboard signals/attention projections, removal of Board counts, stored submitter metadata for budget queues, Delivery Issues counts and shared report presentation adapters are presently justified. Complete-report queue already supplies submitter/time and should be reused.

Budget metadata comes from the existing submitted payload/identity with integrity checking, not an invented/current guessed name. Keep bounded query behavior and profile against N+1/history-wide loading regressions. Preserve authenticated coherent reads, current role/privacy rules and canonical financial models. No new calculation, authority or persistence schema is authorized by these projections.

## 13. R0-R8 implementation gates

The following sequence may later receive one coordinated implementation authorization. Successful gates advance without routine external pauses; in-scope gate failures are repaired/retested before advancing. This document alone does not start execution.

- R0: source/design identity preflight, preserved-diff inventory, route/request/snapshot/print compatibility inventory, lowest-risk Reports host confirmation, no-empty-hub decisions, guarded disposable isolation and fresh preservation baseline.
- R1: shared tokens/components/shell, both navigation trees, representative/custom-shell pages and feature-off paths.
- R2: scoped dashboards, forecasting request removal, Email redesign, enforced Board retirement and authorized historical archives.
- R3: daily forms/drafts, preserved Save and close, Scan Receipt/evidence, exact readers and corrections.
- R4: books/ledger and advances register/detail/lifecycle presentation.
- R5: unified Reports UX, budget preparation/review, verified actionable queue and separate period controls.
- R6: focused Setup/Administration and public authentication presentation.
- R7: integrated financial/role/privacy regression, browser/accessibility/responsive checks, performance sanity and preservation comparison.
- R8: exact-source/browser evidence package for external review and fresh redesigned human-usability protocol preparation.

After implementation and external source/browser review, prepare/conduct the separately identified fresh human walkthrough as authorized/available. Do not resume the old interface's paused G7, mark it complete or use automated actions as human evidence. Original observations remain dated history. Any later walkthrough findings/fixes/retests retain their own source identities and honest assisted/unassisted/failed observations.

## 14. Testing and visual acceptance

Use existing guarded Stage1/2/Stage3 checkpoint, U2/U3/U4/U5/exact-reader, test_stage4_*, test_stage5_*, test_stage6_*, test_stage7_*, external Email HTTP/fake-transport, Accounts and User Management suites as relevant. npm test is a placeholder, not evidence. Add focused tests for new components/nav, KPI projections, route compatibility, queue metadata and retirement. Update presentation selectors where necessary without weakening financial oracles. Report meaningful observed checks, not inflated counts.

Required behavior matrix:

- Roles/direct URLs/forged requests, inactive/session/CSRF/MFA boundaries and flags-off readers/writers.
- One focused Save and close action; keyboard activation; same private draft acknowledged before exit; close/find/resume identity; failed/malformed/lost save response retains populated input and does not navigate or blindly replay; internal Review save is not posting; Confirm/Record/recovery remain separate.
- Budget/Reporting Setup own save contracts independently, rather than reusing an incorrect stay-on-page or ordinary-entry adapter.
- Complex allocations, required errors in disclosure, review expiration, interrupted posting and identical durable retries.
- Advance release/liquidation/return, liability credits, required coverage/proof, corrections, Partial plus Overdue and historical/current actions.
- Budget blank/zero/signed actuals/stale approvals/effective version; live versus exact submitted reports, standalone snapshot deep links, close/reopen/races and duplicate recovery.
- Cash KPI versus noncash/whole-entry totals; corrections/gross-book labels; recognized results; missing configured cash or approved budget; owner-private draft counts and accurate attention states.
- Full-entry book versus line History filtering; General Ledger search/full-period running/ending; full-result exports and truthful print scopes.
- Board valid/forged POST/review rejection, no new Board rows/application uploads, immutable historical rows/audits/files, owner/Management archive and attachment checks, old links; Email Sent/Failed/Unknown/Sending and uncertain recovery through fake transport only.
- Management evidence restrictions across HTML/JSON/print/export/metadata/downloads and correction reuse contexts.

Inspect actual synthetic screens at approximately1366x768 and1920x1080, including lower content and horizontal scroll. Compare approved boards, without implementing tiny generated text as authority. At both sizes verify shared shell, readable14px+ financial columns, unambiguous primary actions/states, no hidden required controls/blockers, no competing Reports destinations, no active Board or forecasting UI and no unnecessary hub clicks. Basis help must be keyboard/focus accessible, brief and secondary; visible errors may not be relegated to a tooltip.

Check keyboard order, visible focus, menu/dialog/drawer behavior and focus restoration, disclosure state, unsaved dismissal, semantic tables/captions/headers, error summaries/field associations, async announcements, zoom/reflow, accessible horizontal scrolling and reduced motion. Include representative screen-reader checks. Contrast tokens alone do not establish accessibility compliance.

Print removes navigation, preserves role-safe identity/period/copy/read-only labels, repeats headers and does not crop accounting columns. Preserve Submitted Copy identity and export scope.

Profile dashboard/queue/ledger/report/correction/full-export paths on controlled small/large synthetic workloads. Retain reviewed engineering targets of HTTP medians within2seconds and visible navigation within3seconds where applicable; investigate regressions instead of claiming a production SLA. Keep bounded comparison reads and existing integrity checks.

## 15. Preservation and hard stops

Use new guarded disposable databases, private application/session/evidence copies and blocked/fake external providers. Never use working financial drafts/receipts as fixtures. No working migration, client mappings/opening balances/budgets, SMTP/provider activation, global configuration change, recovery/repair, commit or push. Refresh read-only preservation at authorized execution gates; prior dated comparisons do not establish current working state.

Stop for material source/unrelated production drift, failed isolation or normal database health, unapproved accounting/client policy, new approval authority/permissions, unresolved financial/privacy/integrity/recovery failure, reconciliation needing unknown classification, broader schema/service scope, or archive access requiring widened authorization. Do not invent missing data or follow a misleading mockup to avoid a blocker. Routine in-scope presentation defects are repaired/tested within the approved gates.

## 16. Final review artifacts and pending authorization

Deliver design hashes/precedence decisions, baseline/preserved-diff/final source inventory, actual changed-file manifest, forward/reverse patch reconstruction against db15b542..., component/nav inventory, role/viewport/state/source-labelled before/after captures, keyboard/error/focus/zoom/print evidence, relevant financial/security test results, Board archive/retirement and Email recovery evidence, performance, fresh preservation comparison, known/unperformed checks, sanitized commands/logs, exact ZIP manifest/verifier and an unperformed working rollout procedure. Include a fresh redesigned human task/observation protocol after external review, without claiming completion in advance.

Implementation, automated verification, external review, working rollout and human/client acceptance remain separate. Existing Stage4-7 G7 remains incomplete historical work; it is not resumed on the old interface. **Current next action: wait for the user's explicit redesign implementation authorization.**
