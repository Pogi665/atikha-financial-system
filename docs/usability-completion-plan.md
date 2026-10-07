# Atikha usability and remaining system completion plan

October 7, 2026. **Proposed staged roadmap for review.** This planning task authorizes documentation only. Each usability stage and remaining completion stage needs its own detailed plan, review and implementation authorization. No application change, migration, activation, financial posting, commit or push is authorized by this document.

## 1. Goal and evidence

Make everyday accounting work easier to find, understand and finish while retaining the existing accounting engine and completing the original system roadmap. The user reports difficulty finding pages, understanding fields, navigating the number of controls, and interpreting Review/Post. They explicitly want to retain the original completion stages. These are user-reported usability concerns and requested design goals, not measured client task results or new Atikha accounting policies.

Read alongside [client context](client-context.md), [decisions](project-decisions.md), [current status](project-status.md), and the approved plans/deliveries for [Stage 1](stage1-delivery.md), [Stage 2](stage2-plan.md), and [Stage 3](stage3-plan.md). The downloaded original `plan_complete.md` was located and inspected during this task; sections below retain its Stage 4-7 identities and account for subsequently reviewed dependency changes.

Stages 1-3 are source-implemented and deployed as recorded in status. Stage 3 migration 021 is verified complete and its configuration flag is enabled. Working browser acceptance, current-data restoration and protected-file byte verification remain deferred, not passed. Automated isolated implementation checks remain required. Period closing from the earliest whole-system proposal was deliberately deferred until report prerequisites exist; it was not implemented as part of the approved Stage 3 correction scope.

Current inspection baseline: repository HEAD `7476316` (`stage_3`), clean tracked working tree before this documentation task. Earlier delivery fingerprints and uncommitted-state claims describe their dated runs, not this new baseline. Local private configuration is outside Git. This task did not query or change the working database.

### Source findings and inspection limits

- `includes/nav.php` puts daily entry, historical books, communication, drafts and administrative maintenance at the same sidebar level. CRB/CDB links currently open `financial_records.php?view=crb|cdb`, not entry forms. The entry links appear inside those books. The earlier brainstorming inference that those sidebar links directly open forms is superseded by this source inspection.
- `includes/accounting_entry_page.php` shares a template across ordinary entries, advances and corrections. It exposes default project, cash-line project and allocation-line projects, plus bookkeeping/evidence controls. Those fields have different contracts; consolidation needs explicit handling of split projects.
- `assets/js/accounting_workspace.js` already saves the draft as part of Review. Staff need not click Save draft immediately before Review. Review renders another panel below the existing entry/document sections; Post then renders a result. The proposed simplification should clarify those states without adding a page for every step.
- Cash advances already have a linked detail/register and server-generated control lines. The usability work should improve presentation and access rather than replace the lifecycle calculations.
- Draft resume/discard dispatch already distinguishes ordinary, advance and correction workflows. A simpler list must preserve that routing and owner privacy.
- `financial_records.php` currently titles the main line-history view "Journal History / General Ledger". It is not the dedicated account ledger with opening/running/ending balances promised by original Stage 4.
- `reports.php` currently exposes Trial Balance and frozen revisions. The other original Stage 5 statements and complete bundle remain later deliverables.

These are source observations. No current visual/end-to-end UX audit was completed: the browser inventory exposed no connected surface. Supplied screenshots and user experience inform the brief, but are not new audit captures. U1 must collect fresh visual evidence and measure current journeys before claiming improvement. Source inspection does not establish keyboard accessibility, layout quality or task-completion speed.

## 2. Design boundaries

