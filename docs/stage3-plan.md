# Stage 3 implementation plan: linked reversals and replacements

Status: planning only, prepared October 6, 2026 and amended after the user-provided Gemini and ChatGPT reviews. Implementation, migration 021 and feature activation are not authorized by this document.

Progress note, October 6, 2026: the status above describes the specification's original preparation. The user later authorized Checkpoints 1-3 and accepted their delivery reports after external review. Checkpoint 4 now has a [detailed planning supplement](stage3-checkpoint4-plan.md), based on current local source inspection; the user subsequently authorized its scoped implementation and disposable verification. See [current project status](project-status.md) for deployment, verification limits and the documented feature-gate discrepancy. This note does not alter the approved accounting scope.

Delivery update, October 7, 2026: Checkpoint 4 source implementation and focused disposable verification are complete. See [Checkpoint 4 delivery](stage3-checkpoint4-delivery.md). Both supplied delivery reviews support proceeding to Checkpoint 5 planning. Checkpoint 5 and working migration/activation remain pending; the accounting scope above is unchanged.

Planning update, October 7, 2026: the user requested the [detailed Checkpoint 5 plan](stage3-checkpoint5-plan.md). It specifies ordinary/advance availability integration, complete-schema verification and final delivery guidance. This documentation update does not implement the gate change, run tests or authorize deployment.

Final delivery update, October 7, 2026: the user authorized Checkpoint 5. Stage 3 source implementation and isolated complete-schema verification are complete; the availability discrepancy is resolved. See [final delivery](stage3-delivery.md) for fresh results, source fingerprints and deployment/recovery guidance. Working 021, activation, restoration rehearsal and full working acceptance remain pending. Earlier planning/progress notes are dated history.

## 1. Outcome, current baseline and scope

Give accounting staff a controlled way to correct a posted record without editing or deleting its original journal, evidence, advance operation or audit history. Support both full reversal without replacement and full reversal with a linked replacement. Preserve the complete chain in financial records and advance balances.

The inspected repository baseline is `a5a681b` (`stage_2`), following `c74ddfe` (`stage_1`). The existing uncommitted change to `docs/stage2-delivery.md` records subsequent working deployment; preserve it. Working migration 020 and configuration activation have been verified, and the user confirmed that Cash Advances appears. Full working-system acceptance and current-data restoration rehearsal remain deferred to final integration. Do not describe menu visibility as completion of those checks.

Stage 1 provides private version-2 drafts, ordinary CRB/CDB/GJ posting, project/party masters, evidence reservations and complete originating-book entries. Stage 2 adds version-3 advance drafts, generated control lines, release/liquidation/return operations, return confirmation, deadline history, privacy and per-account reconciliation. Stage 3 must integrate with those services, not replace them with an unrelated journal engine.

### Sources and decisions

- Client visit notes describe an adjustment journal, cash advances linked to journal entries, corrections and archived records that cannot be directly edited. They do not specify the exact correction algorithm or correction dates.
- The supplied worksheet establishes CRB/CDB/Journal movement columns. It does not establish reversal routing, correction approval authority or period-closing policy.
- The user previously chose full reverse-and-replace over difference-only corrections.
- During Stage 3 planning, the user selected requiring settlement resolution before correcting an advance release, allowing reversal without replacement with a required reason and preview, defaulting the correction date to today while permitting validated earlier dates, and routing generated reversals to GJ.
- Atomic posting, exact centavos, server authorization, immutable history and full reconciliation are system controls.
- Atomic same-date bundles, new advance identities for replacement releases, private version-4 drafts and the remaining bounded implementation defaults below are selected design proposals for this review, not independently confirmed client policies. Clearly preserve that distinction in delivery documentation.

### Included

- Ordinary cash receipts, payments, General Journal entries and cash transfers.
- Existing posted legacy General Journal records with sufficient intact accounting data, without inventing missing historical metadata or monetary evidence reviews.
- Corrections of advance liquidations and returns; controlled reversal/replacement of advance releases after settlement dependencies are resolved.
- Full original-to-reversal line mapping, replacement chains, correction-linked evidence reuse, durable retries, private correction drafts, role-aware history and printable comparisons.
- Integration with existing journal-derived figures, frozen Trial Balance snapshots, My Drafts, account protections and advance reconciliation.

### Excluded

Difference-only adjustment automation, ordinary CRUD edits of posted records, deleting posted history, bulk chain reversal, period closing/reopening, new financial statements/report bundles, opening-balance migration, budget approval, payroll/tax automation, automatic reimbursements, foreign-currency automation and OCR upgrades. Standalone balanced General Journal entries remain available; do not infer that every adjustment must reverse a previous journal.

Period closing must wait for the report prerequisites from later stages. Stage 3 adds no pretend closed-period status based on an existing reviewed Trial Balance.

### External review disposition

Gemini supports the overall design and recommends checkpoint execution. Its commercial-software comparisons are not independent implementation verification. GJ routing does not guarantee bank-aligned cash-book activity totals, and temporary correction reservations are distinct from permanent posted evidence associations. Retain the dependency order in section 8 rather than treating its quoted execution prompt as implementation authorization.

