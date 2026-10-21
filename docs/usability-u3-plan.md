# U3 plan: cash advances as a connected workflow

**U3-B-D ready for exact-source review, October22,2026:** database reconstruction, normal SQL and guarded Administrator/Management login acceptance passed before resumption. B/C/D are implemented and verified:71 backend (B12/C24/D26 plus9 integration),128 focused browser,58 advance adapter; affected U2/Stage2/Stage3/U3-A suites passed against final production hashes. Working financial data, permissions, objects,62 audit rows and all53 uploads remain unchanged since login acceptance. Apache is stopped; normal MySQL remains running. [Final handoff](usability-u3-bcd-delivery.md) and [review ZIP](usability-u3-bcd-review-package.zip). External source approval and full Atikha acceptance remain separate; no commit/push or later stage. Older entries below are dated history; do not rerun consumed recovery helpers.

**Current execution boundary, October21,2026:** database reconstruction/normal SQL recovery passed, but the user is unavailable for the remaining normal login/MFA application gate. U3-B partial source remains preserved; B integrated checks and C/D have not advanced. See [the recovery handoff](database-logical-recovery-20261021.md). Existing combined B-D authorization persists for automatic resumption only after environment acceptance; requirements and later-stage exclusions are unchanged.

**October21 combined-plan review:** supplied ChatGPT approval is recorded, including committed-selection/existing-contract advisory constraints and separate B/C/D gate reporting. [The combined plan](usability-u3-bcd-plan.md) is ready for the user's explicit implementation instruction; no further broad planning review or B?D implementation has occurred. Earlier pending-review statements below are dated.

**Combined B–D planning update, October 21, 2026:** U3-A is approved and independently verified pushed at `797d47cbbd2f0b8f5ea91e2bd583a8196285ec38`. The user selected one coordinated delivery of B–D; the [combined detailed plan](usability-u3-bcd-plan.md) is prepared for review, not implementation. Once reviewed/approved and explicitly authorized, it supersedes only this document's separate B/C/D external authorization/review boundaries; B → C → D remain internal correctness gates. U3-A and every substantive accounting/evidence/privacy/recovery requirement remain unchanged. The older checkpoint-order wording below records the former sequencing and does not independently authorize any implementation.

**U3-A source checkpoint closed, October 21, 2026:** the user supplied ChatGPT's final exact-source approval of the corrected implementation, with no remaining blocker. Source implementation, integrated verification and the scoped reconciliation correction are complete. [Approval record](usability-u3-a-approval.md) distinguishes external approval from working/Atikha acceptance. The reviewed ZIPs/manifests remain unchanged; this is a documentation-only closure with no new tests or database/server operations. **Next: the user commits/pushes U3-A, then separately requests U3-B planning for review.** U3-B/C/D remain unstarted and unauthorized. No commit/push occurred in this closure. Earlier pending-review statements below are dated and superseded.

**October21 U3-A review correction:** the first-load reconciliation-health fix passed43 presentation and91 focused authenticated checks. [Corrected final-review handoff](usability-u3-a-revision.md) supersedes the pre-correction source state below; scope/authorization is unchanged and U3-B/C/D remain unstarted.

**Current checkpoint update, October 21, 2026:** the user resumed U3-A only after separate working database recovery. Its prepared source is unchanged and pending integrated verification is now complete:269 fresh browser/HTTP checks passed. [Final delivery/exact-source review package](usability-u3-a-delivery.md) supersedes the blocked-verification status below. External U3-A review and working/client acceptance remain pending; U3-B/C/D have not started. This update changes no approved scope or authorization boundary.

**Authorization/update, October 20, 2026:** the user explicitly authorized U3-A only. [Implementation handoff](usability-u3-a-delivery.md) records prepared source and database-blocked integration verification; U3-A is not complete. U3-B/C/D remain unstarted. The user selected a [U2 follow-up before U5](usability-u2-followup-plan.md) and separately authorized read-only database investigation with no repairs after the connection failure. Original planning-only statements below describe the October 17 state.