1. Lead with tasks: record money received, record a payment, manage an advance, find a transaction, and view books/reports. Retain familiar accounting names as secondary labels and in the detailed journal.
2. Preserve Admin posting permissions and Management's permitted read/review surfaces. A simpler menu does not grant mutation or private-evidence access. Do not introduce new roles or management approval stages.
3. Keep exact-centavo balance checks, durable submission identity, fresh review rules, server-generated advance controls, transactional audit/history, immutable posted data, and Management redaction. Change presentation and necessary adapters around the existing services, not their financial meaning.
4. Keep explicit final posting confirmation. Saving a draft, attaching/scanning a document and reviewing an entry never post it. Ordinary documents remain optional; liquidation needs full gross expenditure coverage; returns need reviewed proof and bound confirmation.
5. Reduce navigation and repeated decisions before removing controls. Advanced options stay available and discoverable; a switch never discards hidden values or silently changes accounts, sides, projects or evidence.
6. No silent account/tax inference. Use today in Asia/Manila as already permitted, existing eligible selectors, and explicit choices. Do not guess balances, donor rules, evidence authenticity, or opening account values.
7. Keep Communications access, including External Email and board/message functions, under an appropriate secondary group without removing them or altering their behavior. Administration remains role-gated. Preserve old URLs and direct links.
8. Keep scanning's selected General Journal handoff. A new scan-to-payment or scan-to-advance route requires a separately reviewed choice; navigation consolidation does not change it.
9. No automatic saving is included initially. Review already saves; retain a clearly labelled Save draft action and honest unsaved/saved status. Autosave, receipt batch imports, payroll/tax automation, bank integrations and mobile-app development are deferred.
10. No migration or working configuration change is expected for the usability stages. If a genuine schema/service limitation appears, document the smallest amendment before expanding implementation scope. Do not rerun 015/019/020/021.

## 3. Proposed common journey

Use one entry workspace with distinct **Enter details**, **Review**, and **Recorded** states. These are states, not three mandatory new pages. Editing and reviewing must not display competing primary actions simultaneously.

- Enter details: show the task title, necessary fields, optional document area, saved status, primary Review transaction and secondary Save draft. Offer advanced allocations when required.
- Review: show date, party, purpose, cash/payment amount where applicable, accounts/categories, project allocations, and truthful evidence/advance information. Explain "Not recorded yet" and that this is an accounting check, not management approval. Make Edit details available; expose the complete debit/credit journal without hiding an imbalance or liability.
- Confirm and record: the final deliberate posting action uses the current server review and durable submission identity. Editing relevant contents invalidates review. Busy/uncertain outcomes do not offer a second unrelated submission; recover the existing result through the current retry contract.
- Recorded: show an unambiguous success, journal reference and relevant advance/correction identity. Offer one primary next action and a small secondary group. Existing recovery or reopening shows the same recorded state with controls read-only.

For a normal receipt/payment, the proposed navigation target is one direct action from the Transactions destination, with an optional direct dashboard shortcut. After details are entered, Review and Confirm are the two required progression actions. Document review and advanced accounting actions are counted separately; do not promise every workflow takes only two clicks.

A typical single-project form shows one Project selector. Internally it initializes the relevant line tags. Existing mixed-project drafts automatically reveal split allocations and preserve every tag. Changing the common project after a split requires an explicit scope choice; merely hiding line-project controls cannot overwrite them. The server remains authoritative.

## 4. Usability stages U1-U5

These stages are a supplement to the original numbered completion stages, not their replacements. Implement one reviewed stage at a time, with focused verification at each delivery. Layout proposals below remain proposed until their detailed stage plan is approved.

### U1 — Workflow inspection and screen specification

**Outcome:** a reviewable, evidence-grounded design before changing application behavior.