ChatGPT reviewed the specification and relevant Stage 1/2 code at `a5a681b`, reported no structural blocker, and requested one clarification: replacement advance releases must not inherit the inactive-reference exceptions for linked settlements or ordinary replacements. That clarification is explicit in sections 2.4 and 4.1 and the acceptance cases below. Neither external review verifies Stage 3 implementation, which has not begun.

## 2. What a correction means

An accounting reversal corrects what the system recorded. It does not itself refund a supplier, return physical money, transfer funds or constitute management approval. Staff must distinguish an erroneous/duplicate accounting record from a legitimate transaction followed by an actual refund or return; the latter belongs in the appropriate ordinary or advance workflow.

Every correction requires a nonempty reason of at most 2,000 valid UTF-8 characters. Show the reason beside the target and proposed journals before posting. Require explicit acknowledgment of the proposed accounting effects; accounting review remains separate from management approval.

### 2.1 Reverse without replacement

Generate the exact opposite of every original journal line, preserving account IDs, project IDs and original project snapshots. Swap positive debit and credit amounts; never insert negative values into line columns.

Preserve the original journal header and lines. The generated reversal has its own ID, accounting date, actor, UTC creation time, system-generated description and immutable correction link. Preserve the original payer/payee snapshot in the reversal without substituting a current master name.

No replacement journal is created. This mode is appropriate for a duplicate or invalid record after staff verify the underlying facts. It cannot silently discard a valid outstanding obligation or assert that cash physically returned.

### 2.2 Reverse and replace

Prepare the reversal and corrected replacement together. One database transaction posts both journals, correction links, evidence associations, applicable advance links and audits. If either journal, evidence write, operation link or audit fails, roll back everything; do not leave an original reversed with its intended replacement missing.

The replacement must pass its actual workflow's validation. It receives a new journal ID and posting-time snapshots, with narrowly permitted original inactive party/project references where specified below. The original and its snapshots remain untouched.

### 2.3 Accounting dates and book routing

User-selected date policy: prefill today's Asia/Manila date, but allow an explicitly selected valid correction date on or after the target entry date and no later than today. Use one date for the reversal and replacement; do not allow different dates within a correction bundle. UTC recording time remains separate. Require a reason for an earlier-than-today date and visibly explain that it can change historical live figures.

For advances, date validation also includes the full historical balance and release/settlement dependency rules in section 4. A current nonnegative balance alone is insufficient. Do not silently move an invalid selected date.

User-selected reversal routing: all generated reversals originate in GJ, reflecting the adjustment-journal concept from the notes. Use `transaction_kind='correction_reversal'`. The reversal is not constrained by ordinary CRB/CDB cash direction or ordinary advance-control exclusions; only the dedicated server-generated correction path authorizes it.

- Ordinary replacements default to the original book. Staff may explicitly change among CRB, CDB and GJ when correcting an incorrect book classification; validate the resulting cash rules and payer/payee requirements. Cash transfers require GJ and retain ordinary transfer validation.
- Advance replacements retain their operation kind and its established book: release CDB, liquidation GJ, return CRB. A correction cannot turn an advance release into an expense, transfer it to another employee by a submitted flag or switch a liquidation into a return.
- Missing original legacy book metadata stays missing. Its reversal and default replacement use GJ; do not retroactively assign an invented historical originating book.

With GJ reversals, a cash book can contain the original and replacement while the offset appears in the Journal column. The combined worksheet/ledger is correct; individual book debit/credit activity totals must not be relabelled as the final corrected business amount.

### 2.4 Chains and eligibility

- Correct only an intact posted journal that has not already been the target of a posted correction.
- Do not correct a generated reversal directly. When a replacement is wrong, correct the latest eligible replacement, retaining root and parent links.
- A target may receive at most one posted correction. Multiple private drafts may exist; the first successful post wins, and other drafts receive a conflict with no financial write.
- Reject malformed/unbalanced target journals, missing original lines, inconsistent correction mappings, unknown advance/control usage or damaged evidence. Do not auto-repair historical records during correction.
- Any active Admin may correct an eligible posted journal, including one posted by another Admin. Correction drafts remain owner-private.
- Reversals may use an inactive original account because the exact original line must be cancelled. Replacement accounts must be active and satisfy their workflow; reactivating an eligible old account is an explicit separate setup action.
- Ordinary replacements may retain their exact original inactive party/project IDs with historical snapshots. Linked advance-settlement replacements retain only Stage 2's narrow exception for the original advance employee and originating project; other allocation projects must be active. Changing to another eligible reference requires it to be active. Do not permit arbitrary inactive master selection or overwrite the original snapshot.
- Replacement advance releases are explicitly excluded from all inactive-reference exceptions. They create new advances and must pass normal release validation: an active person-type accountable employee, an active selected project or Organization operations, an active eligible designated control account and an active eligible paying cash/bank account. This also applies when the selected IDs match the original release. Their snapshots come from the validated active references at replacement posting, not a grandfathered inactive context. Reversing the original still preserves its exact original IDs/snapshots.
- Account designation and posted-use maintenance restrictions remain effective even after a transaction is reversed. Reversing a record does not make its account historically unused.

## 3. Worked accounting results

