# Stage 2 delivery: cash advances, liquidation and cash returns

Delivered on October 6, 2026, against the Stage 1 source baseline `c74ddfe`.

**Implementation, working migration and activation complete; full working-system acceptance pending.** The user authorized implementation after reviewing the revised Stage 2 plan. At initial delivery, migration 020 had been applied only to explicitly guarded disposable databases, the working `atikha_finance` database remained before 020, and working `config.php` had not changed. Subsequent user-run working deployment and explicitly delegated activation are recorded below. This delivery did not commit or push the implementation.

## Delivered behavior

- Dedicated cash-advance release (CDB), supported liquidation (GJ) and unused-cash return (CRB) workspaces, with complete journal previews and separate book/history/advance links.
- Owner-private version-3 drafts across save, resume, evidence upload/retry, attach, removal, discard, review, confirmation, posting and durable successful recovery. Ordinary version-2 entry routes reject advance drafts; existing ordinary shapes and canonical hashes remain compatible.
- One posted release per advance, a permanent `CA-` number derived from its ID, immutable original employee/project/control context, and multiple partial settlements. No inferred historical advances or top-ups.
- Server-generated control lines and atomic journal/advance/operation/evidence/audit posting. Current balance and both-direction control-line reconciliation are checked inside the shared write barrier. Competing settlements cannot consume the same balance twice.
- Liquidation evidence covers expenditure or eligible asset debits only. Liability credits require explanatory allocation notes. Gross costs and net advance reduction are separate; evidence cannot be allocated to the generated control credit.
- Optional reviewed informational release proof. Cash returns require reviewed informational proof and an explicit server-owned confirmation bound to the actual advance, amount, receiving account, accounting date and reviewed document context/hashes. Changes require a fresh confirmation. Posted operation/audit records preserve confirming actor, UTC timestamp and context.
- Posted register, filtered totals, 25-row pages, independent full reconciliation, historical as-of balances, effective deadline history and separate overdue indicators/buckets. Failed or obsolete register requests cannot replace the last successful results. Pagination preserves applied filters.
- Original inactive employee/project references remain narrowly settleable with release snapshots. New unrelated inactive references remain ineligible. Journal-line allocations remain authoritative for project activity.
- Read-only Management register/detail/print summaries. Advance drafts, external approval metadata, document images and private document reviews remain restricted to Admin. Existing cash-book/history HTML/JSON and metadata/download paths enforce privacy on the server, independently of UI flags, without losing the permitted coverage summary. Ordinary evidence permissions remain unchanged.
- Printable Draft and Posted views, searchable entry selectors, My Drafts workflow labels/resume/discard routing, scoped stylesheet additions and feature-gated navigation. No new dependencies or Tailwind build.

The workflow concepts come from the visit notes and user clarifications; the reference crops establish account concepts and worksheet book columns. One-release limits, due-date policy, evidence gates, role privacy, book routing and confirmation interaction are selected design decisions. Balanced/atomic posting, authorization and preserved history are system/accounting controls. These statements do not claim that the cropped screenshots establish the entire workflow or that Stage 2 alone guarantees complete financial statements.

## Files and integration

New primary files:

- `migrations/020_stage2_advances.sql`: additive tables, draft context columns, restrictive foreign keys, unique operation/control/release links, validated JSON and immutable-history triggers. No `USE` statement, data seeds or automatic execution.
- `includes/stage2_common.php`: schema/feature checks, persisted sensitivity classification and authenticated private-evidence projection.
- `includes/cash_advance.php`: dedicated authorization, version-3 normalization, canonical financial input, evidence coverage, confirmation, atomic posting, deadline history, register/detail and reconciliation.
- `cash_advance_actions.php`, `cash_advance_entry.php`, `cash_advances.php`, `assets/js/cash_advances.js`: API, entry forms, register/detail, printing and filter/request handling.
- `scripts/preflight_stage2.php`, `scripts/test_stage2.php`, `scripts/test_stage2_browser.py`: read-only deployment checks and guarded synthetic verification.

Integration changes are confined to the existing workspace/template/script/styles, ordinary accounting action guard, My Drafts routing, accounting read model, receipt metadata/download authorization, sidebar and `config.example.php`. The example configuration defaults `STAGE2_ADVANCES_ENABLED` to false. Actual configuration remains untouched.

