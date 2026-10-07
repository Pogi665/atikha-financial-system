# Stage 3 Checkpoint 4 delivery: cash-advance corrections

Implemented October 6-7, 2026, under the user's instruction to implement the reviewed [Checkpoint 4 plan](stage3-checkpoint4-plan.md). This report covers source implementation and focused disposable verification. Working migration/activation, working financial postings and Checkpoint 5 are separate and remain pending.

## Delivered behavior

- Release, liquidation and return corrections now use the dedicated atomic correction writer. Originals remain posted and immutable; the generated exact reversal goes to GJ. Optional replacements retain their prescribed CDB/GJ/CRB routing and the same correction date.
- A replacement release creates a new advance number and independently validated employee/project/control/cash/due-date/approval context. The v4 draft retains the original advance ID; committed operation links determine old/new result navigation. Replacement settlements retain the original advance and control account.
- Effective settlements, including replacement settlements, block release correction. Resolving current dependencies does not allow a release reversal to predate their effective reversals.
- One shared operation/event model serves register/detail/aging, correction validation, normal Stage 2 settlement validation and preflight integrity. It recognizes normal v3 operations, legitimate v4 replacements and exact operation reversals. Partial 021 fails closed; absent 021 still supports Stage 2-only installations.
- Complete date groups determine released, liquidated, returned and outstanding amounts. Candidate corrections and normal settlements are simulated through the full recorded timeline. Amounts and operation counts must remain valid at each date; current funds restored by a later reversal cannot fund an earlier settlement.
- Both-direction control-line/operation validation detects missing, extra, forged and offsetting unlinked entries. Each designated account reconciles independently. Filtered register totals remain separate from full-account reconciliation under the existing consistent read snapshot.
- Liquidation replacement coverage applies once to gross eligible expenditure/asset debits. Liability credits reduce the advance reduction; generated control credits add no evidence claim. Return replacements require fresh amount/context-bound proof confirmation.
- The register supports dated Cancelled and Replaced states, effective totals, original released amounts, reversal/replacement timelines and old/new identity links. Retired advances have no settlement/deadline-extension action. Historical due changes and aging remain dated.
- Review displays exact before/after amounts, accounting date, earliest affected date and provisional replacement-release balance. Posted, reopened and recovered results show the committed identities and ordinary book/history destinations.
- Advance-specific durable hashes include canonical advance fields, liability notes, proof and target context while excluding mutable balances and review tokens. Matching successful retries recover before current eligibility/token checks, including after another correction. Original normal advance requests still recover their original journal/advance after correction.
- Management coverage stays truthful while private documents/reviews/confirmation remain redacted across books, history, comparison, detail and downloads, including with accounting UI flags disabled. Existing private drafts and immediate-lineage evidence reuse remain intact.

## Components and transaction boundary

Added `includes/cash_advance_lifecycle.php`. Updated `includes/cash_advance.php`, `includes/journal_corrections.php`, `includes/correction_drafts.php`, `includes/correction_posting.php`, `cash_advances.php`, the shared entry template and workspace JavaScript. Existing register JavaScript/applied print scope and evidence reader primitives are retained.

The correction writer owns one coordinated `workspace_tx()` transaction. It generates journals, creates the replacement operation/new identity through a transaction-owned helper, records immutable correction snapshots and exact operation-reversal mappings, persists association-specific evidence and confirmation, finalizes the draft, releases only applicable stale reused-image reservations, writes audits and revalidates full reconciliation before commit. It does not call public transaction-owning advance posting inside that transaction.

The replacement operation is inserted before the correction row so the immutable correction snapshot can contain the actual new advance ID. Both are private intermediate writes in the same transaction; completed provenance is validated before commit. Failure rolls back both, including draft/revision/evidence/reservation changes. No schema amendment was needed.

Existing uncommitted Checkpoints 1-3, Stage 2 and Scan Receipt supplement work was preserved. No migration or configuration file was changed by this checkpoint, and no commit/push occurred.

## Worked verified results

