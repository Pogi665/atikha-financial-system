# Usability U2: navigation and ordinary transaction workspaces

October 7, 2026. **Detailed implementation plan; application implementation had not started when this plan was written.** The user accepted the corrected U1-C prototype and chose to proceed without another external prototype review. This accepts its screens as the design reference, not as Atikha validation or working-system acceptance. Implement one authorized checkpoint at a time.

**Subsequent disposition:** U2-A was separately authorized and delivered on October 7, 2026. See [delivery, actual checks and screens](usability-u2-delivery.md). Sections describing existing source remain dated planning findings. U2-B/C require separate authorization and remain unimplemented.

Read alongside the [roadmap](usability-completion-plan.md), [screen specification](usability-u1-design.md), [accepted prototype revision](usability-u1-prototype-revision.md), [decisions](project-decisions.md) and [current status](project-status.md).

## 1. Outcome and boundaries

Staff can start a payment or receipt directly, complete a faithfully simplified form, review it in a focused view, deliberately record it, and open that exact recorded journal. Saving, reviewing and recording have distinct meanings and truthful statuses. Complex accounting remains available and retains its complete data.

Preserve server financial validation, exact centavos, eligible accounts, authoritative line projects, owner-private multi-draft storage, immutable postings/evidence, audit transactions, evidence review, durable retry recovery, feature/schema gates and Management privacy. This is a presentation and read-adapter stage, not a replacement accounting engine.

No working migrations, seeds, financial test postings, configuration/flag changes, commits or pushes. No new draft version, autosave, compulsory navigation hub, digital approval module, inferred account/tax classification or external financial-data upload. No mobile-support claim. Use existing scoped styles; add no UI dependency. Rebuild Tailwind only if its input/classes actually change and inspect the resulting CSS.

U3 redesigns advances; U4 redesigns finding/drafts/corrections/evidence; U5 measures integrated usability. Shared-code safety checks for those existing workflows belong to U2 now, rather than waiting for their redesign. Retain original Stages 4–7 and the reports → approved budgets/report integration → period-controls dependency. U-PERF-01 remains a separate diagnostic task; this stage does not claim to fix the measured advance-comparison delay.

## 2. Current implementation and discrepancies

Inspected HEAD: `73f7d5baa227b2a8ad133c63f6d881ec30986159`, with existing uncommitted documentation, prototype and manuscript work preserved. This planning task inspects source, not the working database or current deployed browser state.

- `includes/accounting_entry_page.php` serves ordinary, advance and correction workflows. It currently uses CRB/CDB book active keys for entry forms. Review and completion sections sit below the editor/documents rather than replacing them. Header/status updates must accompany panel changes.
- `assets/js/accounting_workspace.js` has native-ID-backed searchable selectors, stable allocation `client_id`s, shared document/review handlers and a Review action that already saves. `mark()` invalidates the token but leaves operation status text behind. `posted()` changes the draft label and shows a result without consistently clearing the prior saved/unposted message or hiding ordinary editing.
- The current advanced-mode switch checks positive opposite-side amounts. It does not implement the accepted exclusion of every nonzero, negative or malformed opposite-side value. Inspect the complete payload before hiding either side.
- `read()` reads controls into state; document rendering/reading also carries review/allocation meaning. Merely hiding or removing repeated controls can overwrite hidden state. Introduce deliberate mode adapters rather than copying prototype DOM behavior wholesale.
- `accounting_actions.php` currently returns a single `error` string, not structured field errors. Field associations need a client presentation adapter; unclassified server failures remain global. Do not parse English error text to guess affected IDs.
- `workspace_review()` returns authoritative input, complete lines, party, coverage and a token. Those server results supply Review; the prototype's local synthetic calculations do not replace them.
- Recorded links currently open broad book/history lists. The proposed `journal_transaction.php` is not implemented. A new exact-journal reader must precede the new primary success link.
- `accounting_records()` already reads complete journals, computes coverage internally, redacts evidence for the viewer and attaches correction metadata. Its list query cannot simply be called with an invented journal-ID filter or used to load all history for one result.
- `correction_posted_detail()` currently calls the all-date records reader and then selects the correction's journals; `correction_metadata()` scans correction rows before selecting matching roles. The new basic exact-journal route must avoid borrowing that full-comparison path. These source findings do not establish the cause of U-PERF-01; scope any shared projection/metadata extraction to explicit requested journal IDs and regression-check its existing callers.
- `includes/nav.php` currently places entry, books, communications and administration together. CRB/CDB links open books, not entry pages. Task links must preserve those books and the existing URL/role branches.
- Production has multiple private v2/v3/v4 drafts. The demo's single-saved-snapshot resume choice is not a production requirement. A new entry never deletes or substitutes an existing draft; Resume opens an explicitly selected ID.
- `general_journal.php` retains a separate scalar OCR/receipt handoff. Keep its session/review contract and GJ-only scan routing; the ordinary shared workspace is a distinct path.

