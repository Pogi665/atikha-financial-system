# U2-C plan: task navigation and integrated U2 handoff

**Implementation handoff, October 15, 2026:** the user subsequently approved this clarified plan and authorized U2-C. [Delivery, final checks and review package](usability-u2-c-delivery.md) record completed source scope and isolated verification, including the test-fixture routine-registration limitation. Planning-only/authorization-pending statements below are dated October 14 context, superseded by that instruction. Working/client acceptance and later checkpoints remain separate.

October 14, 2026. **Planning only; U2-C application implementation has not started.** The user reported committing/pushing U2-B and requested starting U2-C. The preceding agreed next step was detailed planning and review before implementation. This document makes that next checkpoint concrete without changing application source, configuration or data.

Read with the [approved U2 plan](usability-u2-plan.md), [accepted U2-B correction delivery](usability-u2-b-revision.md), [current status](project-status.md), [client context](client-context.md) and [selected decisions](project-decisions.md). U2-A/U2-B source approval remains separate from working-system and Atikha acceptance.

**Plan-review clarification, October 14:** the user supplied ChatGPT's favorable review, conditional on explicit correction-provenance navigation and its test cases. The rules in sections 4 and 6 below now incorporate that clarification. ChatGPT also reported verifying GitHub `main` at the local U2-B baseline; that is supplied reviewer evidence, not an independent remote check by Codex. U2-C implementation remains unauthorized.

## 1. Baseline and inspected findings

Local HEAD is **`7674261e8aa999c580ae6ed6365c3603e19c1bae` (`U2-B`)**. The working tree was clean at inspection. Read-only hashes confirm that the four committed U2-B application files match the approved correction manifest. The push is user-reported; no remote GitHub inspection was performed in this planning task.

The inspected sidebar already uses the shared layout, role branches, original URLs and dedicated `assets/css/sidebar.css`. Its CSS targets direct child links under the navigation element. Preserve that structure so grouping does not accidentally remove link styles. The page shell has a fixed 16rem sidebar and desktop minimum width; a new mobile shell is outside U2-C.

Current presentation gaps:

- Tasks are interspersed: payment is direct, receipt entry is reached from its book, and drafts/setup/communications appear in an ungrouped sequence.
- Ordinary CRB entry uses active key `crb`, shared with the read-only receipts book. CDB already has distinct `payment` and `cdb` keys.
- `accounting_drafts.php` passes `general_journal`, so My Drafts highlights another task.
- `journal_corrections.php` passes `general_journal`, including for Management, whose sidebar has no journal-authoring link.
- Dedicated advance entry/correction pages inherit cash-book/GJ highlighting through the shared entry template rather than their Cash advances location.
- Only the payment link currently emits `aria-current="page"`. Other links rely on color classes.

Existing gates must be retained exactly: Stage 1 needs its flag and schema; Stage 2 additionally needs Stage 1 and its schema; Stage 3 needs Stage 1 and installed Stage 2/3 schema, but ordinary correction availability does not require the Stage 2 flag. OCR has its own flag/schema gate and is not inherently dependent on the Stage 1 flag. `general_journal.php` keeps an Admin-only scalar fallback when Stage 1 is off, and keeps its separate OCR branch when `receipt_id` is present.

Financial Records already serves authorized Management read views. Its book links currently appear only in the Admin sidebar; adding Management links to those existing read views would improve reachability without granting new permissions. This is the proposed navigation default, not a newly verified client requirement.

## 2. Outcome and scope boundary

U2-C will organize the existing navigation around tasks, give every displayed destination a truthful active state, and verify U2-A/U2-B together with the affected read/advance/correction paths on isolated integrated fixtures.

Within scope:

- Task groups and labels in the shared sidebar, preserving the existing Admin/Management branding and routes.
- Correct active keys in the entry/draft/comparison callers where needed.
- Accessible group labels, current-page state, keyboard focus, long labels and independently scrollable navigation.
- A role/flag navigation browser matrix and focused integrated regression evidence.
- Final screenshots, source/verification manifests, updated delivery/status and a shareable review package containing exact changed source/diffs.