- Release **10,000**, liquidation **8,000**: outstanding **2,000** before the correction date. Reversing that liquidation and replacing it with **7,000** leaves **3,000** from the correction date. Its replacement still blocks release correction.
- A **3,000** return closes that balance; replacing it with **2,500** restores **500** from the correction date. Earlier historical views remain settled.
- Gross cost **4,500**, withholding liability **500**: evidence must cover **4,500**, while the control-account reduction is **4,000**. A 10,000 advance therefore remains **6,000** outstanding. Eligible equipment replacements use the same rule.
- Replacing an unsettled **10,000** release with **9,000**, including a different control account/employee, retires the original as Replaced with zero effective balance and creates a distinct new advance with **9,000** outstanding. Both accounts reconcile independently. Earlier views preserve the original 10,000 release.
- A liquidation reversed today cannot finance an earlier 8,000 settlement that would make intervening balances negative. Posting it on the restored-balance date succeeds. A release reversal before its settlement reversal is likewise rejected.
- Browser example: release **10,000**, original liquidation **8,000**, original return **2,000**; corrected liquidation **7,000** and return **1,500** leave **1,500** outstanding, with all original/reversal/replacement entries visible.

Amounts are PHP, using exact centavos. Book totals continue to describe gross originating activity; linked GJ reversals supply offsets. These examples establish selected system behavior, not a newly confirmed client policy or physical bank reconciliation.

## Verification executed