The shared draft loader understands v2 ordinary and v3 advance context. Shared internal document helpers retain v3 fields and clear affected return confirmation. Public ordinary endpoints enforce their v2 boundary before image storage or mutation. The common journal writer remains transactional and is reused within the dedicated advance posting transaction.

## Verified accounting examples

1. PHP 10,000 release debits the advance asset and credits cash; it records no expense. PHP 8,000 fully supported expenditure liquidation credits the original advance account. PHP 2,000 cash return debits cash and settles the remaining advance. Books, journal lines and register agree.
2. PHP 4,500 supported gross expense plus PHP 500 withholding payable produces a PHP 4,000 advance-control credit. Evidence required: PHP 4,500. Advance reduction: PHP 4,000. The Management history summary retains this denominator after attachment redaction.
3. An eligible equipment purchase can debit a noncash Asset. A partial cash return may precede liquidation, and several subsequent supported partial liquidations can settle the remainder.
4. Filtering the employee owing PHP 6,000 keeps the full control-account reconciliation at PHP 10,000 when another employee owes PHP 4,000. Reconciliation does not inherit employee/project/status/search/page filters.

No automatic tax calculation, reimbursement payable, digital approval, correction workflow, closing, frozen reports or new financial statements was added.

## Verification evidence

Focused synthetic checks passed on MariaDB 10.4.32 and XAMPP PHP, with external calls blocked and manual evidence/OCR disabled:

- **96 Stage 2 backend checks**, plus **73 Stage 1 backend regressions** in the shared bootstrap.
- **41 Stage 2 Edge browser/HTTP checks** on the final expanded build.
- **67 affected Stage 1 Edge browser/HTTP regressions** for ordinary entries, selectors, drafts, documents, cash-book search, retry behavior and permissions.
- PHP syntax validation of all 16 affected/new PHP entry/service/test files; JavaScript syntax checks of all three affected/new scripts; `git diff --check` passed.

The 96 backend checks include preservation of posted journals/hashes on 020, generated book routing, session/expired-token recovery, changed-content conflicts, unsupported/partial/overallocated/excess claims, gross withholding coverage, equipment, missing confirmation, changed amount/bank confirmation, historical deadlines and 0/1/30/31/60/61/90/91 overdue-day boundaries, inactive original references, concurrent settlements, rollback on operation-link failure, immutable history, draft ownership, role restrictions, current control reconciliation and wrong/offsetting unlinked control lines. A second database connection posts during a repeatable-read callback; repeated register/reconciliation reads remain the same snapshot.

The 41 browser/HTTP checks include visible selectors and inline person creation, private saved drafts, complete release/liquidation/return walkthroughs, retained liquidation allocations on resume, lost-upload-response retry without a second image, amount-bound return reconfirmation, posted disabling/navigation/reopening, independent reconciliation after filtering, failed-refresh preservation, ordinary document-route rejection, anonymous/Management restrictions and successful HTTP replay after login with an expired review token. Existing history/book redaction and direct image denial pass with Stage 2 both on and off; history privacy also passes with the Stage 1 UI flag off.

Final Stage 2 disposable database: `atikha_test_stage1_s2h` (the prefix reflects reuse of the guarded Stage 1 bootstrap). No working data was copied into it. The intentional audit/link failure fixtures print expected errors while testing rollback; the completed runs pass.

Private final Stage 2 artifacts:

- `.migration-private/stage1-core-251b9506f1/stage2-fixture.json`
- `.migration-private/stage2-browser-ed69334d39/`: isolated app/session/server log and `release`, `liquidation`, `return`, `register` screenshots at 1366x768 and 1920x1080, plus `detail-print-1366.png`.
- Stage 1 browser regression artifacts: `.migration-private/stage1-browser-7fec7092e7/`.

Screenshots and print-media output were visually inspected. They establish the tested Edge layouts, not physical printer pagination or all browsers. Chrome, live Gemini extraction, working-system posting/evidence acceptance and recovery from a current working-data backup were not performed in this delivery. A review based only on this guide is not independent source-code verification.

## Working preflight and pending deployment

Before working migration, the read-only preflight against `atikha_finance` passed with no problems, `writes_performed=false`, `deployment_state="020 not applied"` and `schema_ready_for_020=true`. This verified the inspected database/schema/data prerequisites; it did not establish that a backup restores correctly or that the working workflows had passed acceptance.

The earlier Stage 1 SQL export/file-copy evidence and waived restoration rehearsal belong to Stage 1. They are not current Stage 2 backup/restoration evidence. No Stage 2 restoration rehearsal has been recorded.