- Capture fresh read-only entry/navigation states; use an existing isolated fixture or a separately authorized disposable environment for filled/review/posted journeys. Do not create working drafts or postings as audit fixtures.
- Walk through ordinary payment, receipt, draft resume, advance release/liquidation/return, transaction lookup and correction. Identify missing evidence when a journey cannot be captured. Inspect Admin and Management separately.
- Record current navigation actions, page changes, initially visible decisions, required versus optional actions, and places where the state is unclear. Do not count mandatory evidence verification as avoidable navigation.
- Prepare annotated screen layouts for one simple payment: entry, review, validation/recovery and recorded states. Produce the navigation map, field inventory, plain-language glossary and the receipt/advanced-GJ variants. A prototype uses clearly synthetic data and calls no financial API.
- Select a small shared presentation pattern: labels, primary/secondary buttons, progress/status, document summaries, contextual help, keyboard behavior and empty/error states. Build on current scoped styles rather than replacing the application framework.
- Specify unsaved-change handling for controlled navigation/task switches and native browser close/back behavior; distinguish Review's saved revision from later edits. Define exact quick/split/advanced representation conditions and preserve complete complex-draft contents.
- Measure the advance-comparison timing baseline during U1 under documented isolated single-user conditions, then repeat it in U5. A larger test timeout is not a measured performance result.
- Proposed navigation destinations: Dashboard; Transactions (entry, recorded lookup, private My Drafts); Cash Advances; Books and Reports; Communications; Administration. Management sees only authorized destinations. A hub must shorten routes rather than add an obligatory intermediate page.

**Gate:** user can explain the payment flow and the differences between Save, Review and Record from the proposed screens. Reviewers receive the annotated layouts, inspected-source findings, state contracts and outstanding questions. No application implementation in U1.

### U2 — Navigation and ordinary transaction workspaces

**Prerequisite:** approved U1 layouts and the detailed U2 implementation plan.

- Add direct task actions and consistent active/breadcrumb/back navigation. Separate entering transactions from inspecting books; retain old endpoint URLs and feature/role guards.
- Implement the common entry/review/recorded pattern for receipt and payment, using payment as the initial vertical slice. Use payer/payee and purpose labels people understand; show familiar account names with codes secondary, without renaming stored accounts.
- A single allocation shares the entered receipt/payment amount; additional allocations require amounts whose total is validated. Editing an advanced draft must not collapse its lines or overwrite opposite-side amounts. Validate simple/advanced transitions on both UI and server paths.
- Keep required controls visible; disclose optional references, split project tags, extra allocation lines and detailed debit/credit fields. Advanced GJ, liabilities, asset payments and transfers remain reachable and accurate.
- Integrate searchable selectors and inline master creation into the layout. Preserve authoritative IDs, unavailable draft selections, eligibility, focus restoration, and review invalidation on committed changes.
- Save draft remains optional before Review because Review saves already. Improve saved/unsaved/busy/review-expired/error messages; recover retained inputs and uploads.
- Define an authorized exact-record view/navigation mechanism in the detailed plan if the success screen promises "View this transaction". Existing all-date book links do not target one journal; do not invent unsupported query parameters.

**Gate:** isolated ordinary payment and receipt can be entered, reviewed, recorded, reopened and recovered; advanced/split-project/GJ/transfer cases retain their semantics. User can distinguish unrecorded draft from posted record. Accessibility/focus and laptop/desktop captures pass the approved layout checks.

### U3 — Cash advances as a connected workflow

**Prerequisite:** stable U2 shared presentation and approved detailed U3 plan.

- Advance list emphasizes employee, purpose, project, due date, outstanding amount and settlement/overdue status. Retain historical/as-of filters and clear applied scope. Move full reconciliation details into a clearly labelled secondary area, but always expose any blocking discrepancy prominently.
- One advance detail supplies Release, Liquidated, Returned and Outstanding summaries, timeline and eligible next actions. Liquidation and return start with the original advance already linked.
- Release asks for employee, purpose, date, cash/bank account, amount, project and due date. The recorded control account remains visible in the summary. Multiple eligible controls require an explicit choice; a shortcut must not silently choose a different advance arrangement.
- Liquidation presents claimed expenditure/asset lines and reviewed documents together. Keep gross supported costs, liabilities and net advance reduction separate. A simple PHP 8,000 supported liquidation on PHP 10,000 leaves PHP 2,000 outstanding; a PHP 4,500 cost with PHP 500 liability reduces the advance by PHP 4,000 and requires PHP 4,500 support.
- Return presents receiving account, amount, reviewed proof and its bound confirmation in context. Changing amount/account/date/document context requires fresh confirmation as defined by Stage 2. Combining visual steps cannot remove the confirmation contract.
- Preserve one release per advance, current/historical balance protection, partial settlements, inactive-reference exceptions, return proof, overdue history, and per-account reconciliation independent of display filters.

