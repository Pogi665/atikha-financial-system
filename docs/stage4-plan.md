# Stage 4 combined plan for cash books and General Ledger

> **October 23 presentation supplement:** the [master plan's adoption requirements](stages4-7-plan.md#11-usability-acceptance-is-part-of-every-internal-gate) apply to this ledger annex: guided accounting tasks, familiar read-only financial tables, obvious View transaction, no spreadsheet editing of posted records, and G1's internal adoption checklist. The external project-software observation establishes familiarity only, not integration, external policy or client acceptance. Ledger arithmetic and existing book/History semantics remain unchanged.

> **October 23, 2026 master-plan supersession:** the user requested expansion to original Stages4-7. Use the [combined master plan](stages4-7-plan.md) for current scope, permissions, dependency order and review/human checkpoints. This dated Stage4 plan is the ledger technical annex. Its standalone S4 human/final external reviews, seven-file whole-delivery ceiling and exclusions of later stages are superseded by the master proposal; ledger financial/privacy/request contracts remain. Combined external plan review and subsequent implementation authorization are pending. No implementation has started.

Prepared October 22, 2026. **Source inspection and planning only. External plan review and subsequent implementation authorization are pending.** The user selected a coordinated Stage 4 delivery and explicitly requires the same usability and clarity achieved in U1-U5. Their instruction to proceed follows the agreed plan-first sequence; it does not authorize a working migration, financial posting, deployment, commit or push.

## 1. Baseline and boundaries

Local branch `main`, HEAD and cached `origin/main` are `60206a7047d99975a29245e236ae786d16e31bc7` (`U-PERF-01 + U5`). No live remote request was made during this planning task. Earlier documentation saying that the U-PERF-01/U5 commit or external review is pending is superseded by the supplied approval and this observed local commit. Recheck local/cached/live identities at implementation preflight rather than silently changing this baseline.

The pre-existing working changes are the accepted manuscript detour: `docs/client-context.md`, `docs/project-status.md`, and the three files under `docs/manuscript/compact-flowchart/`. Preserve them. This task adds the Stage 4 plan and source inventory and updates current handoff documents. There was no pre-existing application/test/configuration diff. The original supplied manuscript and frozen U-PERF-01/U5 review package remain unchanged.

The original downloaded `plan_complete.md`, Stage 4 section, was inspected again. It requires complete originating cash books, a distinct account/period General Ledger, balances before search/pagination, exact full-journal drilldown, inactive-account history and Trial Balance agreement. The retained [completion roadmap](usability-completion-plan.md) and [client context](client-context.md) preserve that scope. This is not Stage 5 statements, Stage 6 budgets, Stage 7 exports, or a second usability program.

The [source inventory](stage4-source-inventory.json) records baseline file identities, inspected entry points, related references and proposed changes. Related-reference entries do not claim an exhaustive line-by-line audit. It is static source evidence, not a schema inspection or an application test result. No SQL, server start, migration, fixture construction, browser test or working login was performed to prepare this plan.

## 2. What exists and what is missing

- `financial_records.php` plus `accounting_records()` already provide Journal History and originating CRB/CDB views. With the existing Stage 1 feature enabled, book selection uses stored `source_book`; matching book entries retain all journal lines. Search/filtering and gross-activity labels were delivered in U2/U4. Preserve both the enabled behavior and existing flag-off contracts.
- Journal History is deliberately line-oriented and now displays journal identities. It is not a dedicated ledger with opening, running and ending balances. An older roadmap source-inspection sentence quoting a combined Journal History/General Ledger title is dated; the current title is `Find a transaction (Journal History)`.
- `accounting_trial_balance()` already sums posted `debit_amount - credit_amount` cumulatively through an inclusive accounting date and provides exact debit/credit balances. Its full-source fingerprint and frozen-report workflow remain authoritative and unchanged.
- `accounting_read()` owns a repeatable-read transaction only when needed and safely reuses the caller's transaction. `accounting_cents()`, `accounting_add()`, `accounting_decimal()` and `accounting_money()` provide exact arithmetic and overflow rejection. Reuse them.
- `journal_transaction.php` and `accounting_posted_journal()` already provide exact complete journals and permitted evidence/correction information. Stage 4 should link to this reader rather than create a second attachment, correction or financial writer.
- The existing account list includes inactive accounts and `Normal_Balance`. Account names/codes are current master labels. Party and line project labels have posted snapshots; these are different historical contracts.
- Migration source defines journal status/date and account/journal indexes. Their current deployed presence and query plans were not inspected in this task. No new migration/index is expected; verify on disposable clones before relying on a performance assumption.
- `includes/ledger_query.php` contains older Incoming_Funds/Expenses readers. Those are not the new General Ledger's financial basis. The file is also imported by `journal.php` for existing utility functions, so do not delete or rewrite it as dead code.
- Navigation and the Reports books section have CRB/CDB entries but no dedicated General Ledger destination. Live Trial Balance rows currently have no account-ledger drilldown.

Stage 4 therefore retains the delivered cash books and supplies the missing account ledger, its navigation/drilldown and bounded verification. Do not rebuild complete workflows just to rename this checkpoint.

## 3. User-facing outcome

Add a clearly visible **General Ledger** link under **Books & reports** for both existing roles. Preserve **Find a transaction (Journal History)** as the way to locate a complete transaction. Do not rename either page so they appear interchangeable.

The General Ledger opens with a short explanation, a searchable account selector, **From**, **To**, and one **Apply** action. Default dates are the current month through today in Asia/Manila. Initially no account is silently selected and no fabricated zero balance is shown: say **Choose an account to view its ledger**. Choices show code/name and an inactive label; typing uncommitted account text never silently selects a different hidden ID.

After Apply, show the applied account/code/status and period, followed by three easy-to-read summaries: **Opening balance**, **Period movements** (separate debit and credit amounts), and **Ending balance**. Explain once that opening includes recorded postings before From and ending includes postings through To. Earlier unrecorded client balances are not inferred.

Use a chronological movement table with date, journal number/book, purpose/reference/party, debit, credit, balance and an obvious **View transaction** action. Show the selected line's project-at-recording information as secondary detail without adding a separate project filter. Each row is one selected-account line; more than one line in a journal can legitimately affect the same account. State **Oldest first** and use fixed chronological order. Do not offer arbitrary sorting that makes the running sequence misleading.

Keep cash amounts aligned, labels readable, long descriptions wrapped and journal IDs visible. Use the existing role shell and interaction patterns. Scoped CSS should fit 1366x768 and 1920x1080 without document-wide horizontal scrolling. A table-only overflow fallback must remain keyboard accessible; it is not a substitute for inspecting those two layouts. Do not claim physical-device or cross-browser acceptance from desktop captures.

## 4. Ledger accounting contract

For selected account A, From F and inclusive To T:

- Opening = sum of debit minus credit on every posted line of A with accounting date before F.
- Period debits and credits = the separate exact sums on every posted line of A from F through T.
- A row's balance = opening plus the cumulative debit-minus-credit movement in **accounting date ASC, journal ID ASC, line ID ASC** order through that row.
- Ending = opening + period debits - period credits. It must equal A's cumulative net in the existing live Trial Balance at exactly T.
- For comparison, Trial Balance debit balance is max(ending,0) and credit balance is max(-ending,0). Display the absolute amount with the actual **Debit** or **Credit** side; zero has no invented side. Do not flip actual signs using an account's normal side, use type-based shortcuts, or label all credit balances as negative cash.

Only posted journals count. Include CRB, CDB, GJ, legacy/unclassified General Journal, originals, reversal entries, replacements, all correction generations, transfers and linked advance operations. Never exclude an original just because it was corrected. A reversal takes effect on its recorded accounting date, not retroactively on the original date unless those dates actually coincide.

Income/Expense are not silently reset at year boundaries. Opening is cumulative recorded history, with any genuine closing journals naturally included if present. This stage creates no opening journal, closing policy, fiscal-year rule, fund restriction, grant classification or balancing plug. An actual journal debit/credit side governs a contra-account balance.

Null book metadata is labeled **Legacy General Journal** consistently with the existing projection. Do not infer historical CRB/CDB routing from cash lines. Account labels are identified as current where needed; preserve recorded party/project snapshots. A non-null project ID with no historical snapshot must not be mislabeled Organization operations or given an invented historical name.

## 5. Exact arithmetic and consistent reads

Use one outer `accounting_read()` snapshot per successful ledger HTML/JSON/print request, including database-backed active Admin/Management authorization, account validation, opening, period totals, row count, running balances and the requested page. Nested helpers reuse that same PDO/transaction. Do not split the displayed totals and rows into independently observed requests or connections. Ordinary session validation remains in place; direct helper calls must not trust a forged session role.

Reuse exact server arithmetic. SQL DECIMAL aggregates/window results must be passed as exact decimal strings and range-checked through the existing helpers. No binary floating-point summation in PHP or JavaScript. JSON carries authoritative decimal/centavo strings; JavaScript formats/displays rather than recomputes ledger balances. Overflow, malformed source amounts and invalid required schema fail visibly rather than produce zero, a truncated total or a successful-looking ledger.

Validate the complete posted journals contributing to the selected account through T for the existing financial invariants: at least two lines, one positive debit or credit per line, positive equal complete-entry totals. A selected-account slice is not expected to balance by itself. Integrity failure must not receive a balanced/verified badge or usable printout. Do not drop offending entries to make the total agree. The integrity check must cover opening-source journals as well as visible-period journals, including journals hidden by search/pagination.

Ledger summaries are a financial read of posted lines, not a claim that every correction/evidence dependency across the organization has been independently revalidated. Do not add unvalidated correction-status badges or private evidence summaries. Full journal/correction/evidence inspection continues through the existing exact readers and their checks.

Each page request is live and internally consistent; it is not a frozen revision shared across page requests. Show live/source-date context and load time. Later legitimate backdated postings can change live historical figures; existing frozen Trial Balance revisions must remain unchanged. Do not introduce a cross-request cache or hold a transaction open while a user browses.

## 6. Filters, search and pagination

Use a dedicated validated request parser rather than changing `accounting_records_request()` or its cash-book counterpart. Proposed inputs are `account_id`, `from`, `to`, `search`, `page`, `page_size`, and a strictly enumerated HTML/JSON/print format. Reject arrays, malformed UTF-8, impossible/reversed dates, unknown response formats, invalid/out-of-range IDs or pages, unresolved account text and missing accounts. Inactive accounts remain selectable for history. An invalid ID must never broaden to every account.

The neutral landing page may omit account selection. A submitted ledger requires both dates; explicit blanks or a single supplied date are errors, not an unbounded scan. No year/fiscal reset is inferred. Valid supported future end dates are reading selections, not permission to create future postings; label the result as current recorded data through that selected date. Reset returns to the neutral account prompt and default dates. These are proposed Stage 4 interface defaults for plan review, not new client accounting policy.

Retain explicit Apply, applied versus draft criteria and **Changes not applied**. Successful requests update the applied account/period, summaries, URL and table atomically. Errors, timeouts, malformed responses, expired sessions and stale/out-of-order responses retain the last successful result with its old applied criteria clearly visible. An initial failure shows unavailable, not zero. Browser Back/Forward restores applied query state. Use abort/sequence protection; aborting a read does not trigger a writer or an automatic financial retry.

Search is an explicitly applied literal text search, at most 1,000 UTF-8 characters, across public journal number/book, reference, description, permitted party snapshot and the selected line's recorded project labels. Escape SQL LIKE wildcard characters and parameterize values. Do not search private filenames, document IDs, drafts, proof context or audit payloads. Do not add numeric amount search unless the reviewed contract is amended.

Calculate opening, period totals, ending and all row running balances **before** text search. Then select matching rows and paginate them. The three summaries continue to describe the complete account/period. When searching, show **Showing X matching movements out of Y period movements; balances include movements hidden by search**. Clearing search restores the same period rows and balances. Pagination uses applied criteria and does not silently submit unfinished filter edits. Return links preserve applied criteria, not unsaved filter typing.

Proposed page sizes are 25 (default), 50 and 100. Counts and page boundaries are server-derived. Changing applied criteria/page size resets to page 1; invalid or beyond-last requested pages must be handled visibly and canonically, with no false empty-account claim. A zero-row period with a nonzero opening carries that balance to ending. A newly created account with no posted history is a truthful zero-history result.

## 7. Query scope and performance

Do not call unbounded `accounting_records()` to build the ledger, or prefetch complete evidence projections for every historical journal. Read the selected account's posted lines, compute exact opening and full-period totals, and calculate deterministic row balances before the outer search/page restriction. Prefer a tested SQL window/CTE design on the actual disposable MariaDB version. Its final LIMIT belongs outside the balance computation. A bounded selected-account streaming implementation is acceptable if it meets the same semantics and measured target; no all-account/all-history evidence load.

Use current indexes where they exist. Capture relevant query plans, selected-account/period counts, query counts and response size. Current migrations suggest usable status/date and account/journal indexes, but installation/optimizer behavior remains unverified until implementation preflight. No index migration, persistent cache, global timeout/configuration change or removed integrity/privacy check is preauthorized to satisfy a target.

Proposed engineering acceptance: repeat HTTP median <=2.0s and browser-visible Apply/navigation median <=3.0s for both roles on two explicitly declared synthetic workloads targeting 500 and 5,000 selected-account lines. Report actual final workload counts; do not rename near-sized data or claim these are old U1/U5 datasets. Finish fixture construction first and run timings serially without concurrent suites. Record first sample, five repeat samples, median, slowest, status, bytes, role, account/period/search/page, PHP/server/browser mode and source hashes. Measure a normal first page and a representative search/later page. Report print preparation separately; do not turn browser print-dialog time into HTTP performance.

These are reviewable engineering targets, not an Atikha SLA. A miss is reported exactly and investigated within the bounded read path. A necessary migration/index/global-setting or shared semantic change is a scope stop. Do not round a miss into a pass, raise timeouts, flush caches, restart MariaDB between samples or skip validation. Preserve the approved correction-comparison optimization and its existing read behavior.

## 8. Drilldown, navigation and printing

Use the existing exact `journal_transaction.php?journal_id=...` reader for **View transaction**. The ledger must not build its own partial journal dialog that hides counterpart lines. Maintain permitted correction/advance links through the reader's existing projections. Management receives no authoring/private evidence access.

Provide a visible **Back to General Ledger** context on that exact reader when opened from the ledger. Carry only explicitly validated account/date/search/page/page-size fields and build a fixed local `general_ledger.php` URL. Accept no arbitrary `return_url`, external scheme, hostname, path or fragment. Invalid optional return context is safely omitted, without weakening transaction authorization or changing its existing default destination. Returning restores applied account/period/search/page and requests a current live result.

Add a General Ledger destination in the Reports books section and account-ledger links on **live** Trial Balance rows using stable account IDs and that report's month-start/as-of period. Do not add such a link to a frozen row as if it were a frozen ledger: frozen snapshots remain unchanged and their different source time is not concealed. A custom mid-month ledger must be compared with Trial Balance at the same exact date in verification; the existing month-end reports route is not represented as an arbitrary-as-of comparison.

Provide a clearly labeled **Print full period** action using the applied account and period, all chronological period rows and all summary balances. The label deliberately distinguishes this from a search-narrowed on-screen table; print heading states the full account/period and no search restriction. Do not silently print only the current page. Disable printing while edits are unapplied or the source is unavailable/invalid. Print uses a fresh authorized coherent read, marks live generation time, repeats table headings, hides navigation/actions and handles long descriptions. No CSV/XLSX export, PDF generation service, export bundle or frozen ledger revision is included; those belong to later scope.

## 9. Privacy and preservation contracts

All new HTML, JSON and print paths require an active database-backed Admin/Management actor. Refuse unsupported methods without mutation. Use private/no-store responses, safe HTML/JSON encoding and DOM text insertion. Do not expose database errors, credentials, session/CSRF/submission keys or internal upload paths.

The ledger list needs only whitelisted posted financial fields. It must contain no attachment bytes/URLs/filenames, proof confirmation context, draft payload, reservation or owner-private record. Existing exact readers remain responsible for their own privacy projection and direct-download enforcement. Required schema failure is unavailable, not a permissive fallback. Posted ledger access is not tied to an authoring UI flag; test permitted posted reads with Stage1/2/3 authoring flags off while the required installed schema remains valid.

Use fresh guarded disposable databases and private synthetic configuration, sessions and evidence for every mutating verification component. Never use the working database or working drafts/receipts as fixtures. Clone only a proven existing integrated synthetic source with the established guarded fixture helper, using each suite's required namespace and fresh destination. Do not rerun019's hardcoded database selection, destructive015, any already-applied migration, or remove immutable triggers to make an obsolete test fixture work.

At execution P0 capture current working table/schema/object/audit/permission fingerprints, protected upload bytes and application/server configuration/binary pins; compare after automated work and after the human read-only session. The prior U5 record of34 tables/24 triggers/62 audits/53 files is dated evidence, not permission to force current data back to those counts. Record actual fresh baselines and investigate unexplained changes during isolated work. No working financial posting, real receipt mutation, working login, grant change, repair/restore, recovery helper, upgrade or persistent configuration change is authorized.

If normal MariaDB is stopped, request one normal user-operated XAMPP start only when tests require it. If it fails or health is abnormal, stop; do not enter recovery or repeated startup attempts. Static preparation can proceed without SQL. Backup/health and migration009 diagnosis remain separate scopes.

## 10. Worked synthetic acceptance example

This example is a test oracle, not actual Atikha balances or a client policy. Use isolated synthetic accounts/parties and valid existing writers:

- Before the period, a balanced test journal records Bank debit PHP 10,000.00 and test Equity credit PHP 10,000.00. Bank opening on October 1 is PHP 10,000.00 Debit.
- October 2: ordinary payment PHP 1,000.00, expense debit and bank credit. Bank running PHP 9,000.00 Debit.
- October 3: receipt PHP 5,000.00, bank debit and income credit. Bank running PHP 14,000.00 Debit.
- October 4: advance release PHP 10,000.00, advance asset debit and bank credit. Bank running PHP 4,000.00 Debit.
- October 5: supported liquidation PHP 8,000.00, expense debit and advance asset credit. It changes no Bank line.
- October 6: return PHP 2,000.00, bank debit and advance asset credit. Bank running PHP 6,000.00 Debit.
- October 7: exact GJ reversal of the PHP 1,000.00 ordinary payment gives Bank debit PHP 1,000.00; replacement PHP 900.00 gives Bank credit PHP 900.00. Fixed journal-ID ordering gives running PHP 7,000.00 then PHP 6,100.00 Debit when those two journals share that date.

Bank period debits are PHP 8,000.00, credits PHP 11,900.00 and ending PHP 6,100.00 Debit. Expense ending is PHP 8,900.00 Debit; the advance asset is settled at zero; Income is PHP 5,000.00 Credit; test Equity is PHP 10,000.00 Credit. Trial Balance at the same date has PHP 15,000.00 debits and PHP 15,000.00 credits. CDB retains the original payment PHP 1,000.00, advance release PHP 10,000.00 and replacement PHP 900.00 as PHP 11,900.00 gross originating cash activity; CRB has PHP 7,000.00 gross receipts; the GJ reversal supplies the PHP 1,000.00 debit offset. Do not force either cash book's gross total to become the account ledger's corrected net.

Run independent cases for credit-normal and contra accounts, same-day/multiple same-account lines, an intra-cash transfer, nonzero opening with no movements, true empty history, inactive/missing/renamed accounts, exact large amounts, period boundaries and backdated/multi-generation corrections. A separate4500 gross expense/500 liability/4000 advance-reduction case must still require gross monetary support and show the actual ledger lines. A search showing only selected descriptions must retain each row's original complete-period balance and all summary figures.

## 11. Proposed one-shot execution sequence

One approved plan and one explicit implementation authorization should cover the following internal gates. Do not request external implementation approval after every passing component.

**S4-0 Preflight and preservation.** Verify baseline/local/cached/live refs, enumerate the retained documentation diff, stop for unrelated application/test/configuration drift, capture preservation, verify isolated fixture schema and database health. No silent reset/stash/rebase. Complete the proposed UI and calculation contracts before writing production code.

**S4-1 Exact read model.** Implement the dedicated parser/read model and focused financial/privacy/concurrency tests on fresh guarded clones. Prove worked examples, balance-before-search/page, complete contributing-journal integrity checks, all-account Trial Balance agreement, deterministic order, no role bypass and transaction ownership. This is an internal gate, not a separate external delivery.

**S4-2 Usable page and integration.** Build the focused selector/date/summaries/table, explicit filter states, exact drilldown/back context, live Trial Balance links, navigation and full-period print. Verify keyboard access, long labels, empty/invalid/inactive states, staleness/network/auth failures and desktop/print layouts. Reuse U1-U5 conventions; do not add an extra visual-prototype approval cycle unless a material unresolved design decision requires one.

**S4-3 Integrated verification and timing.** Exercise ordinary, advance and correction data through the ledger and existing books/readers; run affected regressions and role/privacy tests; time final read-path bytes serially; inspect screenshots and compare working preservation. Correct in-scope failures and rerun affected final-byte checks autonomously. Avoid inflating totals with preparation or superseded failed attempts.

**S4-4 Actual user usability walkthrough.** Prepare all independent automated work, a fresh guarded demonstration, synthetic role sessions and concise goal-only tasks before asking the user to interact. Ask once when the environment is ready. Suggested tasks: find a Bank ledger and period; explain opening versus ending; locate a movement with search without mistaking its running balance for a filtered subtotal; open the complete journal and return; inspect an inactive account and Management's read-only view; identify the full-period print scope. Do not give control-by-control instructions before the attempt. Record assistance, original confusion/failures and actual outcomes; invent no times, success rates or historical click counts.

Within the same authorization, fix a demonstrated in-scope label/layout/filter/read-calculation defect, rerun affected automation and retest only the affected human task. A human participation pause is an evidence dependency, not another implementation authorization. If the user is unavailable, preserve the ready state and mark this gate pending; do not declare full Stage 4 acceptance. Synthetic session setup is not normal production login/MFA evidence.

**S4-5 Final handoff.** Regenerate final source/test hashes, relevant final-byte evidence, production/test patches, sanitized commands/logs, timing samples, screenshots, working preservation and one exact-source ZIP/manifest after all in-scope fixes/retests. Include required test dependencies and forward/reverse patch reconstruction. Update README/status/roadmap only to established facts. Stop for **one final external Stage 4 exact-source/evidence review**. No commit or push.

## 12. Verification coverage and evidence

New focused tests should prove the financial oracle and public contracts rather than mirror private implementation details. Planned coverage:

- Opening/cumulative/period identities for every account, exact Dr/Cr side including contra balances, no zero substitution, overflow handling, fixed order and one row per selected line.
- Search/page variants, later-page carry-forward, matching/total counts, page-size changes, no-results with retained opening, reset/deep links, malformed inputs, inactive accounts and literal wildcard/HTML payload handling.
- Direct helper and HTML/JSON/print authorization, deactivated/changed actors, all authoring flags off, no private evidence/drafts in any ledger response, full-journal exact reader and existing download denial.
- Real concurrent actor/account/posting changes between staged reads using separate test connections, proving one snapshot per response and next-request visibility. Read-only fingerprints cover all application tables/audits and protected synthetic evidence before/after reads, print and navigation.
- Complete-origin CRB/CDB versus line History retention, gross cash-book totals with GJ reversal offsets, old book search/filter state, exact journal/advance/correction inspection and live/frozen Trial Balance distinction.
- Current selectors, keyboard focus, no accidental Apply from typing, visible journal IDs, full-entry/return context, pending/failure/expired-login states, guarded print scope and zero uncaught JS errors. Inspect1366x768/1920x1080 and print captures individually.

Existing source entry points to evaluate for affected regression reuse are `scripts/test_usability_u2_c_browser.py` (navigation/books/History), `scripts/test_usability_u4_browser.py` (exact inspection/corrections), `scripts/test_usability_u3_a_readback.py` (Management evidence/posted reads), `scripts/test_usability_u5_browser.py` (integrated daily workflow) and the accounting/reporting tests. Inspect each fixture's schema/prefix/consumed-state contract before executing it. Older Phase3 tests that insert raw journals must not be forced through current immutable writers/triggers; reproduce the necessary accounting/report assertions on an integrated disposable fixture where the old harness is incompatible. Do not disable safeguards to produce a larger test count. Broaden to additional Stage2/3 suites only if changed shared behavior, failure or unresolved concerns warrant it.

Keep automated action traces, source-reconstructed expectations and actual human observations separately classified. Count navigation/page changes, committed field changes, progression actions and assistance using the retained U1/U2 convention where observed. Do not invent a historical General Ledger baseline or count every keystroke as a financial field action. Later screenshots/regressions must identify their exact production hash set; if a tiny final change leaves earlier component evidence applicable, explain that qualification instead of claiming byte identity.

## 13. Expected files and review limits

Proposed production additions are `general_ledger.php`, `includes/general_ledger_query.php`, `assets/js/general_ledger.js` and `assets/css/general_ledger.css`. Expected existing production changes are `includes/nav.php`, `reports.php` and `journal_transaction.php` for the bounded navigation/live-row/back-context additions described above. Seven production files are anticipated. Confirm the final allowlist at implementation preflight; do not silently expand into shared writers, schema helpers or unrelated screens.

Reuse existing accounting/authorization/exact readers without changing their financial semantics. New Stage4 fixture/backend/browser/performance/packaging scripts may be added, with narrowly justified adaptations to affected existing tests. No Tailwind rebuild is expected for scoped CSS. Include executable dependencies and describe full-baseline/disposable-fixture prerequisites in the review package.

Documentation includes this plan, source inventory, future Stage4 delivery/verification/task guide and current status/roadmap. Keep client statements, user usability requirements, proposed design defaults and reviewer recommendations distinguishable. The accepted manuscript detour is preserved, not a Stage4 source blocker or permission to rewrite its original DOCX.

Hard stops during future execution: unrelated source/isolation/preservation drift, abnormal database health, unexplained working writes, unresolved financial/privacy regression, a needed schema/index/global configuration change, a writer/lifecycle/role change, a new client opening/year/grant/tax policy, or another material scope expansion. In-scope reversible UI/read fixes do not require a new routine approval.

Outside scope: Stage5 statements/worksheet/frozen bundles, Stage6 budget/dashboard implementation, closing/reopening policy or writers, Stage7 export/OCR expansion, normal client acceptance, working postings/login, migration009 diagnosis, backup/health/recovery work, migrations/feature activation, commit/push and production rollout. No future stage starts merely because Stage4's internal checks pass.

## 14. Next action

Submit this combined Stage4 plan for the user's chosen external plan review. Incorporate any in-scope clarification, then obtain the agreed implementation instruction. Successful execution would proceed S4-0 through S4-5 under that single authorization, with only genuine stop conditions and actual human-participation dependencies interrupting it.
