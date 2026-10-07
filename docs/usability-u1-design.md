# Usability U1-B: screen and interaction specification

October 7, 2026. **U1-B specification received favorable Gemini/ChatGPT reviews; targeted wording amendments incorporated. Production changes are not implemented.** The user authorized U1-B after Gemini's and ChatGPT's reviews of the [U1-A baseline](usability-u1-baseline.md), its screenshots and timing CSV. The subsequent U1-B reviews support U1-C without another planning round; reviewer prompts do not themselves authorize building it. U1-C's required clickable payment prototype remains a separate checkpoint. Use this document with the [U1 plan](usability-u1-plan.md), [roadmap](usability-completion-plan.md), [client context](client-context.md) and [decisions](project-decisions.md).

## 1. Outcome and evidence

**Subsequent U1-C handoff:** the user separately authorized the [local payment prototype](prototypes/usability-u1-payment.html). Its [delivery](usability-u1-prototype-delivery.md) records completed isolated interaction checks and screenshots. This implements the design as a synthetic mockup only; production adapters/readers and user/Atikha acceptance remain pending.

Staff should reach a daily task directly, enter only the fields needed to represent it, understand whether it is saved or recorded, and find the resulting transaction. Preserve the existing accounting, document, privacy and recovery controls. These are proposed interface contracts for review, not confirmed Atikha policies or evidence that the new interface is already easier.

The selected visual foundation is the existing dark sidebar, light workspace, white cards, slate text and green primary action. Retain existing fonts, account names and icons. Concentrate on repetition and state clarity rather than inventing a new visual identity. The [empty payment](images/usability-u1-baseline/payment-empty-1366.png), [review](images/usability-u1-baseline/payment-review-1366.png) and [recorded result](images/usability-u1-baseline/payment-recorded-1920.png) ground the changes below.

Verified source at U1-B start: HEAD `73f7d5baa227b2a8ad133c63f6d881ec30986159`; earlier U1-A documentation/evidence changes are uncommitted and preserved. Reinspected shared entry template/script, workspace payload service, navigation, Financial Records detail renderer, coverage and evidence projections. Application files still match the U1-A source manifest; that preservation is checked at handoff. No working schema, configuration or client-data queries are part of U1-B.

Baseline problems and responses:

- Entry access requires opening an accounting book first. Provide direct task links without a mandatory hub.
- Simple payment exposes two amount fields and three projects. Use one amount/common project only under the representation rules in section 4.
- Review is appended below the editable form. Display one focused step at a time within the workspace.
- Posted result retains the title New cash payment and a stale unposted message. Replace the entire entry view with a recorded result and render all status from one current state.
- Save/Review labels leave stale saved information after edits. Tie saved status to the acknowledged revision, independently of whether a draft exists.
- Advance comparison response delay reproduces in isolation. Track response-path diagnosis separately; no design or timeout change establishes a fix.

Reviewer evidence: Gemini supports specification/prototype work; its two supplied responses are identical. ChatGPT's later review inspected all nine images and recalculated four timing medians. Neither review exercised the application or establishes server-side acceptance. The reported return Journal View was not among those nine published images; U1-B's narrower privacy clarification is supported by current source inspection, not attributed to an unseen reviewer screenshot.

## 2. Navigation and roles

Proposed sidebar hierarchy, preserving existing destinations and URLs:

- **Dashboard:** existing dashboard. Optional Record a payment and Record money received shortcuts lead straight to the forms; redesigning financial KPIs is outside this slice.
- **Transactions:** Record a payment → `cash_disbursement.php`; Record money received → `cash_receipt.php`; Other journal entry (General Journal) → `general_journal.php`; Scan receipt → `ocr_expense.php`; My drafts → `accounting_drafts.php`.
- **Cash advances:** `cash_advances.php`, with its existing release action and linked detail/settlement actions. Liquidation and return start from the selected advance, never an unlinked generic payment.
- **Books and reports:** Find a transaction (Journal History) → `financial_records.php`; Cash Receipts Book → `financial_records.php?view=crb`; Cash Disbursements Book → `financial_records.php?view=cdb`; Reports → `reports.php`. Journal History is not relabelled as the dedicated Stage 4 ledger.
- **Communications:** retain External Email, board messages, Management review queue and board inbox where currently permitted. Grouping does not alter messages, roles or approvals.
- **Administration:** Accounting setup, Chart of Accounts, User management and Audit trail retain existing authorization.

Transactions is a section with direct links, not a compulsory extra page. Its active location reflects the actual workflow, with book identity as secondary text. Keep old bookmarks, direct URLs, correction links and feature/schema gates. Stage 3 correction authoring starts from a permitted transaction detail rather than an unexplained top-level task.