Use these synthetic cases in a disposable environment. Their amounts are examples, not Atikha's actual transactions.

### Ordinary payment: incorrect amount

Original CDB: Miscellaneous Expense debit PHP 1,000; Cash credit PHP 1,000. Correct amount: PHP 900.

Reversal GJ: Cash debit PHP 1,000; Miscellaneous Expense credit PHP 1,000. Replacement CDB: Miscellaneous Expense debit PHP 900; Cash credit PHP 900. Combined account effect is Expense debit PHP 900 and Cash credit PHP 900. All three journals remain visible and posted.

With the selected GJ route, CDB contains PHP 1,900 debit and credit activity across original/replacement, and GJ contains PHP 1,000 reversing activity. Do not exclude the original while still including its reversal: that would subtract the original twice.

### Wrong account or project

Reverse the exact original account/project lines. Post the replacement to the correct eligible account and authoritative line-level project tags. Overall debit and credit balance remains zero difference; activity moves between accounts/projects. A change in payer/payee or reference also uses a linked correction rather than rewriting the original header.

### Duplicate receipt: reversal only

Reverse a duplicate PHP 1,000 Cash debit/Donation Income credit with Donation Income debit/Cash credit PHP 1,000. The valid separate receipt remains. The duplicate target and its reversal net to zero. Do not record an actual outgoing refund merely because the duplicate is reversed.

### Advance liquidation: PHP 8,000 corrected to PHP 7,000

Release PHP 10,000. Original liquidation: Expense debit/Advance credit PHP 8,000, leaving PHP 2,000.

Reversal: Advance debit/Expense credit PHP 8,000. Replacement: Expense debit/Advance credit PHP 7,000, fully supported by reviewed cost allocations. Effective liquidated amount is PHP 7,000; outstanding is PHP 3,000. Preserve both original and new evidence allocations and explain their correction relationship.

### Withholding corrected

Original: Expense debit PHP 4,500; Withholding Payable credit PHP 500; Advance credit PHP 4,000. Reverse all three lines, including the liability. Replacement: Expense debit PHP 4,500; Withholding Payable credit PHP 450; Advance credit PHP 4,050.

Replacement evidence requirement remains PHP 4,500, not PHP 4,050 and not the combined debit-plus-credit total. Its advance reduction is PHP 4,050. Block the bundle if other effective settlements leave insufficient balance. Stage 3 neither calculates nor certifies a tax rate.

### Advance cash return corrected

Release PHP 10,000; liquidate PHP 8,000; return PHP 2,000. Correct the recorded return to PHP 1,500: reversal Advance debit/Cash credit PHP 2,000; replacement Cash debit/Advance credit PHP 1,500. Outstanding becomes PHP 500. Require new amount-bound return confirmation and reviewed proof for PHP 1,500; this accounting correction does not physically withdraw PHP 500 from the bank.

### Release correction after dependency resolution

An advance with any effective liquidation or return cannot have its release reversed/replaced. Merely correcting a settlement with another active settlement does not remove this dependency.

After all effective settlements have been legitimately reversed without replacement, release reversal may cancel the old advance. A replacement release creates a new advance ID/number and validated new release context. Keep the old identity and deadline history intact. Do not encourage staff to cancel legitimate settlement records merely to bypass the dependency; if their facts require a more complex chain treatment, leave that case explicitly unsupported in Stage 3.

## 4. Cash-advance correction model and reconciliation

### 4.1 Original operations remain immutable

Keep every `cash_advance_operations` record and its original control-line link unchanged. Record a distinct reversal link to that operation and the generated reversing control line. A replacement settlement creates a new normal operation against the same advance. A replacement release creates a new normal release operation against a new advance.

Do not introduce a client-supplied bypass for protected accounts. Load the target operation/advance on the server, derive its original control account and generate the reversal. Replacement settlements must use that same account and employee. A new replacement release may select a newly eligible employee/project/control account only after dependency resolution and normal release validation.

Apply that release validation independently of the original advance context: never pass the original party/project inactive-reference allowlist into a replacement release's resource eligibility checks. Even unchanged original IDs must be active for this new advance; its employee must be person-type and all selected accounts must be active and eligible. Preserve the narrower original employee/originating-project exception only for linked settlements. Recheck active eligibility inside the posting transaction, so deactivation after review prevents the entire reversal/replacement bundle from posting.

The current `advance_state()` validates normal operations against a version-3 workflow/draft identity. Extend it with a precise version-4 replacement predicate: the immutable correction must identify this draft and replacement journal, the replacement must have the prescribed advance kind/book/control line, and its advance ID must be the original advance for a settlement or the newly created advance for a replacement release. The correction draft's original `advance_id` is not the replacement release's new ID. Do not accept an arbitrary `correction` draft as a valid advance operation or weaken the existing v3 checks. Apply this predicate in preflight, register/detail, posting eligibility and recovery as well as the new endpoint.

### 4.2 Effective amounts as of a date

Derive exact-centavo amounts from posted linked control lines whose accounting dates are on or before the selected as-of date:

```text
Effective released   = release control debits - release-reversal control credits
Effective liquidated = liquidation control credits - liquidation-reversal control debits
Effective returned   = return control credits - return-reversal control debits
Outstanding          = effective released - effective liquidated - effective returned
```

Include all replacement operations in their normal operation group. Reversals take effect on their own accounting date; do not globally erase the original operation from earlier views. Original/reversal/replacement amounts remain separately available in timelines. No editable total/status cache is a source of truth.

An advance with a reversed release is Cancelled, or Replaced when its correction created a new advance; it is not simply Settled. This applies only as of the reversal date. Before that date, its original status and aging still apply. Cancelled/replaced advances have no overdue amount or settlement actions. Add explicit register filters and lifecycle links; show their original released amount alongside effective values in detail, without mixing original gross amounts into effective totals.

A reversed/replaced release requires zero effective settlements both now and through its proposed correction date, with the relevant settlement reversals effective by that date. Enforce the dated dependencies, not just net amounts. Extensions remain on their original advance; do not copy their history to a replacement. A replacement release requires a valid initial due date and independently reviewed approval information; optional external approval is not automatically re-approved.

### 4.3 Historical balance protection

Under the shared write barrier, simulate every affected advance's dated event sequence after the proposed bundle. Group all events of one accounting date together because balances are reported by date. At every date through today, require a valid effective release lifecycle, nonnegative effective settlement totals and nonnegative outstanding. Reject any settlement that takes effect before its applicable release or against an already cancelled release.

Extend this check to existing Stage 2 release/settlement posting, not only the correction endpoint. Example: an PHP 8,000 liquidation is reversed today; a new PHP 8,000 liquidation dated before today's reversal must not consume the restored balance early if it makes a historical PHP 10,000 advance negative. Explain the conflicting date/balance; do not silently change its date.

Current posting eligibility uses the full current state, independent of historical register filters. A settled advance may become outstanding after reversing a settlement; a cancelled advance cannot receive a new settlement. Drafts and confirmations remain financially inert.

### 4.4 Both-direction control reconciliation

For each designated account, compare the full posted ledger debit-minus-credit through the same date with all advances' effective outstanding balances. If a replacement release changes control accounts, reconcile both old and new accounts separately. Never offset discrepancies between accounts.

Validate in both directions:

- Each original and replacement operation has its prescribed journal, draft, control account, direction, amount and book.
- Each operation reversal maps to the correct original operation and exact opposite control line, has the selected reversal book and belongs to a valid correction.
- Every posted designated-control line belongs to exactly one validated normal operation or operation reversal.
- Every correction maps all original lines exactly, including control lines; offsetting unlinked lines are integrity errors even when their net balance is zero.

Keep filtered register totals separate from full-account reconciliation. Calculate register/detail/timeline/reconciliation in one consistent read snapshot. Repeated corrections must not count a replacement twice or treat a generated reversal as a normal cash release.

## 5. Evidence reuse without weakening duplicate protections

### 5.1 Keep original ownership and bytes

`Receipts.JournalEntryID` and unique `Posted_File_SHA256` currently identify the primary posting. Preserve both, original bytes/hashes, every original association/review/allocation and existing immutable triggers. Never move the receipt to the replacement, clear its posted hash, duplicate it with another upload or disable the global posted-file hash uniqueness constraint.

Correction-linked reuse refers to the same receipt ID through a new association. It is permitted only in the replacement of that receipt's actual target journal, including an immediate target that already reused it in an earlier correction. No reuse of unused portions for unrelated transactions is introduced.

### 5.2 Draft and posting rules

- New unposted images use the existing owner/private upload and reservation rules.
- Reused posted images have a separate correction reservation linked to the owning correction draft and target association. Another user's private draft cannot steal or remove it.
- Reuse may be prepared by any authorized Admin correcting the posted target, even if another Admin originally uploaded the image. Authorize through the valid target association and posted-evidence privacy rules; do not apply the unposted uploader-only test to it. New unposted images remain uploader/owner-private.
- Starting a correction does not auto-confirm old document reviews. Show the prior snapshot as reference and require staff to review the original image again for the replacement's declared/accepted PHP amounts, supported side and new stable line allocations.
- Each monetary allocation must be positive, refer to an eligible replacement line, and sum exactly to that document's accepted amount; accepted is positive and no greater than the reviewed declared amount. Require an exclusion reason when accepted is lower.
- Changes to previously reviewed declared/accepted values or document purpose require an explicit re-review reason preserved in the correction audit. Legacy supporting images can receive a new review in the replacement, without inventing an original monetary review.
- Compute replacement coverage under its workflow. Ordinary evidence remains optional with truthful coverage labels; advance liquidation needs full coverage of every gross cost/asset debit; release proof remains optional; return proof and its explicit amount-bound confirmation remain mandatory.
- No two active claims for the same image may coexist outside its correction lineage. Original claims remain historical; the full reversal cancels their effective claim only from its accounting date. The replacement then becomes the effective association. This is not permission to reuse an image while the target is still financially active in an unrelated journal.
- Generated reversals reference original evidence for explanation, but have no new monetary claims or evidence allocations. Label coverage as a reversal/reference, not unsupported expense or newly supported expenditure.
- Discarding a correction draft releases both reservation types. Only new unposted draft-owned images can be soft-discarded under existing rules; never discard or delete a reused posted image or mutate its prior review.

