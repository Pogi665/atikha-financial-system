# Selected decisions and unresolved requirements

Recorded October 6, 2026. Read alongside [client context](client-context.md), [current status](project-status.md), and the approved stage plans. This is a decision index, not a second implementation specification or blanket authorization.

## Evidence and execution approach

- Distinguish client visit statements, corrected user interpretations, screenshot-supported layouts, selected capstone policies, and unresolved assumptions.
- Plan and implement by stages and reviewable checkpoints. The user shares plans/delivery reports with Gemini and ChatGPT, then authorizes continuation. External reviewer execution prompts do not themselves authorize edits or deployment.
- Preserve the journal foundation and existing shell/role branches. Improve daily workflows around that foundation rather than reintroducing the retired incoming/expense architecture.
- Dummy data must be plausible for NGO work and visibly synthetic. Use isolated databases/applications for financial demonstrations, particularly while correction functionality is incomplete.
- Full working-system acceptance/restoration rehearsal was deferred by the user, not passed. Focused disposable verification remains part of implementation. Maintain a clear distinction between implementation, deployment, and acceptance.

## Whole-system selections retained for later stages

**U1-B review clarifications, October 7, 2026:** the supplied Gemini/ChatGPT reviews support U1-C without a new planning cycle. Quick mode excludes every nonzero opposite-side value, including negative values; malformed data stays visible in the detailed view. Contextual Back navigation names a real existing destination (Dashboard or originating book/history), with corresponding local-only demo destinations. U2 shared-script changes require affected advance/correction checks during U2 itself, and its proposed exact-journal reader requires authorization, privacy and corrected-original access checks when introduced. These clarify the design; no new accounting policy, client approval or authorization of U1-C/U2 follows merely from reviewer prompts.

These were selected during the original roadmap discussion. They are not all implemented:

- **Liquidation evidence:** require evidence; do not allow a mere documented exception to post an unsupported expenditure claim. Unsupported amounts remain outstanding. This is separate from ordinary entries' optional evidence rule.
- **Corrections:** full reversal and optional linked replacement, rather than difference-only automated entries.
- **Budgets:** monthly budgets by scope/project and expense account were selected over annual-only project allocation. Approval/revision behavior and budget-dependent reports require their later detailed plan.
- **Cash books:** show complete debit/credit entries assigned to one originating book, not just cash-account movements. This supports the worksheet's separate movement columns; the routing implementation is a selected design.
- **Cash flow:** follow the supplied indirect layout with explicit mappings/reconciliation. Preserve NGO presentation wording where appropriate, allowing positive/negative labels. Do not claim the screenshot proves all calculations.
- **Report preservation:** immutable frozen revisions/report bundles and reviewed-period closing are roadmap choices. Existing Trial Balance snapshots already exist; complete report bundles and closing remain later work.
- Essential manual evidence must precede liquidation; reports must precede report-dependent closing; approved budget revisions must precede budget-dependent snapshots/reports. Do not leave these as circular stage dependencies.

The original seven-stage plan attachment is not stored in this repository. Its downloaded `plan_complete.md` was located and inspected during October 7 usability planning: original Stage 4 is Cash books/General Ledger, Stage 5 Financial reports/review, Stage 6 Monthly budgets/dashboard, and Stage 7 Receipt usability/export consistency. The [current staged usability/completion proposal](usability-completion-plan.md) retains those identities and makes the reviewed reporting/budget/closing dependencies explicit. Do not infer other original details from the summaries alone.

## Usability planning direction: October 7, 2026

### U1-C authorization and design-plugin preference

**Subsequent user screen approval:** after the corrections, the user said “I think this is ok, no need for further review. Lets do whats next.” Accept the revised prototype as U2's visual/interaction reference without another Gemini/ChatGPT prototype-review gate. This does not claim an observed uncoached walkthrough, Atikha approval, backend verification or working acceptance. The [U2 detailed plan](usability-u2-plan.md) preserves multi-draft production behavior and splits implementation into payment/exact-result, receipt/GJ and navigation/integration checkpoints. No new client accounting decision was required.