**Gate:** release, partial/full liquidation and unused-money return are understandable in isolated walkthroughs; unsupported/excess claims fail without changing the advance; accounting output matches the established Stage 2/3 examples. Admin/Management surfaces retain their distinct privacy.

### U4 — Finding work, corrections and evidence clarity

**Prerequisite:** approved detailed U4 plan; shared presentation from U2/U3.

- Make My Drafts accessible beside transaction actions. Show familiar workflow labels and last-saved status; keep Asia/Manila last-saved filters, owner-only results and correct version 2/3/4 dispatch. Preserve input/list state on failure and stale-response protection.
- Make Find transaction a clear destination with complete-entry details, payer/payee/purpose/date/reference and permitted documents. Keep CRB/CDB full-journal search/totals and gross activity explanation; Journal History keeps its specified line-filtering behavior.
- Start correction from the actual transaction. Offer plain descriptions for "Correct a recorded transaction" and "Reverse an entry recorded by mistake" without disguising the accounting operation. Real cash returned uses the return workflow, not cancellation.
- Review shows original, proposed replacement where applicable and the corrected effect; exact reversal and full journals remain inspectable. Present required reason/date, specific dependency blockers, original/replacement advance identities, evidence reuse/fresh review, and stale-draft status clearly.
- Place documents beside what they support with one clear review flow. Keep reviewed monetary amounts, allocations, excluded reasons and uncertainty visible. Never label an unreviewed upload Verified or imply AI confirms authenticity.
- Keep Scan Receipt's existing upload/manual fallback and GJ-only handoff. Improve consistency with the new labels/navigation where needed; broader itemized OCR/export functionality remains original Stage 7.

**Gate:** isolated users can resume the correct draft, find a posted transaction, understand and review a correction, and complete manual evidence review without losing accounting context. All lineage/privacy/rollback/retry protections remain covered by affected regression suites. No full audit-compliance claim is made from UI simplification.

### U5 — Integrated usability verification and handoff

**Prerequisite:** U2-U4 delivered; a reviewed detailed U5 acceptance plan.

- Compare against U1's measured baseline, using the same tasks and document requirements. Report primary decisions, navigation actions and page changes separately from necessary data entry/review. No invented percentage reduction or arbitrary universal click target.
- Use plausible synthetic NGO fixtures: training payment, funds received, an employee advance, liquidation, return and a correction. Define expected journals/balances and visible states before testing.
- Ask the user to walk through the safe prototype/disposable app: can they find the starting action, explain each required field, distinguish a saved draft/review/posted result, and finish without coaching? Record assistance/errors honestly. Atikha validation follows when available; do not substitute reviewer praise for observed client use.
- Verify visible keyboard selectors, focus, validation recovery, long labels, no-result/slow/failure states, owner privacy, Management redaction, retry recovery and posted read-only behavior at 1366x768 and 1920x1080. Measure advance comparison timing with representative isolated data under normal single-user conditions.
- Run focused backend/browser regressions for changed contracts, with the full integrated schema. Broaden only for shared changes/failures or unresolved concerns. Keep source, isolated evidence, user walkthrough and working acceptance separate.
- Deliver a short task guide and a prepared demonstration in an isolated environment, along with source/verification/deployment evidence and remaining checks. Avoid making a long manual the remedy for an unclear screen.

**Gate:** existing daily workflows meet the reviewed usability criteria and retain financial/privacy behavior. Working acceptance and restoration can remain explicitly deferred at the user's direction. No synthetic working postings. U5 checks current capabilities; later reporting/budget screens still require their own usability checks.

## 5. Resume original system completion stages

### Original Stage 4 — Cash books and General Ledger