### Subsequent working deployment: October 6, 2026

- The user supplied a screenshot of `C:\Users\ACER\Documents\Atikha Backups\Before020\atikha_finance_before_020.sql`, showing a 73,390-byte SQL export. File existence/size is user-demonstrated evidence, not a verified restoration.
- The user ran Robocopy from the working application folder to `C:\Users\ACER\Documents\Atikha Backups\Before020\app`. The reported summary shows 20,287 files copied, 813.16 MB, zero failures, zero mismatches and zero extras; completion was October 6, 2026 at 03:11:04. This includes the application/configuration and protected receipt files by copying the application tree, excluding junction traversal. The copy has not been independently restored.
- The user chose to defer restoration rehearsal and remaining acceptance testing to final integration, then imported migration 020 into the working database and reported success. The earlier rehearsal recommendation remains recovery guidance; rehearsal is pending, not passed.
- Post-migration read-only preflight independently reports `deployment_state="020 already applied"`, `schema_complete=true`, `schema_ready_for_020=false`, no problems and `writes_performed=false`. The false ready-for-020 result is expected after successful migration; do not rerun 020.
- Read-only preservation comparison passes for all 16 pre-existing tables: counts and SHA-256 hashes of all original columns match the captured pre-migration baseline. This includes 2 journals, 4 journal lines, 32 accounts, 3 users, 6 historical identities, 1 receipt, 2 OCR attempts, 25 audit records, 1 party and 2 drafts. Newly added draft columns are deliberately excluded from the original-column hashes. No synthetic working postings were created. Private baseline/comparison artifacts are in `.migration-private/stage2-working-deployment/`.
- After the user explicitly delegated enabling, working `config.php` was updated to define `STAGE2_ADVANCES_ENABLED` as true alongside the existing true Stage 1 flag. PHP syntax validation passed, and the read-only schema preflight again reported complete 020 with no problems. The user subsequently confirmed that the new Cash Advances menu is visible in the working system. This is user-confirmed navigation evidence; full working-system functional and role acceptance remain deferred. Migration/preservation/configuration/navigation verification does not complete deferred functional acceptance or later financial-statement integration.

Deployment sequence, after the user's separate instruction:

1. Run `C:\xampp\php\php.exe scripts/preflight_stage2.php --database=atikha_finance` from the application folder. Stop on problems; do not repair data automatically or rerun older migrations.
2. Stop working-system postings while backing up/deploying. Export the current database and copy the application/configuration and all protected receipt files; keep the backup outside the served web directory. Record exact backup paths/time and verify successful output.
3. Restore that current backup into an explicitly named disposable database and isolated application/evidence/session copy. Verify target selection and restoration/preservation before applying 020 there. Smoke-test the new workflows in that isolated environment. Fictional fixtures and a preflight do not substitute for this current-data restoration rehearsal.
4. After rehearsal, apply `migrations/020_stage2_advances.sql` **once** to the explicitly selected and verified working database. It contains no hardcoded `USE` override. Record schema/constraint/trigger verification and preserved journal/line/evidence/master/draft counts and journal hashes against the pre-migration baseline.
5. Run the read-only preflight again. A complete 020 deployment must report `schema_complete=true` and no problems; `schema_ready_for_020=false` is then expected because the migration is already applied.
6. Only after schema verification and explicit activation authorization, set `STAGE2_ADVANCES_ENABLED` to true in working `config.php`, keeping Stage 1 enabled. Verify Admin and Management navigation/permissions and the working-system workflows using agreed data.
7. Record working-system acceptance or keep the remaining items explicitly pending. Stage 1's previously deferred working evidence/posting/retry/book/access checks remain pending until actually exercised.

**Never rerun 020, 019 or 015 on the working database.** DDL implicitly commits and is not transactionally undone. On a partial migration failure, stop and inspect the actual schema and recovery options rather than blindly retrying the file.

Disabling the Stage 2 flag hides its workflows; it does not undo schema or financial records. Existing advance journals, operation links, confirmations, reservations, account guards and confidentiality remain. Database rollback requires restoring a verified backup together with matching protected files/application state and accounting for legitimate records created after that backup. Do not delete posted advances to simulate rollback.

**Completion boundary:** Stage 2 implementation, focused disposable verification, working migration/preservation verification and explicitly authorized configuration activation are complete. Current-data recovery rehearsal, full working-system acceptance and later-stage reporting remain separate pending work.