Admin sees only enabled entry/setup actions. Management receives permitted dashboard, books/reports, advance reads and communications, with no entry, private-draft, setup or correction-authoring privilege. A future exact-record view checks authorization on the server. A hidden menu item is never an access control. Missing prerequisite or permission produces an explanatory permitted destination, not a partially enabled form.

## 3. Field contracts

All mappings below describe existing IDs and payload meaning; production adapters are later work. Requiredness applies to Review/Record, while Save draft may retain incomplete data. Labels include optional explicitly; placeholders never replace labels. Existing server validation remains authoritative.

### Ordinary payment and receipt

1. **Accounting date:** `aw-date` → `entry_date`; required; today in Asia/Manila for a new entry. Preserve a loaded date, existing real-date bounds and no-future rules. Never default a resumed transaction back to today. Show a field error with its entered value retained.
2. **Payee / Payer:** `aw-party` → `party_id`; required for CDB/CRB. Search eligible names/codes; no automatic first selection. Add payee/payer opens the existing inline party dialog, with person/organization meaning retained. GJ instead labels Party (optional) and permits No party.
3. **Purpose:** `aw-purpose` → `description`; required, maximum 2,000 characters. Example hint: Training materials for the community workshop. Error: Enter the purpose of this payment. The server's generic description error must be associated with this field through a later adapter, not changed into different validation.
4. **Amount paid / Amount received (PHP):** `aw-cash-amount` → `cash_amount`; required positive exact-centavo amount. Blank initial value; no invented amount. In a representable quick entry, the same amount explicitly drives its one counterpart as described in section 4. Split/advanced amounts remain independent. Invalid input remains visible, not rounded or interpreted as zero.
5. **Pay from / Receive into:** `aw-cash-account` → `cash_account_id`; required eligible cash/bank account. Names first, codes secondary and searchable; no inferred bank, cash account or account classification.
6. **Record against account:** allocation select retains `data-field="account_id"` and its existing `aw-line-<client_id>-account` identity. Required eligible noncash counterpart in ordinary quick CRB/CDB. Help: Choose what this payment records, such as a cost, asset purchase or liability settlement. Receipt help permits appropriate receivables/liabilities and other eligible counterpart meanings, not only income.
7. **Project:** common selector presents Organization operations by default for a new quick entry, meaning no project tag. It deliberately initializes `cash_project_id` and the counterpart's `fund_project_id`; `default_project_id` remains a shortcut/context, not the ledger authority. Loaded tags and a different saved default are preserved. Show cash-line and individual line projects whenever they differ. No assumption of a balanced independent project ledger.
8. **Reference (optional):** `aw-reference` → `reference`; maximum 100 characters; under Other details. Preserve an existing reference when collapsed and show it on Review.
9. **Supporting documents (optional for ordinary entries):** retain the existing attach/upload/remove/review contracts. Initially a compact disclosure: No documents attached. Any attached documents automatically expand with their review requirements; a blocking unreviewed item cannot be hidden. Uploading does not run OCR or record money.

Changes to serialized fields mark the draft unsaved and invalidate its reviewed token. Query typing, opening disclosures, focus changes and viewing a journal do not change IDs/payload or dirty state. Committing a different selection does. Native selectors remain the authoritative IDs, with existing unavailable selections visible and server rejection preserved. Inline creation returns focus to the originating selector; do not move or rename underlying controls in a way that loses their bindings.

### Split and advanced ordinary entry

Allocation rows retain `client_id`, `account_id`, `fund_project_id`, `debit_amount` and `credit_amount`; document allocations continue to reference those client IDs. Split form shows Amount for the book's counterpart side, account and actual line project. Show cash-line project if it differs. Advanced shows both Debit and Credit with explicit sides and complete totals. Existing GJ/transfer `transaction_kind` stays explicit. Do not silently drop blank rows with IDs/evidence or extra metadata during loading.

### Advance fields and evidence

Release retains employee `party_id`, purpose/date, cash account/amount, originating project, `control_account_id` and `due_date`; required controls stay visible. Multiple eligible control accounts need an explicit choice. Do not infer that two client account names are interchangeable. External `approval_name`, `approval_date`, `approval_reference` remain optional staff-recorded context; partially entered approval data still requires the existing complete validation. No new digital approval workflow is introduced.

Liquidation/return retain linked `advance_id`, workflow and saved employee/control context. Liquidation expenditure/asset debits, permitted liability credits, `line_notes` and authoritative line projects remain explicit. Net reduction is gross claimed debits minus permitted liability credits, not total evidence or all journal debits. Full evidence covers gross expenditure/eligible asset debits only; advance-control credit is not another claim.