Outside scope: accounting formulas/validation, posting/recovery identities, evidence rules, authorization services, database schemas/migrations, feature activation, local configuration, new digital approval policy, mobile navigation, dashboard/report redesign, performance fixes, U3–U5 and original Stages 4–7. Do not add the previously suggested currency or transfer-coverage wording refinements to this checkpoint; keep them tracked separately. No working financial postings, commits or pushes are part of this implementation plan.

## 3. Proposed destinations and role visibility

Use visible headings with direct links underneath. Groups are organizational labels, not routes or accordions. Do not require visiting a Transactions hub, expand hidden sections or add a click before a task.

**Dashboard:** keep `dashboard.php` as the first direct link for both roles. Retain dashboard content and existing active key `dashboard`.

**Transactions — Admin:**

- Record a payment → `cash_disbursement.php`, active key `payment`, Stage 1 enabled.
- Record money received → `cash_receipt.php`, new active key `receipt`, Stage 1 enabled.
- Other journal entry (General Journal) → `general_journal.php`, active key `general_journal`, existing Admin route preserved even when Stage 1 is off so the scalar fallback remains reachable. Wrap the complete label rather than truncate away General Journal.
- Scan receipt → `ocr_expense.php`, active key `ocr_expense`, existing OCR flag/schema gate.
- My drafts → `accounting_drafts.php`, active key `accounting_drafts`, Stage 1 enabled.

Management has no Transactions group and no entry, private-draft or OCR links. A heading is rendered only if it has visible destinations.

**Cash advances:** keep `cash_advances.php`, active key `cash_advances`, for Admin and Management when the existing Stage 2 helper enables it. Preserve Admin release/settlement links and Management read-only behavior inside the existing pages. Do not create generic liquidation/return sidebar links detached from an advance.

**Books & reports — both authorized roles:**

- Find a transaction (Journal History) → `financial_records.php`, active key `financial_records`.
- Cash Receipts Book (CRB) → `financial_records.php?view=crb`, active key `crb`.
- Cash Disbursements Book (CDB) → `financial_records.php?view=cdb`, active key `cdb`.
- Reports → `reports.php`, active key `reports`.

These are existing read destinations, not new ledgers or permission grants. Keep their current default date scope; do not silently append all-date parameters to normal sidebar links. Existing contextual success/Back links retain their explicit all-date scope. Keep readers available under their current installed-schema contracts when entry flags are off; do not gate this group with the Admin workspace flag. Preserve the existing flag-dependent Financial Records behavior rather than altering query semantics as a navigation change.

**Communications:** External Email → `external_email.php` for both roles; Message the Board → `board_messages.php` for Admin; Review Queue → `management_reviews.php` and Board Inbox → `board_inbox.php` for Management. Retain their current active keys, restrictions and existing review meaning. Moving a link does not approve a journal or authorize sending a message.

**Administration — Admin:** Accounting setup → `accounting_setup.php` with Stage 1 enabled; Chart of Accounts → `admin_accounts.php`; User management → `admin_users.php`; Audit trail → `audit_trail.php`. Preserve the existing keys and authorization; Management receives no Administration group.

Feature-query failures hide affected gated tasks and retain generic error logging without exposing private diagnostics. Existing static/fallback links are not a promise that a failing database will serve their pages. No new flag or permissive gate fallback is introduced.

## 4. Active-state and interaction contract

Emit `aria-current="page"` on the same unique link selected by the page's trusted active key. A group heading is never marked current. Preserve role-specific active colors and the selected visual foundation; any extension of entry highlighting should use already available classes/scoped CSS.

Caller changes:

- Shared ordinary CDB/CRB/GJ entry: `payment` / `receipt` / `general_journal`, including Enter, Review, Recorded, reopened and uncertain-result states. Step changes do not turn the entry into a book page.
- Draft list: `accounting_drafts`.
- Financial Records/history and book pages: retain `financial_records` / `crb` / `cdb`.
- Exact transaction reader: retain `financial_records` for its Find a transaction location; keep its existing source-book Back links unchanged.
- Dedicated non-correction advance entry: `cash_advances`, derived from its existing trusted workflow configuration, not arbitrary query flags.
- Any correction whose trusted target contains an advance operation (`$correctionConfig['target']['operation']`): `cash_advances`, for both `reverse_only` and `reverse_replace`. This target check takes precedence over ordinary book mapping and does not depend on `$advanceMode` or `$advanceConfig` being populated.
- Non-advance ordinary correction authoring: derive the location from the original target journal's persisted `source_book` (`$correctionConfig['target']['journal']['source_book']`): original CRB → `crb`; original CDB → `cdb`; original GJ → `financial_records`. Missing/legacy source-book provenance uses the read location `financial_records`, without inventing a cash-book origin.
- Posted correction comparisons: use `financial_records` for both roles, avoiding a Management current-page reference to an inaccessible authoring task.
- Scalar/manual/OCR General Journal: retain `general_journal` under the Admin Transactions group. Preserve its POST form, receipt handoff and IDs.

Apply correction provenance first, then the dedicated non-correction advance rule, then ordinary entry mapping. Never derive a correction's location solely from `$workspaceBook`, the generated reversal's GJ book, a replacement book, `$advanceMode`, or untrusted request/draft flags. Current source confirms why: `correction_book()` returns GJ for every reverse-only correction, and `journal_correction.php` populates `$advanceConfig` only for advance reverse-and-replace. The trusted target still carries the original journal and advance operation for reverse-only. This clarification changes only the planned active-key selection, not correction routing, eligibility, posting books or authorization.

If an entry task is deliberately hidden by its feature gate, the sidebar must not highlight an unrelated task merely to force a current item. Existing readers such as exact journals/comparisons retain a visible read location even when entry flags are off.

Keep the sidebar anchors and their real `href` values. The ordinary workspace's existing delegated click handling must continue to protect dirty navigation, preserve current-task edits, wait for Save-and-leave success, retain failed saves and block controlled navigation while a posting result is unknown. Do not add a second navigation handler, new posting key, browser financial store or arbitrary return URL.

Preserve the direct-child anchor structure used by sidebar CSS and existing href-based test selectors. Give the navigation an accessible name and group headings actual heading semantics. Add scoped visible `:focus-visible` styling if absent. Long labels wrap, icons do not shrink, and focused links scroll into view within the sidebar. Use a discoverable vertical scrolling treatment for the longer grouped list; avoid page-width overflow. Maintain print hiding and the fixed desktop shell. No Tailwind rebuild is expected unless implementation introduces a utility absent from the compiled stylesheet.

## 5. Implementation order and expected files

These are implementation steps within one U2-C checkpoint, not additional authorization gates:

1. Recheck the committed baseline/clean tree and preserve any later unrelated edits. Capture application/test hashes before editing.
2. Update `includes/nav.php` and scoped `assets/css/sidebar.css` for the catalog, gates, headings/current state/focus. Retain PDO scope fallback and existing gate helpers; do not modify role/schema services.
3. Update only active-key selection in `includes/accounting_entry_page.php`, `accounting_drafts.php` and `journal_corrections.php`. Other callers remain unchanged unless inspection identifies a concrete active-state dependency; report any additional file explicitly.
4. Add `scripts/test_usability_u2_c_browser.py` for the actual rendered navigation/route and role/flag matrix on a complete-schema isolated fixture. Update existing browser expectations only where navigation labels/current state actually changed. Prefer semantic links and exact hrefs over visual-position selectors; retain substantive accounting/privacy assertions.
5. Run the focused integrated verification below against final application bytes, resolve failures within scope, inspect final captures, then prepare the U2-C and overall U2 handoff.

Expected production changes are PHP navigation/active keys and scoped sidebar CSS. No shared accounting JS change is planned. If integration reveals a shared state issue, identify it and run its directly affected workflows immediately; do not silently bundle an unrelated redesign.

## 6. Isolated verification plan

No application or database tests were run during planning. Counts below are suite identifiers/contracts, not promised or fabricated pass totals.

### Fixture and external-call boundary

