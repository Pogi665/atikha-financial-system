# U2 follow-up before U5: final delivery and evidence addendum

**A, B and C are implemented and verified; external exact-source/evidence approval is pending.** The user explicitly authorized proceeding while preserving the five existing planning/handoff documents for later commit/push. The [approved plan](usability-u2-followup-plan.md) includes all four supplied review clarifications. No commit or push was performed.

## Source identity and package

Execution preflight verified branch main, local HEAD, origin/main tracking ref and live GitHub main as `65de5c8c8b91a62083cc7244145db255b575ae88` (U4). The only pre-existing changes were the five explicitly permitted documents. Production and tests initially matched U4. U4's reviewed reports, patches, manifest and ZIP remain unchanged.

- [Exact-source/evidence ZIP](usability-u2-followup-review-package.zip), [archive SHA-256](usability-u2-followup-review-package.sha256) and [source/screenshot manifest](usability-u2-followup-source-manifest.json).
- [Production patch](usability-u2-followup-production.patch), [test patch](usability-u2-followup-tests.patch), [sanitized verification log](usability-u2-followup-verification-log.txt) and [structured final results](usability-u2-followup-verification-results.json).
- [Action ledger CSV](usability-u2-followup-actions.csv) and [classified evidence/source identities](usability-u2-followup-evidence.json).

Current source means the U4 baseline plus the two final production hashes, not an unchanged U4 checkout. Packaging applies both patches to separate baseline files and verifies exact normalized reconstruction. All packaged files, safe historical evidence files and screenshots are hash-verified after ZIP creation. Private sessions, credentials, database exports and real receipts are excluded.

## A - Comparable action evidence: complete

Counting uses logical committed actions, with navigation controls, actual page changes, field selections/edits and progression controls kept separate. Keystrokes, selector search text, waits, automation inspection, scrolls and assertions are excluded. A task starts on Dashboard and ends with acknowledged recording and its exact journal identity. The simple scenario uses PHP 1,000, an existing party/cash account/counterpart, today's default accounting date, Organization operations and no optional attachment. Required/optional evidence actions for these simple tasks are zero; this does not remove evidence safeguards from other workflows.

Evidence is classified explicitly:

- **U1 simple payment, historically observed:** two navigation controls/two page changes, six committed field actions and two progression actions. Source application revision `73f7d5baa227b2a8ad133c63f6d881ec30986159`; counts come from the dated U1 report, not a newly run old application.
- **U1 simple receipt, dated documented form evidence:** six field actions and two progression actions. Dashboard-to-receipt navigation is **unavailable** because its capture began at the book. That unavailable ledger row is not a counted navigation action.
- **Frozen U2-C payment and receipt, source-reconstructed/inferred:** each has one direct navigation control/one page change, five field actions and two progression actions. Exact revision `ab653a1b59431d6c1ef0fad080f583da64961b8f`; direct task links, Quick amount synchronization, default mode and Review/Record handlers support every counted action. The package includes the three exact safe source files used for this reconstruction and their hashes. No historical browser deployment was needed or launched.
- **Final integrated source, newly reproduced:** both payment and receipt actually used one navigation control/one page change, five field actions and two progression actions. Each trace selected party, entered purpose, selected cash/bank, entered the single common amount and selected counterpart. Review saved/validated without an unnecessary separate Save; keyboard confirmation recorded one real synthetic journal. Trace preparation and later fault/evidence tests are outside these task counts.

Thus the matched payment path has one fewer navigation control/page transition and one fewer committed amount-field edit than the documented U1 path. Receipt form actions have one fewer amount-field edit; its historical Dashboard navigation difference is not claimed. Frozen U2-C and later integrated measurements are never merged into a falsely observed historical result.

The ledger and classified JSON make these counts auditable. They do not establish an uncoached user success rate, client acceptance, elapsed-time improvement or percentage reduction. Optional evidence/split/save-resume tasks were not given invented comparable counts; the separate regression/state checks below are assertions, not extra measured journeys.

## B - Narrow presentation changes: complete

Only two production files changed:

- `assets/js/payment_presentation.js`: adds pure strict coverage formatting/text helpers while leaving existing editable `cents()` and Quick/Split classification unchanged.
- `assets/js/accounting_workspace.js`: ordinary focused Review alone calls the new helper. The advance, correction and legacy scalar OCR branches retain their existing renderer.

Authoritative server coverage accepts only canonical nonnegative nonempty decimal strings with two fractional digits, converted and formatted using BigInt centavos. Null, missing, empty, malformed, negative and out-of-contract data produces `amount unavailable`, never a fabricated peso zero. The formatter supports aggregates above one line's DECIMAL(15,2) limit, bounded by the existing 100-line journal contract. This is a display-input bound, not a new writer rule. Existing server status and valid side values remain visible if another field is unavailable. No floating-point monetary arithmetic is used.

For ordinary focused GJ only, the special transfer explanation requires authoritative reviewed kind transfer, status Not applicable, both eligible sides exact zero and both covered sides exact zero. A nonzero/inconsistent side or a status disagreement retains the side details. Editable browser state cannot select this explanation. The full transfer journal remains inspectable. The wording does not claim proof of payment, authenticity or complete financial verification.

