# U3-B–D combined plan: release, liquidation and unused-cash return

**Implementation authorization, October 21, 2026:** after supplying external plan approval, the user explicitly said "Yes, i authorize" for the combined B-D implementation, including both clarifications. Execute the ordered internal gates without another external pause between successful slices. [In-progress delivery](usability-u3-bcd-delivery.md) records execution; the earlier pending-authorization paragraph below is retained as planning history. No commit/push or expansion beyond this plan is authorized.

October 21, 2026. **External plan review approved; explicit user implementation authorization pending.** The user supplied ChatGPT's approval of this combined plan, with the two narrow clarifications incorporated below. No new broad planning review was requested. The user selected one coordinated B–D implementation and final external source-review cycle after U3-A approval. This document does not itself authorize application changes, synthetic posting, migration, working activation, commit or push; the review's embedded implementation prompt is a recommendation for the user to authorize.

## 1. Baseline, evidence and precedence

Local HEAD and a read-only `git ls-remote origin refs/heads/main` both identify **`797d47cbbd2f0b8f5ea91e2bd583a8196285ec38` (U3-A)**. The tracked/untracked working tree was clean before this documentation task. The user reported pushing U3-A; the remote comparison independently confirms that checkpoint. No fetch, checkout, reset, commit or push was performed.

U3-A's source implementation, integrated verification, correction and supplied external exact-source review are complete. Its [approval record](usability-u3-a-approval.md), [delivery](usability-u3-a-delivery.md) and [correction report](usability-u3-a-revision.md) remain dated evidence. Earlier 269 integrated assertions and final correction 43 presentation / 91 focused assertions are not new results for B–D. Working/Atikha acceptance remains separate.

Read this plan with the [original U3 plan](usability-u3-plan.md), [selected decisions](project-decisions.md), [client context](client-context.md), Stage 2/3 contracts and [current status](project-status.md). Once this combined plan is approved and implementation is authorized, it supersedes only the original U3 plan's **separate B/C/D authorization and external-review boundaries**. B/C/D become internal ordered work units within one checkpoint. U3-A and all substantive accounting, privacy, evidence, recovery and verification requirements remain intact. Older statements requiring a new external authorization after each B/C/D slice are historical sequencing, not an additional gate inside the approved combined task.