Document fields retain `receipt_id`, purpose, support side, confirmed total (`declared_amount`), accepted amount, exclusion/review information and `allocations[].client_id/amount`. Existing manual review is a required staff action. Do not infer an accepted total from cash paid, synchronize it silently or interpret an upload/AI result as verified authenticity. Show exclusion amount/reason and posted-image reuse restrictions beside the document. Informational attachments add no monetary coverage.

Return retains receiving account, amount, reviewed informational proof and the dedicated context-bound confirmation. Label its checkbox/action precisely: I confirm that these reviewed documents support this return of PHP X to [account] for advance [number]. Keep the existing server confirmation operation; changing any bound context invalidates it. Present the status separately from expenditure coverage. The ordinary quick classifier never handles advance drafts.

### Correction fields

Retain target journal, original advance identity where applicable, correction mode, required reason, accounting date/backdate reason, replacement book/workflow and full replacement payload. Default date remains Asia/Manila today; validated dates stay between the original date and today, with same-date atomic reversal/replacement. Reason and reversal preview are never collapsed into ordinary optional fields.

Reverse-only has no fabricated replacement. Replacement releases require new eligible active references and new advance identities; settlement replacements remain linked to the original advance. Required fresh evidence review and return confirmation survive correction. Existing release-dependency and historical-balance blockers are visible with permitted links, not bypassed by a simplified form.

## 4. Quick, split and advanced representation

These are presentation modes, not new workflow kinds, draft versions or permission flags. Derive them from persisted provenance and the complete payload. Dedicated version-3 advances and version-4 corrections remain in their own workflows, even when a replacement portion resembles a simple payment.

**Quick mode is permitted only when all conditions hold:**

- Ordinary version-2 CRB or CDB, ordinary transaction kind, one generated cash line, zero or one counterpart row. A genuinely new blank form may initialize one row once with its stable client ID.
- No nonzero opposite-side counterpart amount, including negative values, additional financial row, distinct line project or unrepresented row metadata. Opposite-side fields must be blank or parse faithfully to exact zero; malformed/unparseable values force the detailed advanced view and remain visible unchanged. Server rejection does not justify hiding invalid data. Extra blank rows/evidence links are retained in split/advanced rather than discarded to satisfy this condition.
- Cash and counterpart share the same actual project tag, including Organization operations. A separate saved default project is context; it cannot overwrite those actual tags during classification.
- For a loaded counterpart, both amount fields are blank, or both parse to equal cents on the expected sides. Existing one-sided missing, invalid or mismatched values reveal separate amounts. They are not silently repaired merely because the intended transaction might be simple.
- All existing document allocations/reviews/client IDs can be preserved and inspected. A attached document is not by itself a reason to reject quick mode; a linkage the adapter cannot preserve is.

Missing party/account/date/purpose or equal zero amounts can still be a representable incomplete quick draft, but cannot pass posting validation. Representability and posting eligibility are separate. A loaded unavailable account remains visibly unavailable; it is not replaced by an eligible default.

For a new quick entry, typing its one amount sets the intended cash and counterpart amount together. For an eligible loaded quick entry, an explicit amount edit changes both visible transaction amounts; retain and revalidate existing evidence allocations rather than resizing them silently. On entering quick mode from another mode, **do not modify the payload at all**. Preserve unequal formatting if it represents equal cents until an explicit amount edit. Display mode changes alone do not invalidate Review or mark the draft dirty.

**Split mode:** ordinary CRB/CDB with same-side noncash allocations, possibly multiple projects or a single mismatched allocation. Show actual cash amount, every counterpart amount, line projects, cash project when different, and debit/credit/difference totals. A mismatch calls out Allocations differ from cash by PHP X. Keep Review available to explain invalid inputs; Record remains unavailable until server review succeeds.

**Advanced mode:** all ordinary GJ/transfers, opposite-side noncash amounts (including withholding), unparseable hidden sides, extra cash rows or metadata that the other views cannot faithfully represent. Unsupported structure displays its detailed fields and validation rather than normalizing it. Dedicated advance/correction forms use their own eligibility and generated control lines.

Mode transitions are lossless: quick → split reveals the same row; Add allocation creates one stable ID and marks a real structural edit. Split → advanced reveals existing opposite sides. Returning to a simpler mode is offered only when the complete structure already qualifies. Otherwise explain the specific reason, such as Different projects are assigned to your lines. User-directed row/project changes occur in the detailed form with normal evidence/review effects; no destructive mode-switch confirmation conceals deletion.