Use `scripts/test_stage3_checkpoint5.php --database=<NEW_GUARDED_NAME>` once to create a fresh disposable complete-schema operational fixture, with 019/020/021 installed before operational cases. The approved helper targets only a new `atikha_test_stage1_*` name and rewrites SQL database selection for isolation. Never apply those migrations to `atikha_finance` or reuse a working draft/receipt.

Create independent copies for suites that mutate fixtures using `scripts/test_stage3_fixture.py --fixture=<PRIVATE_CP5_FIXTURE> --database=<DISTINCT_NEW_GUARDED_NAME>`. Record exact actual names/manifests. Use `stage2-fixture.json` for Stage 2 and `stage3-checkpoint4-fixture.json` for CP4; do not repeat the earlier CP3/CP4 metadata mismatch. No server start/stop, repair, permission change or production configuration change is needed.

Copied applications exclude working `config.php`/`db_connect.php`; generate isolated connections, session directories and synthetic evidence paths. Block external browser traffic. For the new navigation runner, also deny or stub `forecast_ai.php` before opening Dashboard and block unrelated provider/mail/anomaly actions: external-browser blocking alone does not prevent server-side calls initiated by localhost endpoints. Never click Send, run AI diagnostics or manipulate users/budgets as part of a navigation check. Synthetic login helpers are confined to disposable copies; working MFA is not bypassed.

### Navigation and role/flag matrix

Test Admin and Management with all eight Stage 1/2/3 boolean combinations and OCR both on/off on the complete installed schema. Serialize isolated configuration changes rather than racing server flags. Assert the actual helper-derived enabled states, not the raw booleans alone.

- Every displayed link has its intended route, label, group and unique active key; empty groups are absent and there are no duplicate task/book links.
- Both roles reach permitted history/books/reports/communications. Management cannot see or access authoring, private drafts, setup, accounts/users administration or OCR tasks; direct requests remain protected by server authorization.
- Stage 1 off hides payment/receipt/drafts/setup but preserves the Admin scalar GJ fallback. OCR visibility follows its own gate. Stage 2 cannot appear without its prerequisite Stage 1. Stage 3 off removes correction starts under existing rules without hiding posted history.
- Stage 2 off does not disable eligible ordinary corrections when Stage 1/3 and installed schemas permit them; advance correction starts remain unavailable. Existing installed-schema evidence restrictions survive entry flags being off.
- Use additional independently disposable partial-schema cases for missing Stage 1, Stage 2 and OCR prerequisites; gates fail closed and no migration is performed to make a case pass. Do not mutilate the full operational fixture or the working database.
- Anonymous/inactive/unsupported sessions cannot gain access through direct links. Navigation is presentation, never the security boundary.

Run every normal sidebar destination with no form submission. Snapshot isolated business tables around that read/navigation pass; unexpected financial/draft/evidence mutations fail the pass. Keep this separate from later deliberately mutating regression suites. Session/CSRF initialization is not a financial mutation. Any normal authentication/notification side effect must be identified, not deleted to make a fingerprint pass.

### Current-page, leaving and layout cases

Check payment vs CDB, receipt vs CRB, GJ, My drafts, setup, advance register/detail/release/liquidation/return, ordinary/advance corrections, posted comparison, exact record and each communication/admin destination. Verify one current link when that location is visible, keyboard focus/scroll, full labels and print hiding at 1366×768 and 1920×1080.

Explicit correction-current-state cases, using eligible synthetic targets and asserting the selected sidebar link plus `aria-current="page"`:

- Original ordinary CRB, both reverse-only and reverse-and-replace → only `crb` current.
- Original ordinary CDB, both modes → only `cdb` current.
- Original ordinary GJ, both modes → only `financial_records` current.
- Advance-linked release, liquidation and return, each in both modes → only `cash_advances` current, including reverse-only with no `$advanceConfig`.
- For permitted ordinary reverse-and-replace cases with a different replacement book, the current item still follows the original target's book. Reverse-only's generated GJ reversal never makes `general_journal` current.
- Reopen saved correction drafts and change permitted replacement-book presentation: target provenance still selects the same location. Missing/legacy original-book metadata uses `financial_records`.

