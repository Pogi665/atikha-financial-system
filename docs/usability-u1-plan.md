# Usability U1: detailed workflow inspection and design plan

October 7, 2026. **Reviewed plan; U1-A, U1-B and U1-C delivered under separate user authorization.** See the [baseline](usability-u1-baseline.md), [screen specification](usability-u1-design.md), [U1 handoff](usability-u1-delivery.md) and [prototype delivery](usability-u1-prototype-delivery.md). The user requested this detailed plan after supplying Gemini's and ChatGPT's reviews of the [usability/completion roadmap](usability-completion-plan.md). U1 is inspection, measurement and design; production application implementation belongs to U2 and later authorized stages. U1-C's local payment prototype is complete; user screen approval precedes U2.

## 1. Outcome, authority and limits

Deliver an evidence-grounded screen specification and safe clickable design of one simple payment journey. Define navigation, fields, transaction states, unsaved-change behavior, simple/complex eligibility, component patterns, and baseline usability/performance measurements. Use that reviewed specification as the foundation for U2; U1 does not implement new production behavior.

Preserve original completion Stages 4-7, including the General Ledger, financial statements, budgets, report/budget integration, period controls and remaining receipt/export work. Their sequencing follows the roadmap. The selected accounting/evidence/privacy/retry rules remain governed by [decisions](project-decisions.md) and the approved Stage 1-3 plans/deliveries, not replaced by these screen proposals.

U1 execution, when explicitly authorized, permits documentation, standalone design artifacts and guarded disposable inspection/benchmark fixtures. It does not permit modifying production PHP/JavaScript/CSS, working configuration, working drafts/receipts/journals, applying working migrations, granting new roles, committing/pushing or starting U2. A prototype is not the deployed system. Working acceptance and restoration remain deferred as recorded in [status](project-status.md).

## 2. Requirements and reviewer disposition

- User-selected direction: fewer confusing choices, clearer task access, understandable fields and clear Save/Review/Record outcomes. The user reports all three areas as difficult; do not treat this as an observed Atikha rejection or assume client staff have the same proficiency.
- Existing client context: regularly used NGO finance software alongside funder-required tools; general ledger, linked advances and immutable adjustment history remain needed. Screenshots establish some account/report concepts, not undocumented approval policies.
- Gemini supports starting U1. Its quoted execution prompt is reviewer content, not permission to implement U2. Review already saves the draft, so a separate Save click must not be taught as a required prerequisite.
- ChatGPT's recommendations are included: explicit unsaved-change handling; exact simple-form representation rules; advance-comparison timing measured in U1 and repeated in U5.
- Detailed-plan reviews support U1-A execution. ChatGPT identified inconsistent prototype wording; the small standalone payment prototype is a required U1-C deliverable, consistent with the opening outcome. External design services remain optional. This clarification does not authorize building U1-B/U1-C during U1-A.
- The accounting contracts must remain unchanged. A later UI/service adapter can be necessary, but must not silently change financial meaning. U1 documents such needs rather than implementing them.
- Default design tool: a local, standalone HTML/CSS/JavaScript prototype with synthetic data and no backend calls. Figma or MagicPath are optional if the user chooses them. Do not upload real receipts, screenshots with private contents, account exports or sessions into a design service.

## 3. Inspected baseline and discrepancies

Planning inspection baseline: `7476316` (`stage_3`). U1-A found the newer HEAD `73f7d5b` (`Fix error`), which adds/updates the planning records without changing application source. Exact capture source is recorded in the baseline and its manifest. Preserve unrelated changes. Private configuration is not described by Git alone. Neither planning nor U1-A reverified working schema/activation.

