# U2 follow-up before U5: coordinated plan

## Status and authorization

The user supplied ChatGPT's approval for one coordinated A-B-C execution, with four clarifications incorporated below; no further plan-review round is required. The review includes a recommended execution prompt. Implementation was initially held at the clean-tree gate: five documentation-only planning/handoff files were uncommitted. Preserve those changes; no commit, stash or reset is authorized. The user subsequently explicitly instructed proceeding through A-B-C while retaining those five documents for later commit/push. This resolves that gate through a declared documentation-only exception; all application/test baseline checks remain mandatory. Routine successful internal gates require no external approval pause.

The October 20 deferral remains binding: complete ordinary-workflow action-count evidence, inspect consistent evidence-currency formatting and clearer not-applicable transfer coverage, and publish an evidence addendum before U5. This changes delivery sequencing, not accounting policy or the approved U2 source checkpoint.

## Source and evidence inspected for this plan

- Expected execution baseline: branch `main`, local HEAD and `origin/main` both `65de5c8c8b91a62083cc7244145db255b575ae88` (`U4`), and a clean working tree. Fresh local checks match branch/HEAD/local remote-tracking ref. The supplied reviewer independently checked GitHub main at this commit; this task did not fetch/verify the live remote. Before execution require these exact identities again and a clean tree unless the user explicitly grants a declared documentation-only exception. Current uncommitted files are README.md, docs/project-decisions.md, docs/project-status.md, docs/usability-completion-plan.md and this plan. No application/test changes are present.
- Historical [U1 observed baseline](usability-u1-baseline.md): actual captured HEAD `73f7d5baa227b2a8ad133c63f6d881ec30986159`, with an application manifest and documented counting convention. Simple payment: two navigation controls/page changes, six committed field actions and two progression actions with defaults retained. The receipt has the corresponding six-field form, but its captured path started at the book; do not assert an observed Dashboard-to-receipt count that the report does not provide.
- Frozen U2-C revision: `ab653a1b59431d6c1ef0fad080f583da64961b8f`. Its shared workspace source is available in local Git. The historical routes are `cash_disbursement.php`, `cash_receipt.php` and `general_journal.php`; do not invent an older entry filename.
- [U2-B delivery](usability-u2-b-delivery.md), [U2-C delivery](usability-u2-c-delivery.md) and [approved U4 delivery](usability-u4-delivery.md) are dated implementation evidence. Preserve their exact review ZIPs, manifests and production patches.
- Current `assets/js/accounting_workspace.js` already has a BigInt-centavo parser/formatter and U4 document guidance. Its shared Review coverage renderer still concatenates raw decimal strings with PHP labels. `workspace_prepare()` supplies status and separate debit/credit covered/eligible amounts; zero eligible total yields `Not applicable`. These existing values are authoritative. No service/query change is needed or proposed.

## A - Establish comparable task measurements

Before application edits, freeze source identities and define the script/action ledger. Each trace records task, revision, start/end screen, fixed inputs/defaults, individual semantic action, category, resulting state and evidence reference. Keep these separate:

1. Navigation controls and actual page changes.
2. Committed field selections/edits, including explicit mode/add-line choices where required.
3. Progression controls such as Review and Record.
4. Required document upload/review/allocations and optional actions.

Do not count keystrokes, selector search typing, automation waits/inspection calls or scrolls as committed field actions. Record them separately only if relevant. Do not add an unnecessary Save when Review already persists. Do not count assertions as user actions. Required review, evidence and confirmation remain safeguards.

Use fixed synthetic data and equivalent defaults: existing eligible parties/accounts, today's accounting date, Organization operations, PHP 1,000 simple payment/receipt and no optional document for the baseline pair. State the endpoint as acknowledged recording with exact journal identity. For optional save/resume, evidence, withholding/split and GJ/internal-transfer traces, publish separate scenarios rather than blending their work into the simple pair.

