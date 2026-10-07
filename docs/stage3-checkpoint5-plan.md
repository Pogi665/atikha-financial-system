# Stage 3 Checkpoint 5: integrated verification and final delivery

Prepared October 7, 2026. **Planning only; implementation is not authorized by this document.** The current user request authorizes this plan and documentation updates. It does not authorize application edits, test execution, working database migration, financial postings, activation, commits or pushes.

Execution update, October 7, 2026: the user subsequently authorized this scoped plan. Implementation and isolated integrated verification are complete; see [Stage 3 delivery](stage3-delivery.md). The original planning-only statement remains historical. Working migration, activation and financial acceptance were not performed.

## 1. Outcome and scope

Review update, October 7, 2026: the user supplied Gemini's and ChatGPT's reviews. Both support implementation without another planning revision. ChatGPT's assessment is plan-only, not independent source/test verification. The reporting details in section 5.4 are made explicit below; scope and accounting choices remain unchanged. Reviewer execution prompts do not themselves authorize implementation or working deployment.

Finish Stage 3 source integration by resolving the ordinary-correction availability discrepancy, verifying the affected accounting workflows against a fully migrated schema, and publishing the final deployment/recovery guide. Retain the [approved Stage 3 specification](stage3-plan.md), [Checkpoint 4 delivery](stage3-checkpoint4-delivery.md), and recorded client/decision boundaries.

Checkpoint 5 has four deliverables:

1. Separate ordinary and advance correction entry gates, enforced by trusted server context and reflected in navigation.
2. Explicit original ordinary-posting recovery after correction, alongside existing correction and advance recovery tests.
3. A fresh integrated backend/browser verification matrix whose operational cases start with complete migrations 019/020/021.
4. `docs/stage3-delivery.md`, recording actual results, deployment/recovery instructions and concrete outstanding working acceptance.

Preserve unrelated uncommitted work, ordinary v2 drafts, advance v3 drafts, correction v4 drafts, immutable originals/evidence/snapshots, shared locking, exact centavos and private evidence. No new migration is proposed. Do not change working `config.php`, apply SQL to `atikha_finance`, use working receipts/drafts as fixtures, or make synthetic working postings.

Exclude approval modules, period closing, new financial statements/report bundles, budgets, reimbursement, payroll/tax automation and live Gemini troubleshooting. Later financial-statement integration remains a separate dependency. Fix defects demonstrated by the affected checks within the approved contracts; report a genuine schema limitation before changing migration scope.

## 2. Current source and documentation findings

Inspected local source, not just GitHub: HEAD remains `a5a681b` (`stage_2`), with Stage 3 Checkpoints 1-4 and supplements uncommitted. Preserve that working tree. This planning task did not query the working database or execute application tests.

- `includes/stage3_common.php::stage3_enabled()` currently calls `stage2_enabled()`. That blocks ordinary corrections when the Stage 2 UI flag is off, contrary to master section 10.
- `correction_guard()` is a shared global gate. The target/draft services already derive advance identity from posted operations and persisted draft context. Changing only the global gate would make advance correction routes reachable without their additional Stage 2 requirement.
- `financial_records.php` supplies one `correctionsEnabled` value; its JavaScript offers correction links using that value and journal eligibility. It needs workflow-aware availability rather than assuming every eligible journal has the same prerequisites.
- Reverse-only advance corrections have no editable advance replacement configuration. Gate them by the persisted target operation, not by whether a replacement panel exists.
- Migration 021 and its integrity/readers depend on migration 020 tables even if the Stage 2 UI is disabled. **UI availability and installation prerequisites are different.** This plan does not support installing 021 over a database lacking complete 020.
- Backend runners currently chain Stage 1, Stage 2 and Checkpoints 1-4, applying later migrations between suites. The reported 394 assertions do not establish that every Stage 1/2 operational case ran after 021.
- `test_stage1_browser.py` bootstraps its own new Stage 1 database; `test_stage2_browser.py` already accepts a guarded private fixture. Existing runners need an explicit integrated fixture/profile contract, not merely another invocation of the old chain.
- `preflight_stage3.php` already distinguishes absent, complete and partial 021 and returns preservation hashes. Its complete-schema next-step message still refers to Checkpoint 1. It reports `file_bytes_verified=false`; database preflight is not protected-file verification or successful restoration.
- README still identifies Checkpoint 4 planning as next and omits its delivery link. Current status also describes some uncommitted source as Checkpoints 1-3. These documentation discrepancies are corrected with this planning handoff; no past verification totals are rewritten.