The accepted prototype is the visual/interaction target. Do not reopen the broad design or introduce three new payment pages. No fresh application audit or benchmark is claimed by this inspection.

## 3. Canonical state and presentation adapters

Keep the existing ordinary payload and server-generated cash line. Preserve `entry_date`, `reference`, `description`, `party_id`, `default_project_id`, `cash_account_id`, `cash_amount`, `cash_project_id`, `transaction_kind`, every line/client ID and every reviewed document/allocation.

Separate canonical financial state from presentation state: current step, preferred detailed view, query text, field errors, last acknowledged draft revision and operation status. Mode changes/query typing cannot change the canonical payload, dirty status or review identity. Committed financial changes invalidate the review and mark only the current local version unsaved.

Prefer small pure presentation helpers plus an ordinary-workspace controller in the existing shared script or a narrowly scoped module loaded by that template. Explicitly opt ordinary entry into the new state/representation path. Advance/correction branches retain their current dedicated validators/context until U3/U4; do not infer ordinary eligibility merely from their book. Keep existing IDs/data attributes and legacy handlers compatible where needed. Avoid two controllers writing the same controls.

### Quick mode

Ordinary v2 CRB/CDB with ordinary transaction kind, one generated cash line and one counterpart allocation; a new blank form initializes one stable counterpart ID once. A loaded draft with missing/extra rows remains faithfully detailed; do not manufacture or discard rows to obtain quick mode.

The expected counterpart side is CDB debit or CRB credit. Opposite-side values must be blank or exact zero. Any nonzero value, including negative, or malformed value requires Advanced. Cash and counterpart amounts must both be blank or parse to equal cents; loaded missing-one-side, invalid or mismatched amounts remain separate. Cash/counterpart actual projects must match. Preserve a distinct saved default project as context, not authority to overwrite line tags. Unrepresented metadata or evidence links require the detailed path.

Incomplete party/account/purpose/date and equal zero amounts may be representable but cannot post. Unavailable saved IDs remain visibly unavailable. Assets, receivables and liability settlements can be ordinary counterparts; payment does not mean Expense and receipt does not mean Income.

Quick displays one amount and one common Project. Editing the amount deliberately updates cash and its one expected counterpart side; it never changes the opposite side or an advanced entry. Editing the common project updates the cash and counterpart actual tags in this eligible mode only. Selection/mode initialization must not silently rewrite a loaded default project. Preserve existing document allocations; decreasing a line below its support must produce a review error rather than silently resizing support.

### Split and Advanced

Split displays actual cash amount and each allocation's own amount/account/project, plus cash-line project where applicable. Existing mismatches remain visible. Adding a row preserves all client IDs and document allocations, initializes only the new row and switches to Split without redistributing money.

Advanced exposes both sides, complete totals and actual project tags; GJ/internal transfers always use a detailed journal. Preserve blank, negative and malformed entered text for correction. Use exact centavo parsing for comparison/display; never coerce invalid input to zero, use binary floats to balance, or drop extra blank rows that carry IDs/links.

Switching toward Quick succeeds only after representation checks. An ineligible request explains the specific reason and leaves all values/IDs unchanged. Removing a row with support links requires deliberate handling: show which links need removal/reallocation and preserve remaining document review; do not silently discard supported amounts. Existing server validation still decides whether the final claim can post.

## 4. Enter, Review, Recorded and recovery

**Enter:** Record a payment / Record money received; clear progress indicator and one current save status. Payee/Payer, date, purpose, amount, Pay from/Receive into, counterpart account and common Project are visible in Quick. Optional reference, supporting documents and journal preview follow the primary Review transaction / secondary Save draft actions. Incomplete/unreviewed document sections expand when they block review.

Use name-first account labels with searchable codes, retained empty choices, wrapping full labels and explanatory counterpart help. Retain native ID controls, unavailable selections, eligibility, keyboard combobox semantics and inline-create focus. Choosing a new default project via inline creation must have an explicit, consistent application scope; in Quick apply it to the common actual project as a user action, while Split/Advanced retain separate tags until Apply to all is deliberately requested.

Save acknowledges the exact submitted revision. Later financial/document edits say Unsaved changes, with the prior saved time labelled as that older version. Do not clear a validation error just because an unrelated field changes. Save can retain incomplete data under existing server limits; it does not claim posting eligibility.