These current-state checks must not post a correction just to validate navigation. Use independent eligible targets where lifecycle blockers require it; assert original journal/evidence/advance identities and financial rows remain unchanged across page reads. Management remains unable to author corrections; permitted posted comparisons keep their separately specified `financial_records` location. Existing feature gates continue to reject paused/ineligible authoring rather than rendering a misleading current item.

Exercise ordinary clean/dirty/current-task sidebar navigation, Stay, Leave without latest edits, Save-and-leave success/failure, resumed private drafts and unknown-result navigation blocking. Preserve the original durable request during recovery. Do not post simply to exercise a menu; postings belong to the separate synthetic workflow regression cases.

### Affected integrated suites

Against independent complete-schema disposable copies, run:

- `node scripts/test_usability_u2_adapter.js` and `scripts/test_usability_u2_reader.php --fixture=<PRIVATE_CP5_FIXTURE>`.
- `scripts/test_usability_u2_browser.py` and `scripts/test_usability_u2_b_browser.py`, including the corrected visible CRB/CDB blank Split errors, unknown-field classifier, focused states, evidence, drafts, scalar OCR fallback and same-request recovery.
- `scripts/test_stage1_browser.py`, `scripts/test_stage2_browser.py`, `scripts/test_stage3_checkpoint4_browser.py` and `scripts/test_stage3_checkpoint5_browser.py` for the shared navigation/read/authoring dependencies and privacy gates.
- Extend the new integrated U2-C runner with current Financial Records checks: enabled CRB/CDB search/account filters retain whole matching entries and gross originating activity; Journal History retains line filtering; totals span matching pages; failed/stale refresh preserves applied results; original/reversal/replacement remain present; Management evidence remains redacted in reads/downloads with entry flags off. Do not substitute the older pre-pivot `test_records_browser.py` as proof of these current contracts.

If an OCR helper/scalar handoff is actually changed, use the existing Phase 4 receipt suite on its own fresh `atikha_test_phase4_*` fixture with its provider transport double. If those services are unchanged, the new OCR navigation matrix and retained U2-B scalar checks cover the navigation change without claiming live extraction accuracy.

Run syntax checks for files actually changed and `git diff --check`. `npm test` is a placeholder and cannot count as verification. After successful checks, broaden or repeat only for a new edit, failure or uncovered dependency. Record final application hashes and distinguish any test-harness-only edits after captures; do not attribute old results to new bytes.

## 7. Review artifacts and completion criteria

Capture and inspect the new Admin/Management grouped sidebars, distinct entry/book/draft current states, permitted flag-off reads/scalar fallback, dirty-leave dialog and uncertain-result sidebar blocking. Pair laptop/desktop views, and include a print capture showing the sidebar hidden. Full-page image height is not physical-device evidence. Preserve initial U2-A/U2-B screenshots as dated evidence; fresh U2-C captures identify final source.

Deliver `docs/usability-u2-c-delivery.md`, `docs/usability-u2-c-source-manifest.json`, `docs/images/usability-u2-c/` and a review ZIP with actual changed application/test files, cumulative/focused diffs, report, manifest and screenshots. Never include working configuration, private sessions, fixtures, receipts or SQL dumps. Exact source is included so code review need not depend on a later push.

Update the overall U2 delivery/status/README to distinguish initial evidence from final integration. U2 source completion requires correct role/gate navigation, preserved ordinary/dedicated accounting contracts, passing affected final-source checks, visually inspected captures and explicit verification limits. Do not claim all original system stages, working/client acceptance, mobile/cross-browser compatibility or U-PERF-01 complete.

The enabled XAMPP installation serves edited PHP/CSS on refresh, even without migration or flag changes. Implementation must disclose that source-serving effect; it is separate from a deliberate working acceptance run. Any later working smoke check must be separately scoped, use normal login/MFA and preserve actual financial data/evidence. No live smoke is authorized by this plan.

## 8. Next action

Review this U2-C plan, especially the Management book-link visibility, explicit fallback gates and current-page mapping. Implementation begins only after the user's subsequent instruction to implement the reviewed plan. Do not start U3 or original Stage 4 as a side effect of completing navigation. The user retains committing/pushing.