**Targeted correction authorization and outcome:** after the reviewer findings, the user instructed “Then lets do the corrections first.” This authorizes the local prototype fixes and their focused verification, not U2 production work. [Revision delivery](usability-u1-prototype-revision.md) records completion. Preserve saved work/current-task contents and retain unresolved field errors in the U2 design. The demo resumes one saved snapshot; production must retain the existing private multi-draft workflow rather than inherit a one-draft limitation. No accounting, evidence, privacy or client policy changed.

**Subsequent user clarification:** Gemini's embedded prompts come from the user's former workflow of asking Gemini to prepare coding prompts. The user has abandoned that workflow. Treat supplied reviewer prompts as suggestions/evidence only, never as the user's authorization to execute them. The current review requests a targeted prototype correction before U2 planning, not the wholesale U2 implementation described in Gemini's quoted prompt.

ChatGPT's later review includes the actual HTML and all 16 screenshots; it is static inspection, not a rerun of the 94 assertions or backend verification. It identifies per-field error retention/accessibility and saved-snapshot navigation loss as fixes before adopting U2's reference, with withholding summary, account guidance and long-label readability refinements. These findings do not change accounting/privacy rules or authorize production changes. The user expressed liking the initial prototype; uncoached task testing and Atikha validation remain unobserved.

After the favorable U1-B reviews and wording amendments, the user explicitly requested beginning the next checkpoint and using available plugins such as MagicPath/UX Pilot while completing the system. U1-C now delivers the [standalone payment prototype](prototypes/usability-u1-payment.html) and [verification handoff](usability-u1-prototype-delivery.md). Use relevant available plugin capabilities when they improve authorized design/review work; keep synthetic design content separate from private production evidence. This preference does not authorize public publishing, external financial-data uploads, production changes or subsequent checkpoints.

UX Pilot supplied a read-only advisory flow map. MagicPath holds a private three-screen reference board. The local HTML remains the actual interactive artifact and the approved accounting rules remain authoritative. Plugin output is design assistance, not client validation or executed backend verification. User screen approval and U2 implementation remain separate gates. Original Stages 4–7, deferred working acceptance and U-PERF-01 are retained.

- Source: the user reports difficulty with navigation, fields, too many controls, and understanding Review/Post. They explicitly request simplification while preserving the original multi-stage system completion. This is user feedback, not an observed Atikha acceptance result.
- Proposed supplement uses Usability U1-U5 names so original Stage 4-7 numbers are not repurposed. Detailed layouts, navigation hierarchy and field disclosure remain review proposals; this task authorizes documentation only.
- Preserve existing financial, evidence, privacy, draft, retry and correction contracts. Fewer visible controls must not silently modify hidden allocations or remove required review/confirmation. Initial autosave is deferred; current Review already saves the draft.
- The user deferred working browser acceptance after Stage 3 activation. Current-data restoration and protected-file byte verification remain deferred. Focused isolated verification remains required for application changes.
- Corrected source finding: current CRB/CDB sidebar links open books, with New-entry links inside. Earlier brainstorming inferred direct entry-form destinations from the old screenshots; do not treat that inference as current behavior.
- Both supplied roadmap reviews support detailed U1 planning. ChatGPT's recommended unsaved-navigation rules, lossless simple/complex form criteria and U1 comparison timing baseline are included in the [detailed U1 plan](usability-u1-plan.md). These are proposed implementation/design contracts pending stage review, not newly confirmed Atikha policies. The existing native dirty-state warning and Review-save behavior must be preserved and clarified.

### Detailed U1 reviews and U1-A authorization: October 7, 2026

Both supplied detailed-plan reviews support starting U1. The user then authorized U1-A only: current-workflow inspection/capture, field/state inventory, isolated advance-comparison timing and the baseline document. Reviewer execution prompts do not authorize U1-B/U1-C/U2 or working changes.

Resolve ChatGPT's wording clarification consistently with the selected outcome: a small safe clickable payment prototype is required in U1-C; Figma/MagicPath remain optional. The prototype is not built during U1-A. Existing accounting, evidence, privacy and unsaved-change rules are retained; measured presentation/performance findings in the [baseline](usability-u1-baseline.md) inform subsequent specifications rather than creating client accounting policies. The original completion Stages 4-7 remain retained.