October 17, 2026. **Detailed planning only.** The user reported committing/pushing U2-C and asked to start the next work. The roadmap's next usability stage is U3. This document prepares it for review; application implementation requires approval of this detailed plan and authorization of the specific checkpoint. No application, test, configuration, migration or database changes occurred in this planning task.

Read with the [usability/completion roadmap](usability-completion-plan.md), [U1 screen specification](usability-u1-design.md), [client context](client-context.md), [selected decisions](project-decisions.md), [Stage 2 rules](stage2-plan.md), [Stage 2 delivery/supplement](stage2-delivery.md), [Stage 3 delivery](stage3-delivery.md), [accepted U2-C delivery](usability-u2-c-delivery.md) and [current status](project-status.md).

## 1. Baseline and inspected behavior

Local HEAD is **ab653a1b59431d6c1ef0fad080f583da64961b8f (U2-C)**. The working tree was clean at inspection. All 124 application files match the reviewed U2-C manifest. Commit history confirms the local checkpoint; the push is user-reported, not a fresh independent remote verification.

U2-A/B/C source scope is complete, including supplied external exact-source approval. Working-system and Atikha acceptance remain separate. U3 implements the selected usability direction around existing version-3 advance services; it does not implement a new advance accounting engine.

Current source findings:

- `cash_advances.php` already supplies register/detail, as-of scope, four balances, operation/deadline history, original/replacement identities, print and authorized actions. Register rows emphasize all four amount columns but omit the available purpose. Full reconciliation is a large primary section. Filters, matching totals across pages and page-only print scope already work and must be retained.
- `assets/js/cash_advances.js` already retains successfully applied results on failures/stale responses, uses applied filters for pagination, and labels totals/printing scope. Reconciliation is independent of employee/project/search/status filters. Reuse these contracts.
- `cash_advance_entry.php` selects dedicated release CDB, liquidation GJ and return CRB workflows, or resumes a saved version-3 draft. Settlement identity comes from its linked advance. Do not route them through ordinary payment/receipt posting.
- The shared entry template deliberately excludes advances and all corrections from U2's focused ordinary presentation. Advance Review is still appended below the editor/documents; recorded screens do not use the ordinary focused result/recovery interface. Return proof confirmation already exists and is server-owned.
- `advance_review()` supplies authoritative canonical input, full lines, coverage and a review token. Its `outstanding` field comes from lifecycle `remaining`: for ordinary version-3 settlements this is the **pre-entry current balance**, not the projected after balance. The validated lifecycle result already contains separate before/after/replacement display values. U3 must not relabel the existing field as a projected result.
- The dedicated action endpoint has no `session` GET action. The existing guarded `accounting_actions.php?action=session` can refresh Admin CSRF without routing an advance draft through an ordinary save/post action. Recovery needs explicit transport selection rather than blindly copying ordinary endpoint assumptions.

These are source-inspection findings, not a new browser audit or current database inspection. Prior U1/Stage 2 captures and tests are dated evidence. Fresh U3 visuals and workflow results belong to implementation verification.

## 2. Scope and checkpoint order

Use four reviewable checkpoints, each implemented only when authorized:

1. **U3-A — Connected register and detail.** Improve read presentation, visible reconciliation health, contextual navigation, eligible actions and scoped printing. Keep current authoring behavior.
2. **U3-B — Release.** Add the narrowly selected version-3 focused state shell, release validation/review/result/recovery and the additive server review projection described below. Liquidation/return and corrections stay on their existing presentation until their checkpoints.
3. **U3-C — Liquidation.** Add focused expenditure/liability/document handling and authoritative gross-support/reduction/balance summaries. Preserve every line, project, note and evidence allocation.
4. **U3-D — Return and integrated U3 handoff.** Integrate reviewed proof and bound confirmation, focused return states and final cross-workflow/privacy/navigation regression evidence.