Common-project changes in quick mode explicitly apply to cash and its one counterpart. In split/advanced, any Apply project shortcut asks which displayed lines it changes and previews their current/new tags; nothing changes until confirmed. Preserve unsaved contents when cancelling. Never regenerate IDs or reset document allocations to make a uniform project.

The quick Project field displays the actual common line tag, not an unrelated saved default. Loading preserves that saved default as context without applying it. An explicit common-project edit updates the shortcut context and both affected line tags together. Add allocation from quick mode initializes only the new row to the displayed common tag; it cannot apply a hidden old default to existing lines. In detailed modes, new-row defaults remain visible and editable before Review.

Round-trip examples for U2 verification:

- CDB PHP 1,000, one expense debit/cash credit, same tag: quick; reveal/collapse produces equivalent amounts, sides, IDs, projects and evidence.
- CRB PHP 1,000 cash debit/receivable credit: quick; retain receivable meaning rather than income.
- CDB equipment asset debit/cash credit: quick if all other conditions hold; retain Asset type.
- CDB cash 1,000 with one expense 900: split with difference 100; no auto-balancing.
- CDB two debits 600/400, one cash credit 1,000: split; preserve both IDs and allocations.
- Equal two-line amounts but expense Project A and cash Organization operations: split; never overwrite the cash tag.
- CDB expense debit 4,500, withholding liability credit 500, cash credit 4,000: advanced. Gross evidence denominator is 4,500; no second 4,000 amount field disguised as the same amount.
- A counterpart with any nonzero opposite-side amount, including -1.00, or malformed opposite-side text: advanced; preserve and show that exact field. Cancelling a mode request must not zero, delete or normalize it. A draft's server ineligibility remains separate from its visible representation.
- GJ petty-cash debit/bank credit 100: advanced, explicit transfer kind, optional party.
- Any advance/correction draft or unsupported metadata: dedicated/detailed workflow; never ordinary quick posting.

## 5. Annotated payment layouts

These are ordered Markdown screen specifications, not rendered mockups. U1-C will implement and measure them. Synthetic example throughout: PHP 1,000 paid to Demo Training Supplier, for Training materials, from Demo Bank against Demo Materials Account, Organization operations. Date October 7, 2026. Proposed result J-DEMO-001 is local demonstration identity, not a new production numbering format.

### Enter details — desktop 1920×1080

1. **Existing shell:** sidebar/navigation groups and signed-in header retained. Main content max-width approximately 1,100 px; do not stretch short fields across the entire remaining viewport.
2. **Task header:** Record a payment; secondary Cash Disbursements Book (CDB). Back to dashboard or Back to Cash Disbursements Book, as determined by the explicit context contract below, and My drafts are context links. Do not use Back to transactions for a sidebar heading without a destination. A three-step progress list marks Enter details current; Review/Recorded are descriptions, not clickable shortcuts that bypass checks.
3. **Single status:** New draft — not saved, or Draft saved — [time, Asia/Manila]. Later edits show Unsaved changes; the earlier time may be retained as Last saved version: [time], never labelled as the current version being saved.
4. **Main entry card, two columns:** Payee and Accounting date; Purpose full width; Amount paid (PHP) and Pay from; Record against account and Project. Searchable account/party/project controls have visible labels and no automatic selection. One amount and one common project are shown only in quick mode.
5. **Secondary disclosures:** Other details (reference) and Supporting documents (optional) show compact summaries. Add supporting documents expands the existing protected review UI; attached unreviewed documents remain expanded. Add allocation and Use debit/credit view are discoverable text actions, not competing primary buttons.
6. **Action row in normal flow:** Save draft secondary; Review transaction primary. Text directly below: Review saves your draft and checks the entry. It does not record the transaction. The journal can be inspected via View journal preview; incomplete values are labelled incomplete.

### Enter details — laptop 1366×768

Retain approximately 250 px sidebar and existing signed-in header; main content uses available width with 20–24 px outer spacing. Same field order and paired columns where labels fit. Long labels wrap; controls have min-width zero and no truncated account names. Purpose is a compact two-row textarea. No empty full-height documents card. Target the essential card/action area within the first viewport for a clean short-label example; this is a U1-C measurement target, **not a guarantee already verified**. Long labels, attached evidence or validation may extend the page; never shrink text or hide required controls to meet a height target. Action row is not fixed over content.

### Contextual Back destination