The supplied reviews support proceeding, but review the delivery report rather than independently verifying the uncommitted implementation. Their concrete carry-forward items are the four deliverables above. No new Atikha policy is inferred from reviewer praise or screenshots.

## 3. Availability integration

### 3.1 Server contract

Keep `stage3_enabled()` as ordinary correction entry availability: the Stage 3 flag is strictly true, Stage 1 is enabled, and the complete compatible Stage 3 schema is present. Explicitly preserve the complete 019/020 prerequisite validation used by deployment/schema checks; a flag being off must not be confused with a missing schema.

Add or adapt a shared workflow-aware entry guard. For an advance target/draft, additionally require `stage2_enabled()`. Derive the operation from the posted target and validate it against persisted v4 draft identity. Reject mismatched IDs and unrecognized workflow context; submitted `advance`, transaction-kind, book or replacement fields cannot bypass the gate.

Apply this contract to correction page loading, target/resume requests, save, review, attach/reuse/upload, remove/discard, return-proof confirmation and posting. Check advance context before modifying a draft, storing a file or entering a successful retry path. Revalidate authoritative context in the existing posting transaction, without breaking its lock order. Do not obtain resource locks through a new inconsistent path merely to test a UI flag.

Keep owner/role/identity/CSRF checks in place. Ordinary and advance endpoints continue rejecting v4 drafts. Do not put the new workflow gate inside shared posted readers or integrity functions: books, comparisons, evidence associations, lifecycle calculations and authorized downloads must remain correct when UI flags are off.

Selected implementation behavior for paused advance correction entry: its dedicated draft actions, including retries, return the existing unavailable-style response while Stage 2 is disabled. They do not mutate/discard drafts or evidence as a workaround. Matching retries recover normally after prerequisites are restored. Permitted posted comparison/history remains readable throughout. This narrows entry availability; it adds no new permission or financial policy.

### 3.2 UI and response contract

Reflect server-derived per-target entry availability in Financial Records and any other correction-start links. A global ordinary gate alone is insufficient. Separate financial eligibility (for example, already corrected or generated reversal) from feature availability. Do not redact financial history simply to hide a correction-start action.

Audit Cash Advances, correction pages, My Drafts resume navigation and shared workspace configuration. An owner can still see a listed saved draft within existing list permissions; an unavailable resume gives a clear paused-workflow message. Never send another owner's draft details. Existing authorized private-preview rules and posted viewer redaction remain independent of UI activation.

### 3.3 Required availability cases

- Complete 019/020/021, Stage 1 and Stage 3 on, Stage 2 off: ordinary correction save/review/post/recovery works; advance release/liquidation/return correction entry is unavailable, including reverse-only and crafted HTTP requests.
- All three flags on: ordinary and advance workflows work under their normal validations.
- Stage 3 off, or Stage 1 off: correction entry/mutations are unavailable; permitted posted chains and privacy/integrity remain effective.
- Absent or partial 021: no correction entry. Missing/incompatible 020 is a schema prerequisite failure, not a supported ordinary-only installation.
- An advance correction cannot be made ordinary by stripping its advance ID, changing book/kind, forging a payload or targeting its replacement. Gate checks recognize replacement-operation provenance.
- Management, inactive/anonymous users and forged roles cannot mutate corrections. Every denied request preserves journal, draft, audit, reservation and file state.
- Opening a posted chain with flags off retains correct permitted coverage and financial links without private images, hashes or review details for Management.