**Review:** the same workspace hides editing/entry-document mutation panels and presents the authoritative review response. Lead with Not recorded yet and Review is not Management approval. Show party/date/purpose, actual cash amount, counterpart meanings and actual projects. For withholding, show gross eligible costs, liability credits and cash paid together only when the balanced server lines establish that relationship; retain the complete journal underneath. Do not use such a simplified relationship for arbitrary advanced journals it cannot describe.

Truthful evidence summary uses server coverage: CDB noncash debit denominator, CRB noncash credit denominator, GJ sides separately. Optional ordinary partial/missing support remains allowed; attached documents still require manual review. Show Edit details and Confirm and record payment/receipt. Review already saves: no extra Save prerequisite.

Edit details returns retained canonical values. Returning without changes need not mark them dirty, but the final action must have a valid current review. Any committed later change makes recording unavailable until reviewed again. Handle expiry/accounting-data changes distinctly from ordinary field errors; refresh the same draft, not a fresh key.

**Recorded:** hide the editor and review mutations, update title/progress/status and show actual journal identity. Primary View recorded transaction; secondary Record another payment/receipt; smaller correctly labelled book/history links. Recovered/reopened postings show the same read-only outcome and original identity, even if that journal has since been corrected. No saved/unposted status or editable controls remain.

**Busy/uncertain:** freeze the reviewed request, draft ID/revision, submission key, canonical payload and token for posting. Disable duplicate mutation and controlled navigation while pending. Preserve controls that were already protected before the busy state. Interrupted transport, unreadable response or unclassified server failure cannot be treated as proof nothing posted.

Check recording result repeats the same frozen durable request with current authentication/CSRF, not a newly generated key or changed payload. Existing duplicate recovery handles an already-posted result even after expiry. A failed check preserves uncertainty/context. Distinguish explicit server validation/conflict/session/permission failures; do not blindly regenerate review/save state while a post result is unknown. Full-page/session recovery resumes the explicit durable draft; no financial data or credentials are stored in browser local storage. No new backend result endpoint is expected.

Recovered upload/attachment status remains separate from recording, with its existing durable upload key preserved. Production contains no demo failure controls, fake J-DEMO numbers, local synthetic record store or artificial delay.

## 5. Validation and leaving safely

Provide inline messages, a focusable linked summary, and error associations on the visible combobox/input rather than its hidden select. Bind line/document errors using stable client/document IDs; after rerender resolve them to current controls. Summary navigation opens the relevant optional group and focuses the control. Updating a field reevaluates only its relevant/dependent errors; query typing does not clear a selection error. Errors remain visible after mode changes and failed operations.

Use client checks for known form-shape problems consistent with existing server syntax/bounds. Save and the server review remain authoritative. Current unstructured server failures stay in the global summary with their safe explanation; do not infer field identity by matching error prose. Any additive structured-error metadata later found necessary must identify concrete validator fields and preserve rejection/status behavior and existing clients; it must be documented/tested as an adapter, not invented in the UI.

Controlled navigation from an ordinary workspace—sidebar, contextual Back, My drafts and task switches—offers Stay, Save draft and leave, Leave without the latest edits. Wait for a successful save before leaving; failed save preserves edits and destination intent. Leaving without saving abandons only local edits, not saved drafts, stored uploads/reservations or masters. Clicking the current task is inert. Starting a separate new entry is an explicit action; it never replaces another saved draft. Do not adopt the demo's resume-any-saved-draft behavior for production's multi-draft list.

Retain native `beforeunload` for browser Back/reload/close where supported; browsers control its wording and cannot reliably save on close. Do not create history traps or promise a custom native-close dialog. Busy/unknown posting state requires safe result recovery, not a dialog promising rollback. Inline master cancellation returns focus without changing the financial draft; already-created masters are not undone by leaving.

## 6. Exact posted transaction adapter

Add `journal_transaction.php?journal_id=<positive scalar ID>`, GET-only and read-only, with private/no-store and safe output escaping. Validate scalar ID/type/range; require a current active authenticated permitted Admin/Management actor using the established database-backed authorization contract. Return safe 400/401-or-login/403/404/503 responses as appropriate; nonexistent/unposted journals and other users' drafts expose no draft contents. Posted inspection is not restricted to the original author's private draft ownership.