No redesign of version-4 correction authoring, global U2-C navigation, ordinary U2 forms, OCR routing, reports, budgets, period controls or other completion stages. Reuse existing correction/exact-journal links without changing their provenance or accounting. No migrations, new flags, payload versions, permissions, roles, dependencies or working configuration changes are expected. Any genuine new service/schema requirement beyond the response projection below needs a scoped amendment before implementation.

## 3. U3-A: register and advance detail

### Register screen order

Lead with Cash advances, an accounting-date basis, Admin's Release an advance/My drafts actions and the current applied scope. Show matching outstanding and the existing released/liquidated/returned totals with an explicit all-matching-pages label. Keep historical restatement wording: these are currently recorded postings through the selected accounting date, not a frozen report or the database state that existed then.

Rows emphasize advance number/employee, purpose/originating project, outstanding, due date and separate settlement/overdue status. Full monetary breakdown remains in visible columns or a labelled secondary row area; no amount is discarded. Link the real advance identity to its detail with the applied as-of date. Preserve 25-row pages, deterministic order, all existing filters, search semantics and inactive historical references.

Keep a compact **Control-account reconciliation** health summary visible above results. Put full account/register/ledger/difference/error rows in a secondary disclosure. Any discrepancy anywhere in the independent result must produce a prominent warning even when its advance/account is outside the filtered rows. Explain that server validation determines whether an operation can proceed; do not weaken the existing global lifecycle/reconciliation checks or infer safety merely from a positive balance. A failed refresh retains the previous successful rows, totals, reconciliation and applied date together, visibly identifying the failed refresh; it must not present them as a successful new scope.

Empty register and no matches are distinct from loading/failure. Show the applied date and a useful next action for Admin without seeding an example into the working database. Management receives inspection actions only.

### Detail screen order

Lead with CA number, employee, purpose, original project/control context, selected reporting date and four balances: Released, Liquidated, Returned, Outstanding. Keep original released amount distinct from effective corrected balances; show settlement status and overdue independently.

Eligible Admin actions are **Record liquidation** and **Return unused cash**, already bound to this advance. Keep existing `can_settle`, date, lifecycle and server guards; historical views must not look like current authoring contexts. A historical view can offer a separately labelled link to today's detail rather than silently switching dates. Preserve original/replacement advance links and correction blockers. All these pages keep U2-C's Cash advances current state.

Present a readable posted-operation timeline with operation/date/amount, permitted coverage and exact recorded-journal link. Complete journal lines remain available per operation; original/reversal/replacement distinctions cannot disappear behind an ambiguous net label. Existing correction comparison/start links remain under their existing role/gate rules. Do not invoke the slow comparison reader merely to decorate a timeline or result.

Move deadline history and Admin's audited extension form into clearly named secondary areas. Preserve revision checks, required reason and today's Manila effective date. Retain the draft entered extension values and show a useful failure; do not silently retry a stale extension or manufacture an approval. External approval metadata stays Admin-only and explicitly staff-recorded, not system approval.

Register printing still prints the current page, with all matching totals and page/row scope. Detail printing keeps its selected date, balances and history. Required disclosures must print their content; navigation and mutation controls remain hidden. Management print/HTML/JSON must not acquire private document or proof-confirmation details.

## 4. Shared version-3 focused states and U3-B release

Use **Enter details → Review → Recorded**, with distinct busy, validation, stale-review and uncertain-result states. Introduce explicit advance presentation selection independent of the existing ordinary selector. Version-4 corrections, including advance-linked replacements and reverse-only without advance presentation config, remain on their existing branch. Do not enable liquidation/return early while implementing release. No ordinary `PaymentPresentation` Quick/Split classifier or ordinary two-line balancing rule is applied to a server-generated advance entry.