### U1-A report reviews and U1-B authorization: October 7, 2026

Both supplied report reviews support progressing. Gemini's two responses are identical recommendations. ChatGPT's first report review lacked the linked artifacts; its later review inspected the nine images and recalculated the CSV medians, while explicitly not exercising the application or checking server-side access. The user then authorized U1-B only. The [screen specification](usability-u1-design.md) is delivered for review; U1-C and production changes are not authorized by those reviewer prompts.

Carry the proposed single truthful status, lossless quick/split/advanced representation, focused views, exact-journal access and retained evidence safeguards into screen review. Management's permitted confirmed-proof summary means status/aggregate coverage, not private document or confirmation context. Comparison delay remains diagnostic follow-up U-PERF-01; its cause and working performance are unresolved. No accounting policy or client permission was changed. Proposed labels, adapters and route choices remain design proposals pending review/implementation.

## Stage 1 operational decisions

See [delivery](stage1-delivery.md) for the implemented contracts:

- Separate receipt and payment pages; private database-backed drafts; inline eligible project/party creation.
- CRB/CDB each have one generated cash/bank line. Noncash allocations form the counterpart; transfers use GJ under their separate rules.
- Party is required for ordinary receipt/payment; ordinary GJ permits no party. Preserve payer/payee even though the superseded legacy note suggested removing it.
- Native record IDs remain authoritative beneath searchable controls. Query typing does not change financial selection or review state.
- My Drafts dates mean last saved dates in Asia/Manila, not accounting dates. Storage remains UTC; failed/stale requests preserve the last successful list.
- Ordinary supporting documents are optional. Attached documents require review, and coverage labels must be truthful. Informational documents supply no monetary coverage.
- CDB evidence covers noncash debits, CRB covers noncash credits, ordinary GJ summarizes the sides separately. A PHP 4,500 expense / PHP 500 withholding payable / PHP 4,000 cash payment uses PHP 4,500 expenditure coverage.
- Posted party/project labels are snapshots. Line project tags are authoritative; Organization operations is no project. The default project is a shortcut, not another accounting authority.
- Unclassified historical entries remain Legacy General Journal; do not guess their source books or invent evidence review amounts.
- Protect designated advance controls and draft-reserved images through old and new endpoints, even with UI flags off. Multiple eligible control designations are supported without assuming both visible advance labels mean the same thing.
- Successful matching durable retries recover the original posting across sessions/expired tokens; changed contents under the same key conflict.
- Posted result links go to the appropriate all-date book and Journal History lists; they do not claim an unsupported exact-journal filter.

## Stage 2 cash-advance decisions

The [approved Stage 2 plan](stage2-plan.md) controls exact validations:

- One posted release per advance; later additional money is a new advance, not a silent top-up. Normal release requires an active person employee, eligible cash account, designated noncash Debit-normal Asset control, and active selected project or Organization operations.
- Release: control debit, cash credit, CDB. It is an asset, not an expense.
- Liquidation: eligible expenditure/asset debits, optional eligible liability credits, and a server-generated original control credit, GJ. **Advance reduction = expenditure/asset debits minus liability credits.**
- Require full reviewed monetary coverage of gross expenditure/asset debits. Do not count the control credit again or use informational proof as expenditure support. PHP 4,500 cost minus PHP 500 withholding reduces the advance by PHP 4,000 while requiring PHP 4,500 support.
- Return: receiving cash debit, original control credit, CRB. Require reviewed proof and explicit confirmation bound to advance, amount, bank/date, and document context. Changes require reconfirmation.
- Release proof is optional; any attached proof must be reviewed. Returns require reviewed proof. Release/return documents are informational rather than expenditure allocations.
- Partial liquidations/returns are permitted. Excess/unsupported settlements fail without silently trimming amounts or generating automatic reimbursement/payable workflows.
- Outstanding derives from linked posted control lines, not cached UI totals or evidence sums. Drafts are excluded. Settlement links/journal/audit writes are atomic and current balances are locked/rechecked.
- Reconcile all advances per designated account independently of register employee/project/status filters, using a consistent snapshot and checking operation-to-control-line links in both directions.
- Accounting dates cannot be future dates; settlements cannot precede release. Staff enter a due date. Audited extensions take effect today in Manila and preserve historical deadlines.
- Settlement status and overdue are separate. Due today is not overdue. Buckets: 1-30, 31-60, 61-90, and over 90 days. Historical views use accounting dates and effective due history.
- Preserve narrow inactive-original employee/project settlement exceptions; do not change the original employee/control or allow arbitrary inactive new references.
- Management receives permitted posted financial/coverage summaries, not advance drafts, images, hashes, per-document review/approval details, or mutation access. This applies to existing books/history and downloads, not just new screens, with flags on or off.
- Ordinary endpoints reject advance v3 drafts. All dedicated draft/evidence actions preserve their workflow context. Posted images are not automatically reusable for a different partial liquidation.
- A posted image's unused or excluded portion cannot support another liquidation. Explain this before posting, including an exact excluded-amount notice for partially accepted monetary documents. This does not remove Stage 3's separately validated correction-lineage reuse.
- The Stage 2 register prints its current 25-row page, with the applied filter labels and page/row range. Displayed totals cover all matching advances, while reconciliation covers all advances for each control account through the same date. Label these different scopes; do not imply a full-register export. This existing scope was clarified in the user-authorized October 6 completion supplement.
- No new digital approval, payroll/tax automation, automatic reimbursement, or foreign-currency engine was authorized by this stage.