All existing posting/discard/extraction/review paths must respect correction reservations. Posted reused images remain ineligible for OCR reprocessing; correction review is manual. Preserve ordinary unreserved receipt behavior and durable lost-upload-response recovery.

Validate the association chain explicitly at posting and in preflight: each reused association has the same receipt as its source, its source belongs to the correction target, its journal is the linked replacement, and every transition is an eligible posted correction. At each accounting date, follow cancellation/replacement events so at most one effective claim for that receipt remains. A matching net balance or the same file hash alone does not prove a valid reuse relationship. Evidence omitted by a replacement remains historical and is not made generally available for a new claim.

### 5.3 Readers, downloads and privacy

The current internal reader starts from `Receipts.JournalEntryID`, so it will miss replacement associations unless changed. Drive Stage 3 evidence collection from `posted_evidence_associations`, joining the shared receipt and that association's own review/allocations. Keep historical primary/legacy compatibility and avoid duplicate rows. Coverage must come from the relevant association, not the original journal's review.

Update shared entry reopening, Journal History/book HTML and JSON, metadata helpers, printouts and `receipt_attachment.php` together. A download request may specify a replacement journal only when a valid posted association exists. It must never gain access merely because a journal or receipt ID was guessed.

Preserve Stage 2 privacy throughout the correction chain, including GJ reversals and replacement releases with new IDs. Calculate coverage internally before redaction. Management receives permitted posted journal/advance/correction summaries, not advance image IDs, filenames, URLs, hashes, allocation details, review reasons, confirmations or external approval snapshots. Ordinary evidence keeps its existing permissions.

Sensitivity derives from persisted workflow/correction/association links and trusted active viewer identity, not a request flag, role string or whether the UI is enabled. Unknown/inconsistent sensitive links fail closed. A receipt linked to private advance evidence must not become accessible through an older primary URL or a less restricted reused association.

## 6. Proposed storage and service contracts

Names below are the intended implementation contract; retain them unless a discovered repository constraint justifies a documented change.

### 6.1 Additive migration 021

Create `migrations/021_journal_corrections.sql`, apply once after complete 019/020. It must contain no hardcoded `USE`, destructive cleanup, financial seeds, inferred corrections, foreign-key disabling or removal of existing immutable protections.

Create:

- `journal_corrections`: unique target journal, unique reversal journal, nullable unique replacement journal, root journal, nullable parent correction, unique draft/submission key, mode, accounting date, reason, actor/UTC time, canonical request hash and validated private review snapshot. Use restrictive links to journals, drafts, historical actor and parent. Mode enforces replacement presence/absence. Links and posted snapshots are immutable.
- `journal_correction_lines`: correction ID, original line ID and unique reversal line ID; one original line may be reversed only once. Restrictive FKs plus service/preflight checks enforce complete exact-opposite line mapping within the linked journals.
- `cash_advance_operation_reversals`: unique original operation, correction, reversal journal and reversing control-line links. Read amounts/date from the linked line/journal. Immutable append-only rows; do not modify `cash_advance_operations` or the `cash_advances` identity trigger.
- `correction_evidence_reservations`: unique receipt ID, owning draft, source association and created-at UTC. This is mutable private draft coordination, separate from posted history and existing unposted-image reservations.

Extend `journal_drafts` with nullable `correction_target_journal_id`, nullable correction mode, and a `correction` workflow option; preserve existing v2/v3 rows/defaults. Use payload version 4 only for correction drafts. Their `advance_id`, where applicable, identifies the original affected advance; replacement release identity belongs in the posted correction/operation links and must not rewrite this immutable target context.

Extend `posted_evidence_associations` with nullable source-association and correction links. Leave all existing rows NULL, with no fabricated provenance. New ordinary associations also leave these NULL. New correction-upload associations record the correction but no prior source; reused associations record both. Existing unique journal/receipt keys and immutable review/allocation triggers remain.

Add triggers preventing updates/deletes of posted correction/mapping/reversal rows and updates/deletes of posted journal headers/lines. These do not require changing an original status to Reversed. Existing writers insert posted headers before their lines, so do not add a line-insert prohibition that breaks ordinary/advance posting. Balanced creation remains service-controlled and transactional; these protections do not claim resistance to privileged database administrators disabling triggers or writing arbitrary rows.

Use InnoDB, restrictive FKs, validated JSON, UTC writes, exact-centavo limits and compatible current INT UNSIGNED identifiers. Cross-table semantic relations still require service/preflight validation; a foreign key alone is insufficient.

### 6.2 Version-4 correction draft lifecycle

Persist target, mode, root/parent context and original advance identity from the server at creation. Target and mode are immutable after draft creation; changing mode means starting another private correction draft. Ordinary replacement book may change explicitly before posting, with revision/review invalidation; advance replacement kind/book remain derived.

Cover create/save/load/resume, new upload/retry, attach/remove reused evidence, review documents, discard, explicit return confirmation where needed, financial review, atomic post, durable recovery and reopening posted drafts. Empty/incomplete draft values are saveable where existing drafts allow them, but cannot pass final review.