Retain the identity and outcome. Inspect existing complete CRB/CDB work before implementing only the missing pieces. Provide a dedicated account/period ledger with opening balance, dated movements, running and ending balances, inactive-account history and full-journal drilldown. Calculate balances before search/pagination and order deterministically. End balances must reconcile with Trial Balance. Apply the U-stage navigation, filter, explanation and detail patterns; do not label line history as a completed running-balance ledger.

### Original Stage 5 — Financial reports and review

First implement the core monthly worksheet and statements, with reviewed mappings and worked calculations: Trial Balance, period income/expense, financial position, indirect cash flows, project activity and advance aging. Distinguish period movements from cumulative balances, noncash items from cash flows, and cash balances from unrestricted available funds. Resolve remaining account/year/opening-balance assumptions explicitly; no invented balancing plug or inferred historic grant treatment.

Build readable report selection, live-versus-frozen status, warnings and print/review interfaces. Preserve existing frozen Trial Balance revisions. **Budget-dependent reports and complete bundles that include budget revisions wait for Stage 6.** Stage 5's detailed plan must separate core reports from that follow-up, rather than declaring a budget bundle complete early.

### Original Stage 6 — Monthly budgets and dashboard completion

Implement the selected monthly scope/project/account budget revisions and approval behavior, with distinct operations/project buckets, journal-derived recognized spending, immutable approved versions and truthful legacy status. Preserve advances as assets until liquidation; releases do not consume expense budgets. Use the simplified entry/review/status conventions for budgets and role-specific dashboard tasks. Preserve advisory forecasting and disclose insufficient history.

Then complete the **Stage 5 report/budget integration follow-up**: budget-versus-actual, approved-budget snapshots, complete frozen report bundles and review. Report-dependent closing/reopening must come after the necessary reports and atomic source/review coordination. Retain reopening/downstream-staleness rules in its detailed plan. This follows the original reviewers' dependency findings; the closing interface is not a missing Stage 3 correction implementation.

### Original Stage 7 — Receipt usability and export consistency

Audit what is already delivered: multiple documents, manual review, protected evidence, scanning failure guidance and correction reuse. Complete the remaining itemized OCR/manual review/export work without rewriting those contracts. Keep the GJ-only scan handoff unless separately amended. Exports cover all applied matching records or the exact frozen revision, preserve monetary precision and neutralize formula injection. AI extraction remains optional and fallible; live provider verification stays distinct from doubled tests.

### Final system integration and working acceptance

Run a reviewed complete task matrix across daily entries, advances, corrections, ledger, financial statements, budgets, period controls, evidence, exports and Management review. Include the previously deferred working checks, protected backup/recovery evidence, permission coverage, representative timing, and browser/printing limits. Use legitimate working transactions only when separately authorized; demonstrations and destructive/race cases stay isolated. Describe implementation, deployment and acceptance separately before declaring the whole system complete.

## 6. Execution order and review package

Proposed order: **U1 → U2 → U3 → U4 → U5 → original Stage 4 → Stage 5 core → Stage 6 → Stage 5 integration/period controls → Stage 7 → final integration.** Detailed plans may split a stage into smaller checkpoints; none silently authorizes the next. Existing low-impact presentation improvements are inspected for reuse rather than repeated.

For every detailed stage, publish: problem and before/after journey; annotated layouts; role/feature matrix; essential/advanced field and state behavior; reusable components and touched files; unchanged accounting/privacy contracts; backend adapter needs; meaningful isolated/browser checks; migration/configuration implications; known limits; deployment and rollback guidance; and a delivery criterion a reviewer can assess.

Unresolved implementation choices belong in the relevant detailed stage: the exact hub layout, advanced-section disclosure, common-versus-split project transitions, supported exact-record navigation, confirmation placement and the representative performance dataset. Ask focused questions when those materially affect the workflow; do not reopen the selected accounting controls or turn a proposed layout into an asserted client requirement.

**Next task:** review the [detailed U1 inspection/design plan](usability-u1-plan.md), then separately authorize U1 execution. No U-stage application implementation has started, no current UX audit has passed, and no future completion stage has been abandoned.