Transactions is a navigation group, not an implemented destination page. Show a label naming the actual destination. When an ordinary workspace has a known Dashboard launch context, Back to dashboard targets `dashboard.php`. Otherwise use its originating context: CRB shows Back to Cash Receipts Book targeting `financial_records.php?view=crb&from=&to=`; CDB shows Back to Cash Disbursements Book targeting `financial_records.php?view=cdb&from=&to=`; GJ shows Back to Journal History targeting `financial_records.php?from=&to=`. Direct entry or resumed ordinary drafts without a known launch use those book/history defaults. Derive this from validated context and workflow provenance, not arbitrary external return URLs. These existing routes do not claim to open one exact transaction.

Apply the same unsaved-change handling to these links as other workspace navigation. U1-C demonstrates a local mock Dashboard destination for its payment example and an originating-CDB example using a local book panel; it never navigates to the live PHP routes. Stay preserves entered contents; Save-and-leave waits for successful simulated saving; Leave without saving restores the last acknowledged version when the demo is resumed. The destination label must match the demonstrated panel.

### Review — desktop and laptop

1. **Focused heading:** Review payment; progress marks Review current. Persistent status: Not recorded yet. Hide the editable Enter view from visual and keyboard access without destroying its data or widgets.
2. **Summary:** PHP 1,000 paid to Demo Training Supplier; accounting date, purpose, Pay from Demo Bank; Record against Demo Materials Account; actual Project Organization operations; reference if supplied. Readable amounts use PHP/₱ consistently with two decimals.
3. **Evidence summary:** No supporting documents attached — optional for this payment. Other states show accepted supported amount and the actual noncash-side denominator. Missing/partial ordinary support is a warning with its existing permissive meaning; unreviewed attached documents remain a blocker requiring Edit details/document review. Do not call a file authentic or management-approved.
4. **Detailed journal disclosure:** full rows, actual projects, debit/credit amounts and totals available. Default collapsed for a balanced quick example; expand automatically for split/advanced entries, withholding, project differences, reversals or any material distinction the short summary cannot represent. No offscreen-only balance warning.
5. **Final action area:** Edit details secondary; Confirm and record payment primary. Help: Recording adds this transaction to the books. A later correction preserves its history. Accounting review is not management approval. No separate required Save or extra confirmation dialog for an already explicit ordinary Record action.

At laptop width, summary and action row form one column, journal/evidence disclosures follow logically. At desktop width, short facts may use two columns; documents and detailed tables remain within the main card. Scrolling is allowed for complex entries. Focus goes to the Review heading, with a concise announcement; opening a disclosure does not change the review token.

### Recorded — desktop and laptop

1. **Replace entry/review view:** heading Payment recorded; progress Recorded; one success status. No New cash payment title, no unposted message and no disabled edit form occupying the page.
2. **Result card:** journal ID from the server, accounting date and reference, amount/payee/purpose/account/project summary and separate actual posting timestamp when available. A recovered retry says This payment was already recorded. The existing result has been recovered. It is not a second posting.
3. **Primary:** View recorded transaction, targeting that exact permission-checked journal. Secondary: Record another payment. Smaller links: Cash Disbursements Book and Journal History, explicitly lists across all dates. Linked advance/correction results add their correct identity links.
4. **Read-only inspection:** detailed journal and permitted evidence/history available without activating editable draft controls. Any correction action is separately eligible and explicit. Reopening a posted draft shows this same state and its snapshots, not editable current master labels.

Receipt variants change task to Record money received, Payer, Amount received, Receive into, Review receipt, Confirm and record receipt and Money received recorded. Journal debits cash and credits its selected counterpart; do not copy payment-side assumptions. Optional reference/evidence and the same unsaved/recovery contracts remain.

## 6. State, validation and recovery contract

One state renderer owns task title, transaction status, progress, actions, announcements and visible view. Saved-version information is secondary and explicitly refers to the last acknowledged revision. Clear obsolete success/error messages on transitions; a historical saved message cannot outrank the current state.