Source inspected for planning: `cash_advance_entry.php`, `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, `assets/js/payment_presentation.js`, `includes/cash_advance.php`, `includes/cash_advance_lifecycle.php`, the U3-A reports and relevant Stage 2/3 test entry points. No database or live application request was needed for this inspection.

## 2. One delivery, with internal correctness gates

After approval and a separate implementation instruction, execute this order autonomously:

1. Build the dedicated version-3 advance presentation/state infrastructure and release workflow (B); verify its focused service/UI contracts.
2. Add liquidation (C), preserving line/document identity, gross support and liability handling; verify its calculations and evidence behavior.
3. Add unused-cash return (D), preserving the distinct bound proof-confirmation step; verify confirmation and recovery.
4. Run the final affected integration/regressions against final source, inspect screenshots, update handoff and deliver one exact-source review package.

Successful internal checks do not require a new external review or permission question between B, C and D. Fix defects within this approved scope and continue. A genuine authorization, environment or out-of-scope service/schema issue must be preserved and reported rather than bypassed. Completion cannot be claimed with an unverified internal slice. One coordinated delivery does not promise that the final reviewer will find no correction.

## 3. Scope and source boundaries

Implement focused **Enter details → Review → Recorded** workflows for all three dedicated advance operations, with separate busy, validation, stale-review and unknown-recording-result behavior. Preserve normal private Save draft/resume and existing server contracts.

Expected production edits:

- `cash_advance_entry.php`: explicit presentation configuration for eligible dedicated advance entry routes, without weakening role, owner or feature/schema gates.
- `includes/accounting_entry_page.php`: advance-specific shell, labels, review/result/unknown/leave controls and conditional module loading; preserve IDs and existing submission/document bindings.
- `assets/js/accounting_workspace.js`: integrate advance states with existing endpoint calls, draft/evidence machinery, validation, safe navigation and durable same-request recording recovery.
- New small `assets/js/advance_presentation.js`: pure advance view/effect validation and task-identity helpers, loaded only for the eligible advance branch. It must not implement another posting engine or recompute the generated accounting journal.
- `assets/css/accounting_workspace.css`: scoped advance-editor layout, focus, validation, state and print rules. No global redesign or Tailwind rebuild is expected.
- `includes/cash_advance.php`: only the additive public review projection in section 6, plus a pure whitelist helper there if useful. No changes to preparation, canonicalization, lifecycle validation, posting, evidence or audit behavior.

U3-A `cash_advances.php` and `assets/js/cash_advances.js` are integration dependencies, not planned edit targets. Preserve their truthful loading/unavailable/healthy/discrepancy states, applied-result retention, pagination, historical meaning and privacy. If an actual connection defect requires touching a register file, identify the specific need, keep it narrowly within this integration scope and rerun its affected U3-A checks.

Do not redesign version-4 corrections, ordinary payment/receipt/GJ, scalar/manual OCR, global navigation, reports, budgets or period closing. Do not add payload versions, dependencies, migrations, feature flags, permissions, roles, database repairs or working configuration changes. A requirement for a broader accounting/service/schema change needs a scoped plan amendment before implementation.

## 4. Branching, drafts and lossless presentation

The current JavaScript `focused` flag is explicitly ordinary-only; dedicated advances and corrections are excluded. **Do not merely broaden that flag to include advances.** Ordinary-only initialization assumes Quick/Split controls, ordinary document grouping and book-derived nouns. Keep separate ordinary-focused and advance-focused predicates, with explicit version-3 operation selection and `!isCorrection` taking precedence. Reuse state mechanisms deliberately through branch-aware helpers. Version-4 reverse-only and advance-linked replacements stay on their current correction branch, including when an advance config is present.

Do not route advances through the ordinary `PaymentPresentation` Quick/Split classifier. Release and return retain their generated two-sided server journal; liquidation retains explicit independent debit/credit lines and projects. Unsupported, malformed or saved complex values remain inspectable in a detailed view. Changing presentation must never normalize away an opposite-side amount, unknown metadata, invalid value, line note, document allocation or independent saved project field.

Preserve version, workflow kind, draft ID, linked advance identity, revision, submission key, raw draft values, stable line/document IDs and saved evidence/reservation context. Search text is not a committed account/party selection. Use name-first searchable selections, full long labels, visible-control errors and a linked keyboard-focusable error summary. Keep unrelated field errors until their committed values are corrected. Saving incomplete private work remains distinct from server Review requirements.

Read-only review/recorded states must not leave stale editable fields exposed. Back to editing preserves the saved draft; subsequent relevant changes invalidate the accounting review according to the existing contract. No autosave or browser financial storage is introduced. Drafts are owner-private and the existing multi-draft system remains available.

## 5. Release (B)

Enter emphasizes accountable employee, purpose, accounting date, paying cash/bank account, amount, originating project, explicitly selected designated control account and due date. Reference, optional reviewed informational proof and staff-recorded external approval metadata are secondary. Missing eligible control leads to authorized Accounting Setup guidance; do not designate/merge/select one silently. The two historical advance account labels remain unresolved client context and must not be conflated.

Keep generated cash/control project rules and independent saved input intact. The optional existing-advance advisory must reuse the existing authorized `cash_advance_actions.php?action=register` projection filtered by the **committed selected employee ID** (`party_id`), with its current-date basis. Do not add an advisory endpoint, accounting query or alternate service merely for presentation; a genuine limitation requires a reported scoped plan amendment.

Trigger the advisory only when an actual committed employee selection changes, never from searchable-selector typing or blur of uncommitted text. Guard responses with both committed identity and latest request generation so an old A response cannot replace a newer A selection after A → B → A. Use the register's truthful matching count and all-matching totals; never label the first 25 returned rows as the complete set when additional pages exist. If rows are shown, identify their page/limited scope explicitly. Stale/failed advisory responses must not replace a newer selection/advisory, clear valid editor data or write into editor/Review/recording-recovery status. Use an independent advisory region for its loading/failure. Advisory information does not block an otherwise permitted additional release.

Review shows employee, purpose, amount, cash account, advance asset/control, every actual project, accounting/due dates and proof state, followed by the complete generated debit/credit journal. Clearly state that releasing an advance creates an asset, not an expense, and accounting Review is not Management approval. Do not show an assigned CA number before successful recording.

Recorded/recovered results use returned advance ID and exact journal ID. Show View advance, View this transaction and the existing appropriate book/history navigation. **Release another advance** is an explicit new request after a confirmed result, never an automatic top-up or deletion of an existing private draft. Reopening a posted draft shows the same read-only recorded identities.

## 6. Authoritative review-effect response

The current `advance_review()` returns canonical input, generated lines, party, coverage, `outstanding` and token. Its `prepared.timeline` already includes exact display values from lifecycle validation. The current `outstanding` is derived from `remaining`; it is **not** to be relabeled as the proposed post-entry balance.

Add a response-only `advance_effect` object, whitelisting:

- `accounting_date`: the already validated timeline accounting date.
- `balance_as_of`: the server's `journal_today()` date, labelled as the current posted-balance basis.
- `before_outstanding`: `timeline.before_display`, nullable when creating a new release.
- `after_outstanding`: `timeline.after_display`, nullable when creating a new release.
- `new_advance_outstanding`: `timeline.replacement_display` for the proposed new release, otherwise null.

Amounts are exact decimal strings; null means not applicable, never a fabricated zero. Preserve all existing response fields and canonical input. Project only these display values from the already computed prepared result; add no all-history/comparison query, internal candidate ID, full event model, fingerprint or private snapshot. Compute the projection outside the canonical hash/token input. Do not alter lock ordering, normalization, validation, token/fingerprint generation, write version, posting or audit.

Before/after describes today's posted balance and its validated projection after the reviewed entry. The entry's accounting date is separately visible; these values are not a fabricated historical balance at a backdated entry date. Chronological sufficiency remains server-owned. The client validates the effect shape and decimal strings and renders it without floating-point arithmetic or a second journal/balance formula. If the required projection cannot be verified, retain the draft and show a review error/Refresh review, not an invented summary or confirmed-recording control. No posting request has yet occurred in this state.

## 7. Liquidation (C)

Lead with linked CA identity, employee, originating project/control and current outstanding context. Employee/control remain fixed under existing server rules. Actual cost/asset/liability line projects remain independently visible; an originating project does not promise an independently balanced project ledger.

Use **Costs and asset purchases** for supported debit lines and an explicit liability/withholding area retaining credit amounts and explanatory notes. Do not introduce cash movement, infer an account's tax treatment, hide a saved credit, or lose unknown/invalid data. Keep detailed debit/credit presentation available. Generated control credit is read-only and never a monetary evidence allocation target.

Place reviewed documents beside or immediately after supported lines. Retain claimed/declared amount, accepted reviewed amount, allocated amount and excluded reasons. Clearly distinguish gross claimed costs, gross reviewed support and net advance reduction. Before Review any displayed calculations are provisional; Review uses canonical server `advance_context`, full generated journal, coverage and `advance_effect`.

Required examples:

- PHP 10,000 release; PHP 8,000 fully supported expenditure; projected PHP 2,000 outstanding.
- Gross supported cost PHP 4,500; liability credit PHP 500; net advance reduction PHP 4,000. Monetary evidence must support PHP 4,500, not merely the net reduction.

Unsupported/partly supported/excess claims remain unposted drafts; do not trim amounts or invent a reimbursement/payable. Informational proof adds no monetary coverage. Once an image is posted, its unused/excluded amount cannot support another normal liquidation; explain this before recording while preserving Stage 3's separate authorized lineage-reuse rules. AI/upload does not certify evidence. Keep duplicate upload/retry, manual review, saved allocation identity and proof privacy intact.

Recorded/recovered liquidation links to the original advance plus exact returned journal. Repeated partial liquidations, backdated entries, racing settlements and corrected/inactive original references remain governed by existing server lifecycle and resource checks.

## 8. Unused-cash return (D)

Enter emphasizes linked CA/employee, current outstanding, accounting date, receiving cash/bank account, one return amount and required reviewed informational proof. Original control and project rules remain fixed. Do not create an expenditure evidence denominator or require monetary allocations for a cash-return proof.

**Confirm return proof** remains a separate deliberate server-owned action before accounting Review and final recording. Show its bound advance, return amount, receiving account, accounting date and reviewed documents. The inspected canonical context includes draft/workflow/advance identity, amount, cash account, entry date and canonical reviewed proof (including verified bytes); retain this exact binding. Relevant edits clear/stale the visible confirmation immediately, and only the server's returned confirmation proves validity. Unrelated wording edits follow the existing context contract. Add no invented proof expiry, approval policy or confirmation bypass; accounting review-token expiry remains separate.

Review shows amount received back, receiving account, every actual project/control, confirmed-proof status, validated current balance projection and the full journal. Required end-to-end example: PHP 10,000 release → PHP 8,000 liquidation → PHP 2,000 return → PHP 0.00 outstanding. Also verify return-before-liquidation, partial/multiple returns, changed proof contexts, inactive historical references and corrected advance lineage.

Recorded/recovered result retains the original advance and exact returned journal. A changed context or lifecycle rejection leaves a recoverable private draft. Real returned cash uses this workflow, not a journal reversal. Management retains only its permitted financial/coverage projection: no private filenames, URLs, hashes, notes, proof actor/time/context or document allocations in HTML, JSON, print or downloads, including with entry flags off where read access remains supported.

## 9. Leaving, uncertainty and recording identity

Controlled dirty navigation offers Stay, Save draft and leave, or Leave without latest edits. Saving must succeed before navigation; failed save retains input and the dialog. Current-task detection must include known workflow, linked advance and draft identity, with canonical query ordering/representation, rather than just the PHP path. Two different advances on `cash_advance_entry.php` are different tasks; a loaded draft's own equivalent URL is the same task. Cash advances sidebar navigation from an editor leads to the register and honors dirty state. Preserve native tab/close warnings and ordinary/correction behavior.

Freeze the reviewed durable request **before** sending a post: draft ID/revision, submission key, canonical payload, workflow/advance identity and review token. Interrupted transport, malformed response or unverifiable returned posting IDs mean **Check recording result**. Never show Recorded from only a journal ID when the required advance ID is missing/invalid. Disable editing/new identities and block controlled in-app leaving while unresolved. Keep the exact frozen request, not a resaved/rereviewed draft.

Recovery obtains only authenticated CSRF from the existing guarded **`accounting_actions.php?action=session`**, then retries that same post through **`cash_advance_actions.php`**. The advance endpoint has no session GET action; do not invent one or route the financial POST through the ordinary endpoint. Preserve the returned `advance_id` when invoking shared recorded rendering; the current ordinary helper only forwards the journal ID and must not be reused unchanged for new releases. Accept only verified positive returned journal/advance identities. Test same-owner cross-session/expired review-token replay and changed-payload conflicts. Other-owner, Management, gate and schema failures remain failures; do not bypass authorization or obtain a new submission identity to escape an uncertain result.

Do not synthesize success from a failed read or lose an unknown state after a second ambiguous recovery attempt. If the server says recording is unavailable, retain the durable request/draft and explain the actual failure; no new financial write workaround is permitted.

## 10. Verification plan and database boundary

Planning performs **no SQL, server start/stop, clone, migration, seed, browser login or financial action**. Later authorized implementation verification uses new guarded `atikha_test_*` databases, copied applications, isolated configuration/sessions and visibly synthetic private evidence. Validate the synthetic source schema/provenance before cloning; never clone working drafts/receipts as fixtures. Block external browser requests and server-side OCR/SMTP/forecast/audit-AI transports/endpoints in child test runtimes without changing working configuration.

Use a fresh validated full-schema synthetic clone for each independent financial suite. Do not blindly invoke bootstrap-oriented `test_stage2.php` / `test_stage3_checkpoint5.php` on an occupied clone: inspection shows their require chain reaches earlier fixture/bootstrap runners. Add focused combined backend/API checks that operate on guarded complete-schema clones. Use existing meaningful suites as the coverage reference and run affected browser regressions on their own fresh fixtures. No normal migration-registration success is claimed from cloning or the old test-only inline-routine path. Migration-009 error 1304 remains a separate diagnostic; never rerun old working migrations or repair system tables to make tests proceed.

Required final evidence:

- New pure advance-adapter checks: branch selection, exact decimal/null effect validation, safe text, task identity, lossless invalid/complex values and nonmutation. These are view checks, not financial posting evidence.
- New focused backend/API suite, provisionally `scripts/test_usability_u3_bcd.php --fixture=<fresh-private-fixture>`: projection matches the actual validated prepared timeline for all three operations/backdated cases; existing response/input/hash/token semantics remain compatible; Review makes no posted journal/operation/evidence/audit mutation; authorized normal posting and replay retain all service invariants. Test canonical input stability and projected fields without a parallel accounting formula.
- New authenticated browser suite, provisionally `scripts/test_usability_u3_bcd_browser.py --fixture=<its-own-fresh-fixture>`: each B/C/D editor, linked register/detail/result navigation, posted-draft resume, Save/Stay/Leave, failed save, validation/focus, review invalidation, busy state and uncertain-result blocking. Deliberately lose the response **after an actual isolated commit for each operation**, then replay the exact durable request and prove exactly one journal/advance operation/audit outcome; release also creates exactly one new advance.
- Release cases: explicit multiple/no control choice, optional reviewed proof, explicit external-metadata meaning, independent projects, inactive/current resources and a permitted second release. Advisory checks must prove selector typing makes no advisory request, actual committed employee changes use the existing filtered register contract, multi-page counts/totals are truthful, A → B → A races are guarded, and stale/failed advisory requests cannot overwrite editor/review/recovery state.
- Liquidation cases: one/multiple costs and eligible assets, liability notes/withholding, multiple projects, full/partial/malformed preserved evidence, duplicate image/retry, no control/liability-credit support, unused/excluded-image restriction, unsupported/excess/backdated/racing settlements and saved/resumed evidence identity.
- Return cases: missing proof, manual review/confirmation, every actually bound-context mutation, unchanged unrelated wording, review-token expiry, invalid account/amount/date, partial/full/pre-liquidation and multiple returns, corrected/retired advances and preserved proof/draft state after rejection.
- Existing `test_usability_u3_a_browser.py` / readback as affected: register health state, last-success snapshot, correct timelines/as-of/deadline history, exact journal/result links and evidence-bearing Admin/Management HTML/JSON/print/download privacy. UI flags off must not expose restricted evidence; unsupported entry gates must fail closed.
- Existing `test_stage2_browser.py`, `test_stage3_checkpoint4_browser.py`, `test_stage3_checkpoint5_browser.py`: all are required affected regressions because the shared template/controller and review response are involved. Include advance-linked reverse-only/replacement branch exclusion and Stage 2 off with otherwise eligible ordinary corrections.
- Existing U2-A/payment and U2-B/receipt/GJ browser suites plus adapter/reader checks as affected by shared controller/template edits. Keep scalar/manual OCR routing and ordinary Quick/Split/Advanced behavior intact; do not call live extraction. Use U2-C navigation/gate checks where entry/sidebar/task changes exercise them. Do not weaken existing assertions merely to fit the new UI.

At final-source time, identify commands/fixtures actually run, counts, uncaught JS errors and exact tested bytes. Never advertise old 487/269/43/91 results as new combined-checkpoint results. `npm test` remains a placeholder. After a later scoped fix, repeat only genuinely affected checks, documenting which earlier results concern different bytes.

Separate deliberate synthetic service writes from all-application-table fingerprinted read/navigation/print phases. Working application data/schema/object/permission and source/config preservation checks, if used, are bounded read-only checks; they are not fixture creation or working acceptance. No working finance write, upload, grant, configuration edit or server operation is authorized by the implementation scope.

Capture and inspect 1366×768 and 1920×1080 native/full-shell states for each operation: Enter with long labels, visible linked validation, Review/full journal, Recorded/recovered and unknown result; also failed save/proof changes and scoped print. Include keyboard focus and disclosure behavior. Screenshots with synthetic response doubles must be labeled. Desktop Edge viewports do not prove other-browser/physical-device compatibility, PDF pagination or uncoached Atikha task completion.

## 11. Acceptance, handoff and rollback

The combined source checkpoint is ready for external review only when all three workflows and the shared infrastructure are implemented, every required affected verification completes or a genuine limitation is explicitly unresolved, and no accounting/privacy/recovery blocker remains. An incomplete required gate means an incomplete checkpoint, not a quietly deferred test.

Deliver **one** B–D report and exact-source review ZIP: changed application/test source, full baseline patch from `797d47c…`, SHA-256 and normalized Git blob manifest, approved plan, reviewer notes, exact final commands/counts, screenshot gallery and explicit test/operational boundaries. Exclude configuration, credentials, MFA/session data, SQL exports, working evidence and private fixture artifacts. Update status, README/U3 overview and decisions with selected scope and actual outcomes. User retains commits/pushes; external source approval is separate from working/client acceptance.

The final handoff must record **B release, C liquidation and D return internal gate results separately**, before the combined final-source integration/regression results. For each slice state the implemented scope, actual commands/assertions, source revision, pass/failure/unrun status, focused screenshot evidence and unresolved limitations. Separate slice evidence can share a runner but must identify which cases prove each gate. Do not sum overlapping assertions twice or bury an incomplete/failed slice in an aggregate count. One implementation authorization changes review sequencing, not this evidence requirement.

No migration/activation is planned, but edited XAMPP source can serve on refresh; disclose that effect and avoid treating intermediate internal slices as a completed working release. A source rollback restores the approved U3-A code after checking compatibility; it does not delete legitimate journals, operations, drafts, audit or evidence. The review projection is additive and must not create a stored-format incompatibility. Working deployment/backup/acceptance needs separate scope and normal authentication.

U2 follow-up before U5, U-PERF-01, U4/U5, original Stages 4–7, full working/Atikha acceptance and the separate migration-009 diagnostic remain outstanding. No new client accounting policy has been selected by combining delivery checkpoints.

## 12. Next action

The supplied ChatGPT review approved this plan, with the advisory and separate-gate reporting clarifications now incorporated; no additional broad planning cycle is required. Next, the user explicitly authorizes **the combined U3-B–D implementation**, covering the ordered internal work and one final handoff. Do not infer implementation authority from the supplied review or its embedded execution prompt. Once authorized, continue B → C → D → final verification without an external pause between successful internal slices.