## Stage 3 explicit choices and review amendments

The [approved Stage 3 plan](stage3-plan.md) controls exact validation and checkpoint order:

- Preserve each original; generate its exact opposite by swapping debit and credit on original lines, not storing negative line amounts.
- Allow reversal-only for a duplicate/erroneous record, requiring a reason and clear accounting preview. An actual cash return is a return/receipt workflow, not an accounting reversal.
- Generated reversals originate in GJ. Ordinary replacement defaults to its original book, with validated explicit book changes; advance replacements retain their operation's CDB/GJ/CRB routing.
- Default correction date to today in Asia/Manila; allow a valid date between target accounting date and today. Require an earlier-date reason. Reversal/replacement share one accounting date and post atomically; UTC posting time remains separate.
- Require full historical advance-balance validity, not just a nonnegative balance today. These checks must also reach ordinary Stage 2 backdated settlement posting before advance corrections become available.
- A release cannot be corrected while effective posted settlements remain. A corrected settlement's effective replacement still blocks it. Show blockers; do not silently transfer settlements to another advance.
- Replacement releases create new advance identities and require active employee/project/account references under normal release validation, even when matching the old IDs. They cannot inherit ordinary/linked-settlement inactive-reference exceptions.
- Each target receives at most one correction. Correct a subsequent replacement when necessary; do not directly correct generated reversals. Preserve root/parent and line mappings.
- CRB/CDB retain original/replacement entries and **gross originating book activity** labels. GJ supplies offsets. Never label PHP 19,000 gross CDB activity from a PHP 10,000-to-9,000 correction as the corrected payment; ledger net is PHP 9,000. Do not subtract originals twice.
- Correction evidence reuse follows the immediate target's valid association with fresh manual review/allocations. Preserve original bytes/hash, primary ownership, review, and history. Readers/downloads use the relevant association rather than only the original primary journal.
- Successful correction transaction releases only other drafts' reused posted-image reservations targeting that exact journal. Preserve those drafts, entered values, private uploads, ordinary unposted reservations, and all posted evidence. Rollback restores cleanup.
- Reopened stale drafts explain that the journal is already corrected and cannot post; stale review/post tokens fail on the server. Missing reservations are tolerated only for this verified stale cleanup, not unexplained corruption.
- Private v4 drafts use dedicated routes; ordinary/v3 endpoints reject them. Replacement releases' new advance IDs must be recognized consistently by posting/readers/recovery/reconciliation.
- Existing frozen reviewed Trial Balance revisions remain unchanged; relevant live fingerprints change when a correction falls within their date range. Full report bundles and period closing remain excluded.

Checkpoint 3 implements ordinary posting only. Advance posting/reconciliation integration remains Checkpoint 4; do not interpret this decision list as saying all Stage 3 rules are already operational.

### Checkpoint 3 delivery review follow-up: October 6, 2026