- **New/edited:** Enter view; New draft — not saved or Unsaved changes. Relevant edits clear the review. Existing draft identity remains; blank/incomplete Save is permitted under current service rules.
- **Saving:** Saving draft…; disable duplicate save/review and conflicting mutations. On success acknowledge only the saved request revision/content. On failure/conflict retain values and Unsaved changes; do not announce saved, navigate away or silently overwrite another revision.
- **Saved:** Draft saved — [Asia/Manila time], Not recorded in supporting copy. No extra required Save before Review.
- **Reviewing:** save if needed, then review the saved version; status Checking your entry…. Record unavailable. If Save succeeded but review validation fails, show Saved draft — needs correction, with field errors; do not claim unsaved solely because review failed.
- **Review ready:** focused Review, Not recorded yet, explicit final Record action. Token is tied to the saved revision and server context. Edit details preserves values, returns to Enter and clears the token; no payload change alone means it can remain a saved draft. A later edit makes it unsaved.
- **Review expired/context changed:** Record unavailable; Your review needs refreshing. Your saved draft is still available. Review again revalidates the same draft. Do not discard it or imply expiry reverses a posting.
- **Recording:** Recording payment…; freeze the canonical reviewed request and identity; disable duplicate mutation/navigation actions. Do not show Recorded until success is acknowledged.
- **Unknown posting outcome:** We could not confirm whether this payment was recorded. Check the existing result before trying another entry. Primary Check recording result retries the same durable operation/request identity, not a new submission key. Keep frozen contents/recovery context; no Record another action. A confirmed failure can return to saved/edit/review as appropriate; permission/gate/conflict errors cannot be treated as absence of a prior result.
- **Recorded/recovered/reopened:** immutable recorded result and actual journal identity; clear saved/unposted transient messages. Recovery of an original request after correction returns the original journal with its correction links, not its replacement or a new journal. Advance correction retries retain the selected Stage 2 availability boundary.

Save, upload/attach, manual review and return confirmation have distinct success/error states. An upload/confirmation success is not a saved financial entry or posting. Busy controls preserve their existing disabled state rather than indiscriminately enabling protected fields afterward. Pending uploads retain durable retry context.

Validation: field-level messages, accessible linked summary at top, retained values, first actionable field focus, and no color-only cues. Example missing purpose: Enter the purpose of this payment. Invalid amount: Enter a positive amount with no more than two decimal places, subject to existing server syntax/bounds. Balance mismatch: Account allocations differ from the cash amount by PHP X. Errors involving a document/client line link to the relevant row. Server changes in eligibility, balance, deadlines, evidence, reservations or version conflict remain visible; no UI-only bypass.

Controlled in-app leaving with dirty contents offers **Stay**, **Save draft and leave**, **Leave without saving**. Save-and-leave waits for successful current-content save; failure keeps workspace and edits. Leave without saving abandons local edits only. It does not delete a saved draft, previously stored upload, reservation or posted evidence. Explain Already uploaded documents remain with the saved draft when applicable; draft removal stays the separate owner-authorized discard operation. Clean leaving has no redundant warning. Pending operations/uncertain posting require safe completion/recovery rather than a dialog promising rollback.

Browser Back/Forward, reload and tab close retain native `beforeunload` where supported; browsers control its wording and cannot reliably complete Save on close. Do not promise a custom three-action browser-close dialog, autosave or history traps. Inline master-dialog cancel returns focus and preserves the entry; newly created masters remain created even if the financial draft is later abandoned.

## 7. Advance, correction and record-finding variants

**Advance detail:** keep Released, Liquidated, Returned and Outstanding prominent, plus reporting-date basis, deadline, settlement status and separate overdue indicator. Eligible Record liquidation and Return unused cash start from this identity. Operation history can be expandable, retaining complete rows and journal links; blockers/reconciliation discrepancies remain visible, never buried. Filtered totals remain distinct from full-account reconciliation.

**Liquidation Enter:** show linked employee/advance/outstanding first; expenditures/eligible assets and liabilities next; reviewed documents adjacent or immediately following. Keep three separate figures: gross claimed costs, supported gross costs, net advance reduction. Example 4,500 debit costs minus 500 liability credits reduces advance 4,000 and requires 4,500 support. Missing support stops recording and leaves the advance unchanged. Compact document cards disclose details, but every blocking review/exclusion/allocation remains discoverable and automatically expanded when unresolved.

**Return Enter:** linked identity/outstanding; amount and receiving account; proof documents and review; context-specific confirmation. Review summary shows amount/account and Proof confirmed for this return, with required refresh after bound changes. Record remains deliberate; do not combine away confirmation or manufacture monetary coverage for informational proof.

**Correction Enter/Review:** label Correct a recorded transaction versus Reverse an entry recorded by mistake. Original immutable summary stays visible; reason/date, generated reversal and replacement intent remain explicit. Focused Review leads with Original 1,000 → Replacement 900; GJ reversal 1,000; net corrected effect 900. Keep complete journals inspectable. Gross CDB activity is 1,900 in that example; do not subtract the original twice or label 1,900 the net payment. Explain actual money returned uses the return workflow. Release blockers link effective settlements; stale competing drafts retain their values/privacy and permitted corrected-result link.

**Find/resume:** use familiar workflow labels in My drafts, retain last-saved Asia/Manila filters and accounting date as separate data, version 2/3/4 dispatch and owner-only results. Failed/stale refresh preserves the last successful list and its applied filters. Find transaction retains full-entry CRB/CDB search/totals and line-filtered Journal History; full detail is always obtainable. No new date semantics or balance calculations are introduced.