Add a narrowly scoped complete-journal reader or factor the common projection/enrichment from `accounting_records()` into a shared helper. Query the one posted journal by ID with bound parameters; do not load every journal, pass an unsupported filter to the list function or duplicate coverage/lineage math. Read its header/complete ordered lines, exact totals, party/project snapshots, posting identity/time, permitted evidence summary and correction links from one consistent accounting read snapshot. Preserve current account-label limitations where historical account names were not stored; do not fabricate historic names.

Compute coverage internally before redaction, using the relevant evidence association for reused images. Sensitive advance/correction-related evidence remains hidden from Management across HTML, embedded JSON, print and downloads: no image URLs, filenames, hashes or private reviews/confirmation context. Preserve permitted aggregate/status summaries. Link Admin to existing authorized attachment URLs with the relevant journal association. Keep reader privacy and posted access effective with entry flags disabled where installed schema supports it; do not use the Admin-only workspace mutation guard as a posted-reader guard.

Keep corrected originals visible and immutable. Identify original/reversal/replacement roles and permitted correction-detail/advance links; never exclude originals from totals or add a second netting calculation. Show complete debit/credit journals, not filtered single lines. A permitted corrected original remains directly accessible. Do not invoke slow full advance comparison to render a basic journal.

Contextual Back uses fixed whitelisted destinations derived from known workflow/list context; never accept an arbitrary return URL. Include all-date originating-book/history links. No new correction authoring behavior: any existing authoring action uses persisted journal provenance and existing Stage 3/Stage 2 availability, not a submitted ordinary flag. Basic journal reading does not grant private evidence or mutation.

Use this exact route from new success, duplicate recovery and reopened posted ordinary drafts. Read failure retains the known recorded identity and offers Retry/view permitted list; it must not imply recording failed or enable another posting. No schema migration is expected.

## 7. Task navigation

Use existing sidebar/layout and scoped styles, preserving old URLs and role branches. Transactions is a group, not a mandatory hub:

- Dashboard remains the existing destination; KPI/dashboard redesign is deferred.
- Transactions: Record a payment → `cash_disbursement.php`; Record money received → `cash_receipt.php`; Other journal entry (General Journal) → `general_journal.php`; Scan receipt → `ocr_expense.php`; My drafts → `accounting_drafts.php`, where currently enabled/permitted.
- Cash advances: existing `cash_advances.php` destination and linked lifecycle actions.
- Books and reports: Find a transaction (Journal History) → `financial_records.php`; separate CRB/CDB book links retain `view=crb|cdb`; Reports → `reports.php`. Do not claim Stage 4's running-balance ledger is already delivered.
- Communications: preserve External Email, Message the Board, Management Review Queue/Board Inbox and their roles.
- Administration: preserve Accounting setup, Chart of Accounts, User management and Audit trail with existing gates.

Entry active keys distinguish payment/receipt from book viewing. Existing advances/corrections keep contextual active locations and URLs. Management sees authorized reads/communications without entry/private drafts/setup privileges. Use accessible group labels and active state; keep destinations reachable without forced accordion clicks. No added mobile navigation shell in this stage.

## 8. Reviewable implementation checkpoints

**U2-A — payment vertical slice and exact result:** implement the shared representation/state adapters behind an explicit ordinary-CDB branch, payment Enter/Review/Recorded/error/navigation behavior, protected exact-journal reader/page and minimal direct payment navigation. Preserve existing CRB/GJ/v3/v4 presentation while checking affected shared behavior. Deliver end-to-end isolated payment evidence before extending the redesign.

**U2-B — receipt and ordinary General Journal integration:** reuse proven adapters for CRB, implement payer/received/counterpart semantics and coverage, extend focused state/error/recovery handling to ordinary GJ/internal transfers without Quick collapse. Preserve the separate scalar OCR branch and GJ-only handoff. Check ordinary documents/inline masters/complex restored drafts and affected advance/correction paths again where touched.

**U2-C — complete navigation and integration handoff:** apply the final task groups/active keys to Admin and Management, preserving all destinations/gates. Update changed browser suites to use visible controls/new step states. Run the affected integrated-schema matrix, final captures and delivery/status/task guidance. No automatic working migration/activation or synthetic working acceptance.

Each checkpoint needs its own user implementation instruction; a favorable external review does not authorize the next one. U2-A is the next concrete implementation scope. Use the approved prototype/local screenshots and existing private MagicPath reference as design aids, not generated production code. Use relevant available plugin tools where they help; keep content synthetic and do not publish or upload client evidence. No new design-service project is required.

## 9. Focused verification and evidence