First build the frozen U2-C action ledger from exact source at the declared revision plus existing dated U2 reports/screenshots. A source-reconstructed count is acceptable if every semantic action is auditable. Historical browser replay is optional, only when it materially resolves a missing required comparison and is safe without migrations; never launch it merely to upgrade an honestly inferred classification. Label every result historically observed, newly reproduced, source-reconstructed/inferred or unavailable. Measure the final current integrated source separately. Compare with U1 only where task boundaries, default/input choices and evidence requirements match. U1's documented counts may be quoted as dated observed evidence. If a historical scenario lacks an observed count, label a new safe historical replay as a new reproduction, source-only reconstruction as inferred, or leave the comparison unavailable. Never manufacture an observed U1 count or a client success rate. Any arithmetic difference must be reproducible from matched action ledgers; no percentage or speed claim without its own comparable measurements.

Historical replay, if needed, uses an allowlisted source export into a separate application directory, independent synthetic database, configuration, sessions and evidence, and blocked external calls. Never copy historical private directories, tracked cookies, dumps, uploads or connection secrets into the runnable copy. Do not boot historical source against current working credentials. Use a fresh independent clone per mutable scenario; never modify the preserved baseline source or working system. If historical schema compatibility cannot be established without migration, publish the precise measurement limitation and seek a narrow amendment if that prevents the required comparison. Do not migrate to obtain a convenient count.

**A gate:** a declared counting convention, matched source identities, auditable ordinary payment/receipt comparisons and separately labeled gaps/current-U4 measurements. Screenshots and assertions support each trace; they do not replace it.

## B - Apply only still-needed presentation refinements

Inspect the final current source first; U4 may already satisfy part of the deferral. Record an already-satisfied item without changing it.

### Evidence currency

Format displayed ordinary Review monetary coverage consistently with the existing peso/two-decimal presentation, using exact decimal strings through the existing centavo formatter. Preserve covered/eligible values, both accounting sides and the server status. Cover zero, one-centavo values (for example PHP 0.01), thousands separators, partial/full support and large valid values. Do not use floating-point money arithmetic or turn invalid/missing values into a valid-looking zero. Coverage response formatting must require a valid nonempty server decimal string within the actual existing server/database amount contract before exact BigInt conversion. Do not reuse the editable `cents()` empty-string-to-zero behavior for these fields. Null, missing, empty, malformed and out-of-contract values must show a truthful unavailable/error state, never a fabricated peso zero. Add focused assertions for all these rejected inputs and valid larger contract values. Avoid changing editable input values, payloads, API fields, comparison arithmetic or tokens.

Limit changes to the ordinary focused presentation. Shared rendering must preserve the existing advance, correction and legacy scalar-OCR paths unless a small shared formatting change is demonstrably needed and explicitly covered by affected checks. Prefer a small pure display helper if it makes exact formatting/state cases testable; no new service, endpoint, query or persistent feature flag.

### Transfer coverage wording

For a server-reviewed ordinary internal cash transfer with authoritative zero eligible monetary coverage, replace the confusing zero-of-zero presentation with a clear `Not applicable` explanation: monetary support has no eligible noncash line in this transfer. Do not describe this as a missing-document failure, complete financial verification or proof of payment/authenticity. Keep the exact two-cash-account journal and entry kind inspectable.

Do not suppress a nonzero side or hide inconsistent data merely because a payload says transfer. The special display requires all of: ordinary focused path, reviewed source book GJ, authoritative reviewed transaction kind `transfer`, authoritative status `Not applicable`, and both authoritative eligible sides exactly zero. Use the reviewed response, never editable/local state. If either side is nonzero, the status disagrees or the response is inconsistent, retain/show truthful existing side detail; invalid monetary fields use the unavailable/error display rather than being suppressed or converted to zero. Preserve any attached informational document and its existing review state. No new evidence eligibility rule is introduced.

Expected production touch point is `assets/js/accounting_workspace.js`, with a small pure adapter/helper change only if justified. Shared template/CSS edits are not assumed necessary. Record actual scope; stop for an amendment if the requested presentation would require backend/accounting/privacy changes.