The user supplied Gemini's and ChatGPT's reviews of the Checkpoint 3 delivery. Both support moving to Checkpoint 4; no report-level blocker or further planning revision was reported. The user subsequently accepted that delivery and authorized the detailed Checkpoint 4 planning/source-comparison task, with documentation changes only. These reviews do not independently verify the new implementation. Their suggested execution prompts do not expand the approved scope or constitute implementation/deployment authorization.

For final integrated verification, retain ChatGPT's requests to replay the **original ordinary posting request after correction**, recovering its original journal without reposting, and to execute the **complete affected regression suites against the fully migrated schema**. Correction-request recovery and cumulative counts of earlier pre-migration suites do not substitute for these two cases.

Retain the precise stale-cleanup rule above: only competing reused-image reservations for the exact corrected target are released. Gemini's description of unlocking receipts must not be generalized to unrelated or ordinary private reservations.

### Checkpoint 4 planning disposition: October 6, 2026

The [detailed Checkpoint 4 plan](stage3-checkpoint4-plan.md) preserves the approved policies; no new client fact, accounting policy, migration or feature activation was accepted in this task. Shared dated validation must serve correction posting, normal Stage 2 settlements and register/reconciliation readers. Source inspection found an existing correction-aware integrity validator to reuse, not a reason to design different accounting rules.

Current `stage3_enabled()` is stricter than master section 10: it requires Stage 2 for ordinary corrections as well as advances. Preserve current gating during Checkpoint 4 and retain the discrepancy for Checkpoint 5's availability review. This records an implementation difference, not a newly selected universal requirement.

### Checkpoint 4 detailed-plan external reviews: October 6, 2026

After the Scan Receipt supplement, the user supplied Gemini's and ChatGPT's reviews of the detailed Checkpoint 4 plan. Both support implementation without another planning revision. No new accounting policy or module was requested. Preserve shared historical validation for normal settlements and corrections, new identities for replacement releases, original identities for replacement settlements, advance-specific durable hashes, atomic links/cleanup, and reconciliation of both affected control accounts.

ChatGPT explicitly retains the feature-gate discrepancy for Checkpoint 5: current code requires Stage 2 for ordinary corrections too, while the master permits ordinary corrections with Stage 1. Keep the stricter gate during Checkpoint 4; resolve or document it during final availability verification. Their reviews do not independently verify uncommitted implementation or test results. Reviewer execution prompts do not authorize working migration/activation or substitute for the user's implementation instruction.

## Scan Receipt supplement decision: October 6, 2026

The user requested a layout improvement and diagnosis before Checkpoint 4, then explicitly chose **General Journal only; improve the layout and extraction failure handling**. Preserve the existing manual General Journal handoff and explicit posting controls; do not introduce receipt/payment/advance routing as part of this supplement.

The delivered supplement adds local preview, clearer results and safe failure guidance. A read-only inspection showed provider HTTP 503/high demand for the reported attempts. This is a diagnosed service failure, not evidence that the image was unreadable. Completed attempts remain immutable; old provider errors are interpreted for display only. No model/configuration substitution or automatic retry policy was selected. Live provider success and real-image accuracy remain unverified. See [delivery](scan-receipt-supplement-delivery.md).

## Checkpoint 4 implementation record: October 7, 2026

The user authorized implementation of the reviewed plan. Its [delivery](stage3-checkpoint4-delivery.md) records the shared dated lifecycle, operation links, identity-aware recovery and register integration. No new client policy was inferred and no selected accounting choice was reopened. The transaction-owned replacement helper creates its new identity before the immutable correction snapshot, while all writes remain in one validated atomic transaction. The stricter Stage 2 gate for ordinary corrections remains a Checkpoint 5 discrepancy; working deployment and broad acceptance remain separate.

## Checkpoint 4 delivery review carry-forward: October 7, 2026