All financial tests use a newly guarded disposable database with complete 019/020/021 installed before operational cases. Isolate application config, sessions and evidence; never copy working config/receipts or use working drafts. Reuse the complete-schema fixture contract in `scripts/test_stage3_checkpoint5.php` and existing `--fixture` browser harnesses. Test-run fixtures with their explicit partial-schema cases are reported separately; do not sum old stage counts into a new coverage claim.

Add an isolated U2 browser/HTTP runner and focused pure adapter checks where useful. Existing backend regression suites remain appropriate for the touched readers/contracts; update browser selectors/state expectations without weakening financial assertions. Final commands, guarded target names, exact tested source manifest, suite inventory/exclusions and actual counts go in `docs/usability-u2-delivery.md` after execution, not as invented results now.

Required U2-A payment cases:

- Blank/new payment, truthful missing-field errors and saved incomplete draft; linked visible focus, per-error persistence and query cancellation. Save failure preserves edits.
- ₱1,000 expense debit / cash credit; equipment asset debit and liability-settlement debit remain their selected meanings. Review saves without posting; Confirm creates exactly one journal and complete evidence/audit associations.
- Loaded ₱1,000 cash versus ₱900 counterpart; missing-one-side, extra rows, distinct projects, zero/negative/malformed opposite-side text; no automatic repair, hidden invalid amount or ID/allocation loss.
- Split ₱600/₱400; withholding expense ₱4,500 / liability ₱500 / cash ₱4,000. Review summary/complete journal/support denominator agree; ordinary partial evidence stays truthful.
- Actual-line/common-project edits and inline master creation preserve IDs/reviews; mode round trips preserve raw canonical values except deliberate financial edits. Decreased expenditure with larger support rejects without resizing support.
- Post, reopen, repeated click, response lost after commit, matching recovery with same key/payload, changed-content conflict, expired review, session reauthentication and original-request replay after correction. Known result opens original exact journal; unknown result never starts a fresh request identity.
- Controlled clean/dirty/current-task navigation, save-and-leave failure/success, leaving without latest edits, multiple existing private drafts unchanged. Busy/read-only states and native-close limitations.

Required exact-reader cases:

- Admin and Management permitted posted access; inactive actor, unauthenticated/unsupported role, malformed/array/overflow ID, missing/unposted record and GET-only behavior.
- Full lines independent of list/date/account filters, historical party/project snapshots, inactive account history, legacy truthful evidence, corrected original/reversal/replacement access.
- Management summaries without sensitive documents/private context; relevant association on reused evidence; no leakage in HTML/JSON/print/download. Repeat with accounting UI flags off and complete installed schema.
- Read failures do not discard known success; URLs and fixed Back destinations are correct; reader performs no financial/audit/draft writes and avoids all-history loads.

U2-B adds CRB cash debit/non-income counterpart credit, GJ separate side coverage and two-cash-account transfer checks, plus scalar OCR/manual fallback regression. U2-C adds all navigation destinations, role/flag combinations, full-entry CRB/CDB search/totals and retained Journal History line filtering. When reader projection changes, run affected Financial Records/evidence/privacy checks immediately.

Affected v3/v4 safety checks during each shared-code checkpoint: advance release/liquidation/return draft/review/post/recovery and bound confirmation, v4 reverse-only/replacement/stale-target handling, upload/remove/discard/reuse, protected controls, historical balance/reconciliation and Management redaction. Their layout remains existing until U3/U4, but regressions cannot be deferred to U5.

Capture actual isolated blank/filled/invalid/Review/Recorded/recovered/exact-record/withholding/split/long-label screens at 1366×768 and 1920×1080. Verify visible keyboard selection/focus, summary links, primary actions in the simple first viewport, wrapped labels and no horizontal overflow. Use deterministic synthetic data; visually inspect final captures. Record actual task/action counts relative to U1 without inventing client task success. Automated assertions are not Atikha observation.

Run changed PHP/JavaScript/Python syntax and whitespace checks. Inspect source preservation for unrelated files. No working browser acceptance, real provider extraction, performance fix, cross-browser/device pass or restoration rehearsal is claimed by isolated U2 checks.

## 10. Completion and handoff

U2 is source-complete when ordinary payment/receipt/GJ/transfer workspaces, navigation, exact-record access and their focused affected checks pass on the integrated disposable schema; final screens follow the accepted reference; and delivery/status identify source, scope, real verification and limitations. User/client walkthrough and working acceptance remain separately recorded.

After U2-A, record its changed components, actual tests/screens, remaining failures and U2-B boundary. After U2-C, record U2 completion and plan U3 under its separate scope. Keep U-PERF-01, deferred acceptance/recovery and original Stages 4–7 visible. Do not start later work merely because this document lists it.