Final backend command:

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint4.php --database=atikha_test_stage1_cp4fullc
```

Passed **394 backend checks**: 73 Stage 1, 96 Stage 2, 49 Checkpoint 1, 59 Checkpoint 2, 53 Checkpoint 3 and **64 new Checkpoint 4 checks**. This is a fresh execution, not a sum of old delivery totals. The runner applies 019, 020 and 021 in order: initial Stage 1/2 suites run before 021; Checkpoints 1-4 and their affected ordinary/advance cases run with complete 021. It is not the complete Checkpoint 5 regression matrix.

New checks include original advance-request recovery for all three kinds, successive release identities, expired/cross-session correction recovery, changed advance-field conflicts, active-release and narrow inactive-settlement rules, date-grouped history/aging, offset/forged provenance, per-account reconciliation, three two-connection races, and rollback injected at operation-reversal/new-advance/new-operation/audit writes. Checkpoint 3 regressions separately exercise evidence-allocation and stale-reservation rollback through the shared writer. Expected injected SQL failures were followed by successful preservation comparisons.

Browser/HTTP commands use isolated copied apps, private sessions/generated evidence, guarded disposable fixtures and blocked external integrations:

```powershell
python scripts/test_stage3_checkpoint4_browser.py --fixture .migration-private/stage1-core-280c4d87d6/stage3-checkpoint4-fixture.json
python scripts/test_stage3_checkpoint4_browser.py --fixture .migration-private/stage1-core-280c4d87d6/stage3-checkpoint4-fixture.json --followup
python scripts/test_stage3_checkpoint3_browser.py --fixture .migration-private/stage1-core-eb548ed83d/stage3-checkpoint3-fixture.json
python scripts/test_stage2_browser.py --fixture .migration-private/stage1-core-280c4d87d6/stage2-fixture.json
python scripts/test_stage3_checkpoint2_browser.py --fixture .migration-private/stage1-core-eb548ed83d/stage3-checkpoint2-fixture.json
```

Completed Edge results: **48** new Checkpoint 4 workflow checks, **10** final presentation/keyboard checks, **34** affected Checkpoint 3 checks and **56** Stage 2 checks and **44** affected Checkpoint 2 checks: **192** browser/HTTP assertions across these runs. Both disposable fixtures have complete migration 021. The Stage 2 runner now accepts an explicitly guarded existing private fixture so its normal UI regression can run against the integrated schema without silently bootstrapping a Stage 2-only database.

The 48 workflow checks cover actual isolated release/liquidation/return correction posting, a deliberately lost successful response and visible retry, reopened read-only results, cross-session recovery, identity/book links, blockers, coverage, proof invalidation, register filters, failed refresh preservation and Management privacy with flags disabled. The final 10 checks followed the separator cleanup and verify current-source posted links, keyboard cancellation, review date labels and laptop/desktop layouts without creating a financial posting. Earlier workflow/regression captures are dated evidence, rather than a claim every assertion was rerun after that presentation-only cleanup.

Captured and inspected 1366x768 and 1920x1080 review/posted/register/detail screens and print-media register/detail captures. No horizontal page overflow was detected. Headless Edge was exercised; latest Chrome parity and physical printing remain unverified.

Changed PHP files passed `php -l`; workspace JavaScript passed `node --check`; changed Python runners passed compilation. Whitespace verification passed. Scoped layout changes required no Tailwind build.

After the successful browser postings and normal Stage 2 regressions, read-only `preflight_stage3.php --database=atikha_test_stage1_cp4fullc` reported complete 021, checks passed, no problems and no writes. Its `file_bytes_verified=false` remains explicit; posting/evidence suites perform their own protected-file checks. This preflight concerns a disposable database only.

## Test artifacts and issues resolved

Private artifacts remain under `.migration-private`, excluded from Git:

- Final backend fixture: `stage1-core-280c4d87d6`.
- New workflow captures: `stage3-cp4-browser-b416d4edf4`.
- Final separator/keyboard captures: `stage3-cp4-browser-36eaf41ed4`.
- Ordinary correction regression: `stage3-cp3-browser-33494d2cfd`.
- Stage 2 integrated regression: `stage2-browser-2ff58a6437`.
- Checkpoint 2 draft/evidence regression: `stage3-cp2-browser-d91d453651`.

An initial regression race failed while helper edits could leave parent and worker processes using different loaded source versions; the stable fresh rerun passed. A new hash-conflict assertion initially used a nonexistent line-note key; the corrected actual-line test rejected the changed content. Older browser assumptions were corrected to compare full integrated reconciliation instead of a fixed 10,000 fixture balance and to enter earlier-date explanations when test execution crossed Manila midnight. Visible separator placeholders were found during screenshot review and corrected, then checked again. No financial validation was weakened to pass these tests.

Sandboxed browser runs could not access the protected local Playwright installation. Scoped escalated Edge test requests were used. No permission to migrate or activate was requested or used. These tool records do not establish whether approval was automatic or manual.

## Completion and next boundary

Source implementation and focused verification are complete, including the final affected Checkpoint 2 run. The subsequently supplied Gemini and ChatGPT reviews support proceeding to Checkpoint 5 planning; they do not independently verify the implementation. No working database was queried, migrated or seeded in this task; local configuration and feature flags were not changed.

Working 021/Stage 3 activation remain recorded pending from the previous delivery, not freshly verified here. Do not rerun 019/020, and never rerun destructive 015. Preserve recovery guidance and separately authorize future migration/activation after Stage 3 integration.

Checkpoint 5 retains full integrated acceptance, original **ordinary** posting replay after correction, final availability review and deployment/recovery documentation. The current stricter Stage 2 prerequisite for ordinary corrections is preserved; the difference from master section 10 remains unresolved for Checkpoint 5. Current-data restoration rehearsal, broad working acceptance and later financial statements remain pending.

## External delivery reviews: October 7, 2026

Both reviews supplied by the user support moving to Checkpoint 5 without Checkpoint 4 rework. ChatGPT expressly limits its assessment to the report. Preserve the four carry-forward obligations: original ordinary request replay, complete regressions on an already-migrated 019/020/021 schema, the ordinary-versus-advance availability discrepancy, and final deployment/recovery/working-acceptance documentation. The transaction-owned operation-before-correction insertion is accepted at report level because completed provenance is checked before commit.

Gemini recommends execution, while ChatGPT recommends detailed planning. The user has supplied their analyses here, not issued Gemini's quoted execution prompt. Working migration and activation remain pending; no Checkpoint 5 implementation has begun.