Both supplied external reviews support proceeding to Checkpoint 5 planning. No new client accounting policy or module was selected. Gemini recommends correcting the ordinary-correction availability gate to the existing master contract; ChatGPT likewise requires resolving the discrepancy. Plan separate ordinary/advance prerequisites, original ordinary-request replay, complete already-migrated regression coverage, and final deployment/recovery guidance. Reviewer execution wording is not implementation or migration authorization. See [review disposition](project-status.md#checkpoint-4-external-review-disposition-october-7-2026).

## Checkpoint 5 planning record: October 7, 2026

The [detailed supplement](stage3-checkpoint5-plan.md) applies the existing master availability decision rather than selecting a new client rule. Ordinary entry requires Stage 1/Stage 3 flags and complete prerequisite schemas; advance entry additionally requires the Stage 2 flag. Complete migration 020 remains required structurally for 021. The proposed paused-workflow behavior blocks advance correction draft actions/retries while Stage 2 is off, preserves drafts/evidence, and retains permitted posted readers; this is an implementation default for review, not a newly confirmed Atikha policy. No accounting choices were reopened and no application changes were made during planning.

## Open questions and future design work

- Exact paper approval timing, authority, and forms; whether later digital approval is needed.
- Full approved account list/codes, advance-account distinctions, due-to/from project treatment, donation/grant recognition, fund balance components, donor restrictions, and tax/funder-report presentation.
- Reviewed cutover/opening balances and fiscal-year policy. A balanced Trial Balance alone does not prove each opening balance is correct.
- Worksheet Income Statement columns: monthly versus year-to-date; surplus/deficit placement and reconciliation with Balance Sheet columns.
- Cash-flow reporting interval, cash-equivalent definitions, signed working-capital mappings, depreciation/noncash adjustments, mixed classifications, internal cash transfers, and correction provenance.
- Worked later reports must cover prior-year results with/without closing entries, opening entries/reversals, advances across months, equipment via advance, depreciation, and cash transfers. Source books alone do not establish physical cash-flow totals.
- Period-closing coordination with journal writers; stale reviewed revisions and downstream reports after reopening. Reporting prerequisites come first.
- Approved budget versioning and budget-dependent frozen reports; project allocation describes activity unless a separate balanced-project contract is explicitly designed.

## Updating decisions

Record new choices with their source/date and reference to the plan amendment. Mark superseded choices explicitly. Ask only when a material unresolved requirement affects authorized work; do not reopen settled user choices merely because an external reviewer describes them differently.

## Checkpoint 5 implementation record: October 7, 2026

The user explicitly authorized the reviewed plan's execution. The ordinary/advance gate discrepancy is resolved against the master contract: ordinary entry needs Stage 1/Stage 3 flags and complete 019/020/021, while advance entry adds the Stage 2 flag and uses persisted provenance. The reviewed paused-retry default is implemented and tested: unavailable advance actions preserve state, then matching successful requests recover their original bundle after re-enabling, even when the target is already corrected. Posted readers/privacy remain flag independent. This supersedes the stricter gate recorded during Checkpoint 4; it does not change client accounting policy.

Legacy scalar forms retain their session-secret contract; cross-session/expired-token recovery belongs to durable saved drafts. Final integrated results, exact source identification, suite inventory and outstanding deployment/acceptance are in [Stage 3 delivery](stage3-delivery.md). No working migration or activation was performed.


## Usability U2-A implementation disposition: October 7, 2026

The user supplied Gemini's and ChatGPT's favorable U2-plan reviews, then explicitly authorized U2-A. Implement only ordinary payments, exact posted-result access and minimal direct payment navigation. Reviewer suggestions to start later checkpoints do not authorize them. The accepted prototype remains a selected usability reference rather than client-tested policy.

The implementation preserves canonical journal/evidence/project semantics and dedicated v3/v4 presentation. Ordinary Review adds server-validated account type/cash metadata for display only; session refresh is an authenticated read-only CSRF refresh and does not replace the durable financial request. Quick mode may deliberately synchronize its represented amount/project on a user edit, but mode changes or draft loading cannot repair mismatches or trim support. Legacy creation timestamps with unknown timezone remain explicitly labelled instead of being converted by assumption.

[U2-A delivery](usability-u2-delivery.md) records source completion, tested uncommitted hashes and isolated checks. No new client accounting policy, migration, activation, working posting or acceptance was selected. U2-B/C, performance diagnosis, working acceptance/client observation and original Stages 4–7 remain separate.