Keep v2 and v3 canonical shapes, hashes and retries unchanged. The shared loader recognizes v4 without feeding it through a v2/v3 normalizer. Every ordinary and advance public mutation endpoint must reject correction drafts, including uploads before storing files. Dedicated correction routes may reuse validated internal helpers without nesting transaction-owning public posting methods.

My Drafts shows Correction plus mode/target and the correct resume/discard destination. Preserve owner-only results, current last-saved-date filters, UTC/Manila display, ordering, failed-refresh retention and request sequencing. Posted correction reopening shows the posted bundle and immutable snapshots, not an editable reconstructed original.

### 6.3 Pages and actions

- `journal_correction.php`: Admin-only private comparison/workspace, opened from a posted journal or advance operation and resumable by owned draft ID.
- `journal_corrections.php`: posted correction list/detail/print for authorized Admin and Management viewers; all private projections remain server-controlled.
- `journal_correction_actions.php`: dedicated authenticated GET lookups/owned draft/eligible target/posted detail and CSRF-protected save, upload, attach reuse, remove, discard, confirm return proof, review and post actions.
- `includes/journal_corrections.php` and `includes/stage3_common.php`: explicit schema/gate checks, source derivation, canonical v4 payload, authorization, evidence adapters, exact reversal generation, chain state and atomic writer.

Responses use existing JSON envelopes and descriptive 400/401/403/404/409/503 handling. Validate scalar types, supported modes/books, IDs, real dates, revision, money, reason/text limits, owned workflow context and document identity. Request-supplied reversal lines, control accounts, root/parent links, actor, approval, original hashes or private-role flags are never authoritative.

### 6.4 Atomicity, locking and durable recovery

All correction financial writes participate in the existing actor lock and singleton accounting write barrier. Consistent lock order is active actor, shared write-state row, owned draft, sorted target journal/correction rows, sorted affected advances, sorted masters, sorted evidence/source associations, then sorted accounts. Validate compatibility with every existing writer and account/designation maintenance path; do not create another independent mutex.

Inside one transaction:

1. Authenticate, verify CSRF, load owned draft and durable key.
2. Recover an already-posted matching correction before review-token expiry/current eligibility checks. Changed content under the same key conflicts.
3. Lock/verify original target and latest chain eligibility, draft revision, financial/evidence/master fingerprints and current/historical advance state.
4. Generate the exact reversal from persisted original lines. Prepare/validate any replacement, fresh return confirmation and evidence reservations.
5. Insert reversal and replacement journal/line rows with distinct deterministic server-derived journal keys from the draft key. Insert correction/line mappings and applicable original-operation reversal/new-operation links in the same transaction.
6. Preserve reused receipt primary ownership while adding its association; link newly posted receipts normally. Insert validated replacement evidence allocations, release reservations and finalize the owned draft to the replacement journal, or reversal journal for reverse-only.
7. Audit the whole bundle, line provenance, accepted/reused evidence reviews and confirmation context. Reconcile affected accounts and validate dated advance balances before commit. Advance all affected concurrency revisions and the shared accounting version.

Do not call `workspace_post()`, `advance_post()` or `journal_post()` sequentially to build a bundle: they own transactions and would expose a partial correction. Refactor narrow internal writer stages so validated journals, evidence and operation links can be committed together, while keeping ordinary public authorization and idempotency intact.

Use a server-bound, short-lived review token tied to the current draft/version, target fingerprint/chain, generated reversal, canonical replacement, evidence context, selected date, affected advance revisions/balances and shared write version. Any relevant change requires review again. Matching successful retries after lost responses, logout/login or expired tokens return the original bundle, including all journal/advance IDs, without posting another reversal or replacement.

InnoDB row locking must run inside these transactions; autocommit `FOR UPDATE` is not a substitute. See [MariaDB FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).

## 7. Screens, navigation and reporting integration

### Correction workspace

Show the target ID/date/book/party/purpose and original complete lines read-only, followed by mode/reason/date, exact generated reversal, editable replacement if applicable, documents and current affected advance balance. For releases, show the blocking effective settlement list before an editable correction starts. Preserve a keyboard path through searchable selectors and visible validation recovery.

Review displays original, reversal and replacement separately plus the resulting net account effects and advance before/after balances. All journals must individually balance. Use clear action labels: Post reversal or Post reversal and replacement. A copied field or original evidence review must not masquerade as fresh financial review or management approval.

Busy/posted controls stay disabled. Restore focus after dialogs, show validation beside relevant controls, retain unsaved input on conflicts and permit deliberate reload. Retain current laptop/desktop shell and scoped workspace styles; no new UI dependencies or Tailwind build.

### Existing books and Journal History

Extend shared journal metadata/details with correction role, root/parent, target, reversal/replacement IDs, accounting date and permitted reason. Add Admin-only Prepare correction actions to eligible journals/advance operations and read-only chain navigation for other posted viewers. Do not add unsupported parameters to existing book-list links; a dedicated correction detail page may use its own validated correction ID.

Show original, generated reversal and replacement clearly. A replacement may itself have a later correction; retain all links. If a correction is outside the displayed date range, label its date rather than hiding the original or implying it was already reversed within the selected period.

