# Stage 3 Checkpoint 4: cash-advance corrections

Prepared October 6, 2026. **Detailed plan only; application implementation, database migration and feature activation are not authorized by this task.**

This expands Checkpoint 4 of the [approved Stage 3 specification](stage3-plan.md#8-implementation-checkpoints-after-approval). It preserves the user's selected policies and incorporates the supplied Checkpoint 3 delivery reviews. It does not replace the master specification or start Checkpoint 5.

### External review disposition: October 6, 2026

The user supplied Gemini's and ChatGPT's reviews of this detailed plan after the Scan Receipt supplement. Both find it ready for implementation; neither requests another planning revision. Their assessments support the shared lifecycle calculation, dated candidate validation, new replacement-release identity, original settlement identity, atomic operation/evidence links, durable recovery and reconciliation of both affected control accounts. These are plan reviews, not verification of Checkpoint 4 code or test execution.

Retain the current stricter Stage 2 dependency for ordinary corrections during this checkpoint. Checkpoint 5 must explicitly resolve or document its difference from master section 10. No additional migration is indicated by the inspected schema; the amendment rule below still applies if implementation exposes a genuine limitation.

Gemini's suggested execution prompt is reviewer content, not a user-issued implementation instruction. Application implementation remains pending explicit user authorization. No scope change or additional planning round is needed.

### Implementation authorization

The user subsequently instructed: ?Implement Stage 3 Checkpoint 4 using the reviewed plan.? This authorizes the scoped source implementation and disposable verification. Working migration, activation and Checkpoint 5 remain excluded. See current project status for progress.

## 1. Readiness, sources and inspection findings

Read `AGENTS.md`, `README.md`, the project status, client context, decisions, approved Stage 3 plan and Checkpoint 3 delivery. Compared their claims with the current local correction, advance, evidence, schema, preflight and browser integration code. Repository HEAD remains `a5a681b`; uncommitted Checkpoints 1-3 and the Stage 2 supplement are part of the inspected implementation and must be preserved.

The user has accepted Checkpoint 3 following Gemini's and ChatGPT's delivery-report approval. Those reviews establish their assessment of the report, not independent execution or inspection of the new implementation. This task performs source inspection; it does not rerun the reported tests, inspect private configuration or query the working database.

### What matches the records

- `correction_posting.php` implements the ordinary atomic bundle, exact line mappings, immediate-lineage evidence associations, durable recovery and transactional stale-reservation cleanup. It explicitly rejects advance correction posting. This matches the Checkpoint 3 boundary.
- Version-4 drafts already identify the original target and its original advance. Draft review adapts advance replacements to existing validation, including gross expenditure evidence and return confirmation. These are reusable foundations, not completed advance posting.
- Migration 021 already provides operation reversal links and correction provenance. Migration 020 provides immutable normal operations and one release per advance. No additional schema is currently needed for Checkpoint 4.
- Posted evidence readers and privacy primitives already recognize correction associations independently of UI flags. Preserve these paths rather than replacing them with primary-journal-only lookups.

### Gaps and discrepancies to address explicitly

1. **Different advance readers:** `advance_state()` still accepts normal Stage 2 operation/draft relationships and ignores operation reversals. Conversely, `correction_control_integrity()` already recognizes legitimate v4 replacements, operation reversals, date-grouped lifecycle invariants and both-direction control-line links. Consolidate their accounting rules; do not introduce a third independent calculation.
2. **Current-only preparation:** `correction_remaining()` uses a current summed balance. Ordinary Stage 2 settlement preparation also checks available current balance without simulating the candidate through the full history. Both need the same dated candidate validator before correction posting becomes available.
3. **Writer/request contract:** the current ordinary writer rejects advance drafts before recovery, and `correction_request()` uses ordinary replacement canonicalization. Extend the advance request shape deliberately so due dates, control-account choice, approval fields, liability notes and evidence context cannot be omitted from duplicate-conflict detection. Preserve existing ordinary request hashes.
4. **Lifecycle presentation:** the register presently recognizes outstanding, partially settled and settled only. It needs dated cancellation/replacement states, effective balances, old/new advance links and correction-aware operation details. Current posted results have no replacement-advance identity.
5. **Feature gate discrepancy:** master section 10 describes ordinary Stage 3 availability with Stage 1 plus 021/Stage 3, and an additional Stage 2 requirement for advance corrections. `stage3_enabled()` currently requires Stage 2 for all corrections. Preserve the current stricter gate during Checkpoint 4; record this discrepancy for Checkpoint 5's availability review. Do not silently broaden access or claim the two contracts already match.
6. **Historical document status:** the master plan's original planning-only header predates authorized Checkpoints 1-3. Add a dated progress note rather than interpreting it as a prohibition on completed work or rewriting the delivery history.

Working 021 and Stage 3 activation remain **recorded pending**, not freshly verified here. Stage 1/2 working acceptance and current-data restoration rehearsal remain deferred. Neither is relabelled as passed or made a new prerequisite for this source-planning task.

### Source labels and reviewer carry-forward

The visit notes support linked advances, liquidation and adjustment journals. The user selected reversal/replacement, same-date bundles, dates between target date and Manila today, GJ reversal routing, dependency-first release correction and reversal-only. The detailed concurrency, identity, retry and privacy rules are selected system controls; cropped Excel references do not establish them as client policy.

Retain ChatGPT's two final-integration requests: replay an **original ordinary posting request after correction**, and run complete affected regression suites on the fully migrated integrated schema. These remain explicit Checkpoint 5 obligations; correction-request recovery or old cumulative totals do not substitute for them. Add the corresponding original advance-request recovery case to Checkpoint 4 because its writer is directly affected.

## 2. Outcome and exclusions

Enable dedicated correction review/posting for releases, liquidations and returns in a complete isolated Stage 3 installation. Preserve journals, evidence, deadlines and audit history while keeping the advance register and control-account ledger equal at every valid accounting date.

Included:

- Exact GJ reversal of the targeted advance operation, with an optional same-date validated replacement.
- New advance identity for a replacement release; original advance identity for replacement liquidation/return.
- Dependency blockers, historical lifecycle simulation and correction-aware normal Stage 2 settlements.
- Consistent register, aging, detail, reconciliation, posted navigation and flag-independent privacy.
- Focused disposable backend/browser verification and a Checkpoint 4 delivery report.

Excluded: working migration/activation or postings, a new migration, automatic chain reversal/reparenting, top-ups, reimbursements, digital approval, payroll/tax automation, closing, new financial statements, broad final acceptance, commits and pushes. No Tailwind build unless implementation actually changes Tailwind utilities; scoped styling alone needs none.

## 3. Shared accounting and identity contracts

### 3.1 One normalized operation/event model

Extract or adapt a shared lifecycle helper used by `advance_state()`, correction preparation/posting, normal `advance_prepare()`/`advance_post()`, and the correction/preflight integrity reader. A small `includes/cash_advance_lifecycle.php` is appropriate if it avoids circular service dependencies. Function names are implementation details; the following contracts are required:

- Load advances, normal operations, their posted journals/control lines, applicable corrections and operation reversals once into a normalized model.
- Validate provenance before projecting amounts. Invalid or missing links are errors, not silently omitted financial activity.
- Keep Stage 2-only installations with absent 021 usable. Detect absent versus partial/complete 021 explicitly; a partial schema must fail closed instead of ignoring possible reversal history. Existing privacy fail-closed behavior remains intact.
- Provide both a dated read projection and a candidate simulation. The simulation uses server-derived proposed journals/operation relationships, including a provisional new identity for a replacement release; no user-supplied bypass flag or arbitrary control line is accepted.
- Preserve 64-bit exact-centavo arithmetic and overflow protections.
- Avoid recursion between `advance_state()` and `correction_control_integrity()` and avoid having their results disagree. Structural correction validation may remain separate, but amounts and timeline rules come from the shared model.

For a valid normal v3 operation, preserve the existing dedicated workflow, posted draft/journal and advance-ID relationship. For a v4 replacement, require the correction draft, target operation, valid correction bundle, replacement journal, kind/book/control line and original draft advance ID to agree. A replacement release's operation belongs to a **different new advance**; replacement settlements belong to the **same original advance**. Do not broadly accept any v4 journal.

Each operation reversal must reference the original operation and its valid correction, exact mapped reversal control line and GJ reversal journal. Every posted designated-control line must belong to exactly one valid normal operation or operation reversal. Check missing, duplicate and extra links in both directions; offsetting unlinked entries must fail even when net balances match.

### 3.2 Dated balances and lifecycle

Calculate amounts from linked posted control lines through the selected accounting date:

```text
effective released   = release debits      - release reversal credits
effective liquidated = liquidation credits - liquidation reversal debits
effective returned   = return credits      - return reversal debits
outstanding          = effective released - effective liquidated - effective returned
```

Retain original gross amounts separately in details. A correction does not erase an operation before its reversal's accounting date. Exclude drafts and advances whose release date is later than the historical cutoff.

Group all events by accounting date and evaluate the complete daily bundle together. At every affected date through today require nonnegative effective component amounts and outstanding, an effective release count of zero or one, and no effective settlements without an effective release. Validate operation identities and dependencies as well as amounts. Same-day atomic reversal/replacement must not fail because of arbitrary insertion order.

Keep two separate questions explicit:

- **Can the operation be corrected now?** The target must remain eligible and unreversed, with no current effective settlement dependency when it is a release.
- **Is the proposed accounting date valid?** Simulate all intervening dated events. A release correction cannot predate the effective reversals of its settlements, even if its current dependencies are cleared.

A settlement replacement is still an effective settlement and still blocks release correction. List the blocking journals/operations and dates. Do not clear a blocker merely because it has a correction record.

Before a release reversal takes effect, preserve its historical ordinary settlement/overdue state. From that date show **Cancelled** for reversal-only or **Replaced** with its new advance link. Both have zero effective outstanding, no overdue flag and no settlement/due-extension action. A normally **Settled** advance remains distinguishable. Historical status/aging uses the deadline effective at the cutoff, not the latest deadline or browser clock.

### 3.3 Reconciliation and snapshots

Per-account reconciliation covers **all advances** for each designated account through the same cutoff, independent of employee/project/status/search/page filters. Register totals cover only matching advances and use effective amounts. Preserve the Stage 2 supplement's current-page print scope, applied-filter labels and all-matching totals labels.

Read displayed balances, timeline, totals and full-account reconciliation under the existing consistent `accounting_read()` snapshot. Posting decisions always use current integrity plus the candidate's complete dated projection, never an old register view. Reconcile the union of original and replacement control accounts, including replacement releases that change account or employee/project.

Historical financial balances are restated by accounting date as already documented; they are not snapshots of what staff knew at that time. Existing frozen Trial Balance revisions retain their original figures and review state.

## 4. Workflow behavior

### Release correction

- Block until every effective liquidation/return, including replacement settlements, is resolved. Show blockers in eligibility/review and enforce them again inside posting.
- Reverse the exact original release in GJ. Reversal-only cancels its recorded advance; it does not record a real-world return of cash.
- Replacement creates a new advance number/row linked to its new CDB release journal and normal release operation. Leave the original advance row, snapshots, due history and release operation immutable.
- Require active person-type employee, active selected projects, eligible active cash account and designated active Debit-normal noncash Asset control. No inactive-original exception applies even if IDs are unchanged.
- Require independently entered/validated initial due date and external-approval fields under normal release policy. Do not copy later deadline extensions or treat prior approval as newly granted approval. Optional release documents retain their normal review requirements.
- Keep `journal_drafts.advance_id` equal to the **target's original advance** for v4. Store the new identity through its new `cash_advances`/operation relationship; only normal v3 release behavior sets its draft to its newly posted advance.

### Liquidation correction

- Reverse the exact original liquidation in GJ. Its reversal restores only that operation's control-account reduction from the correction date.
- Replacement remains on the original employee/control/advance, uses GJ and preserves narrow inactive-original employee/project exceptions. New references must be eligible; accounts must pass normal validation.
- Generate the control credit on the server. Require full reviewed support for eligible gross expenditure/asset debits; informational documents and the control credit do not add expenditure support.
- Optional eligible liability credits reduce the control reduction: **gross expenditure/asset debits minus liability credits**. Require liability line explanations and a positive valid reduction within the projected available balance.
- Reused evidence receives fresh association-specific review/allocations via the immediate target lineage; preserve primary ownership, original bytes/hashes/reviews. Unused portions still cannot be used for unrelated liquidations.

### Return correction

- Reverse the original return in GJ. Replacement uses CRB, the original advance/control and eligible receiving cash account.
- Require reviewed return proof and fresh staff confirmation bound to the correction draft/target, original advance, current amount, cash account, date and document context. Changes invalidate confirmation. Persist its confirmed context in the private review/audit record.
- Reversal-only needs the required correction reason/preview, but makes no replacement document claim or return confirmation claim.

### Normal Stage 2 posting after corrections

Before a new normal settlement, validate active release lifecycle and all historical balances with the candidate included. A currently available balance restored by an October 6 reversal cannot fund an October 3 settlement if doing so makes October 3-5 negative. Current cancelled/replaced advances cannot receive new settlements. Normal release, settlement, deadline and evidence behavior otherwise remains unchanged.

## 5. Review, canonical requests and atomic posting

### Review and recovery

Advance correction review must show original, exact reversal, optional replacement, gross expenditure support, net advance reduction, original/new identity and before/after effective balance. For a backdated correction, identify its accounting date and earliest affected timeline date. Report dependency, evidence and timeline errors without discarding entered values.

Replace the Checkpoint-4-unavailable notice only after the integrated server writer and readers are ready. Include source version, draft revision, target/correction lineage, affected advance revisions, normalized candidate/timeline and resource review context in the review fingerprint, including reversal-only. Changed concurrent accounting invalidates first-post review.

Preserve the existing ordinary canonical request/hash shape. Define a separate deterministic advance correction shape that retains all relevant advance payload fields, generated accounting lines, evidence/re-review fields and workflow/target identity. Keep mutable live balances and transient tokens out of the durable request hash; they belong in the review fingerprint. Derive workflow from persisted immutable provenance, not a submitted type flag.

On a posted draft, matching authenticated retries recover the same correction, journal and advance IDs **before** checking current target eligibility, current master activity or an expired review token. Changed content conflicts. Validate the posted bundle and its operation relationships. Recovery must work after later corrections and after the original/new advance becomes inactive or cancelled; it must not create another advance.

### Transaction sequence

Use one `workspace_tx()` transaction and its active-actor lock/accounting write barrier. Do not call public transaction-owning `advance_post()` or ordinary posting endpoints from the correction writer.

1. Lock the owner-private v4 draft, check key/context and handle successful recovery.
2. For an unposted draft, lock target/related journals and affected existing advance rows in the approved deterministic order. Validate target eligibility and exact saved canonical content.
3. Lock masters, evidence and accounts in the established order; collect the union of original/replacement account IDs for sorted account locking. Preserve original inactive snapshots for the exact reversal and the limited settlement exceptions only.
4. Rebuild the trusted projection, check current full-account integrity, dependencies, entire dated candidate and return confirmation; validate the review token.
5. Insert exact reversal and optional replacement journals, correction and line mappings.
6. Insert the original operation's reversal link. For replacement release insert a new advance and its normal release operation; for replacement settlement insert its normal operation on the original advance. Use transaction-owned helpers with explicit transaction assertions, not a second public writer.
7. Write association-specific evidence/allocations and private advance/correction review snapshots, including before/after amounts and old/new identity.
8. Finalize the correction draft without changing its original advance ID; update affected advance revisions and append audits. Preserve existing due histories.
9. Perform the existing exact-target stale reused-image cleanup, preserving competing drafts/private uploads/unposted reservations. Revalidate the completed operation/link/timeline/reconciliation model before commit and increment the coordinated accounting version.

Any failure rolls back journals, correction/mappings, operation reversal, new advance/operation, associations, allocations, receipt ownership changes, revisions, draft state, audits, write version and stale cleanup. Generated numbering may have gaps after rollback; do not renumber immutable identities.

## 6. Register, detail, navigation and privacy

- Extend validated status filters and visible choices to include Cancelled/Replaced. Add effective status, lifecycle date and permitted original/new links to summaries; keep overdue separate.
- Detail shows dated normal operations, operation reversals and replacements with relevant journal/correction links. Distinguish original gross figures from effective totals and identify GJ offsets; do not subtract originals twice or label cancelled advances settled.
- Return result and posted-draft reopening expose explicit `original_advance_id` and nullable `replacement_advance_id` derived from committed relationships. Maintain existing result fields for compatibility. Define `advance_id` as the replacement advance for a replacement release, otherwise the original advance; ordinary correction result/hash behavior stays unchanged.
- For replacement release, offer **View original advance** and **View replacement advance**. Settlement/reversal-only offers its original advance plus existing correction comparison, appropriate book and Journal History links. New post, recovered retry and reopening resolve the same IDs; do not use the v4 draft's original ID as the new release's destination.
- Preserve date-filter semantics, searchable controls, posted read-only state, escaped labels, owner privacy, return help and the Stage 2 applied-print scope.
- Management receives permitted financial summaries, lifecycle links and truthful coverage. Do not expose images, receipt identifiers/URLs/hashes, per-document review/allocations, confirmation or external-approval details through API, books/history, comparison, print or downloads. Compute coverage internally before redaction. These restrictions and integrity readers remain active with Stage 2/3 UI flags off.

## 7. Implementation sequence and affected components

Implement in this order after explicit authorization:

1. **Shared lifecycle model/readers:** adapt `includes/cash_advance.php`, `includes/journal_corrections.php` and, if needed, a small shared lifecycle include. Cover v3/v4 provenance, reverse events, as-of projections, reconciliation and candidate validation before enabling a writer.
2. **Review and normal settlement integration:** update `includes/correction_drafts.php` and normal Stage 2 preparation/posting to share candidate checks and fingerprints. Preserve ordinary payload versions and public endpoint separation.
3. **Atomic advance writer/recovery:** extend `includes/correction_posting.php`, using transaction-owned advance helpers where appropriate. Bind canonical advance fields, operation links, new identities, revisions and evidence atomically. Remove the advance posting guard only when these paths are functional.
4. **Reader/UI integration:** update register/detail/result contracts and their consumers: `cash_advances.php`, `assets/js/cash_advances.js`, shared entry template/JavaScript, correction action/page/detail readers as needed. No unrelated redesign.
5. **Focused verification/delivery:** add `scripts/test_stage3_checkpoint4.php` and a focused browser runner or extend existing guarded harnesses. Update affected old assertions that intentionally expected advance HTTP 503; retain ordinary safeguards and record why those expectations change. Update preflight consumers to use the shared committed-history validator without making them writers. Produce `docs/stage3-checkpoint4-delivery.md` and refresh status.

Keep migration 021 unchanged. If source integration discovers a genuine schema limitation, document it and obtain a plan amendment before proposing another migration; do not alter an already-reviewed migration opportunistically.

## 8. Worked accounting cases and acceptance

All cases use clearly synthetic NGO training/transport examples in new guarded disposable environments. Dates below are examples within the October 6 planning horizon; use the suite's controlled Manila accounting clock so future runs remain reproducible.

### Required worked results

1. **Liquidation replacement:** release 10,000 on October 1; liquidate 8,000 on October 2; correct to 7,000 on October 4. Original GJ cost debit/control credit 8,000 stays. GJ reversal control debit/cost credit 8,000 and GJ replacement cost debit/control credit 7,000 yield outstanding 2,000 through October 3 and 3,000 from October 4.
2. **Return replacement:** after a 10,000 release and 8,000 liquidation, a 2,000 return closes the advance. Correct return to 1,500: GJ control debit/cash credit 2,000; CRB cash debit/control credit 1,500. Effective outstanding becomes 500. Require newly confirmed proof.
3. **Withholding and asset coverage:** replacing liquidation with cost debit 4,500, payable credit 500 and control credit 4,000 requires 4,500 reviewed monetary support, reduces the advance by 4,000 and leaves 6,000 on a 10,000 otherwise-unsettled advance. Also exercise eligible equipment/asset debit instead of expense. The reversal undoes the original payable as well as its other lines exactly.
4. **Release replacement:** otherwise-unsettled release 10,000 corrected to 9,000 creates a new advance. Old advance is Replaced with effective zero; new advance owes 9,000. Original/replacement CDB gross activity is 19,000, GJ offset is 10,000, net control/cash effect is 9,000. Test a different designated control account and independently reconcile both accounts.
5. **Reversal-only:** reverse a mistaken settlement to restore its reduction; reverse a mistaken release only after all effective settlement dependencies are cleared. Release cancellation is not proof that physical cash was returned.
6. **Historical dependency:** liquidation reversal dated October 6 does not permit its release correction on October 5. A corrected liquidation with an effective replacement continues to block release correction. The owner sees the blocking journals.
7. **Backdated normal settlement:** release 10,000 October 1, liquidation 8,000 October 2, reversal October 6. A further liquidation of 8,000 dated October 3 fails because historical outstanding would be negative, although today's restored balance is 10,000. The same otherwise-valid settlement dated October 6 may pass.

### Focused backend checks

- Exact reversal-only/replacement journals for every kind; immutable originals, operation mappings and snapshots; one correction per target; generated-reversal targets rejected.
- Strict normal v3 and legitimate v4 replacement relationships, including new release IDs differing from draft target IDs; malformed/forged v4 links rejected by posting, readers, reconciliation and preflight.
- Dated status, balances, due history and aging before/on/after correction; date bounds; same-day groups; multiple successive replacement-release identities; no settlement or due extension on cancelled/replaced advances.
- Gross coverage, liability notes/net reduction, assets, insufficient/excess claims, fresh return confirmation and changes invalidating it; inactive replacement releases fail while narrow settlement exceptions work.
- Both-direction link corruption, offsetting unlinked lines, missing/duplicate reversal mapping, multiple controls, filtered employee/project/status totals versus full reconciliation and consistent read snapshots.
- Two database connections: correction versus settlement, two settlement corrections and two release corrections. Exactly one valid competing correction bundle; no excess consumption or lost revisions. Losing first-post requests must re-review when their context changes.
- Inject rollback failures after operation reversal, new advance/operation insertion, evidence allocation and final audit/stale cleanup. Compare all financial/evidence/draft/reservation/revision/write-state records with the pre-attempt state.
- Lost-response, cross-session and expired-token recovery for all kinds, changed-content conflict, reopening, and original **advance** posting replay after its journal is corrected. Confirm no duplicated journal, operation or advance.
- Run relevant Stage 1/2 and Checkpoints 1-3 regression cases on the complete 019/020/021 disposable schema. Do not present previously recorded totals as new execution evidence. Checkpoint 5 still owns the complete final integrated matrix and original ordinary posting replay case.

### Focused browser/HTTP checks

Use visible controls in an isolated app with private sessions/evidence and blocked external integrations. Cover release blockers, liquidation gross/net review, return confirmation invalidation, new identity links, posted reopening/retry, historical register filters/status/aging and applied print scope. Include stale/failed refresh preservation, Management redaction in old books/history/comparison/downloads, and flag-disabled reader behavior.

Capture and inspect register/detail and original/reversal/replacement review/posted views at 1366x768 and 1920x1080, plus print media. Verify keyboard interaction, validation recovery, read-only posted controls and no horizontal page overflow. Edge is the established focused browser; explicitly report any other browser or physical printing as unverified unless actually tested.

### Completion gate

Checkpoint 4 is complete only when all three advance kinds post atomically in isolation, historical normal settlements are protected, register/reconciliation/readers agree, focused checks pass, and delivery records actual results and limitations. Source implementation completion does not mean working deployment or full acceptance.

The next step after Checkpoint 4 delivery/review is Checkpoint 5 integrated verification and deployment documentation. Working migration 021/activation requires separate user authorization. No database commands or feature activation are part of this planning task.