**Management privacy:** confirmed-proof summary means the status Return proof confirmed and permitted aggregate coverage/balances. It excludes document URLs, filenames, hashes, review narratives/identities, private confirmation actor/time/context, allocations tied to private documents and images/downloads. Calculate permitted coverage internally before redaction; no documents visible is not equivalent to unsupported. Apply the existing server projection to HTML/JSON/print/detail/correction-chain readers, including flags off. U1-B verifies the intended source boundary, not every server response or direct-denial regression; later affected tests remain required.

## 8. Exact transaction access and implementation mapping

Current Financial Records View renders a journal from its filtered result into a dialog. Current recorded workspace links open all-date books/history; there is no supported ordinary exact-journal GET route in those links. Do not invent a current `journal_id` parameter on `financial_records.php` or mislabel an all-date list link.

**Proposed U2 adapter:** a small read-only `journal_transaction.php?journal_id=<positive ID>` destination, explicitly a new route for implementation review. Share the existing complete-journal/detail/evidence/correction projections instead of maintaining competing calculations. Server validates a scalar positive ID, authenticated active permitted role, posted status and valid schema; uses viewer-aware evidence access and historical snapshots; returns safe 400/403/404 or retained error state as appropriate, never private debug data. No ownership-only draft restriction is transplanted onto authorized posted-record inspection.

The page fetches that journal independent of current month/account/list filters, presents its full lines and permitted history, and identifies linked correction roles without excluding corrected originals. Primary access from recorded/recovered/reopened results uses the returned original journal ID. Provide contextual Back to transaction workspace/list when safe, plus all-date book/history links. Correction and advance identity links retain existing routes. Avoid reusing the slow advance comparison just to show a basic journal; profiling is still separate. No schema change is expected; a discovered limitation needs an explicit amendment before expansion.

Production ownership for later reviewed checkpoints:

- **U2:** `includes/nav.php` task grouping; `includes/accounting_entry_page.php` ordinary state views; `assets/js/accounting_workspace.js` lossless classifier, status/state, amount/project adapters, controlled leaving; scoped `assets/css/accounting_workspace.css`; proposed exact-journal page/shared reader and Financial Records detail reuse. Ordinary payment vertical slice first, then receipts and GJ/transfer regressions. Preserve IDs, CSRF, revisions, keys, endpoints and existing server eligibility.
- **U2 verification dependency:** when shared state, selectors, draft loading or navigation change, include affected version-3 advance and version-4 correction checks in U2's own delivery, before their later redesign. Cover payload preservation, review invalidation/recovery, linked workflow identity, privacy and read-only behavior as affected; do not defer shared-script regressions to U5. When the proposed exact-journal reader is introduced, check authorization/direct access, viewer-aware evidence privacy and access to corrected originals with their links. That reader is a U2 implementation obligation, not a prototype feature or an already-existing production route.
- **U3:** advance templates/shared-script workflow branches and register/detail presentation; existing advance/lifecycle/evidence services retain authoritative calculations and confirmation. Shared U2 changes must not break v3/v4 before their redesign is implemented.
- **U4:** My Drafts/find/detail/correction/evidence presentation and inline focus; retain correction services, lineage/reservations/history/privacy. Exact-record foundation starts in U2 because its Recorded view needs it; richer finding/correction journeys follow here.
- **U5:** integrated walkthroughs, focused production regressions, laptop/desktop/keyboard checks and repeated comparison timing; user/client acceptance recorded separately.

Existing style mapping: task header `.aw-header`; essentials `.aw-card`/`.aw-grid`; selectors `.aw-record-selector`; action row `.aw-actions`; journal `.aw-table`/`.aw-table-wrap`; evidence `.aw-document`/`.aw-coverage`; error/status regions `aw-error`/`aw-status`. Add scoped view/progress/summary patterns later. No Tailwind rebuild/new dependency/design-service upload is needed for this specification.

Accessibility targets for U1-C/U2 verification: visible labels; semantic headings/landmarks; keyboard combobox navigation and committed-ID announcements; logical order; focused step/error headings; returning focus on editing/dialog close; one polite status announcement, assertive actionable errors; text indicators alongside colors; no inaccessible hidden active form; wrapped long labels and localized horizontal scrolling for detailed tables. Normal/body/control text approximately 14–16 px, helper text at least 13 px; maintain readable contrast with WCAG AA targets (4.5:1 normal text, 3:1 large text/nontext indicators) checked from actual rendered styles later. Aim for 40–44 px practical hit areas without implying a screenshot proves accessibility compliance.