Preserve CRB/CDB journal-level search selecting complete matching entries and totals, while Journal History retains its selected line-filtering behavior. Include correction IDs/reasons in appropriate search text without leaking private advance fields. Labels are metadata; do not filter corrected originals out of journal-derived balances.

Posted results and reopening/recovery provide correction detail, relevant book lists and Journal History once, plus the affected original/new advance links where applicable. Printable comparisons label Draft/Posted and accounting/recorded dates and enforce Management redaction before rendering.

### Existing financial figures and frozen snapshots

Existing Trial Balance, KPIs, expense/budget actuals and financial-record totals continue to include every posted original, reversal and replacement through the selected accounting date. Net line sums deliver the corrected result. Do not add an alternative latest-entry-only financial calculation.

Existing frozen Trial Balance revisions and reviewed statuses remain unchanged. A correction dated within a snapshot's as-of range changes the live source fingerprint even if its final account totals happen to match; it does not rewrite that snapshot. A correction outside its date range does not retroactively change those balances. New report bundles, stale-period closing rules and reviewed-report prerequisites remain later-stage work.

The later cash-flow stage must classify the net dated ledger effects and understand reversal provenance; neither source-book labels nor raw positive debit/credit activity alone describe actual cash receipts/payments. Stage 3 does not certify the future cash-flow calculation.

## 8. Implementation checkpoints after approval

Execute in this dependency order, keeping each checkpoint reviewable:

1. Schema, schema-aware privacy/chain readers and read-only preflight. Preserve all existing financial data; create disposable migration/preservation fixtures. UI remains disabled.
2. Complete v4 private drafts, target derivation, shared visible components, both evidence reservation types, association reuse/read/download authorization and return-confirmation adaptation. Verify ordinary/advance endpoints reject v4.
3. Exact ordinary reversal/replacement writer, durable recovery, inactive-reference rules, history/book/print navigation and frozen-snapshot compatibility.
4. Advance-operation reversals, replacement operations/new release identities, dated lifecycle validation and full both-direction per-account reconciliation. Integrate the new historical checks into ordinary Stage 2 settlement posting before enabling corrections.
5. Focused backend/browser regressions, screenshots, deployment/recovery guide and acceptance matrix. Resolve failures before marking implementation complete.

Do not activate a partially integrated checkpoint. In particular, a control-account reversal cannot be posted before the advance register and privacy readers recognize it, and reused evidence cannot be posted before all read/download paths enforce its associations.

## 9. Verification and acceptance evidence

The user has deferred broad working-system acceptance to final integration. Keep it pending. Focused automated checks on guarded disposable databases remain implementation verification; they do not create demonstration postings in `atikha_finance` or replace current-data restoration evidence.

### Backend and migration cases

- 021 preserves counts, original-column hashes, v2/v3 drafts, receipt primary links/file hashes, evidence associations/allocations, advances, due history and frozen snapshots. Existing nullable source metadata is not backfilled with guessed values.
- Exact opposite line mapping with several lines, split projects, transfer, inactive original account and ordinary legacy target. Reject incomplete, forged, unbalanced or previously corrected targets and correction of reversal journals.
- Payment PHP 1,000 to PHP 900; wrong account/project/party/reference; duplicate receipt reversal only; zero-difference metadata correction; second correction of a replacement; original status/lines/snapshots unchanged.
- Reversal and replacement each balance; ledger/TB/KPI/expense actuals net correctly. Original + reversal + replacement stays in journal queries. Book movement totals follow the selected routing, not a fabricated corrected-only total.
- Rollback injected at replacement journal, evidence allocation, operation reversal/new operation, mapping and audit insertion. No orphan correction, partially consumed reservation, partial financial bundle or advance imbalance remains.
- Two concurrent corrections of one target; correction versus ordinary advance settlement; changed-content key reuse; lost successful response, new session and expired review-token recovery. Exactly one matching bundle posts.
- Reused evidence from the target and from a corrected replacement; changed accepted amount with re-review reason; new evidence alongside reused; wrong-target reuse and duplicate reupload rejected; sum/side/line limits; discard never deletes posted evidence. Original primary journal/hash remains unchanged.
- Advance liquidation PHP 8,000 to PHP 7,000, equipment reclassification, withholding PHP 4,000 to PHP 4,050 reduction with PHP 4,500 coverage, return PHP 2,000 to PHP 1,500 with fresh confirmation, reverse-only reopening of outstanding balance.
- Release with an effective settlement is blocked; merely replacing that settlement remains blocked. After legitimate full settlement reversals, release cancellation/replacement passes. New release receives a new CA ID/number; old identity/deadlines remain historical.
- Replacement release with an inactive original employee or selected project is rejected even when its IDs are unchanged; non-person employees and inactive/ineligible accounts also fail. Deactivation after review rolls back the complete bundle, leaving the original financial record intact. With explicitly reactivated eligible references, a reviewed replacement can post with fresh snapshots. Exact original reversal and permitted linked-settlement/ordinary inactive references remain supported; forged exception flags do not bypass new-release validation.
- Before/at/after correction dates, historical canceled/replaced status, historical due changes and aging; selected date before target or after today rejected. Historical over-consumption by a backdated Stage 2 settlement is blocked even if current outstanding is sufficient.
- Current and historical full-account reconciliation independent of employee/project/status/search/page filters; changed-control replacement release reconciles both accounts; offsetting unlinked control lines and bad reversal mappings fail both-direction integrity.
- Snapshot figures/review unchanged, in-range live fingerprint changes, out-of-range financial balances unchanged. Designation/account protections persist after correction and when flags are off.