Inspect these components during execution: `includes/nav.php`, `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, `assets/css/accounting_workspace.css`, `includes/accounting_workspace.php`, `accounting_actions.php`, `accounting_drafts.php` and its script, `cash_advances.php` and its script, advance/correction services and entry routes, `financial_records.php` and its script, and `journal_corrections.php`. Consult the relevant approved plan when a field's accounting purpose is unclear.

Fresh planning source findings:

- CRB/CDB sidebar links lead to books; New-entry links are inside the books. Do not repeat the earlier incorrect inference that those sidebar links directly lead to entry forms.
- Review performs Save before requesting review and renders a separate review panel below the form. Post renders a recorded result. Clearing the review token happens on relevant edits, on Back to editing and after saved-draft adoption.
- A `beforeunload` handler already warns when `dirty` is true. Correction-mode switching also has an unsaved-value confirmation. Do not claim that there is no unsaved-change protection; determine which navigation/context changes it covers in the real browser.
- Search query typing is intentionally excluded from financial dirty tracking. Only committing an eligible record changes the ID; leaving an uncommitted query must restore the selected label.
- Simple/advanced currently depends largely on book/opposite-side amounts. A proposed quick form needs stronger representation criteria to preserve multiple lines, split projects and evidence allocations.
- Existing Review, Save, attach/remove and return-confirmation behavior can mutate a draft or its reservations. Inspect filled/review workflows in a disposable environment, not by operating working drafts.
- Existing comparison tests use a 120-second request timeout for an advance comparison after a parallel-run timeout. That is a harness setting, not a measured normal response-time baseline.

These are source findings, not a fresh completed UX audit. Capture visual/behavioral evidence during U1 execution. Report missing screens or benchmarks explicitly rather than claiming that code inspection or older screenshots establish those results.

## 4. Checkpoint U1-A: capture the current journeys and timing baseline

### Environment and evidence setup

1. Confirm source HEAD/diff, capture tool availability and existing isolated test prerequisites. Preserve unrelated changes. Do not read or print secrets to build the report.
2. Prefer a fresh guarded complete-019/020/021 disposable fixture using the existing test/clone infrastructure, with a unique `atikha_test_stage1_u1_...` database name. Validate the infrastructure's actual name/path guards before creation; no reuse/reset of working or unrelated test databases.
3. Isolate the application copy, configuration, sessions and evidence files. Keep migrations that select a database explicitly targeted to the disposable database. Block Gemini/SMTP/other external calls; do not disable authorization. Synthetic fixture roles and authorized test login helpers belong only to the isolated copy.
4. Capture tools: use the connected browser surface if it can reach that isolated copy. An explicitly approved U1 execution may use the existing isolated Edge browser harness for captures when that surface is unavailable. State which route actually succeeded. No new browser/library installation is assumed. Name the limitation if capture is blocked; do not label an inaccessible flow audited.
5. Store raw screenshots, timing samples, fixture manifests and sensitive paths under ignored private artifacts. Public documents contain synthetic/redacted images or concise summaries only. Inspect every captured image before accepting it; retain viewport/state/task/source identity.

### Journey inventory

Inspect each journey from its visible starting point, recording required actions, optional actions, page changes and ambiguous states:

- Simple payment: locate task, enter one allocation, review, edit, record in isolation, inspect success and recover/reopen.
- Simple receipt: same pattern with the proper received-money account and credit counterpart.
- Advanced ordinary entry: multiple allocations, split project tags, a liability/withholding credit, asset/payment account and internal cash transfer. Include incomplete/invalid inputs to distinguish representability from posting eligibility.
- Draft work: save, edit afterward, leave/cancel leaving, resume, and distinguish ordinary/advance/correction draft routes. Confirm last-saved date semantics without redefining accounting dates.
- Advance lifecycle: release, linked partial liquidation, return with reviewed proof, remaining balance and timeline. Show the gross-versus-net withholding example.
- Correction: start from an eligible recorded transaction; inspect correction reason/date, replacement/reversal distinction, dependency blocker and the comparison. Use a valid corrected advance to inspect the comparison page.
- Finding records: journal/history details, CRB/CDB full-entry results and correction links. Keep the distinction between gross originating book activity and net ledger effects.
- Management: permitted books/advance/comparison/report reads; private drafts and advance evidence remain inaccessible. Do not design a new posting or approval privilege.

Use existing isolated posted fixtures for success/comparison states where suitable; any additional synthetic posting stays isolated. Do not rerun a whole suite merely to capture a screen if a safe existing fixture and smaller action suffice.

For each journey record: task and role; starting screen; visible choices; committed navigation actions; edit fields; data-entry actions; necessary evidence review/confirmation; save/review/post transitions; page changes; completion state; failures/confusion observed; and evidence limits. This avoids describing mandatory verification as wasted clicks. Establish baseline counts from observation, not source estimates or invented reductions.

### Advance-comparison timing protocol

Measure `journal_corrections.php?journal_id=<valid corrected advance target>` in the isolated environment, using a permitted GET and role-specific session. No working benchmark or real-data copying is included.

- Dataset A: a validated small complete-schema advance-correction fixture.
- Dataset B: a documented synthetic workload target of 100 advances and approximately 300 valid operations, including a valid comparison chain. Use existing services/fixture protections, record actual counts and chain length, and reconcile the fixture. This is a selected test workload, not a claim about Atikha's volume. If safe construction is unavailable, report B pending instead of substituting a tiny fixture and calling it representative.
- Use one request at a time, no parallel suites, fixed flags and server/runtime settings, and the same correct target for each role. Record server type and PHP/MariaDB versions; a PHP development-server result does not establish XAMPP Apache performance.
- For Admin and Management on each dataset, record first-load timing separately, then five repeated loads. Capture status code, time to first byte when available, total HTTP duration, response size and browser navigation-to-visible-comparison time. Report median and slowest repeated sample, failures/timeouts and response privacy. Do not call first-load "cold cache" without controlling caches.
- The timeout is only an observation ceiling. A timeout is a failed/unfinished sample, never a passing 120-second performance result. Preserve its diagnostic context.
- Specify loading/error proposals separately from measured results. A persistent delay should become a concrete follow-up for the appropriate detailed implementation stage, not merely a larger timeout. Profiling that requires production application instrumentation is outside U1.
- Repeat this protocol in U5. Working single-user timing remains separately pending if only isolated datasets/server were measured.

**U1-A output:** inspected-source/journey inventory, accepted fresh screenshots, private raw measurements, a public synthetic-data baseline summary, and a clear list of unavailable evidence. The design can progress provisionally if a named capture path is blocked; the final U1 audit gate remains pending until its required evidence is captured or the user explicitly accepts that limitation.

## 5. Checkpoint U1-B: navigation, fields and screen specifications

### Navigation specification

Propose Dashboard; Transactions; Cash Advances; Books and Reports; Communications; Administration. Separate entry actions from historical inspection. Make ordinary payment/receipt reachable directly from Transactions, with optional dashboard shortcuts; a hub must not become a compulsory extra step. Private My Drafts sits alongside entry actions, not in administration. Retain direct old URLs and active/back/breadcrumb behavior.

Admin may enter authorized workflows; Management receives only authorized read/review destinations. Preserve communications and all existing role/feature gates. "Other journal entry" retains the technical General Journal label as secondary guidance. Internal transfer stays explicit. Receipt scanning keeps its existing GJ-only handoff.

### Field inventory and decisions

For each field specify: user-facing label; source ID/payload field; requiredness; initial visibility; exact default and authority; validation; dirty/review effects; role constraints; and how it maps to the detailed journal.

Simple payment essential fields: date, payee, purpose, actual amount paid, paying cash/bank account, counterpart account and project/Organization operations. Date defaults to today in Asia/Manila under existing rules. The counterpart is **an account**, not universally an expense: payments can settle liabilities or acquire assets. Never silently classify these as expense because the UI is simplified.

Receipt changes labels to payer, amount received, receive-into cash/bank and recorded-as account. The counterpart is not universally income. Optional references and ordinary supporting documents remain available. Existing account names lead with codes secondary; no database account renaming, inferred default account or invented Chart of Accounts approval.

Define split amounts, withholding/liability lines, different line projects, advanced debit/credit, optional ordinary-GJ party, release due date/control choice, liquidation expenditure/evidence lines and bound return confirmation in their workflow-specific inventories. Required controls must not disappear into an undiscoverable optional section.

### Exact simple-form representation contract

Distinguish **quick form**, **split allocations**, and **advanced journal** as presentation modes, not new posting kinds or draft versions. Classification uses the complete persisted workflow/payload, never a submitted permission-bypass flag.

- Quick form represents an ordinary version-2 CRB/CDB entry with one generated cash line and at most one noncash counterpart allocation; no additional/opposite-side amounts; one common project including cash and counterpart tags; and no hidden structure or line-specific metadata it cannot display faithfully. Its single amount drives the matching counterpart only for a genuinely new single-allocation entry or an explicitly reviewed edit; loaded values must never be silently repaired.
- Blank/incomplete quick drafts remain representable with existing required-field validation. Missing data, invalid dates, unavailable accounts or evidence coverage warnings do not become posting eligibility merely because the UI is simple. An amount mismatch must be exposed for explicit correction rather than silently normalized.
- Split allocations can represent multiple same-side noncash counterparts with explicit amounts/project tags, retaining their client line IDs. Distinct cash-line project tags must be shown when they differ. It still exposes totals and validations.
- Advanced journal is required for ordinary GJ/transfers, opposite-side noncash postings such as withholding, mixed or unrepresentable metadata, and other structures not covered above. Dedicated version-3 advance and version-4 correction contexts retain their own forms and server routes even if their numbers look like a simple payment.
- Existing complex drafts automatically reveal the necessary mode; no collapsing multiple lines into one. Switching modes preserves exact amounts, account sides, client IDs, authoritative project tags, document reviews/allocations and workflow identity. No destructive mode switch or automatic zeroing/deletion is included. When the current structure cannot be represented, retain the detailed mode and explain why.
- One project shortcut initializes actual line tags only under its defined scope. Changing it after a split offers an explicit choice of which lines change. Presentation-only disclosure does not invalidate review; changing serialized financial/evidence contents does.
- Evidence allocations are tied to client line IDs. Do not rebuild those IDs when simplifying rows or replace a detailed allocation with a monetary total that loses its linkage.

U1 supplies examples and round-trip expectations for these conditions. U2 must verify payload equivalence and server enforcement; U1 does not implement the classifier or new adapters.

### Annotated simple-payment layout

Use synthetic example: PHP 1,000 paid to Demo Training Supplier for training materials, Organization operations, from a demo cash/bank account. Expected CDB posting is PHP 1,000 debit to the selected demo expenditure account and PHP 1,000 credit to the selected cash/bank account. All names/data in the prototype are synthetic.

**Enter details:** top area shows "Record a payment", a contextual back link and truthful saved/unsaved status. Main area groups Who/when/why with Amount/account/project. Optional reference and document areas are secondary, with clear disclosure. Primary action: Review transaction. Secondary: Save draft. Advanced/split links reveal needed controls within this workspace. Keep the complete journal available, but do not initially surround a simple entry with several competing tables/status panels.

**Review:** replace the editing view with a concise payment summary, date/payee/purpose, amount paid, selected accounts, actual project allocation, document coverage and any warnings. Label "Not recorded yet" and "Reviewing checks this entry; it is not management approval." Primary: Confirm and record payment. Secondary: Edit details. Detailed journal disclosure exposes both sides and liabilities where present; it never conceals differences or gross evidence requirements.

**Recorded:** show "Payment recorded", journal/reference/date and a concise financial summary. Distinguish recovered existing success from a new posting. Primary: View recorded transaction; secondary: Record another payment, with books/history in a smaller group. Specify the required permission-checked exact-record mechanism for U2; today's all-date book links do not identify one journal. The prototype's view uses local synthetic data, not an invented production query parameter.

**Validation/recovery:** show errors next to the affected fields and an accessible summary; retain entries and focus the first actionable problem. Show separate saving, reviewing and recording states. If posting outcome is unknown, recover the same submission; never suggest preparing another entry before checking the existing result. Review expiry requires fresh review without falsely implying that the draft was lost.

These are view states, not a requirement for three extra pages. Provide annotated desktop/laptop layouts for each state, plus receipt, split/advanced and contextual advance/correction variants. A high-risk reversal still needs its deliberate preview and reason; it is not disguised as an ordinary edit.

## 6. Unsaved-change and review-state specification

Track editing, saving, saved draft, reviewing, review ready, recording, uncertain posting, and recorded states distinctly. `dirty` means payload changes relative to the confirmed saved revision, not whether a draft merely exists. A previous successful Review saves that version; later edits immediately become unsaved and invalidate its review. Review-ready does not mean posted or management-approved.

- Controlled in-app navigation/task switches with unsaved changes offer Stay, Save draft and leave, or Leave without saving. The last option abandons local unsaved edits only; it does not delete an existing saved draft, upload, reservation or posted evidence. Use a separate existing owner-authorized discard flow for draft removal.
- Save-and-leave waits for a confirmed successful save of the current contents before navigating. Validation/conflict/upload/network failure keeps the workspace and contents visible. Do not show "Saved" merely because a request started. Pending uploads and their durable retry identities remain recoverable; saving text is not proof that an upload finished.
- Browser Back/Forward, reload and tab/window close use the existing native `beforeunload` fallback where supported. Browsers control that wording and behavior; do not promise a custom three-button modal on close or trap users in a history loop. Specify and test controlled versus native navigation separately.
- Edit details within the same workspace preserves all entries/documents and clears the review token; it is not leaving or discarding. View-only disclosures, help and search query typing do not mark the draft financially dirty; committed selection does.
- While Save/Review/Post is in flight, stop duplicate actions and avoid offering contradictory navigation that implies the result is known. An unknown Post outcome resolves through the original durable request before another posting can start.
- Recorded entries remain read-only. Resuming a posted draft or successful retry shows the same recorded state. A stale correction draft targeting an already corrected journal displays its blocker and permitted correction link; it cannot be treated as a new ordinary draft.

The prototype demonstrates these states without posting or storing real data. U2-U4 implement and verify the real behavior under their detailed approved scope.

## 7. Shared component specification

Build on `accounting_workspace.css`, shared layout/navigation and existing selectors. U1 defines annotated patterns and production mapping; it does not edit these files.

- Task header: clear title, location/back action and saved/recorded state.
- Grouped essential fields: semantic labels, logical keyboard order, searchable committed IDs, historical/unavailable-state notices and inline master dialog focus return.
- Action area: one primary progression action, visible secondary action, explicit busy state and optional disclosures; avoid adding a sticky region that covers errors or fields at laptop size.
- Summary and detailed journal: plain-language outcome first, exact accounts/projects/debits/credits accessible in details.
- Evidence summary: unattached/attached/unreviewed/partial/complete states with truthful monetary basis; privacy-redacted Management summary still retains internally calculated coverage.
- Error/status: separate unsaved, saved, validation error, review expired, operation pending, uncertain result and recorded/recovered states; announce changes accessibly without flooding screen readers.
- Advance/correction context: original identity, outstanding/dependency information, relevant next actions and clear historical versus current date basis.
- Empty/loading/failure: a useful permitted next action, retained last successful list on failure, no false zero totals and no hidden blocking reconciliation discrepancy.

Include concise glossary entries for payer, payee, account, project, draft, review, recorded/posting, cash advance, liquidation, return and correction. Keep technical labels accessible for accounting staff; explanations supplement a clear screen rather than replace it with a manual.

## 8. Checkpoint U1-C: prototype, walkthrough and handoff

Execution artifacts:

- `docs/usability-u1-design.md`: findings, evidence limits, proposed navigation, field/state/eligibility specifications, annotated layouts and component mappings.
- `docs/usability-u1-baseline.md`: measured journey counts and isolated timing summary, exact dataset/source/server conditions and pending observations. Raw private artifacts stay out of Git and public links.
- `docs/prototypes/usability-u1-payment.html`: required small standalone clickable payment prototype, with a receipt variant when useful; local synthetic state, no PHP includes, secrets, fetch/XHR, external APIs, financial endpoints, service worker, production session access or real document upload. Visible Demo label; persistence off by default and reset local to the prototype.
- `docs/usability-u1-delivery.md`: execution/source identity, captured/uncaptured workflows, prototype checks, reviewer decisions, pending user/client walkthrough, U2 adapter questions and next action.

Prefer numbered annotated layouts and the local prototype. External Figma/MagicPath creation is optional and separately chosen; it is not a prerequisite for U1 completion. A design artifact is safe to click because it cannot record a financial transaction.

Walkthrough questions: can the user find payment/receipt entry; explain required fields; identify what is saved versus unsaved; explain whether Review recorded money; return to edit; recognize a successful recording versus an uncertain result; and find the recorded details? Record whether coaching was required. User walkthrough is not substituted with assertions from automated tests or external reviewer approval. If user/Atikha is unavailable, mark their observation pending and present the artifact for asynchronous review.

Check the prototype at 1366x768 and 1920x1080, keyboard-only, long labels, no-selection/no-match, validation failures, leave/cancel/save failure, later edits after review, simulated expired review and recovered success. Simulations prove the proposed interaction, not production backend integration. No production regression suite, real posting or provider call is needed just to validate this design.

## 9. Completion and review gates

U1 source/design delivery requires: accepted fresh capture coverage or named limitations; current measured navigation/field/state inventory; timing results or explicit pending dataset/tool blockers; exact representation and unsaved-change contracts; annotated payment screens and variants; a checked safe payment prototype; component/route/service mapping; and a review package that can inform U2. Completing U1-A does not complete U1-B/U1-C or the whole U1 phase.

Track gates independently: source specification, visual capture, timing baseline, prototype verification, user walkthrough, client validation. A named blocker makes the affected gate pending; it does not count as passed. No screenshot establishes successful user understanding, and no static prototype establishes deployed accounting correctness. Approval of provisional design may be explicitly requested if missing evidence cannot be obtained; identify what remains unverified.

Before U2, obtain approval of the proposed screens and choices. Retain the existing financial/privacy contracts and record any genuine required amendment. U2 starts with the single-payment vertical slice, then receipts/shared navigation; advance and correction implementation remain U3/U4.

**Current next action:** the user approved the revised U1-C reference without another external review. The [U2 detailed plan](usability-u2-plan.md) is delivered; next is separately authorized U2-A implementation. U1-A/U1-B/U1-C design delivery and user screen approval are complete; Atikha observation remains pending. No working migration/activation/posting or whole-system acceptance follows from this design approval.