Use separate PHP processes/app configurations for flag combinations; defined constants cannot be reliably toggled within one process. Do not edit working configuration to test them.

## 4. Original posting recovery after correction

Create isolated ordinary CRB, CDB and GJ requests and save their successful canonical request context before correction. Include the supported transfer path and legacy journal submission compatibility as distinct cases where their contracts differ. Then correct their posted journals through the normal correction writer.

Replay the **original posting request**, not the correction request:

- Return its original journal ID and duplicate/recovery result. Do not return the replacement journal as the original result.
- Recover after lost response, logout/login, changed session and expired review token, with current valid authentication/CSRF where required.
- Use the original durable key and original canonical payload. Changed canonical content using that key conflicts rather than silently recovering or inserting again.
- Verify no new journal/lines, correction/advance operation, evidence association/allocation, audit row, reservation, draft revision or accounting write version is produced by successful recovery.
- Preserve original/replacement snapshots, original evidence ownership and correction links. Retry must not need to reserve a document again or fail merely because the original journal is now corrected.
- Cover reversal-only, replacement, and a subsequent correction of the replacement. Also exercise recovery through the HTTP boundary, not only direct service calls.

Retain separate correction-request and original advance-request replay cases. A passing correction retry does not substitute for original ordinary recovery. Test changed-content conflict without relying solely on row counts; compare relevant state and returned identity.

## 5. Integrated test harness and execution

### 5.1 Separate schema-transition and operational profiles

Add a guarded integrated profile/orchestrator, proposed entry point `scripts/test_stage3_checkpoint5.php`. Reuse existing assertions and fixture helpers; do not create a second accounting engine inside tests.

The integrated profile creates a new approved disposable database name, isolated evidence/session storage and fixture manifests. It establishes the foundation and applies 019/020/021 once before the first operational regression case. Assert complete schemas, required triggers/FKs and the verified database name at profile start and before independent workers run.

Where legacy pre-migration fixtures are necessary, prepare them during setup, migrate once, and then run their compatibility/replay assertions against the complete schema. Handle migration 019's hardcoded database selection through the existing verified disposable-target mechanism; never send its original working-target statement to a test connection unchecked.

Retain a separate schema-transition profile for assertions that deliberately require absent/partial schemas, initial legacy migration, preservation comparisons or preflight transitions. Do not remove those tests, silently skip operational assertions, or count transition-only results as integrated operational coverage. Test-only damaged-schema/integrity cases use disposable transactions or dedicated databases so later suites never inherit corrupted fixtures.

Refactor the current chained runners enough to share setup and operational cases while preserving standalone use. Ensure flags, source version, database and private evidence paths propagate to concurrency/privacy workers. Failure aborts the affected run and is reported; a partial count is not a passing matrix.

### 5.2 Backend coverage

Run the complete operational assertions from Stage 1, Stage 2 and Checkpoints 1-4 after complete 021, plus the new availability/replay cases. Inventory the affected older journal, accounting/snapshot and receipt suites. Run their compatible operational cases in the integrated profile, keeping foundation-only migration tests separate. Record exactly which suites/cases run in each profile; do not claim all repository tests if unrelated suites were not executed.

The matrix must cover:

- Ordinary balanced entries, exact amount validation, private drafts, master/account protections, evidence allocation/coverage/reservations, last-saved filters and durable posting recovery.
- Stage 2 release/liquidation/return, gross versus net withholding, asset costs, strict support/return confirmation, overspending rejection, inactive-reference rules, deadlines/aging and filtered totals independent of full-account reconciliation.
- Corrections of ordinary and advance operations: exact GJ reversals, same-date atomic bundles, release blockers, new replacement-release identity, latest-target uniqueness and valid correction lineage.
- Historical balance protection in both correction and normal settlement writers, grouped by accounting date; before/on/after correction dates and deadline changes.
- Both-direction control integrity and reconciliation for both affected accounts when replacing a release's control account; offsetting unlinked control lines must not pass merely because net balances match.
- Evidence reuse with fresh review and relevant associations; original primary ownership/bytes/hash unchanged; unsupported, unrelated or duplicate reuse rejected.
- Two-Admin races, correction versus settlement, stale reused-reservation cleanup for the exact target only, and rollback preservation of competing private drafts/uploads/unposted reservations.
- Injected failure at every affected transactional write stage, proving no half bundle, leaked financial operation, consumed reservation, changed audit or partial advance identity survives.
- Original ordinary/advance recovery, correction recovery, wrong-content key reuse, owner/role enforcement and flag-independent Management privacy.
- Existing live ledger/TB/KPI/expense calculations and frozen Trial Balance compatibility: corrected net effect, unchanged frozen figures/review, correct live fingerprint. These checks do not implement the later six-report bundle or indirect cash-flow statement.

Capture expected balances in exact centavos. Include the approved worked examples: PHP 1,000 payment corrected to PHP 900 gives PHP 1,900 gross CDB activity and PHP 900 net ledger effect; PHP 8,000 liquidation corrected to PHP 7,000 restores PHP 1,000 outstanding; PHP 4,500 gross cost with PHP 500 liability reduces the advance by PHP 4,000 while requiring PHP 4,500 monetary support. Add return and release-replacement timelines with explicit expected IDs/dates, not just “posted successfully.”

### 5.3 Browser/HTTP coverage

Adapt Stage 1 and receipt browser bootstraps to accept explicitly verified integrated fixtures, following the guarded fixture mechanism already used by later runners. Keep normal new-database standalone modes. Create separate fresh fixture baselines per browser suite or an explicit isolated reset/recreation contract; one suite must not silently consume another's unposted draft/evidence.

Run affected Stage 1, Stage 2, Checkpoints 2-4 and new Checkpoint 5 browser/HTTP cases against complete 021. Include the Scan Receipt supplement's affected manual/doubled-provider cases against this schema; do not call live Gemini, SMTP or other external services. Fixture transport substitution stays inside the isolated copied test app.

Required visible interactions include:

- Mouse/keyboard searchable selectors, no-match/cancel behavior, multiple rows, inline creation/focus, restored drafts and posted disabling. Search text alone cannot change record IDs or invalidate review.
- My Drafts last-saved Manila boundaries, incomplete accounting dates, owner privacy, stale-response protection and retained successful list on failed refresh.
- Ordinary/advance/correction draft resume, upload/reuse/remove/discard, proof confirmation and changed-context invalidation, review/post/recovery and posted links.
- Original ordinary request replay after correction through authenticated HTTP; posted/recovered/reopened navigation remains accurate.
- Complete-entry CRB/CDB search/totals versus Journal History line filtering, gross labels, correction comparison and old/new advance navigation.
- Filtered advance register/aging/print scope, concurrency conflicts with retained editable values, stale-draft warnings and inaccessible correction entry when its workflow is paused.
- Role/privacy across initial HTML, embedded data, JSON, print, comparisons, associations and guessed downloads, including all relevant flag-off combinations.
- Refreshed Scan Receipt layout, upload/preview, successful doubled extraction, provider-busy failure and manual General Journal handoff; this does not prove real-image AI accuracy.

Capture and inspect 1366x768 laptop and 1920x1080 desktop screenshots for ordinary correction review/posted results, advance timelines/comparisons/register, searchable selectors and drafts. Include print-media checks and horizontal overflow/keyboard recovery. Record the browser/channel used; Edge results do not establish Chrome or physical-printer acceptance. A second browser run is warranted if a demonstrated browser-specific issue requires it, rather than being silently claimed.

### 5.4 Reproducibility and result reporting