### Browser/HTTP cases

- Visible mouse/keyboard selectors, mode/date/reason interaction, full comparison, searchable replacement accounts/projects/party, inline master creation and focus restoration. Typing search text alone never changes selected IDs or review state.
- V4 save/resume/upload/retry/reuse/remove/discard/return confirmation/review/post/recovery/reopen; normal My Drafts last-saved dates and ordinary/advance workflows remain correct.
- Blocked release shows the actual dependency without privately exposing another Admin's draft. Stale review and conflict preserve editable draft values; successful posting leaves controls read-only and links correct.
- Management can navigate permitted posted chains and correct advance coverage summaries without image/review metadata in initial HTML, embedded data, JSON, print or guessed download URLs. Check with Stage 3 off, Stage 2 off and existing Stage 1 UI off where relevant. Anonymous/inactive viewers and forged role parameters fail.
- Screenshot correction workspace/review/posted result, advance corrected timeline/register and printable comparison at 1366x768 and 1920x1080. Inspect output and overflow. Browser/print-media evidence is not physical-printer acceptance or a claim about untested browsers.

Update existing tests to use visible selectors, not hidden native controls. Run relevant PHP/JavaScript syntax checks, `git diff --check`, affected Stage 1 and Stage 2 backend/browser regressions and new correction checks. Broaden only when changes/failures justify it. Report actual counts/results after execution; this plan promises no pre-passed tests.

### Deferred working-system acceptance

Publish an explicit case/expected-journal/expected-balance checklist in the delivery guide. Mark which cases require actual posting. Fictional correction/advance demonstrations stay isolated. Working tests use only legitimate agreed records and separate authorization for financial postings. Carry forward Stage 1/2 evidence, retry, access and book checks; later report integration adds its own checks rather than declaring them covered here.

## 10. Deployment, recovery and completion

Add `STAGE3_CORRECTIONS_ENABLED=false` to example configuration only during implementation. Effective correction entry access requires complete 021, Stage 1 and the new flag; advance correction entry additionally requires Stage 2. Persistent correction links, control protections, journal guards, evidence associations and privacy must remain enforced with UI flags off.

Provide `scripts/preflight_stage3.php` as read-only with explicit verified database target, prerequisite schema, partial-migration detection, original journal/evidence integrity, correction-chain/line mapping checks, both-direction advance reconciliation and reservation consistency. Update earlier preflights so a valid v4 correction reservation/new operation is not mistaken for damaged Stage 1/2 data.

Before separately authorized working deployment, take a fresh database/application/configuration/protected-file backup and record it. The October 6 pre-020 backup is not a post-020 Stage 3 backup. Recommend current-data restore/migration rehearsal; if the user defers it, record that limitation and do not mark recovery verified. Apply 021 once to the explicitly selected database, record schema/triggers/FKs and preservation hashes, then separately enable the feature when authorized.

Never rerun 015, 019, 020 or 021 on the working database. DDL such as ALTER TABLE and CREATE TRIGGER can implicitly commit and cannot be treated as transactionally reversible; on partial failure, stop and inspect the actual schema/recovery path. See [MariaDB implicit-commit documentation](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

Disabling the new flag is a UI rollback, not a database restore. It leaves posted original/reversal/replacement journals and protections intact. Recovery requires a verified matching backup and accounting for legitimate records created afterward; do not delete corrections or disable integrity triggers to imitate rollback.

Implementation is complete only when all selected workflows, evidence/read/privacy paths, ordinary/advance integrations and focused disposable checks pass and `docs/stage3-delivery.md` records actual evidence and pending work. Working migration/activation, restoration evidence, broad working acceptance and later report integration have separate statuses. Nothing in this plan commits, pushes, migrates or activates the system.

## 11. Requested external review

Review this document against `a5a681b` and the current Stage 1/2 contracts. Distinguish client evidence, user selections, proposed defaults and implementation controls. Prioritize gaps that can produce duplicated/cancelled balances, unsafe evidence reuse, privacy leaks, deadlocks, incompatible draft versions or incorrect historical advance states.

Specifically assess the GJ reversal/book totals rule; same-date atomic bundles; latest-target uniqueness and durable recovery; v4 lifecycle separation; receipt association/download migration; gross liquidation coverage; release dependencies/new-advance identity; dated negative-balance prevention in existing Stage 2 posting; flag-independent privacy and integrity; immutable snapshot compatibility; and migration/preflight preservation.

Do not expand Stage 3 into approval, closing, new reports, payroll or reimbursement modules merely because they are later dependencies. Report source-code inspection separately from a plan-only review. After review, amend concrete issues and obtain implementation authorization; a reviewer's quoted execute prompt is not itself user authorization.