Preserve IDs, raw draft values, version, workflow kind, submission key, revision, linked identity and document context. Saved complex/invalid data stays inspectable; presentation switches never repair or remove it. Search text is not a committed selection. Use name-first account labels, searchable codes, full long-label text, visible-control errors and linked error summaries. Resolve only the error affected by a committed edit. Keep incomplete private Save draft separate from stricter Review.

Release Enter asks for employee, purpose, accounting date, paying cash/bank account, one amount, originating project, designated advance control and due date. Reference, optional reviewed informational proof and staff-recorded external approval are secondary. Keep explicit control selection; multiple eligible controls must never be silently merged or chosen. No control available leads to authorized Accounting Setup guidance, not automatic designation. Generated cash/control journal lines use the existing authoritative originating-project rules; independent saved fields are not silently rewritten to achieve simplification.

Release Review prominently shows employee, amount, paying account, advance asset/control account, actual project, accounting/due dates, purpose and optional proof state. Show the complete generated journal. Explain that release creates an advance asset, not an expense. A CA number is assigned only after successful posting. Existing-active-advance summaries are advisory and must not overwrite an unrelated status or stop a permitted separate release.

The recorded result contains the returned advance ID/CA link and exact journal ID link. **Release another advance** is explicit and creates a new request; it never deletes a private draft or tops up an existing advance. Reopened posted drafts show read-only recorded results.

### Additive response-only adapter

Permit a small change to `advance_review()` in `includes/cash_advance.php`: project a whitelisted public `advance_effect` from its already computed validated `prepared.timeline` display values. Include the reviewed accounting date, current-balance basis, before/after for existing advances and proposed new-advance outstanding for release. Return exact decimal strings, with null meaning not applicable. Do not expose synthetic internal candidate IDs, full event models, hashes or private evidence snapshots.

The before/after figures describe the current posted balance and projected current balance after the reviewed entry; they are not a fabricated balance at the backdated entry date. Label that basis and the entry's accounting date separately. Full chronological validation remains server-owned.

Keep the existing `outstanding` response field and canonical input unchanged. No changes to normalization, fingerprint/token construction, write lock order, lifecycle calculation, validation, posting or audit. Reuse the already calculated timeline; add no all-history/comparison query. Test the projection against the server's actual prepared result, not a second client formula.

### Leaving and uncertain recording

Offer Stay, Save draft and leave, or Leave without latest edits for controlled dirty navigation. Saving must succeed before leaving. Current task detection must include the actual workflow/advance/draft identity: two different advances on the same PHP path are not the same task. A Cash advances sidebar link from an editor leads to the register and must honor unsaved changes. Preserve native close/tab warnings and existing drafts; no autosave/browser financial storage.

Before posting, freeze the saved draft ID/revision, workflow/advance identity, submission key, reviewed payload and token. An interrupted, malformed or ambiguous posting response means **Check recording result**; disable editing/new identities and block ordinary in-app leaving until resolved. Recovery refreshes only authenticated CSRF through the existing guarded session endpoint, then replays the same request against `cash_advance_actions.php`. It does not resave, rereview, regenerate a key or create another advance. Validate both positive journal and advance IDs before rendering success. Existing flag/authorization failures remain failures; no recovery bypass is introduced.

## 5. U3-C: liquidation

Lead with the linked advance, employee, original control/project and current outstanding context. Keep the original employee/control locked. Actual expenditure/asset line project tags remain independent and visible; originating project is not a promise that the entry is a separately balanced project ledger.

Use **Costs and asset purchases** for supported debit lines, with an explicit liability/withholding section retaining credit amounts and required notes. Do not silently classify taxes/accounts or make noncash liquidation look like another bank payment. Stable line IDs, notes, documents, accepted/declared amounts, excluded reasons, review state and allocations survive edits/resume. Detailed debit/credit presentation remains available for complex or malformed entries; hiding a control cannot hide its error or discard an opposite-side value.

