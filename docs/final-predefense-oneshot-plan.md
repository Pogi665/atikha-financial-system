# Final Pre-Defense One-Shot Implementation Plan

**Planning checkpoint: October 29, 2026. Status: proposed execution contract for external review; implementation is not authorized by this report.**

Targeted revision: incorporates the user's external-review corrections of October 29 without replacing the approved structure or reopening the visual direction. The later implementation target is the **actual current local Atikha application source in the VS Code/XAMPP workspace**. Those actual source files will be updated, preserving and extending the existing uncommitted polish. Disposable databases, private application copies and preview environments are for safe migration, mutation, automated/browser and financial/security verification; they are not a separate temporary product in which the approved features would exist exclusively. The working `atikha_finance` database remains untouched unless a separately reviewed working rollout is explicitly authorized.

The current user request and its addendum define defense alignment. The finalized printed manuscript and presentation were not supplied, are not needed, and were not reconstructed or sought. This report does not infer additional requirements from older manuscript material. The approved visual direction remains **Atikha Finance — Refined Client Experience**, with Minimal Executive simplicity first. It does not propose another visual direction or accounting engine.

Only this planning report may be created/updated in this run. Production files, tests, configuration, databases, runtime environments, sessions, uploads and previous evidence remain unchanged. All implementation actions and validations described below are future work unless explicitly identified as inspection already performed.

## 1. Current-State Inspection

### 1.1 Verified repository identity and local boundary

- Repository: `https://github.com/Pogi665/atikha-financial-system`; branch `main`.
- Local HEAD, cached `origin/main`, and live `refs/heads/main` all verified as `1bb6a074f99c42569c88bf73a3d2a54dd3f532d2` — `Finalize auth carousel source review handoff`.
- Live verification used read-only `git ls-remote`; no fetch, checkout, reset, clean, stash, stage, commit or push.
- Pre-report working tree: **18 tracked modified files, 12 non-ignored untracked files, zero staged files**. These are the known local visual-polish/transaction-UX result, not unexplained remote drift. No additional unrelated non-ignored change was found during this inspection. This statement does not claim Git inventories every ignored/private file.
- SHA-256 was captured in memory for all **1,124 existing tracked/non-ignored files** before creating this report, for a final planning-only comparison. No preservation archive or generated inventory file is created during this run.

Targeted-revision preflight: branch/HEAD/cached `origin/main` still match the checkpoint above; the original local work remains present, with this existing untracked plan bringing the count to **18 tracked modified / 13 non-ignored untracked / zero staged**. In-memory hashes cover all **1,125 pre-revision files**. Only this report may change in this revision; the earlier inspection findings are retained as dated findings, not newly executed runtime tests.

Tracked modifications already present:

```text
accounting_drafts.php
assets/css/atikha_dashboard.css
assets/css/atikha_ui.css
assets/js/accounting_drafts.js
assets/js/accounting_workspace.js
assets/js/atikha_ui.js
dashboard.php
docs/current-state.md
docs/decisions/007-refined-client-experience.md
docs/decisions/010-financial-reader-scopes.md
financial_export.php
financial_records.php
includes/accounting_entry_page.php
includes/financial_export.php
includes/nav.php
includes/presentation.php
journal_transaction.php
new_transaction.php
```

Non-ignored untracked files already present:

```text
advanced_accounting.php
assets/css/transaction_workspace.css
assets/js/transaction_detail.js
assets/js/transaction_records.js
docs/transaction-ux-batch2-delivery.md
docs/visual-polish-batch1-delivery.md
includes/transaction_detail_page.php
includes/transaction_presentation.php
includes/transaction_records_page.php
scripts/test_transaction_presentation.php
scripts/test_transaction_readers.php
transactions.php
```

The future implementation baseline is **these exact local bytes plus this plan**, not a clean copy of GitHub main. Preserve the local task launchers, dashboard attention/action tiles, private draft controls, desktop rail/flyouts/persistence, hidden scrollbar chrome, mobile drawer and authentication carousel. At execution preflight, capture the then-current whole-tree identity and diff against both this checkpoint and Git main; stop on unexplained changes instead of overwriting them.

### 1.2 Inspected source and actual capabilities

Read the normal memory order: [project brief](../PROJECT-BRIEF.md), [current state](current-state.md), [decision index](decisions/README.md), relevant role/privacy, advance, save, presentation, Board retirement, evidence, reader, reporting and budget decisions, [client context](client-context.md), and [artifact policy](repository-artifact-policy.md). The two local polish deliveries describe dated prior validation; those checks were not rerun during planning.

Source inspection covered:

- Shell/components: `includes/nav.php`, `includes/layout.php`, `includes/header_bar.php`, `includes/presentation.php`, `includes/presentation_queries.php`, current `assets/css/atikha_ui.css`, `assets/js/atikha_ui.js`.
- Transactions: current hubs, `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, draft PHP/JS, transaction projection/reader/detail files, `financial_records.php`, `journal_transaction.php`, `general_ledger.php`, export PHP.
- OCR/evidence: `ocr_expense.php`, `ocr_extract.php`, `includes/receipt_ocr.php`, `includes/gemini_receipt.php`, `includes/evidence_item_reviews.php`, `evidence_item_review.php`, receipt and item-review JS/CSS structure. Existing normalized suggestions include `suggested_debit_account_id`, reason, confidence and printed items. Confirmed manual review is already separately revisioned and source-bound.
- Advances, budgets and reports: `cash_advances.php`, shared advance entry, budget page/JS, `includes/budget_actuals.php`, `includes/budget_revisions.php`, `report_bundles.php`, `financial_statements.php`, `reports.php`, `includes/report_component_view.php`, `includes/financial_report_query.php`, `includes/report_configuration.php`, `report_setup.php` and its JS.
- Administration/communications: accounting setup, Chart of Accounts, `admin_users.php`, user JS, `includes/users.php`, `includes/user_roles.php`, `includes/user_session.php`, `includes/require_role.php`, `resolve_reset.php`, `auth.php`, MFA logging calls, `external_email.php`, email JS, audit page/query/logger, notifications and their call sites, Management queue.
- Dormant AI: `forecast_ai.php`, `includes/forecast_query.php`, `audit_ai.php`, relevant `includes/gemini_client.php` functions, and retained legacy Management dashboard helper. Existing forecast history already uses recognized-expense reports and approved revisions, but the exposed generation endpoint still calls Gemini. Existing audit scan/summary endpoints also call Gemini. Dormant code is not equivalent to the requested deterministic modules.
- Schema/source contracts: migrations `002`, `007`, `013`, the `019`–`025` component contracts/guards, `023` allocation identity, `024` submissions/periods and `025` immutable item reviews; relevant schema validation helpers and test inventory. These files were read, never executed. No protected or preview database connection was opened for this plan. Actual deployed schema/health is **not** certified by static inspection.
- Dependencies: `composer.json`, dependency layout, `package.json`, PHP CLI version/modules. CLI is **PHP 8.2.12**; project manifest currently permits **PHP >=8.0**. DOM, XML, mbstring and GD are loaded; **ZIP is not loaded**, although `php_zip.dll` exists. No installed PDF/XLSX library was found. Composer currently requires PHPMailer, while `npm test` is a placeholder. No installation/build/config change occurred.

Important source distinctions:

1. Local Transactions/Advanced Entry, neutral Review, compact Recorded, transaction-level books/finding and truthful explicit CSV choices already exist. Extend them; do not rebuild them.
2. Budgets still render account × project columns, although the canonical payload already is a list of `(account_id, project_id, amount)` allocations. A row editor needs no financial schema change.
3. Reports already have a unified primary `report_bundles.php` destination and complete immutable submissions. `reports.php?snapshot_id=...` remains an exact legacy Trial Balance reader. Worksheet rendering is still very wide; setup/revision/technical information remains dense.
4. `users_save()` protects self-disable/self-demotion and locks actor/target rows, but there is no persistent primary-admin invariant. `resolve_reset.php` is a separate credential writer and must be covered too.
5. Stored roles are `Admin`/`Management`. The current shared visible label is `Management and Board`; the new requirement is exactly **Management & Board**. This is a presentation vocabulary change, not a third role.
6. Audit rows are append-only and authenticated-actor based. Successful LOGIN/LOGOUT and many business events exist; failed credential attempts are not currently recorded by `auth.php` into an authoritative failed-auth event stream. Do not pretend they exist.
7. Notifications exist, including submitted/reviewed/returned budget/report calls and duplicate recovery. Legacy expense warnings are not evidence of current journal-based warning coverage.
8. `external_email.php` still exposes a Legacy Communications Archive link. Retired Board routes already preserve authorization/history and must remain compatible without ordinary discoverability.
9. Existing export formats are genuine **CSV and HTML print/Save PDF**, not downloadable PDF or native XLSX.

Representative inspected source hashes, to make these findings reproducible:

```text
includes/forecast_query.php       a29ed21be79a13127da540e2f3d64368529e355782a32fd52869b2f93f8f107a
forecast_ai.php                   0872ead29b5906294763ea26f797f24299a7380be26ef49e048f01e6343a8e25
audit_ai.php                      ab30a4e29b2174d44ea39f99b77a71ef5b6c4717285ec598e9301ee71034ce46
includes/users.php               2410743a42465dc59b5e7f374cadfcb4c0145d1b9fec54481fb898d6395d9085
resolve_reset.php                7e412138469e23d06029c5af7d9e6e4f334c9c538b3aa48a3535a91b7ee2bae7
includes/budget_actuals.php       50b2f2fe3b12162fa3439558009a082490d4ab98c047f801c6edfe5ca743ef5a
includes/financial_export.php    edf55525dd508bc1694cee1f616c5ad79cb7af9e62bb5b60070d9ec73fc761e0
includes/transaction_presentation.php 6f8fdf172bdab4d28e98c7cf7935d114df772aab8adec0c5cb93d7621c184e1b
```

### 1.3 Explicit reconciliation with older guidance

- Decision 007 removes forecasting from **normal dashboards**. Keep that. Its dormant-service wording is extended by the requested separate deterministic Planning & Forecasting module; do not restore the old forecast dashboard/helper.
- Decision 008 retains archive access. Remove ordinary archive links under this request, while retaining authorized old URLs, attachments, historical notification targets and audit history. Discoverability retirement does not authorize deletion or access widening.
- Decision 010 and AGENTS preserve cash-book **gross originating activity**, including corrected originals. The suggested bare “Total Payments” must not imply corrected net spending. Use a transaction count and, only where mathematically safe, **Recorded Payment Activity (Gross)** / **Recorded Money Received (Gross)** with concise secondary basis. Do not sum ambiguous movement summaries or call cash movements recognized expenses/income.
- Decisions 011/012 keep exact submissions and effective approval. Change visible “Working Figures” to **Live Report**, “Submitted Copy” to **Submitted Report · Revision X**, without changing stored states, payload hashes, readers or financial review semantics.
- Decision 006 remains binding: focused Payment/Receipt/Advance/Correction has one **Save and close**, not a second Save Draft. Budget and report setup retain their own save/review/submit contracts.
- Decision 004 permits liquidation costs/assets and liability offsets. The request's simplified “liquidation recognizes expense” must not erase asset eligibility or gross-support/net-reduction distinctions.
- No client fiscal start, alert threshold, operating hours, financial mapping or primary-account identity is established by defense wording. Do not infer these from older documents. Current date formatting must not change any underlying time or accounting date.
- The user identifies prior formal usability/evaluation, completed U-series work, Administrator and Management & Board walkthroughs, hands-on testing and screenshot/video/module reviews as completed requirements-discovery evidence for this final pass. Another formal human study or full-system discovery walkthrough is **not a remaining prerequisite**. Existing current-state/protocol references to a pending fresh walkthrough are older handoff wording, not the current completion contract. Preserve historical observations and the protected participant workflow exactly; its recorded paused A01 state is not retrospectively relabelled completed. What remains after implementation is Codex's detailed verification plus a short final owner acceptance/smoke check, not renewed participant testing.

## 2. Requirement Reconciliation Matrix

Impact: **P** presentation; **R** read model; **B** backend; **S** additive schema; risk concerns implementation scope, not claimed defects.

| Requirement | Current behavior / gap | Planned implementation and principal files | Impact / risk |
|---|---|---|---|
| Preserve local work | Two uncommitted polish batches, remote older visually | Pin local bytes/diffs; extend current components and routes | P / medium shared regression |
| Transactions hub | Already present, separate label/disclosure | Verify destination/gates/rail/mobile; no duplicate hub | P / low |
| Simple and advanced entry | Already separated; some secondary wording remains | Additional Details; preserve complex-mode resume, all validation and Save and close in entry PHP/JS | P / medium |
| Review and Recorded | Local redesign already implements most requirements | Complete labelled summaries, source-available recorded-by/account/project, responsive action/error states; no invented metadata | P / medium |
| Exact transaction | Business-first local renderer, complete details retained | Polish metadata/actions, preserve exact authorization, correction lineage and ledger return | P / medium |
| Cash books / finder | One journal per row locally; legacy detailed contracts retained | Responsive prioritization, safe labelled gross summaries, existing quick preview/export scopes | P/R / medium |
| General Journal | Compact debit/credit editor already present | Finish disclosure/date/action consistency; preserve balancing/transfer control | P / medium |
| General Ledger | Account mathematics sound; View may be beyond wide region | Compact identity/description, accessible View beside identity; secondary Reset, friendly dates | P / medium; no balance changes |
| Global tables and dialogs | Mixed module-specific widths and native confirms | Per-table column/reflow contracts; shared accessible confirmations; avoid dangerous global hide/overflow rules | P / medium-high shared scope |
| OCR and categorization | Extraction/suggested Expense account and immutable manual detail review exist; flow fragmented | Linear workspace, two columns, integrated header review, secondary items, truthful unavailable path; reuse signed intake/manual services | P/B adapter / high evidence coupling; no new schema |
| My Drafts | Continue/Discard styled locally; native confirm remains | Accessible Discard Draft modal, keep revision/CSRF/unknown-result controls and friendly saved time | P / medium |
| Cash Advances | Existing lifecycle/gross coverage/reconciliation; wide nine-column register | Six-column operational register, clear statuses/timeline/current actions, disclosure of accounting detail and prominent blockers | P / medium |
| Budget preparation | Wide matrix over canonical allocation-list backend | Row allocations, duplicate-key validation, blank/zero preservation, current-approval comparison and role-specific summaries | P / medium; no schema |
| Reports / worksheet | Primary bundle route exists, legacy exact readers retained; very wide worksheet | Three navigation groups, vertical worksheet sections, clean cash flow, secondary revisions/setup/month controls | P / medium |
| Report export | CSV/print only; no native binaries | Keep CSV/print; add required real PDF/XLSX over authorized models after isolated maintained-library/platform feasibility; no assumed PHP-floor increase | B/dependencies / high; no financial schema |
| Email | Shared outgoing backend/recovery; nested scroll and archive link | Viewport-fitted panes/footer, delivery disclosure, remove ordinary archive discovery | P / medium; provider unchanged |
| Accounting / account setup | Tabs exist; large editors/technical identity | Focused modal/drawer editors, meaningful names, preserved eligibility/locks | P / medium |
| Reporting Setup | Immutable setup revisions with broad mappings/exception inputs | Readiness-first guided sections, contextual unresolved-account/transaction links, retained free-form policy choices | P/R / high classification mistakes must block |
| User/role wording | Two roles, visible “Management and Board,” stale descriptions | Central display label “Management & Board,” source-scoped copy cleanup; stored enums unchanged | P / low |
| Primary Administrator | No singleton designation; separate reset writer bypasses any new UI-only guard | Singleton/FK, explicit initialization, transactional protections in supported user/reset writers and safe row locks; no transfer, mandatory new reauthentication or planned triggers | B/S / high security |
| Audit Trail | Full raw fields/AI tools prominent | Human primary columns/details, complete forensic disclosure, no audit rewrite | P/R / medium |
| Audit Alerts | Gemini scan/summary, no deterministic rules UI | Explainable audit/security patterns over reliable audit/correction/OCR/login data; Admin only; ordinary financial attention stays outside anomalies | R/B / medium-high |
| Planning / trends | Recognized history and approved-budget helper; Gemini generation | Automatic complete-history basis; independent overall recognized-expense forecast plus granular estimates/coverage; transparent methods/errors/insufficiency | R/B / high analytical truthfulness |
| Suggested budget baseline | Not present; approved/draft workflow exists | Explicit Admin-reviewed prefill into private Draft; no silent write, replace, submission or approval | B adapter / medium |
| Notifications | Existing lifecycle notifications/retry handling plus legacy warning helpers | Preserve/polish current notifications and existing safeguards; calculated conditions use their own read-only surfaces, not a new persistent bell-event mechanism | P/B reuse only / low-medium; no schema |
| Management & Board | Separate role UI exists; shared pages still dense | Oversight summaries, exact Review/View decisions, no Admin permission expansion | P/R / medium |
| Legacy / other AI | Board readers retired; old forecast/audit endpoints callable | Remove discoverable legacy entry points; deactivate Gemini forecast/audit calls; retain evidence/history and OCR dependency | B compatibility / medium |
| Whole-product quality | Prior completed human testing/reviews produced these requirements; new changes still need technical verification | Codex inspects complete affected workflows/screenshots, fixes documented adjacent low-risk defects and hands off only a short owner smoke check | validation / high coverage scope |

## 3. Proposed Information Architecture

Use the current role-aware shell and current expanded/72px rail/mobile drawer behavior. Add only one secondary analytical destination, not a new navigation hierarchy.

**System Administrator** (stored `Admin`):

- Dashboard
- New Transaction → current hub; Record a Payment, Record Money Received, General Journal Entry, Scan Receipt, My Drafts; Advanced Accounting Entry remains a deliberate secondary hub task, not a cash-advance mode.
- Transactions → current hub and separate disclosure; Find a Transaction, Cash Receipts Book, Cash Disbursements Book, General Ledger.
- Cash Advances
- Budgets
- Reports
- Email
- Visually separated secondary **Planning & Forecasting** destination.
- Setup disclosure → Accounting Setup; Financial Report Setup (existing `report_setup.php` route); Chart of Accounts.
- Administration disclosure → User Management; Audit Trail. Audit Alerts is an Admin-only tab in Audit Trail, not another top-level destination.

**Management & Board** (stored `Management`): Dashboard; Review Queue; Reports; Budgets; Cash Advances; Email; secondary Planning & Forecasting. No New Transaction, Setup, User Management, Audit Trail/Alerts or private evidence access. Permitted financial drilldowns remain available through Reports/Transactions links where already authorized, not newly granted authoring controls.

**Reports:** retain `report_bundles.php` as primary and exact submitted context. Financial Statements = Income & Expenses, Financial Position, Cash Flow, Budget vs Actual. Accounting Reports = Trial Balance, Monthly Worksheet, Project Activity, Advance Aging. Related Records = General Ledger, cash books, finder. Current month configuration and closing sit in secondary sections; old Trial Balance snapshots remain protected deep links/history, not a competing center. Preserve existing GET/JSON/print endpoints.

**Email:** New Email, Sent, quiet Delivery Issues when needed; no Legacy Communications Archive link. Old Board links continue only as authorized historical readers, including existing notification links. No incoming mailbox or new internal Board workflow.

## 4. UI/UX Implementation Plan

### 4.1 Shared foundation and table-by-table contracts

Extend current PHP helpers and scoped `.ui-*`/module CSS; no framework rewrite or blanket table conversion. Introduce shared friendly date/month/time helpers with explicit input basis and fallback **Not recorded**. Reuse the transaction helper where safe; UTC `DATETIME`, local wall-clock and accounting `DATE` inputs must not all be parsed as the same thing. ISO remains in date-input values, URLs, machine JSON and integrity serialization. Technical/audit disclosure may retain exact timestamp and basis. Do not remove timezone logic.

Add one reusable accessible confirmation component to the current presentation layer: labelled modal, summary/reason, Cancel initially focused for destructive actions, Tab containment, inert/background lock, Escape/cancel, focus restoration, adequate targets and reduced motion. Confirmation collects intent only; existing mutation endpoint/CSRF/revision/request identity stays authoritative. No silent retry after unknown result. Native `beforeunload` is a separate platform protection: retain it where browser-controlled; do not promise an arbitrary custom browser-exit modal. Replace ordinary in-page `confirm()` calls progressively and test each caller.

Create a screen contract list before editing. For each list: identity, status, primary action must fit at 1366 expanded. Keep actions near identity, combine two-line human fields, secondary metadata in details, numeric tabular alignment and natural wrapping. Mobile uses labelled stacked rows where practical. Permit inner scrolling only for genuine accounting detail, never whole-page overflow. Do not globally hide columns, truncate meaningful identity or use tiny type. Preserve accessible table headers/captions and logical reading order.

Table dispositions:

- Finder/books: Date/identity + party/purpose + project where relevant + safe amount/status + View; combine reference beneath identity if needed.
- Ledger: Date/reference/description + Debit + Credit + Running Balance, View accessible with identity. Party/project can be a second line. Running basis remains complete period before search/page.
- Advances: Employee/advance + Purpose/project + Outstanding + Due + Status + View. Partial and Overdue are separate coexisting dimensions.
- Budgets: Expense/project identity + Approved + Actual + Remaining + utilization/status. Draft row editor is account/project/planned/remove.
- Review Queue: Submission + Submitted by/date + Status + Review/View. Open exact submission before decision.
- Setup/users/audit: focused operational columns; details/drawers carry descriptions, protection reasons and forensic fields.
- Monthly worksheet: vertical sections with repeated account identity, not one twelve-column mandatory scroll. Additional monthly-result/position bridge columns remain accessible in a separate accounting section; no financial column is lost.

### 4.2 Transactions, drafts and General Ledger

Treat local batch 2 as the baseline. Only complete adjacent requirements: Additional Details naming, consistent back/draft controls, source-available completion metadata, graceful advanced resumption, modal/action/footer behavior and friendly dates. Do not remove required allocation/project/party/evidence fields because they are secondary. An invalid collapsed field opens its section, shows a summary and receives focus.

Keep truthful optional-document language only for ordinary optional entries. Coverage/errors that block posting remain prominent. Complex/multiple-cash entries retain alternate summaries and complete journal inspection. Corrected originals remain in all authoritative calculations; raw detailed History/export compatibility remains as currently preserved.

My Drafts gains a custom Discard Draft confirmation while keeping draft id/revision, reservation cleanup, owner isolation and Check discard result. Exact transaction corrections remain under More Actions with all eligibility and immutable links. Ledger changes are presentation only; test search/export-matching versus print-full-period with nontrivial opening and corrected entries.

### 4.3 Scan Receipt and manual review

Use **Upload → Review Receipt → Continue to General Journal**. The final CTA names the real destination; “Continue to Accounting” may be explanatory heading only. Desktop preview and form are balanced columns; original can open/zoom in an accessible dialog without publicizing its private URL.

Integrate verified merchant/reference/date/total/currency into the review workspace by reusing `item_review_confirm()` and immutable intake revisions. Do not invent a parallel mutable Receipt record or overwrite normalized provider attempts. A thin shared view/adapter may be factored from `evidence_item_review.php`; preserve existing direct page and JSON consumers. Save Receipt Details uses expected revision/request key/CSRF/source hash, confirmation and recovery behavior. Its result is evidence, never a posting.

Show Suggested account/category from the validated active Expense catalog, its reason and a clear confirmation step in the accounting handoff. Never silently treat a supplier name, item description or provider confidence as an approved account. Revalidate active/protected account eligibility on handoff and final Review. Provider-unavailable uses the genuine manual proposal path, not fabricated extraction. Hide retry when unconfigured; allow meaningful transient retry without weakening attempt/lease guards.

Items are collapsed when absent, optional for ordinary header-only review, and expanded when useful suggestions exist or a mismatch needs action. Present Qty as printed descriptive quantity and Line Total as the authoritative entered row amount; never change multiplication semantics. Preserve 100-row limit, PHP-only confirmation, accepted/excluded amounts, exact item totals and incomplete explanation. For header-only incomplete review, explain the missing itemization plainly and preserve the existing server requirement rather than inventing item rows or auto-confirming completeness.

### 4.4 Cash Advances

Compact register, scannable two-dimensional status, truthful current/as-of scope and View. Reconciliation becomes a secondary **Accounting records reconciled** disclosure only when verified; discrepancy, unavailable or action-blocking states remain primary. Keep page-print versus all-match-total scope available without engine prose.

Detail tells Released → Liquidated → Returned → Settled through actual events (not fabricated milestones). Current eligible Admin actions remain visible; Management remains oversight only. Historical views never initiate current settlement. Release retains explicit advance-control selection, no inferred default; remove inline master creation. Additional Details/Supporting Documents organize reference/approval/proof without hiding unresolved required input. Liquidation preserves gross eligible debits, liabilities, net reduction, required gross support and exact-centavo reconciliation; return retains explicit context-bound proof confirmation.

### 4.5 Budgets

Replace visual account × project columns with rows using the existing canonical key and payload. + Add Allocation creates an unsaved row. Duplicate `(account_id, bucket_key)` rows are rejected with focus; never add them silently. Blank amount means omitted/no allocation; explicit `0.00` remains a real allocation. On Remove, show a confirm if populated; totals use exact integer centavos. Retain unavailable/inactive source labels when resuming existing drafts; do not flatten saved versions.

Primary action depends on current effective approval: Prepare Budget, or Create Revision from Approved Budget. Copy preserves exact source revision and current approval epoch. Save Draft → Review Budget → Submit for Approval remains this workflow's contract. New submissions never replace approval visually or financially. Review has row allocations plus current-approved comparison and clear differences. Management sees exact pending revision and effective approved budget, Approve/Return with reason, no draft editing. Friendly labels Pending Review, Returned, Approved · Current / Superseded map stored states without rewriting them. Legacy Budget Records stays secondary and never becomes approved by relabelling.

### 4.6 Reports and Financial Report Setup

Keep a single report experience; preserve `bundle_id`, month, component, snapshot and format contracts. Live Report is current records; Submitted Report · Revision X is exact preserved submission with captured setup/budget. Show earlier/stale/reopened-cycle conditions and blockers visibly. Mark as Reviewed remains review, not “Approve,” and never implies Close. Month Controls retain authorized close/reopen/reason/dependency and durable recovery behavior, in a secondary section.

Worksheet vertical tables: Opening Balances, Cash Receipts Activity, Cash Disbursements Activity, General Journal Activity, Ending Balances, then monthly-result/position reconciliation detail. Reuse existing fields and totals; no recomputation of classifications. Cash Flow groups opening, operating/investing/financing, closing/reconciliation; unbalanced/incomplete state cannot be styled successful. Project Activity has a human summary and About This Report caveat. Advance Aging uses identity/settlement/outstanding/due/View. Financial Position and Income & Expenses primarily get spacing/actions/date improvements.

Financial Report Setup starts with Cash & Bank Accounts, Financial Statement Groups, Cash Flow Categories and Needs Attention. Focus on unresolved relevant accounts first, Review Existing Setup second. Preserve explicit free-form statement sections and existing eligible cash selections; no invented NGO account policy. Mixed/noncash/opening/closing adjustments live under Advanced Reporting Adjustments. A read-only authorized journal/line chooser may expose human identities while submitting the same validated underlying line ids. Error deep links open the correct task and required fields, not a preselected accounting treatment. Retain exact immutable revision/save/publish/epoch contracts and disclose setup history.

### 4.7 Email, setup and user/audit presentation

Email uses a scoped viewport-fitted desktop workspace with one scroll per list and detail/compose pane, pinned accessible compose actions, compact attachments and mobile reflow. Human delivery labels remain distinct: Sent, Sending, Failed, Delivery Could Not Be Confirmed. Provider/raw metadata under Delivery Details; uncertain send recovery stays intact. Remove archive discovery, not historical data or URLs. No SMTP activation.

Accounting Setup keeps Projects/Parties/Advance Accounts tabs. Compact drawers/modals reuse existing actions, validation and dirty-state handling; generated party ids remain secondary. Chart of Accounts groups only populated types, compact descriptions under account identity, searchable filters, one Actions control, sticky editor footer and explicit protected-used-account explanations. Preserve all server restrictions and disable confirmation.

User Management keeps normal role model/reset queue. Primary badge, locks and permissions follow section 8. Change central role presentation to Management & Board; replace stale descriptions about retired Board/AI panels. Audit table becomes Date & Time | User | Activity | Area | Record | Details. Detail maps CREATE, EDIT, discard and review events to meaningful fields; raw JSON/IP/source/hash remains complete in Technical Details. Do not alter source log values or claim unknown fields changed. Render both full snapshots and existing diff-shaped payloads safely, escaped and secret-redacted.

### 4.8 Management & Board and adjacent-polish boundary

Preserve role-specific dashboard KPIs and existing basis; no giant predictive panel. Show concise pending count, meaningful attention items, friendly chart labels and About These Figures. Planning link is secondary. Shared Reports/Budgets/Advances/Email use role-appropriate wording and real read/decision permissions; no Admin copy/actions in Management markup or returned JSON.

During later execution, record and fix low-risk adjacent clipping, select text, wrapping, empty spaces, action hierarchy, modal scrolling, state refresh and accessible control defects within these screens. A finding that needs new financial policy, new permission, third-party integration or another major service stops that workstream; it is not permission for unbounded redesign.

This is the final major pre-defense product-quality pass: the changed system must look good, work good and feel good as one understandable product. A technically present feature is not a completed screen. Codex must inspect the complete resulting workflows and actual screenshots, including empty/loading/error/success states and expanded/collapsed/sidebar/mobile layouts, and correct obvious directly related low-risk defects before handoff. Do not knowingly return clipping, disappearing select text, inaccessible actions, excessive blank space, nested scrollbars, stale state, inconsistent buttons/destructive wording, raw internal statuses/dates, role-inappropriate or retired-feature copy, broken focus or print regressions merely because the original prompt did not name that instance. Log each adjacent fix and its affected retest. This authority never permits financial formulas/policy changes, new roles or permission models, destructive restructuring, external integration or unrelated redesign.

## 5. Defense-Alignment Plan

The explicit requirements supplied by the user are the entire defense-alignment source. Do not seek the finalized manuscript/PPT or revive old UI from its supposed contents.

| Claim | Truthful visible implementation | Claim boundary |
|---|---|---|
| Centralized records; incoming/expense tracking | Current journal-backed transaction hub/books/finder, recognized reports and exact drilldown | Money received/paid is not necessarily recognized income/expense |
| General Journal / Ledger / cash books | Existing balanced entry, account running balances, complete transaction books | Different scope/semantics retained |
| Budgets / predictive budgeting | Approved-revision workflow plus Suggested Budget Baseline from deterministic planning | Prefill is private editable proposal, not approval or financial authority |
| Forecasting / trends | Separate deterministic Planning & Forecasting with methods/data/error/insufficiency | Advisory estimate, not bank-feed, Gemini oracle or guaranteed outcome |
| Notifications | Existing lifecycle notifications polished/reused; financial attention remains on Dashboard, Budgets, Advances and Planning | Existing retry/duplicate safeguards retained; no new calculated-advisory persistence or claim of universal event coverage |
| OCR / automated categorization | Real extraction suggestions and validated suggested Expense account with human confirmation/manual fallback | Automated assistance, not authenticity verification or automatic accounting |
| Audit/anomaly monitoring | Human audit reader and deterministic explained Audit Alerts | Signals for review, never fraud findings or automatic punishment |
| Financial reports / PDF / Excel | Required genuine PDF and native XLSX over existing exact models after feasibility gate; retained CSV/print | Unsupported dependencies are a reported blocked requirement, not an excuse to fake formats or silently raise runtime requirements |
| Management & Board | Exact visible label, distinct oversight navigation and current financial review decisions | Same stored Management role; no revived Board messaging |
| Security/access controls | Existing sessions/MFA/CSRF/private evidence/roles plus protected primary designation | No claim of provider acceptance, security certification or client deployment |

Gemini boundary: live permitted use is OCR extraction only under separately configured provider authorization. Remove normal calls to forecast and audit Gemini tools. Preserve shared transport/OCR helpers; do not delete their module because some functions become dormant. Retain old cache/history bytes and clearly deprecate old endpoints, as described in section 13. No real provider request is needed to validate this pass.

## 6. Planning & Forecasting Design

### 6.1 Sources and correctness

Create a dedicated advisory service, not a variant of the posting engine. Reuse `financial_report_model()` and `budget_actual_model()` in their repeatable-read boundary, including report configuration, source-valid/correction checks and authoritative line project tags. Source **signed recognized Expense** by `(account_id, project_id/operations, calendar month)`, excluding designated opening/closing recognition under existing `statement_kind()` rules and including correction original/reversal/replacement effects exactly once. Advance releases and cash returns are not expenses; eligible liquidation expense lines are. Refunds/credits remain signed. Do not use legacy `Expenses`, gross CDB totals, cash balance or OCR totals as training data.

Automatically select the most appropriate recent complete historical period, initially at most 12 consecutive complete calendar months before the selected forecast month and never beyond the last elapsed month. Determine its bounds from authoritative recorded accounting activity and the existing recognition/reporting validity checks, not the old fixed twelve-month zero-filled array. Conservatively exclude an ambiguous initial recording/cutover month unless existing source evidence establishes usable coverage, stop at missing/invalid recognition coverage, and use the most recent sufficient contiguous valid run. A source-invalid month is not skipped and relabelled consecutive. Exclude the current partial month from training, display actual-to-date separately and refuse future/invalid cutoffs. Missing history before supported recording coverage is not zero. Missing source validity or a classification affecting recognition blocks only the affected series/period; unrelated cash-flow-only incompleteness is identified separately, not “repaired” by forecasting.

Normal staff do not confirm a historical start or reason about fingerprints each time. Show a human summary such as **Historical basis: May–October 2026 · 6 complete months** (illustrative, never seeded as a claimed fact). An optional advanced period adjustment may narrow the eligible window, but cannot waive completeness/integrity checks or manufacture coverage. If recorded evidence cannot establish enough trustworthy complete months, show **Insufficient complete history for a reliable forecast.** Put source ids, eligibility decisions, fingerprints and cutover/coverage diagnostics under **How this forecast was calculated** / **Technical details**. Explain that completeness is evaluated against recorded data; it is not proof that unrecorded real-world activity does not exist.

Within a source-supported covered month, zero recognized activity is valid. Distinguish no usable history, genuine zero history, incomplete history, credit-dominated history and valid positive-cost history. Preserve signed actuals, accounting dates and historical account/project labels. Granular coverage begins only when that account/project bucket has supportable historical coverage; do not backfill a new bucket's pre-existence with zeros. A new project does not invalidate otherwise complete organization history.

### 6.2 Proposed deterministic method contract

One-step monthly forecast first; horizon selector 1–3 months can show repeated/recalculated baselines explicitly, without claiming later-month backtest accuracy from a one-step test. Core first release may keep the user-facing horizon at one month to avoid unsupported precision; the service contract records horizon explicitly.

Candidates:

1. Last complete month baseline: `F = x[n]`.
2. Three-month moving average: `F = (x[n-2]+x[n-1]+x[n])/3`.
3. Three-month weighted average: weights `1,2,3` oldest to newest, divided by 6.
4. Ordinary linear trend over the last six covered months, extrapolated one step; include only with enough training and validation history. Do not fit a decorative trend to two points.

Operate on integer centavos for source/sums; use exact integer/rational rounding once to the nearest centavo for averages. Trend arithmetic must use bounded finite values, checked sums and a documented deterministic rounding rule, not unchecked floats as accounting facts. Never write estimates back to journals.

Minimum output rule: at least **three consecutive usable complete months** for a suggested monthly cost baseline. With 3–5 months use the three-month mean and label it **Baseline estimate; not back-tested**. With at least six months, evaluate last-month/MA3/weighted candidates on at least three identical rolling-origin one-step folds, training only on earlier months. Linear trend joins only when there are at least nine months so it also has six training months and three common test origins. Compare all eligible candidates on the same origins, at most the six most recent folds. No look-ahead or random split.

Select lowest mean absolute error in centavos; ties choose simpler method in fixed documented order (last month, mean, weighted, trend). Show **Historical average error (PHP)** and fold count. WAPE may be secondary only if sum of absolute observed values is positive; never advertise `100 − MAPE` as “accuracy,” and never divide by zero. This is validation on the selected history, not guaranteed future performance.

Reject nonfinite/overflow output. A negative net estimate is displayed as signed credit-dominated activity with no automatically usable spending baseline; do not silently clamp refunds or convert it into positive expense. All-zero covered history may show a zero baseline with an explanation, not fabricated percentage accuracy. Extreme extrapolation relative to observed scale is marked unsuitable and falls back transparently to the valid three-month baseline; record the fallback reason and method. Threshold for that statistical stability check is a versioned engineering guard, not an accounting policy.

### 6.3 Overall forecast, granular coverage, budget comparison and prefill

**Overall organization forecast is a first-class independent estimate.** Build the monthly organization series from total authoritative signed recognized Expenses, then apply the eligible deterministic methods/back-testing to that series. When it has sufficient complete trustworthy months, show **Projected Expenses — [forecast month]**, forecast amount, method, human historical basis, historical average error where meaningful, total effective approved budget and explicitly signed variance. The availability of this overall estimate does not depend on every account/project having an individual forecast. A newly created project alone cannot suppress a valid organization forecast.

**Granular account/project forecasts are separately qualified.** Apply the same recognition/completeness/method rules to each requested disjoint bucket only where its own history is sufficient. Otherwise show **Insufficient history**, never a substituted zero. Display **Partial detailed coverage** when only some buckets have estimates, with counts/period context. Current live account/project lists, historical actual coverage and forecast availability remain distinct concepts.

The independent organization estimate is not presented as the sum of granular estimates. Even where all detail buckets are covered, independently selected methods can differ; label any detailed subtotal as a **Detailed estimate subtotal**, not the organization forecast. Reconcile historical actuals to the underlying recognized-expense model, but do not invent a residual allocation, rescale buckets, fill missing estimates with zero or promise that granular predictions reconcile to the overall prediction. Management & Board sees the overall executive interpretation and a concise coverage note; Admin may inspect deeper account/project results.

For the selected forecast month, load only the effective approved budget revision. Compare the overall estimate with the overall effective approved total and a granular estimate with only its matching approved bucket. `Projected remaining = approved − forecast`; label negative remaining as projected over budget. Missing approval/allocation is **No approved budget / No allocation**, not zero. Preserve blank versus explicit-zero allocations. Show approved revision/basis secondarily and source state changed since generation visibly. Keep proposed versus effective approval distinct.

Charts/tables show actual history, the appropriate estimate and matching approved budget only where available. Overall and granular methods, error measures, coverage and period remain distinguishable; no private document context is exposed to Management & Board.

**Suggested Budget Baseline** is an explicit Admin action: inspect valid eligible suggestions, select/edit allocations and target month, then choose Create Budget Draft. Server recomputes/revalidates the source context, active Expense/project eligibility and expected approved revision before calling existing `budget_save()`. Preserve existing draft request key/duplicate recovery; a repeated identical request returns the same draft. No overwrite of an existing draft, no automatic submit/approve, no unknown-to-zero conversion. Invalid/inactive/negative/incomplete suggestions remain visible but cannot be silently prefilled.

### 6.4 Persistence and performance

No forecast schema or Gemini cache reuse is required. Compute from a bounded, consistent source window; extract the minimum approved projection once per month/request and reuse request-local account/configuration data without removing integrity checks. Release session locks for long read work. Forecast results include version, source fingerprints, approved revision, as-of, coverage, chosen method and test folds. A deliberate draft write is a separate endpoint/CSRF action.

Do not use the old 2-positive-month sufficiency rule or old provider `forecast_cache` identity for the new model. Preserve existing cache rows as historical implementation data. No GET cache write, scheduled job, external service or hidden training process. Benchmark the bounded window and multi-project fixture before choosing any persistent cache; needing a new cache/schema is an explicit review boundary, not an unnoticed expansion.

## 7. Audit Alerts Design

### 7.1 Explainable rule set and unavailable evidence

Add an **Audit Alerts** tab to Admin Audit Trail for unusual audit/security activity patterns. Pure rules receive an authorized read model, a fixed as-of time and a versioned policy object; outputs contain rule id/version, informational/review severity, observed values, threshold/window, supporting record identities, source coverage and exact reason. Wording is **Needs review**, not fraud. Raw immutable audit rows remain authoritative. Ordinary high-value payments, normal budget utilization and overdue advances are not audit anomalies; keep them in their operational contexts.

Initial supported rules:

- **Edit burst:** e.g. 8 distinct financial mutation events by one actor within 5 minutes. Exclude notifications, advisory refreshes, views, login and unrelated generic configuration noise. Count distinct business events; group correction-created reversal/replacement as one correction, not three “edits.” Explain actor, event count and window.
- **Repeated corrections:** e.g. 3 distinct successfully posted correction chains by one actor within 24 hours. Include exact correction/original ids and reasons, not failed draft previews or duplicate recovery.
- **Repeated OCR reading failures:** e.g. 3 genuine non-manual failed attempts by one owner within 15 minutes. Exclude manual proposals, unconfigured-provider path, superseded leases and deliberately unavailable-provider fixtures from unusual-activity claims. Never expose private image contents or provider response bodies in summaries.
- **Significant before/after monetary changes:** enable only for allowlisted structured financial amount fields with reliable old/new values and a documented comparison threshold. Show field, before, after, actual delta and source event. Do not compare arbitrary JSON numbers, hashes or generated control lines; a large transaction's ordinary creation amount alone is not a suspicious change. Where the structured evidence or threshold is unavailable, report this rule as not monitored rather than inventing a comparison.
- **New successful sign-in IP:** informational only, after a minimum prior successful-login history, comparing trusted `REMOTE_ADDR` records. NAT/VPN/shared networks are not suspicious by themselves; unknown CLI `0.0.0.0` is excluded. Show source coverage and never block login.

These numerical pattern thresholds are **proposed transparent engineering defaults** for review/testing, not client policy. Keep a small versioned server-side rule configuration; unsupported structured-change rules and operating-window rules remain disabled. Do not build a new role/policy-admin engine. Ordinary monetary/budget/overdue attention keeps its existing legitimate operational treatment on Dashboard Needs Attention, Budgets, Cash Advances, Planning & Forecasting and existing Notifications where supported; it does not enter Audit Alerts because of amount or status alone.

**Not enabled without evidence:** repeated failed authentication cannot be computed reliably from current audit rows; do not infer it from missing successful logins or PHP error logs. Adding failed-login instrumentation/retention/rate-limiting would affect authentication and needs separate explicit scope. Operating-hours alerts remain disabled until a timezone/window and justified policy are provided. Significant old/new monetary-change rules apply only to typed known amount fields with before/after values; never compare arbitrary numbers, hashes or generated control lines. Unsupported rules show **Not monitored — source/configuration unavailable** in coverage details, not a false clean bill.

### 7.2 UI, permissions and scope

Read model validates database-backed active Admin authorization at the endpoint. Management gets only already-authorized financial pressure/advance summaries elsewhere, never raw audit/security/IP evidence. Each rule is bounded by explicit date/event limits and indicates truncated coverage; no “no alerts” conclusion from the latest 100 rows. Query supporting records by indexed date/module/actor/ids and stable ordering; no blind whole-history PHP scan.

Alert detail leads with reason, period and supporting event links. Technical detail exposes thresholds/version, exact source ids and timestamps without credentials/MFA/session values. Escape all old/new values and route labels; allowlist drilldown destinations. No automatic account disable, transaction reversal, approval, posting or original-log mutation. A malformed source row produces a coverage warning, not an invented event.

### 7.3 Reuse existing notifications; keep advisory surfaces separate

Preserve and polish the current `Notifications` infrastructure, existing budget/report submission/review/return events, appropriate existing password-reset notifications/workflow and legitimate existing alert behavior. Improve human wording, timestamps and safe relevant links where needed. Preserve existing mutation-result/duplicate checks (including suppression on an already committed identical retry); do not move notification creation into a page GET, add a polling writer or attach a new advisory persistence obligation to financial writers. A notification delivery failure remains a warning, not retroactive failure of an already recorded transaction.

Calculated conditions stay read-only on the appropriate surface: overdue/financial/review items on Dashboard Needs Attention and Cash Advances; budget utilization in Budgets; projected pressure in Planning & Forecasting; unusual audit/security patterns in Audit Alerts. Refreshing these views or marking a notice read creates no new bell notices. Audit patterns may be grouped by supporting events for readable results, without persisting advisory episodes or creating an acknowledge/resolve subsystem.

No new notification schema, event-receipt service, scheduler, publication endpoint or durable advisory-dedup mechanism is required by this scope. Reuse existing duplicate-result safeguards; do not promise concurrency-safe new notification classes through an ad hoc check-then-insert. If later source inspection exposes an existing notification defect that cannot be corrected safely within the existing mechanism, preserve and report it rather than quietly adding persistence. No real email/provider effects are introduced.

## 8. Primary Administrator Design

### 8.1 Persistence and invariant

Keep `Users.Role` unchanged. Proposed additive component `026_primary_administrator.sql` introduces a singleton `system_primary_administrator` row: constrained id `1`, unique non-null `user_id` referencing `Users.UserID` with restrictive FK, designation actor/time and component version. This identifies the account explicitly; it is not hardcoded UserID 1 and is not a permission role.

Because a CHECK/unique key alone proves “at most one,” not “at least one,” deployment is incomplete until an **explicitly selected existing active Admin** is designated. Do not silently choose the oldest/id1 account. Guarded bootstrap selects/locks the supplied target, verifies role/status, inserts the singleton and audit atomically after DDL setup. Initialization retry accepts only identical existing designation; different/partial state stops. A newly created empty table is not reported as a functioning invariant.

Once initialized, the singleton/FK and transactional checks in every supported user/credential writer preserve exactly one designated active Admin during normal application operation. No application route may remove or reassign the designation in this no-transfer release. Readiness/preflight validates table shape, singleton constraints/FK and the designated user's active/Admin relation; account-changing endpoints enforce the guard even when unrelated UI feature flags are off. **No new database triggers are planned.** Existing server writer boundaries and consistent locking are sufficient for the supported-operation threat model; defending against a privileged DBA deliberately editing tables is outside it. If later implementation inspection proves a normal supported writer cannot be protected this way, stop and explain the concrete need before proposing extra trigger/recovery complexity.

### 8.2 Service and credential protections

All normal user mutations acquire a consistent order: primary singleton coordination row → actor/target user rows in numeric order → applicable reset request row. Reconcile `resolve_reset.php` to this order, rather than keeping its current reset-first order and introducing a deadlock. Do not insert this lock into the journal writer lock order or unrelated finance actions.

Ordinary Admin cannot demote, disable/delete, change primary name/email/password, or resolve a password reset for the primary account. Enforce the restriction in `users_save()` and `resolve_reset.php`, not hidden fields. Reject forged action/target combinations with unchanged credentials/state and no false success audit. Under the singleton lock, revalidate actor, target and designated active/Admin state before committing any supported account change. A restrictive FK prevents supported deletion of the referenced account; there is no new designation-delete/reassignment writer. Audit failure rolls the account change back.

Primary may manage its own otherwise-supported identity/credentials through the existing form using current session/CSRF/password policy and existing interaction behavior; never log submitted credentials. **Do not add mandatory current-password reauthentication or a new identity flow for these self-edits.** Source inspection did not establish a reusable current-password challenge in this writer, so it is not an assumed prerequisite. Keep role locked and disable unavailable. An ordinary Admin still cannot take over through the reset queue. This is focused account protection, not general identity-management redesign.

Read UI shows Primary Administrator badge, locked role and clear reason, no Disable action. Other Admin/Management accounts retain current CRUD/reset rules. Requests targeting a primary password reset remain preserved and clearly protected; do not mark them resolved without action.

### 8.3 Transfer, failure and recovery

**Do not expose transfer in this batch.** No web request can temporarily clear the designation. Lost primary access requires a separately authorized, offline guarded recovery procedure with proven database target, recoverable backup, hidden credential input and audit. No fallback to id1, second Admin override, zero-primary state or secret bypass URL. Document the procedure; do not exercise it on protected environments.

Absent/partial/corrupt designation causes account-changing endpoints to fail closed with an actionable setup/recovery message; readers and financial workflows should not be casually redefined by this condition. Never auto-repair schema or designate a user on a page request. Successful deployment/readiness must prove exactly one active Admin designation. Normal UI protections do not establish that invariant on an unconfigured working installation.

## 9. Data / Schema / Migration Plan

### 9.1 Only proposed additive schema component

**026 — Primary Administrator:** singleton designation table, constrained/unique identity, restrictive user FK and component/readiness checks, combined with transactional protections and row locking in the existing supported user/reset writers. No new triggers are required or planned. Explicit initialization establishes the designated active Admin; an empty table is not a completed deployment. No alteration of stored role ENUM, financial tables, MFA columns, evidence, reports or budgets.

The expected new application schema contains only this designation component. Existing Notifications rows/read flags remain unchanged except for ordinary authorized application use during future isolated testing. No notification event/deduplication table or separate advisory persistence is proposed.

No forecast cache/training table, budget-grid schema, OCR evidence rewrite, Board deletion, replacement audit log, new financial transaction kind or financial policy migration is planned. Report binary dependencies are a packaging/runtime prerequisite, not SQL.

### 9.2 Guarded migration and deployment boundary

During later authorized implementation, create a **new verification clone** from a proven disposable synthetic source with schema/data provenance; never use working rows. Check `SELECT DATABASE()`, target allowlist and forbidden databases; current component shapes/triggers/indexes/FKs; zero cross-schema references; absence of proposed component or exact already-complete state. Stop on partial/unexpected state. New migrations must not contain hardcoded `USE atikha_finance`, DROP/TRUNCATE of existing tables, trigger replacement on old accounting components, or blind IF NOT EXISTS that hides an incompatible table.

Apply 026 once only on that verification clone, with explicit primary fixture identity. Prove clean install, exact rerun handling, partial-state refusal, concurrency and rollback of service writes. Check existing financial/audit triggers remain unchanged; do not add primary-account triggers by default. MariaDB DDL implicitly commits: separate DDL and designation initialization, record each phase, never claim whole-migration transactional rollback. Do not replay 015/019/020/021 or “refresh” 022–025 merely because scripts exist.

Protected working, participant and finished defense environments receive **no migration/config/source/session/data refresh**. Preserve the interactive preview's current DB, uploads, sessions and reset snapshot before later refresh. Since this proposal introduces schema, do not quietly turn the current preview into an uncertain partially migrated DB: validate a new complete synthetic clone and propose a deliberate preview-only connection switch preserving the old DB/snapshot. Clearly disclose any preview session re-entry or target change. Reusing port 59912 is preferred, but must not override existing ownership checks. The participant source/oracle remains frozen and A01 remains paused.

Working rollout is a separate unperformed procedure with backup/recovery, explicit target primary designation and runtime prerequisites. Implementation authorization for source/disposable tests is not permission to initialize the real primary or mutate `atikha_finance`.

### 9.3 Recovery

Before disposable DDL: hash schema/data/private evidence/runtime snapshot. On failure stop the isolated app, preserve logs/state, restore only its owned disposable clone/snapshot if authorized; never compensate by disabling FK/audit triggers. After any new data exists, prefer forward fix/restore from verified backup, not dropping protection tables or erasing notification/audit evidence. No automatic downgrade that removes the primary invariant. Source rollback must use preserved local copies/diffs, not `git reset` over user work.

## 10. Validation Plan

No suite is run merely for this planning document. Future test results must be attributed to exact source/dirty baseline, isolated DB, role, fixture state and viewport. Do not recycle dated assertion counts as final evidence.

### 10.1 Source, accounting and security

- PHP lint for all changed/new PHP; JS syntax; UTF-8/mojibake/language scan; `git diff --check`; Markdown links; source inventory with before/after hashes and preserved prior modifications.
- Existing `test_stage1.php`, `test_stage2.php`, `test_stage3_checkpoint2.php` through checkpoint5/transition cases, `test_stage4_ledger.php`/queries, Stage5 reporting/configuration/cash-flow/period-edge/race/flag-off suites, Stage6 budget/integrity/read-model suites and Stage7 evidence/export/negative/flag-off suites selected against touched dependencies. Inspect each harness/fixture precondition rather than blindly executing historical runners.
- Existing U2/U3/U4/U5 adapters/regressions, `test_transaction_presentation.php`, `test_transaction_readers.php`, relevant redesigned shell/daily/security/setup/accessibility/browser tests. Update obsolete selectors/contracts intentionally; never relax financial assertions to match a new layout.
- Real isolated Payment/Receipt/GJ, complex saved drafts, discard reservation recovery, stale Review, duplicate/post-result recovery, unbalanced rejection, transfers, exact correction lineage, advance partial/gross-liability settlement and return proof; closed-period retries recover committed identical result first.
- Negative direct requests: anonymous/disabled/wrong-role/forged id, owner-other draft/evidence, Management HTML/JSON/print/PDF/XLSX/download filtering, flags-off paths, CSRF, preserved historic links and secret redaction. No test sends a real MFA/SMTP/Gemini request.

### 10.2 New feature oracles

- Forecast: hand-derived centavo series for constant, trend, intermittent zero, signed refund, insufficient/missing coverage, current partial month, inactive masters, opening/closing exclusions, advance release/liquidation and exact corrections. Prove automatic trustworthy window selection with no mandatory start-confirmation step and no missing-to-zero conversion. Test independent overall availability with a new/insufficient-history project; granular Insufficient history / Partial detailed coverage; historical actual reconciliation without falsely reconciling independent forecasts. Retain rolling-origin no-leakage, common folds, deterministic ties/rounding, zero-denominator error measures, source invalidation, approved-versus-pending comparison and explicit editable/private/stale/duplicate-safe prefill.
- Alerts: exact activity-pattern boundaries/windows, grouping without duplicate event counts, duplicate recovery exclusions, manual/unconfigured OCR exclusions, reliable structured before/after comparisons, trusted-IP-only logic, neutral explanations, source truncation/unavailable-rule warnings and Admin-only access. Verify ordinary high-value creation, budget utilization and overdue advances do not become audit anomalies. No audit mutation, persistent advisory publication, fraud claims or false clean results.
- Primary: explicit initialization, no id1 assumption, singleton/FK/readiness and transactional-writer guards without new triggers, duplicate initialization, role/status/delete protection, forged identity/credential/reset takeover, preserved self-edit behavior without a new password challenge, audit rollback, lock ordering, simultaneous disable/demote/reset, and unrelated Admin functionality. Verify one designated active Admin after every successful/concurrent supported action; working initialization remains unperformed.
- Notifications: regression-test existing budget/report submit/review/return events and appropriate existing password-reset/alert behavior, retry/duplicate-result suppression, owner/read flags, human wording and authorized links. Repeated advisory/dashboard reads must insert no notices. No new publication API or persistence is expected; notification failure must not undo already recorded accounting. Budget null/zero/positive-denominator and signed-pressure checks remain in operational budget/dashboard tests, not Audit Alerts.
- OCR/manual review: unavailable/error/success/ambiguous-date/inactive suggestion, header-only/incomplete itemization, exact mismatch explanation, manual-epoch supersession, image integrity, draft reservation/posted links and GJ handoff. Confirmation does not post.

### 10.3 Real exports, dependencies and print

Use the existing `financial_export_model()` as the authority. PDF/XLSX writers must serialize its live or exact captured-submission sections, not scrape visible DOM/paged tables or run independent financial formulas. Submitted exports use stored amounts, labels, configuration and revision even if today's masters/budgets change; no “refresh” of frozen data.

Real native PDF and XLSX are required defense-aligned capabilities, not optional substitutes for CSV/Print. Initial library candidates remain `dompdf/dompdf` for a controlled PDF template and `phpoffice/phpspreadsheet` for XLSX, but selection/versioning is conditional on a **first isolated dependency-feasibility gate**, not assumed from a package name. Inspect then-current maintained/supported versions and their authoritative requirements, Composer resolution/security/license/platform results, installed PHP/extensions and actual web-versus-CLI runtime. Test genuine binary generation/read-back privately before adopting a dependency in the actual local source. Do not install anything during this planning revision.

Preserve the application's declared PHP minimum (`>=8.0`) by default. The earlier inspection found PHP CLI 8.2.12 and current [PhpSpreadsheet documentation](https://phpspreadsheet.readthedocs.io/en/latest/) requiring PHP 8.2+ for its current line; that observation alone does **not** authorize raising the application minimum or pinning an obsolete unsupported release. Compare maintained compatible library/version choices, validate against the intended supported runtime range and current execution runtime, and distinguish “runs here on PHP 8.2” from “supports the application's declared floor.” Change Composer/package dependencies only after later implementation authorization and successful feasibility evidence. If preserving the floor is genuinely incompatible with every safe supported option, document the proven required/safe minimum change for review rather than silently applying it; mark the affected export requirement blocked until that prerequisite is resolved.

ZIP was not loaded in the inspected CLI even though its DLL exists. If needed and compatible, load it only in the isolated test runtime's own startup configuration after validation; never change global XAMPP/PHP configuration. Actual working-runtime changes or enablement remain separately authorized rollout work. No library choice may weaken report integrity, activate external effects or disguise a platform limitation. Failed feasibility is a documented blocked native-export requirement, not permission to fake files or destabilize accounting.

Use [Dompdf's documented capabilities/limitations](https://github.com/dompdf/dompdf) to design a separate simple paginated print template, not flex/grid screenshot conversion. Disable remote fetch and embedded PHP/script, restrict local asset roots, escape user text, keep temporary output private and clean up. No provider/network asset retrieval, uploaded HTML or arbitrary file URL rendering. Repeat headings, avoid unbreakable huge rows, page numbers, human period and exact submitted revision. Amounts reconcile to CSV/model before any success headers; no partial binary download.

XLSX: genuine ZIP/OOXML container and MIME/extension; explicit string cells for untrusted text/identities, not formulas or macros; numeric amount presentation with exact source decimal reconciliation, noting Excel's precision limit for exceptionally large amounts. Where exact cents exceed safe spreadsheet numeric precision, emit exact-decimal text with a visible explanation, never silently round. Separate sheets for report sections, frozen/live status, period and relevant basis; no credentials or hidden private document sheet. Reopen and compare all rows/totals with an independent reader; check formula injection strings, Unicode, long labels, blank/zero, leading-zero reference, >one page and >one sheet.

Retain CSV as explicitly **Excel-compatible CSV (.csv)** and Print / Save PDF as browser print, alongside real binary choices when ready. If platform/dependency validation cannot support real XLSX/PDF safely, report a blocked export capability and retain truthful existing options; do not present fake files or quietly claim defense alignment complete. Existing GET/JSON/CSV/print/snapshot contracts remain supported.

### 10.4 Browser/product-quality and performance gate

Capture full pages/states at 1920×1080, 1366×768 expanded and collapsed, 390×844; include both roles and error/empty/loading/long-label/large-result states. Every operational table must expose identity/status/action without horizontal scroll at 1366 expanded. Test `document.scrollWidth <= viewport` and row/action reachability; genuine detail scroll regions must have independent bounds/labels. Inspect actual screenshots, not only selector assertions.

Cover all listed modules, menus/selects, rail/flyouts/persistence/mobile drawer, keyboard Tab/Enter/Space, visible focus, focus trap/restoration, Escape, dialog body-scroll/footer behavior, dirty state and nonduplicating error recovery. Zoom/reflow and reduced motion are explicit checks; real screen-reader/physical-device/Firefox coverage is reported performed or unperformed, never inferred from ARIA. Auth appearance/carousel and functional form scripts stay unchanged and get a narrow shared-shell spillover check rather than redesign.

Print/PDF pages must be rendered/visually inspected at representative page sizes; check headers, totals, clipping and readable multi-page output. Exercise Email pane scroll and sticky actions without sending external mail. Synthetic fixtures use readable labels in human screenshots; hostile strings remain separate negative tests, not ordinary showcase data.

Performance: repeat cold/warm medians for affected readers, planning, alerts, reports and exports on small and controlled larger synthetic workloads, with query count/peak memory. Proposed engineering targets: ordinary readers <=2s HTTP median, <=3s visible; bounded planning/alerts <=3s warm median; binary exports complete within a documented timeout/memory budget on representative data. Compare before/after current local baseline, retain failure evidence and limitations. No user-visible success from a truncated source scan. Do not weaken integrity checks to meet targets or claim a production SLA.

## 11. Short Final Owner Acceptance / Smoke Checklist

Prior formal evaluation, U-series work, user walkthroughs and recent Administrator / Management & Board hands-on, screenshot/video and module reviews are completed requirements-discovery evidence identified by the user. **No new formal usability study, giant discovery walkthrough, participant protocol or unfinished broad human-testing prerequisite is planned.** Do not restart, refresh or repeat the protected participant workflow, alter its data or rewrite historical observations. Its archived A01 status remains historical, not a gate for this source implementation.

Before owner handoff, Codex must complete the detailed automated, accounting/security, browser/screenshot, responsive/table, keyboard/focus/modal, export/print and appropriate performance verification in section 10. The owner check confirms representative delivered changes only, in an isolated approved preview, with no working/client write or provider activation. It is intentionally short; screenshots and prepared example records should reduce manual repetition.

1. Check Dashboard, Needs Attention and expanded/collapsed sidebar at the normal laptop viewport.
2. Record one isolated Payment **or** Money Received entry and open its result.
3. Upload/review one receipt and check the clear manual/assisted handoff.
4. Open one cash-book transaction and its General Ledger activity.
5. Inspect one Cash Advance's status/detail.
6. Prepare **or view** one row-based Budget and its current approval context.
7. Open one Report and representative genuine PDF/XLSX, CSV and Print outputs already validated by Codex.
8. Open Planning & Forecasting; check overall result, human historical basis and a detail coverage state.
9. Use a safe Management & Board session and review one prepared submitted item where authorized.
10. Check User Management's Primary Administrator badge/locked actions.
11. Open Audit Trail and one explained Audit Alert.
12. Quickly check representative responsive readability/actions at the normal laptop size; use Codex's recorded desktop/mobile evidence for the exhaustive matrix.

Record owner acceptance or a concrete remaining blocker; do not turn this confirmation into another formal study with unassisted-task scoring, mandatory teach-back or a full discovery phase. Existing historical evidence remains truthful and unchanged. Any later formal client acceptance, real-provider acceptance or working rollout remains a separate claim; none is fabricated by this short owner check.

## 12. Risks / Open Decisions

These are explicit review/deployment inputs, not requests to reopen broad planning:

- **Primary designation:** the real active Admin account to protect has not been selected. Implementation can use an explicitly chosen synthetic fixture; working initialization cannot proceed without a real selection and separate rollout authorization.
- **Runtime/export support:** real PDF/XLSX remain required. Maintained-library/version and isolated runtime feasibility is a first gate; PHP >=8.0 stays declared unless proof establishes a required safe change for review. No assumed floor increase, unsupported pin or global configuration change. If unresolved, report native exports blocked, retaining truthful CSV/Print without claiming they satisfy the binary requirement.
- **Client classifications/fiscal/thresholds:** retain unresolved client policies. Advisory default numbers and record-coverage choices are engineering conventions, plainly labelled/configurable; no new chart mapping or working calendar policy is guessed.
- **Failed-auth monitoring:** current source lacks the needed event data. The core alert design deliberately excludes it rather than broadening authentication and storing submitted identities/IP history without an approved policy. Do not claim that capability in defense unless separately scoped and tested.
- **Forecast completeness:** automatically selected overall history and granular history have independent availability. A new project cannot suppress a valid overall forecast; show Insufficient history / Partial detailed coverage for affected detail and do not imply its subtotal equals the overall prediction. Ambiguous overall coverage or signed/credit-dominated series may still block a usable cost baseline; no fabricated history, forced zero or positive floor.
- **Totals:** gross originating payments include corrected originals/replacements. Neutral labels/counts are mandatory even if a requested example says Total Payments. New receipt/category suggestions remain constrained to supported accounts, not blanket tax/capitalization inference.
- **Evidence review coupling:** integrated OCR editing changes intake context/signatures; use existing immutable reviews and fresh journal Review, never a second mutable truth. Quantity remains descriptive.
- **Shared CSS/JS:** global shell changes can affect old screens; scoped component adoption and broad role/browser checks are required. Do not hide overflow to conceal inaccessible actions.
- **Scope/timing:** this is a larger coordinated batch than prior presentation-only work. New security designation, analytics and binary export need their own internal gates. “One shot” means one authorization across successful gates, not skipping blocked capabilities or inventing test results.
- **Acceptance:** completed prior human testing/reviews inform this pass; another formal usability study is not outstanding. Remaining work is implementation, Codex's detailed verification and short owner smoke confirmation. Source/external review, preview, provider acceptance, working rollout and Atikha/client acceptance remain distinct; old approvals do not cover future source changes, and archived participant records are not rewritten.

## 13. Expected Production File Inventory

This is the proposed bounded inventory for later authorization, not files changed during planning. Names for new components are fixed here for review; an unforeseen production file/service/schema outside this inventory must be explained before expansion. Existing security/financial services listed as dependencies are not carte blanche to rewrite their formulas.

### 13.1 Existing production files expected to change

Shared components, navigation, friendly dates, role label, confirmations and dashboards:

```text
includes/presentation.php
includes/layout.php
includes/nav.php
includes/header_bar.php
includes/user_roles.php
includes/presentation_queries.php
assets/css/atikha_ui.css
assets/css/atikha_workspaces.css
assets/css/atikha_dashboard.css
assets/js/atikha_ui.js
dashboard.php
```

Existing local transaction work, drafts and ledger (bounded finishing, no rebuild):

```text
new_transaction.php
transactions.php
advanced_accounting.php
includes/accounting_entry_page.php
assets/js/accounting_workspace.js
assets/css/transaction_workspace.css
includes/transaction_presentation.php
includes/transaction_records_page.php
includes/transaction_detail_page.php
assets/js/transaction_records.js
assets/js/transaction_detail.js
accounting_drafts.php
assets/js/accounting_drafts.js
general_ledger.php
assets/css/general_ledger.css
assets/js/general_ledger.js
```

OCR/evidence, advances, budgets, reports and setup:

```text
ocr_expense.php
assets/css/ocr_expense.css
assets/js/ocr_expense.js
evidence_item_review.php
assets/css/evidence_item_review.css
assets/js/evidence_item_review.js
includes/receipt_ocr.php
cash_advances.php
assets/js/cash_advances.js
assets/css/accounting_workspace.css
budgets.php
assets/js/budget_workspace.js
assets/css/budget_workspace.css
report_bundles.php
financial_statements.php
reports.php
includes/report_component_view.php
assets/css/financial_reports.css
assets/css/report_bundles.css
assets/css/reports.css
assets/js/report_workspace.js
assets/js/report_bundles.js
report_setup.php
assets/js/report_setup.js
accounting_setup.php
assets/js/accounting_setup.js
admin_accounts.php
assets/css/admin_accounts.css
assets/js/admin_accounts.js
```

Administration, notifications, communications, analytics retirement and exports:

```text
admin_users.php
assets/css/admin_users.css
assets/js/admin_users.js
includes/users.php
resolve_reset.php
management_reviews.php
includes/review_ui.php
audit_trail.php
includes/audit_query.php
audit_ai.php
forecast_ai.php
external_email.php
assets/js/external_email.js
includes/notifications.php
notifications_api.php
assets/js/notifications.js
financial_export.php
includes/financial_export.php
assets/css/financial_export.css
composer.json
composer.lock
```

New dependencies are installed/locked only in later authorized work; ignored `vendor/` is not bulk committed. Do not rewrite generated Tailwind unless an inspected missing utility actually requires the existing build; prefer scoped CSS.

### 13.2 Proposed new production / guarded operational files

```text
planning.php
planning_actions.php
includes/planning_query.php
includes/planning_methods.php
includes/advisory_policy.php
assets/css/planning.css
assets/js/planning.js
audit_alerts.php
includes/audit_alerts.php
assets/js/audit_alerts.js
assets/css/audit_trail.css
assets/js/audit_trail.js
includes/primary_administrator.php
includes/receipt_review_view.php
includes/report_export_binary.php
includes/report_export_pdf_view.php
assets/css/external_email.css
migrations/026_primary_administrator.sql
scripts/preflight_predefense.php
scripts/initialize_primary_administrator.php
```

`audit_alerts.php` is an authorized tab/data endpoint, not a public audit feed. `planning_actions.php` owns explicit draft-prefill/advisory actions, not posting logic. `receipt_review_view.php` shares presentation with the existing item-review endpoint rather than replacing it. Binary writer receives the existing authorized export model and uses private temporary storage.

`forecast_ai.php` becomes a protected deprecated compatibility route to deterministic planning data (or a documented non-generating response for old actions), with **no Gemini call/cache write**. `audit_ai.php` returns protected deprecation guidance to Audit Alerts for old AI actions; the new UI does not call it. Preserve authentication/CSRF where applicable and test legacy responses; do not silently make old callers believe an AI scan ran. Existing Gemini forecast/audit helper definitions and `forecast_cache` remain dormant until a separate removal review; Gemini OCR dependencies remain live and unchanged in authority.

### 13.3 Dependencies expected to remain behaviorally unchanged

Journal posting/workspace/correction/advance/period engines; `includes/financial_report_query.php`, `includes/budget_actuals.php`, `includes/budget_revisions.php`, report configuration/bundle/snapshot services, authoritative receipt/item-review services, original images/associations and audit logger are reused. A view adapter may call their existing functions; financial formula changes are outside this plan. Login/MFA/reset UI, authentication carousel, `auth.php`, MFA issue/verify/resend services and credentials remain unchanged. `resolve_reset.php` is the explicitly justified primary-target protection exception, not provider/auth-flow redesign.

No edits to real/private `config.php`, working database connector, historical migrations, prior evidence packages, completed rehearsal or preserved participant artifacts. After implementation, create `docs/final-predefense-oneshot-delivery.md` as specified below, include validation/rollout prerequisites and the short owner smoke checklist; do not create or restart a fresh formal human protocol. Rewrite current-state then to acknowledge completed prior human review/discovery and the remaining owner confirmation accurately. Update decisions 002/007/008/011/012 as genuinely needed and add compact decisions for deterministic advisory analytics/primary protection with index links. Preserve historical wording/evidence.

## 14. Expected New/Changed Tests

New focused tests, without arbitrary target counts:

```text
scripts/test_predefense_presentation.php
scripts/test_planning_methods.php
scripts/test_planning_read_model.php
scripts/test_planning_prefill.php
scripts/test_audit_alerts.php
scripts/test_predefense_notifications.php
scripts/test_primary_administrator.php
scripts/test_predefense_schema.php
scripts/test_report_binary_exports.php
scripts/test_predefense_browser.py
scripts/test_predefense_accessibility.py
scripts/test_predefense_performance.py
```

Extend `scripts/test_user_management.php` for focused primary protection/existing self-credential cases, relevant OCR/item-review tests for integrated intake, Stage6 browser selectors for row allocations and existing presentation/transaction/export tests for explicit changed UI contracts. Keep original exact-centavo/security/privacy oracles. Existing financial suites remain tests of unchanged engines; do not edit them just to increase pass counts. New schema tests prove the singleton/FK/constraints and transactional readiness checks, failure on partial/foreign-schema state and unchanged existing financial/audit triggers; no new primary triggers or notification persistence are assumed. The notification test exercises the existing infrastructure/regression contract, not a new service. Separate unit/read-model/HTTP/browser/migration evidence; counts reflect meaningful executed checks only.

### Required later implementation delivery record

Create **`docs/final-predefense-oneshot-delivery.md`** only after implementation is authorized and performed; it is separate from this plan. It must include:

- **Baseline and source target:** starting branch/HEAD, dirty tracked/untracked inventory, exact current-local source identity and preserved prior work; explicitly confirm that actual local application source files were updated, not only a disposable application copy.
- **Files:** every production file changed/created, every test changed/created, migration and dependency changes; no unsupported inventory promises or omitted source expansion.
- **Databases/environments:** every actual test DB, provenance, migration application target/result, protected databases/environments not touched, working `atikha_finance` rollout explicitly unperformed unless separately authorized, and preserved participant/finished defense/practice state. Include preview target/session/source and any disclosed safe target switch.
- **Checks actually executed:** command/result/source-bound evidence for PHP/JS syntax, financial/accounting suites, authorization/privacy, focused Primary Administrator cases, deterministic overall/granular forecasts, audit-pattern alerts, existing notification regressions, OCR/manual fallback, budgets/reports, separate PDF/XLSX/CSV/Print validation, responsive browser, accessibility/keyboard/focus and performance. Mark skipped/unperformed checks plainly; do not repeat dated counts as reruns.
- **Product-quality evidence:** actual inspected 1920×1080, 1366×768 expanded, 1366×768 collapsed and 390×844 states, screenshot/artifact locations, every directly related adjacent UI defect found/fixed and its affected retest. Report any known remaining issue instead of relying on selectors/counts alone.
- **Feature status:** completed/partial/blocked for overall UI polish, Transactions, OCR, Cash Advances, Budgets, Reports, Email, Accounting Setup, Chart of Accounts, Reporting Setup, User Management, Primary Administrator, Audit Trail, Audit Alerts, Planning & Forecasting, Suggested Budget Baseline, existing Notifications, native PDF/XLSX and Management & Board oversight.
- **Limitations:** intentional deferrals, blockers, runtime/dependency prerequisites, unperformed working rollout, provider/assistive-device/browser limits and exact unresolved requirements. Do not substitute CSV/Print for a blocked native format or normal financial attention for audit anomalies.
- **Owner confirmation and Git:** section 11's short final owner smoke checklist, no outstanding new formal study; explicit commit and push status, including that both remain unperformed without separate authorization.

## 15. Implementation Sequence and Stop/Recovery Boundaries

This sequence supports one future coordinated implementation authorization across successful internal gates. **This planning report grants none.** No routine external pause is required between successful gates once implementation is explicitly authorized; real blockers are preserved and reported.

1. **P0 — Preflight/preservation:** reverify main/local/live source, full dirty inventory, exact local hashes and protected environment identities. The actual current local VS Code/XAMPP source is the implementation target; preserve and extend it, never reset it to GitHub. Snapshot only authorized synthetic runtime before any mutation. Record protected runtime hashes only where permitted, never connect to working financial records. Establish complete disposable schema/provenance and fake/unavailable providers; inspect harnesses before reuse.
2. **P1 — Security/dependency feasibility:** prepare the bounded primary schema/service changes in actual local source; apply/test only the designation component on the disposable clone, with safe existing-writer locks and no planned triggers or added self-reauthentication. Require explicit primary fixture initialization and partial-state refusal. First test maintained PDF/XLSX dependency/version and platform feasibility in isolation; do not assume a PHP-minimum increase or global changes. Required native formats that cannot safely pass remain explicit blockers, not fake capabilities or hidden runtime upgrades.
3. **P2 — Shared presentation:** friendly date helpers, exact role labels, modal component and per-screen table contracts. Preserve rail/persistence/mobile/auth and local polish. Test both roles/keyboard at all required sizes before module adoption.
4. **P3 — Daily accounting/OCR/advances:** finish existing transaction UX rather than replace it; integrate manual OCR review via existing immutable service; compact advances and validate proof/coverage/chronology. Gates require real isolated round trips and negative privacy/recovery cases.
5. **P4 — Budgets/reports/setup/export:** row allocations, exact submitted/effective distinction, worksheet/cash-flow/setup grouping, real export writers and preserved old links/formats. Validate all financial models against unchanged independent oracles and multi-page binaries before visual acceptance.
6. **P5 — Planning/Audit Alerts:** update actual local source with automatic-history deterministic overall forecast, separately qualified granular estimates/coverage, trends, explicit editable Suggested Budget Baseline and Admin-only audit-pattern alerts; deactivate normal non-OCR Gemini use. Reuse/polish existing notifications without new persistence; operational calculated conditions stay on their own surfaces. Validate no leakage, false reconciliation, missing-to-zero estimates, fraud claims, GET writes or existing retry/notification regression.
7. **P6 — Management/Email/admin integration:** role-specific oversight copy/decisions, primary user UI/reset restrictions, audit details, focused setup/user modals, Email scrolling and archive discovery removal. Preserve source-level archive compatibility and uncertainty guidance.
8. **P7 — Full product-quality verification:** Codex completes detailed relevant regressions, races, flags-off/accounting/security/privacy, full workflow/screenshots and actual visual inspection at all required viewports, keyboard/focus/modal/zoom/reduced-motion, genuine binary read-back/print and performance. Actively fix and log adjacent low-risk polish defects and retest; a merely present feature, selector pass or future formal study is not an excuse to hand back obvious related visual/interaction defects. No known unresolved blocker is hidden behind counts.
9. **P8 — Delivery and isolated preview:** prove actual local application source has the approved changes, then refresh only the verified private visual sandbox for safe validation/inspection after preservation. If the new designation schema requires a preview target switch, preserve old data/runtime and disclose it; runtime `SELECT DATABASE()` must match its allowlist. Leave guarded preview/stop/restart/reset instructions, no providers. Create `docs/final-predefense-oneshot-delivery.md` with every required field above, exact source/dirty-layer patches and reconstruction, schema/apply evidence, sanitized results, role/state/viewport screenshots, actual PDF/XLSX samples or explicit blockers, hashes/manifest/verifier, limitations and unperformed working rollout. Keep generated package private/untracked. Include only the short final owner smoke checklist, not a new formal human protocol or protected-participant continuation. Stop for external review/owner inspection. No automatic real rollout, commit or push.

Hard stops: unexplained source drift; protected/unknown DB target; DB instability or cross-schema dependency; missing/partial schema; impossible primary invariant/credential bypass; financial/privacy/integrity failure; need for unconfirmed accounting policy, new role hierarchy, external integration/provider activation, global runtime changes, destructive historical migration, or material expansion beyond this inventory. Do not repair protected environments, silently reseed data, suppress failed checks or rewrite original human observations.

Recovery remains owned-environment specific: preserve failure evidence; stop only the isolated owned process; return to its verified snapshot/local-source checkpoint when authorized. Never stop unrelated services, reset user Git work, alter the finished defense app/mailbox or refresh the participant environment. One shot means one coordinated implementation effort with internal safety gates, not forcing failed gates through. Completed prior human testing/review is preserved; remaining representative owner confirmation does not establish working deployment, real-provider acceptance, accessibility certification or Atikha/client acceptance.

**End of planning scope. The next step is external review of this document, not implementation.**