## Completion supplement: October 6, 2026

After a read-only review of the approved Stage 2 scope, the user authorized this small supplement before Stage 3 Checkpoint 4. It completes the missing pre-post explanation and clarifies existing UI/documentation; it does not extend the accounting model.

- Liquidation shows that a posted image's unused or excluded amount cannot support another liquidation. A partially accepted monetary document also shows its exact excluded amount while editing and after draft resume. Fully accepted or invalid amounts do not show a fabricated excluded amount. This does not change separately validated Stage 3 correction-lineage reuse.
- Supporting-document help now describes full expenditure/eligible-asset debit coverage for liquidation, optional reviewed informational release proof, and required reviewed return proof/confirmation. Ordinary-entry wording remains intact. Evidence amounts, allocations, payloads, review validation and posting rules remain authoritative and unchanged.
- The register shows its successfully applied employee, originating project, control account, settlement status, overdue and search filters alongside the authoritative as-of heading. Printed output keeps these labels, identifies the current page and row range, and explicitly states that printing includes only that page. Totals are labelled as covering all matching advances across all pages; reconciliation retains its separate full-account basis.
- Unsaved filter changes, failed refreshes and older responses cannot change the printed scope or replace the last successful results. Print is unavailable until the register has successfully loaded. No full-register export was added.
- The README now states **64-bit PHP 8.1 or later** and the preflight-required extensions, distinguishing the application requirement from Composer's less restrictive dependency constraint.

Changed supplement files: `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, `cash_advances.php`, `assets/js/cash_advances.js`, `assets/css/accounting_workspace.css`, `scripts/test_stage2_browser.py`, `README.md`, this report, and the project status/decisions documents. Earlier uncommitted Stage 3 Checkpoints 1-3 remain preserved. No working database query/write, migration, local configuration change, new dependency, Tailwind build, commit or push was performed for the supplement.

### Supplement verification

Final command:

```powershell
python scripts/test_stage2_browser.py --database=atikha_test_stage1_s2supp20261006b --browser=msedge
```

- **169 backend checks passed:** 96 Stage 2 checks and 73 Stage 1 regressions, through the existing guarded bootstrap.
- **56 Edge browser/HTTP checks passed:** the prior 41 scenarios plus 15 supplement checks. These include optional/required workflow help, full/partial/invalid document amounts, resumed partial evidence, unchanged PHP 8,000 liquidation coverage from a PHP 10,000 document with PHP 2,000 excluded, failed-refresh preservation, applied filters, both 25-row page boundaries, unapplied changes, print-media visibility, and deliberately late responses that ignore cancellation.
- Existing release/liquidation/return posting, confirmation, privacy with UI flags on/off, owner restrictions and durable retry checks passed in that disposable installation. External requests were blocked and OCR stayed disabled.
- PHP syntax passed for the shared entry template and register page; JavaScript syntax passed for both changed scripts; Python syntax and `git diff --check` passed.
- Screenshots at 1366x768 and 1920x1080, plus print media, were captured. Laptop liquidation/register and desktop print screenshots were visually inspected. The 40-result/two-page renderer cases use explicit browser response fixtures with repeated sample rows, not 40 newly posted advances; their screenshots establish scope/label rendering, not account reconciliation or real financial totals. Real journal/register rules are covered separately by the disposable backend/workflow checks.

Private final artifacts: `.migration-private/stage1-core-5f6a007cad/stage2-fixture.json` and `.migration-private/stage2-browser-14351015a5/`, including `liquidation-1366.png`, `liquidation-1920.png`, `register-supplement-1366.png`, `register-supplement-1920.png`, and `register-supplement-print-1920.png`.

The sandbox could not import the existing private Playwright dependencies; the approved elevated rerun used the same isolated runner. An initial browser run caught a duplicated currency prefix in the new warning; it was corrected before the passing final run. Expected injected audit failures belong to the backend rollback tests and are not unresolved application errors.

**Supplement implementation and focused isolated verification are complete.** The working database/configuration were untouched, and no separate migration or activation is needed for these source changes. Current-data restoration rehearsal, broad working-system acceptance, Chrome/physical printing, Stage 3 Checkpoints 4-5 and later reporting remain pending. This run used the Stage 1/2 migrated disposable schema; it is not the deferred full-regression run on the fully integrated Stage 3 schema.