Place documents beside or immediately after the lines they support. Show claimed gross costs, reviewed supported gross costs and net advance reduction distinctly. Client figures are labelled provisional until authoritative Review; Review uses canonical `input.advance_context`, full journal, `coverage` and the new validated effect projection. Keep the full journal visible for liabilities, multiple projects or any material distinction. Server-generated control credit is read-only and never an evidence allocation target.

Required examples: release PHP 10,000, support PHP 8,000, leave PHP 2,000; gross cost PHP 4,500 minus liability PHP 500 reduces the advance PHP 4,000 while requiring PHP 4,500 support. Review also exposes every account, amount, note and actual project, not merely these totals.

Unsupported/partially supported/excess claims remain unposted drafts. No silent trimming, reimbursement or invented payable. Posted unused/excluded image amounts cannot support another partial liquidation; explain before posting. Preserve Stage 3's separate lineage-reuse rules. Informational uploads do not become monetary support and AI does not certify evidence. Record/recovery/resume follow the version-3 state/identity rules above.

## 6. U3-D: unused-cash return

Show linked employee/advance/current balance, accounting date, receiving account and one amount, then required reviewed informational proof. Original control and project context remain fixed under existing rules. No expenditure evidence denominator is invented for return.

Keep **Confirm return proof** as a distinct deliberate server-owned step, showing which advance, amount, receiving account/date and reviewed documents it concerns. Review and final recording remain separate actions. The client can mark confirmation stale immediately after a relevant edit, but only the server's returned confirmation proves it is valid. Amount/account/date/document/review-context changes require fresh confirmation as existing canonical binding dictates; unrelated wording edits follow that existing contract, not a broader invented approval policy. Do not add a time-based proof-confirmation expiry that the existing contract does not define.

Review leads with cash returned, receiving account, actual project/control, proof-confirmed status and authoritative projected current balance, followed by full journal. Required example: PHP 10,000 release → PHP 8,000 liquidation → PHP 2,000 return → zero outstanding. Also cover return-before-liquidation, partial/multiple returns and settlement after correction/inactive original references.

Recorded/recovered results target the original linked advance plus exact returned journal. Any changed proof context or stale lifecycle result must block recording without discarding the draft. Real returned cash uses this workflow, not an accounting reversal. Management sees permitted proof status/aggregate coverage only, never private filenames/URLs, hashes, notes, confirmation actor/time/context or document allocations.

## 7. Source boundaries