Use stable fixture date anchors derived from Asia/Manila and explicit boundary cases. Avoid mixing browser-local “today” with accounting dates or crossing midnight during confirmation construction. A test clock, if needed, stays confined to isolated fixture code; do not add a working accounting-date override.

Run PHP lint on changed PHP, JavaScript syntax checks on changed scripts, Python compilation for changed runners, and `git diff --check`. `npm test` is a placeholder; scoped CSS needs no Tailwind build. Broaden verification only for new changes, failures or uncovered affected contracts.

The proposed integrated command, to be implemented and then recorded with an actual fresh run, is:

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint5.php --database=atikha_test_stage1_cp5integrationa
```

This is a planned interface, not an existing executed command. Record actual commands for each browser runner and guarded fixture from execution. Report backend operational, schema-transition, browser/HTTP and presentation counts separately, plus failures/fixes and final source revision. Do not add old overlapping totals together as new checks or promise a passing count in advance.

Identify the exact tested working source, including uncommitted and untracked implementation files. Record HEAD plus a deterministic SHA-256 manifest of relevant application/assets/migration/test files and dependency lockfiles, with a manifest fingerprint per final run. Preserve detailed manifests privately and publish the fingerprint and source scope; exclude credentials, sessions, uploads, exports and private artifacts. Identify isolated app copies and deliberate test-only substitutions separately. If source changes after a run, the delivery must identify which final checks exercised the changed source; HEAD alone is insufficient.

Publish a suite inventory in the final delivery: runner/profile, schema at operational start, included case labels or groups, actual command, fresh assertion count/result, and every exclusion with its reason and verification status. Distinguish migration-transition assertions from integrated operational assertions, and response-fixture presentation cases from genuine financial workflows. Preserve case-level traceability in private logs. A large total without this inventory cannot support the completion claim.

## 6. Final Stage 3 delivery and deployment instructions

Publish `docs/stage3-delivery.md` after execution. Summarize final behavior, changed components, gate prerequisites, exact worked results, fresh integrated evidence, remaining limitations and the current working deployment state. Link checkpoint reports as dated evidence; they need not be rewritten as if every earlier run used the final schema.

Update the preflight's obsolete next-step text during implementation. It should describe the actual schema state and direct readers to final deployment guidance, without implying activation is authorized or complete. Retain explicit database verification, absent/partial/complete handling, no writes and the truthful file-verification limitation.

The guide must give this read-only command from the application folder:

```powershell
C:\xampp\php\php.exe scripts/preflight_stage3.php --database=atikha_finance
```

No working preflight or migration is run by this planning task. During a separately authorized deployment, use this sequence:

1. Confirm the intended database and current schema. Absent 021 with no problems permits migration preparation; complete 021 means verify it and **do not rerun**; partial/incompatible 021 means stop and inspect a recovery path.
2. Take a fresh, coordinated backup of current database, application, configuration and all protected storage identified by the actual installation. Keep it outside served folders and out of Git. Pause writes while taking paired snapshots. The pre-020 backup is not a current Stage 3 backup.
3. Record backup size/hash, private file manifest and source/configuration identification without exposing secrets or receipt details. Inspect exports for database-selection/creation/deletion statements before restoring to another target.
4. Recommend restoring that actual backup into a deliberately isolated database/app with separate private files and sessions and external calls blocked. Verify restoration counts/hashes before applying 021 there; an empty database migration is not current-data restoration rehearsal. If the user defers rehearsal again, record it as pending rather than passing or turning it into an invented authorization requirement.
5. Rehearse 021 once on that selected restored target, with Stage 3 disabled. Verify tables/columns/FKs/triggers, integrity and preservation of original fields; newly added schema fields are handled explicitly by the preservation manifest. Verify protected file bytes separately because preflight does not do so. Smoke-test the copied application without fictional postings into the working installation.
6. With applicable explicit working migration authorization, back up any intervening changes, confirm the working target again, then import only `migrations/021_journal_corrections.sql` once. It contains no hardcoded `USE`; still select/verify the database. Never import the full base schema or rerun 015/019/020. DDL implicit commits mean failure cannot be treated as transactional rollback.
7. Repeat read-only schema/integrity/preservation checks. Record actual before/after manifests and distinguish original unchanged records from any separately authorized later operations. Keep activation off on a failed/partial migration; do not repeatedly rerun SQL to conceal it.
8. Enable Stage 3 only with applicable activation authorization and complete verified prerequisites. Record ordinary/advance flag behavior accurately. Run nonposting working smoke checks, then separately agreed legitimate-record acceptance where authorized.

Recovery instructions must distinguish disabling the UI flag from restoring the database. Flag-off retains posted corrections, evidence and protections. A true restore uses a verified matching database/files backup and accounts for legitimate records created afterward; never delete correction rows, disable history triggers or discard subsequent financial records to imitate rollback. Report missing/unverified recovery evidence honestly.

### Working acceptance checklist

For each case, the final guide lists setup, expected outcome, whether it posts, evidence collected and Passed/Pending/Blocked status. Carry forward Stage 1/2 cases rather than declaring them passed because isolated tests succeeded.

- **No posting:** current schema/flags/navigation; saved draft review; selectors/dates; permitted book/history/comparison/register display; role/draft privacy; authorized evidence download/review. Draft/file mutations still require agreed test inputs and must not take reservations from real workflows.
- **Posting required:** ordinary posting/retry and correction; legitimate advance release, supported liquidation, return and correction; expected lines, book, before/after outstanding and applicable historical dates. Specify exact amounts/accounts/records before execution and obtain applicable financial-posting authorization. Do not fabricate a real transaction just to make this checklist green.
- **Isolated only unless a legitimate matching case exists:** duplicate cancellation, intentionally invalid evidence, rollback injection, concurrent competing corrections, malformed requests and historical-overconsumption demonstrations. Keep fictional NGO demonstrations disposable.
- **Separate future evidence:** current-data restoration/recovery if deferred, later financial-statement/report-bundle integration, live provider accuracy and physical printing.

## 7. Implementation order after authorization

1. Implement workflow-aware availability and navigation, with focused gate/privacy tests. Preserve existing posted readers and locks.
2. Add the integrated test profile and independent fixture contracts; retain schema-transition tests separately. Add original ordinary-posting replay cases.
3. Run the complete affected integrated backend/browser matrix; fix demonstrated regressions within scope and repeat affected checks after fixes. Inspect screenshots and record fresh results.
4. Publish final delivery/deployment/recovery guidance, update README/status/decision links and run documentation/whitespace checks. Do not execute working deployment or final working financial acceptance as part of source completion.

No separate database-only checkpoint is needed for this scope. Checkpoint 5 is final Stage 3 source integration, not an authorization to implement later stages.

## 8. Completion and reviewer checklist

Checkpoint 5 source completion requires correct ordinary/advance gates, original posting recovery without duplicate writes, all affected operational suites passing on complete 019/020/021, retained schema-transition coverage, inspected UI captures and final delivery documentation matching actual evidence. Report unresolved failures explicitly rather than describing partial checks as full acceptance.

Maintain separate statuses for **source implementation**, **isolated integrated verification**, **current-data restoration rehearsal**, **working migration**, **activation**, **working acceptance**, and **later reporting integration**. Stage 3 can be source-complete while working deployment/acceptance remains pending. It does not complete the entire system.

External reviewers should prioritize gate bypasses, missing integrated operational cases, original-versus-replacement recovery identity, flag-independent privacy, fixture isolation and false deployment/acceptance claims. Share the master plan, this supplement, Checkpoint 4 delivery and relevant current source for a source review; a delivery-only review cannot verify code. No new client accounting decisions are needed to proceed with this scoped plan.