Contrast targets follow W3C's [text contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) and [non-text contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html), with applicable exceptions. Selector behavior follows the [W3C combobox pattern](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/). These references guide later checks; they do not establish compliance of this unrendered specification.

## 9. Performance follow-up

Track **U-PERF-01: advance comparison response-path diagnosis**. U1-A serial median HTTP: A Admin 5.706 s / Management 11.637 s; B Admin 6.146 s / Management 12.227 s. Recalculated reviewer data supports these isolated observations; neither working Apache performance nor root cause is established.

During the appropriate separately authorized implementation checkpoint, profile a copied synthetic application: total request, shared schema/readers, correction-chain/lifecycle/reconciliation queries, evidence/privacy projection and rendering. Compare Admin/Management call counts/times and repeated reads; do not assume privacy checks are the cause or remove them for speed. Record exact source, workload and server; choose a minimal measured fix under preserved integrity/privacy semantics. If it requires a service/index/schema change, document scope and migration implications before proceeding. No production instrumentation, schema optimization or benchmark rerun is part of U1-B.

Loading proposal: show Loading correction details… for a controlled transition; avoid repeated requests and retain the last successful context on failure. A blocking full-page GET may require a shell/loading adapter before it can display a dynamic status; do not claim that merely adding server HTML displays progress before the response arrives. A clear retry/error is useful independently of performance. Reuse the same measured protocol in U5; do not accept an increased timeout as improved response time or invent a completed speed target.

## 10. U1-C prototype and review handoff

**Subsequent reviewed-reference revision:** the user authorized the [targeted prototype corrections](usability-u1-prototype-revision.md). Carry these into U2: persistent errors beside each affected visible control, summary links focusing those controls, per-error clearing with accessible associations, preserved saved snapshots during task navigation, no reset when clicking the current task, grouped gross costs/withholding/cash before confirmation, plain counterpart-account guidance and readable full labels. These are implemented in the local demo only. Its single saved-draft resume policy is a prototype choice; production navigation must preserve the existing multi-draft model and never silently replace work.

Required later artifact: `docs/prototypes/usability-u1-payment.html`, standalone local HTML/CSS/JavaScript, visible Demo — no financial records are created. Synthetic defaults only; no PHP include, fetch/XHR, live endpoint, secrets, service worker, external fonts/scripts or real file upload. No storage by default; Reset demo clears only local prototype state. The exact-journal view is a local demonstration panel, not a claim the proposed production route exists.

Demonstrate Enter → Review → Recorded, truthful save/later edit, field validation, Edit details, ordinary optional evidence summary, controlled Stay/Save-and-leave/Leave-without-saving, save failure, review expiry, uncertain recording and recovery of the same local result. Include a loaded split/withholding example that cannot silently switch to quick, and a long-label/no-match selector state. Simulate evidence states without pretending a local mock performs real review/authorization. Prototype step changes preserve input values and focus. Browser-close protection, if exercised, follows native limitations.

Include the reviewed quick-mode exclusions for negative/nonzero opposite-side fields and malformed values, retaining their visible detailed state. Demonstrate the named local Back destinations and unsaved-change guard described in section 5. Prototype evidence establishes mock interaction only; production classification, routing, authorization and privacy require U2 checks.

Check at 1366×768 and 1920×1080, keyboard-only navigation, first-viewport primary action for the simple example, long labels, contrast/focus, validation/error recovery and exact local details. Publish inspected synthetic captures and a clear scenario inventory. No financial fixture posting or whole-system suite is necessary to test a standalone design. User walkthrough asks whether they can find payment, explain fields, distinguish Review from recording, edit, leave/resume and find the result; record coaching. Atikha walkthrough remains pending until actually observed.

Concise screen glossary: **Payee/Payer** — person or organization paid/paying; **Account** — where the transaction is recorded; **Project** — activity allocation, or Organization operations; **Draft** — private saved work, not recorded; **Review** — check the proposed entry, not management approval; **Recorded/Post** — immutable journal added to the books; **Advance** — money accountable to an employee until settled; **Liquidation** — record supported costs against that advance; **Return** — record unused money received back; **Correction** — linked reversal and optional replacement preserving the original.

U1-B completion is the inspected specification and maintained handoff. No classifier, new route, performance fix, prototype or production state change has been implemented here. U1-C prototype verification, screen approval, user/client walkthrough, production U2-U4 implementation, U5 integration, working acceptance/restoration and original completion Stages 4–7 remain distinct pending work. Next: review this specification, then separately authorize U1-C.