Expected production files, selected per checkpoint: `cash_advances.php`, `assets/js/cash_advances.js`, `cash_advance_entry.php`, `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, and scoped `assets/css/accounting_workspace.css`. The only planned server-service edit is the additive `advance_review()` response projection. `accounting_drafts.php` dispatch, `journal_transaction.php`, correction pages, readers/writers and gate helpers are verification dependencies, not planned redesigns.

Preserve version 2 ordinary, version 3 advance and version 4 correction contracts, original IDs and source books; existing Stage 1/2/3 schema/flag prerequisites; exact centavos; historical/current balance protection; generated control lines; atomic journal/operation/evidence/audit writes; one release per advance; fresh review and durable replay; deadline/lifecycle history; all Management privacy boundaries even with UI flags off. No global CSS/Tailwind rebuild unless an actual utility change requires it; scoped rules should suffice.

## 8. Isolated verification and database boundary

Implementation tests use only new guarded disposable databases, copied applications, private synthetic evidence/sessions and blocked browser **and server-side** provider endpoints. Never use working drafts/receipts as fixtures, open the working dashboard or run live OCR/SMTP/forecast/audit-AI. Separate explicit synthetic fixture writes from all-table-fingerprinted register/detail/navigation/print reads.

Migration-009 error 1304 remains an unresolved diagnostic, not U3 work or evidence of repaired database health. Prefer a fresh isolated clone of a validated synthetic full-schema fixture with preserved source/schema provenance. If a new bootstrap uses the accepted explicit disposable-only anonymous-block helper, disclose that boundary again; it does not certify normal migration registration/deployment. Stop/report unexplained fixture failures rather than repairing system tables, changing permissions or rerunning old working migrations. No database operation runs for this planning document.

At each checkpoint, capture and visually inspect affected 1366×768 and 1920×1080 states, with real keyboard focus, error-summary links, long labels and print media. These viewport checks do not establish other-browser or physical-device compatibility. Do not invent assertion counts or claim client task completion from automated checks.

- **U3-A:** both-role register/detail, purpose and full labels, no data/no matches, as-of and historical restatement, applied/draft filters, multiple real synthetic pages and all-match totals, failed/stale reads, print scope, normal/partial/settled/overdue/cancelled/replaced states, deadline extensions and stale revisions. A reconciliation mismatch excluded by display filters must stay prominent; successful reads must not mutate any application table. Privacy must hold in HTML/JSON/print/downloads with entry flags off where existing readers permit access.
- **U3-B:** release validation and generated asset/cash journal, explicit control selection, optional reviewed proof, approval metadata wording, saved/resumed/posted drafts, current-task versus other-advance leaving, failed save, stale review and request recovery. Inject response loss after an actual isolated commit and prove one journal/advance/release operation/audit result. Check cross-session same-owner recovery, changed-payload conflicts and denied other-owner/Management access. Validate server effect fields without changing canonical hashes or token semantics.
- **U3-C:** one/multiple costs, eligible assets, liabilities/required notes, split projects, partial and full supporting documents, declared/accepted/excluded amounts, malformed preserved inputs, duplicate image/retry, no control-credit allocations, evidence-resume identity, unsupported/excess/backdated/racing settlements and withholding denominator/reduction. Review stays unrecorded; uncertain response recovers one liquidation.
- **U3-D:** required proof/confirmation, each bound-context mutation, confirmation invalidation under the actual server contract, expired accounting review tokens, partial/full/pre-liquidation returns, historical balances and lifecycle blockers, identity-preserving retry and corrected advance lineage. Finish register/detail/entry/navigation/evidence privacy integration across all three operations.

Use existing meaningful commands as affected regressions: `scripts/test_stage2.php`, `scripts/test_stage3_checkpoint5.php` for integrated backend preparation/checks; `scripts/test_stage2_browser.py`, `scripts/test_stage3_checkpoint4_browser.py`, `scripts/test_stage3_checkpoint5_browser.py`; ordinary `scripts/test_usability_u2_browser.py` / `scripts/test_usability_u2_b_browser.py`; reader/adapter and U2-C navigation checks as affected. Provide new focused U3 coverage. Earlier U2-C counts are dated evidence, not U3 results. Run against final application bytes, document fixture versus operational checks and any test-tool-only late edits, and repeat only checks affected by a later change/failure. `npm test` is a placeholder.

## 9. Delivery, rollback and completion gate

For each authorized checkpoint, deliver exact changed source/diff and hash manifest, updated report/current status, actual test commands/results, visually inspected synthetic screenshots and explicit limitations. Keep configuration, exports, sessions and private evidence out of review ZIPs. The user retains commits/pushing. Scope the first implementation instruction to U3-A; later checkpoints need their own instructions.

No migration/configuration activation is planned, but edited XAMPP PHP/JS/CSS can serve on refresh; disclose this source-serving effect. A source rollback restores the preceding checkpoint's code after confirming no incompatible format change; it never deletes posted records or claims to reverse legitimate business activity. Working backup/restoration/smoke requires separate scope and normal authentication.

U3 source completion requires connected read views and all three focused operations, truthful balances/support/confirmation, preserved payload/history/privacy/retry behavior, final affected regression evidence and inspected screens. It does not close Atikha observation, working acceptance, restoration, U-PERF-01, U4/U5 or original Stages 4-7.

## 10. Next action

Review this U3 plan, particularly checkpoint boundaries, the response-only balance projection and preserved return confirmation. After plan approval, explicitly authorize **U3-A: connected register and detail**. No U3 application checkpoint, database diagnostic or performance work has started.