No template/CSS change, Tailwind rebuild, query/service/endpoint change, schema/migration, configuration, feature flag, writer, canonical input, token/hash, lifecycle, permission or evidence-policy change was needed. Unchanged key accounting/read/write contract Git blobs are verified in the manifest.

## C - Final affected verification: complete

All runs used new guarded disposable atikha_test_* targets derived from the existing complete-schema synthetic source, with isolated application configuration, sessions and evidence. Working drafts, users and real receipts were not fixtures. Server external transports were disabled; browser external HTTP was blocked. No dependency installation, working login, historical migration or recovery helper ran.

Final component outcomes against identical production hashes:

- New strict formatting/state adapter: **52** assertions.
- Existing ordinary / advance / correction adapters: **90 / 58 / 41**.
- New connected action/display suite: **32** checks, including actual payment/receipt recording, zero/partial/full support, unavailable malformed coverage, authoritative transfer N/A, inconsistent nonzero transfer detail, keyboard confirmation and overflow. Zero uncaught JavaScript errors.
- U2 payment: **124** browser/HTTP assertions.
- U2 receipt/GJ/transfer: **199** browser/HTTP assertions, including independent GJ coverage sides and scalar OCR/manual handoff.
- Focused U4 correction matrix: **146** assertions across twelve ordinary/advance-linked correction cases and both correction modes, including actual committed-response loss and exact replay with no duplicate writes.
- Focused U4 evidence/OCR: **17** assertions, including Management rejection and safe uncertainty handling.
- Stage 2: **56**; Stage 3 checkpoint 4: **48**; checkpoint 5: **54**, including relevant role/privacy/flag boundaries.
- Receipt/OCR/manual handoff: **68** HTTP/browser checks.
- Changed JavaScript syntax, changed/new Python runner AST parsing and git diff whitespace checks passed. No PHP production file changed. `npm test` is not evidence.

Only two existing browser expectations changed: raw 4500.00 and 1200.00 denominator text now expects the exact formatted peso amounts. The gross expenditure/noncash-credit assertions and all financial checks remain. New helpers/runners/package scripts contain the additional evidence, not new production accounting behavior.

Dated failed attempts are excluded from these counts. The first sandbox batch failed to import the existing private Playwright installation; approved outside-sandbox execution used that installation without modifying it. The new focused runner initially waited for editable controls after recording, then omitted the existing required exclusion reason on a synthetic document. Both were test setup defects, corrected without production changes. The initial full driver therefore exited with a failed focused component; all its other components passed. Only the focused component was repeated on new clones, and its passing result is explicitly linked as an affected rerun in the final certificate. The final certificate contains nine passing component groups with identical production pins; it does not relabel the original failed driver invocation as successful.

All **12 final screenshots** were individually inspected: payment/receipt zero support, payment partial/full support, malformed unavailable amount and transfer N/A, each at **1366x768 and 1920x1080**. These are full-page headless Edge captures and may exceed viewport height. No horizontal overflow or obstructed action was found. This is not cross-browser, physical-device, assistive-technology or client acceptance evidence.

## Working-system preservation

Preflight and final read-only SQL identify normal MariaDB 10.4.32 on3306, intended C:\xampp\mysql\data\ and recovery0. The before/after snapshot covers every row/schema of all **34 working application tables**, **24 triggers**, other database objects and hashed permissions. All match, including **62 audit rows**. All **53 protected upload files**, working application configuration, MariaDB executable/INI and Apache configuration match the fresh before pins. No working financial mutation, working receipt action, grant/account change, repair, restore/cutover or persistent configuration change occurred. Only disposable synthetic databases were cloned/imported for tests. Apache remained stopped; test runners cleaned their owned temporary servers.

## Reproduction and handoff

The ZIP includes the two final production files, changed/new tests, required unchanged pure adapters/dependencies, patches, source identities, action evidence, safe historical sources and screenshots. The pure suites can run directly after extraction:

```
node scripts/test_usability_u2_followup_adapter.js
node scripts/test_usability_u2_adapter.js
node scripts/test_usability_u3_advance_adapter.js
node scripts/test_usability_u4_adapter.js
```

Browser/database tests require the full public U4 application reconstructed with the production/test patches, PHP/Python/Node/Edge/Playwright dependencies and a new private complete-schema synthetic fixture. Use `scripts/test_usability_u2_followup_regressions.py --source-fixture=<fresh guarded private synthetic fixture>`. Never substitute working credentials/data, reuse consumed targets or run migrations to satisfy that instruction. Private commands/results are indexed by `.migration-private/u2-followup-current.json`; private fixture/session/export artifacts are excluded from the ZIP. The public log redacts those paths while naming the actual runners and outcomes.

The retained U2 follow-up implementation/evidence requirements are complete and ready for **one external exact-source/evidence review**. They are not yet externally approved or committed/pushed. Source rollback, if separately requested, affects only this checkpoint's two-file presentation diff and never posted journals or evidence. U-PERF-01, U5, full Atikha acceptance, original Stages 4-7, migration-009 diagnosis and backup/health work remain separate and were not started.