**B gate:** both deferrals are either demonstrably already satisfied or narrowly corrected and verified, with unchanged authoritative amounts, payloads and workflow branches.

## C - Final affected checks and evidence addendum

Run syntax and focused pure formatting/state assertions. Use authenticated isolated browser checks for ordinary payment/receipt with no/full/partial evidence, GJ side-specific coverage and a valid internal cash transfer. Include 1366x768 and 1920x1080 captures for changed states, keyboard/readability and overflow checks. Cover advance/correction non-regression where the shared render path is exercised, Management restrictions and the scalar OCR handoff if touched. Preserve all substantive financial/privacy assertions.

If only the expected shared JavaScript presentation changes, rerun ordinary/advance/correction pure adapters; U2 payment and receipt/GJ browser suites; focused U4 correction/evidence cases that exercise the renderer; and the relevant Stage 2/Stage 3 smoke/regression paths for affected branches. Select and document the precise affected suites after inspecting the actual diff. A broader controller/template/dispatch edit requires the corresponding full affected suites. Fix within-scope defects and rerun affected checks against final bytes. Do not repeat unrelated database suites just to increase a total.

Pin all final production hashes. Repeat relevant current action traces if the final edit changes those controls or task steps. Keep frozen U2-C traces separate. Recheck the protected working fingerprints/files/configuration when operational verification uses the same MariaDB instance. Use existing bounded read-only guards; no repair, recovery helper or historical migration.

Publish one `usability-u2-followup-delivery` addendum with separate A/B/C outcomes, source identities, action ledger/CSV, commands/results, inspected screenshots and limitations. Include one exact-source ZIP/manifest/patch when source changes exist, changed test files and runnable required pure dependencies. If source edits are unnecessary, publish an evidence-only package and say so. Exclude private sessions, exports, credentials and real evidence. Record unavailable comparisons honestly; do not mark a required comparison complete if its evidence is missing.

Update current status/README/roadmap so the retained U2 follow-up is no longer pending only when its required evidence and refinements are complete. Preserve approved historical U2/U4 reports and ZIPs. Stop for one final external source/evidence review; do not commit/push without the user's instruction.

## Environment and scope boundaries

Before operational execution, resolve the clean-tree gate and perform fresh exact source/process/listener/SQL-identity/isolation preflight. The implementation envelope, when explicitly authorized, permits narrow application/test/documentation edits, creation/deletion of new guarded disposable atikha_test_* targets, isolated synthetic sessions/evidence, existing bounded test servers/harnesses, screenshots and the final addendum/review package. It permits no commit or push. The plan makes no claim about current MariaDB startup, data integrity or browser availability. If the database is unstable, stop rather than retrying startup or opening a recovery detour under this scope. Financial fixtures use only new guarded disposable `atikha_test_*` databases, isolated synthetic users/sessions/evidence and blocked provider activity. No working posting/login fixture, real receipt mutation, configuration edit, schema change, migration, account/grant change or server repair is authorized by this plan.

Keep U-PERF-01, U5/user walkthrough, full Atikha acceptance, original Stages 4-7, migration-009 diagnosis and backup/health work separate. No new accounting policy, writer, canonical hash/token, lifecycle behavior, role, permission, evidence rule or query is in scope. Source rollback, if requested later, is restricted to this checkpoint's presentation diff; never reverse posted journals or alter history as a source rollback.

## Review and execution sequence

**One external plan review -> one explicit implementation authorization -> A/B/C internal gates without routine external pauses -> one final exact-source/evidence review.**

Stop for preservation/isolation failures, unstable database, unprovable required comparison, financial/privacy regressions, a need for an excluded service/schema/policy change or other material scope expansion. Otherwise continue autonomously through the authorized internal gates. This is a small U2 completion checkpoint, not another U2 redesign or U5 acceptance claim.
