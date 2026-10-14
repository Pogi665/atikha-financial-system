# Usability U2-B delivery: receipts, General Journal and transfers

**Later exact-source correction:** [October 14 revision](usability-u2-b-revision.md) supersedes this initial implementation's blank Split amount error targeting and multi-line unknown-field classification. Its final source, focused results and two new screenshots are supplied separately. The initial counts, manifest and 48-image ZIP below remain dated evidence.

Delivered October 14, 2026. The user accepted corrected U2-A after Gemini's and ChatGPT's reviews and explicitly authorized U2-B. This implements section 8 of the [approved U2 plan](usability-u2-plan.md). U2-C navigation has not started.

For external review, the [sharing ZIP](review-packages/u2-b-review-20261014.zip) contains this report, the source manifest, all 48 screenshots, the local gallery and reviewer notes. It contains no private test applications, sessions, receipts or exports.

## Result and scope

Ordinary cash receipts now use focused **Enter details → Review → Recorded** views with faithful Quick/Split/Advanced presentation. Ordinary General Journal and internal transfers use those focused states while retaining their complete debit/credit editor and journal. Review remains an accounting check, not Management approval or a financial posting.

The changes are in the existing XAMPP application source. Refreshing enabled Stage 1 entry routes can serve them immediately; absence of a migration/configuration change does not keep the interface disabled. Verification below used isolated synthetic databases/application copies, not working transactions or client observation.

Changed application files:

- `includes/accounting_entry_page.php`: opts ordinary workspaces into focused presentation, progress, contextual Back, state-specific actions, exact-record access and unsaved-leave dialog. Dedicated advance/correction branches remain separate.
- `assets/js/accounting_workspace.js`: extends state, validation, saved navigation, authoritative review summaries and durable recovery to receipts/GJ/transfers.
- `assets/js/payment_presentation.js`: mirrors the existing classifier for CRB; its default CDB contract and existing file/global names remain compatible.
- `assets/css/accounting_workspace.css`: scope comment updated; existing focused styles reused. No Tailwind build was needed.

The sidebar, accounting writers, exact-record reader, schema, flags and local configuration were not changed. No working financial posting, migration, recovery operation, server-account permission change, commit or push was performed. Unrelated manuscript/recovery documentation was preserved.

## Receipt behavior

Quick receipts use one received amount and one actual common project, with payer, receiving cash/bank account and counterpart account. The counterpart retains its selected meaning: income, liability, equity or asset recovery. Deliberately editing the represented amount updates the receipt's cash debit and counterpart credit.

Quick is used only when it faithfully represents the payload. Independent amounts, distinct projects or multiple credit allocations use Split. Nonzero opposite-side values, negative/malformed text, unsupported line fields or evidence references requiring the complete representation remain detailed. Mode changes never balance, trim or replace saved amounts, line IDs, project tags or evidence allocations.

Review uses the server response and visibly shows the receiving account, actual cash project, cash received and every allocation's account, amount and actual project before confirmation. Split review exposes the ₱600/₱400 breakdown. Advanced receipts with counterpart entries on both sides automatically open the complete journal and label debit allocations. CRB monetary coverage uses noncash credits: the advanced example has a ₱1,200 denominator despite ₱1,000 net cash received.

Optional partial support stays truthful: the captured receipt shows ₱800 supported out of ₱1,000. Unreviewed attached evidence blocks review. Decreasing the entry below its support is rejected without shrinking the allocation. Recorded/recovered screens show the original journal identity and its exact-record link. Starting another entry preserves existing private drafts.

## General Journal and transfers

GJ/transfers always retain the detailed editor: no Quick/Split buttons, both accounting sides visible and independent actual line projects preserved. Default project context remains separate from actual tags; inline context creation does not overwrite line projects. No entered values are automatically balanced.

Focused Review shows authoritative entry kind, debit/credit totals, side-specific evidence coverage and the complete journal open. A transfer retains its two cash-account lines and `transfer` kind. Existing server validation rejects a noncash transfer while retaining the entered values. Party remains optional for GJ; its existing date contract, including permitted future dates, remains intact. GJ partial evidence stays on its selected side: the captured example has ₱400 debit support and zero credit support.

The scalar `general_journal.php?receipt_id=…` OCR handoff remains a separate existing form. Failed-extraction/manual-review and explicit currency-confirmation controls were checked. Live OCR extraction/provider availability and accuracy were not tested.

## Validation, saving and recovery

Receipts/GJ reuse linked summaries, inline messages and error associations on visible searchable controls. Correcting a purpose clears that error while unrelated payer/account/amount errors remain. Summary links focus the visible field and open its optional group. Quick amount errors use receipt wording.

Unsaved navigation offers Stay, Leave without latest edits, or Save draft and leave. Failed saving retains edits and destination; successful navigation waits for acknowledged persistence. Starting another entry preserves saved snapshots.

Tests lost receipt/GJ/transfer responses **after real synthetic commits**. Unknown results disabled editing and advised checking the same request. After synthetic reauthentication, recovery retained draft ID, revision, submission key, payload and review token. Each scenario created exactly one journal, without generating a new financial request.

One shared presentation bug was found and fixed: native selects emit `input` before `change`. Changing a document's purpose with validation visible could validate its old controls before new amount fields appeared. The generic input handler now leaves selects to the change handler after the document editor updates. Final U2-B and affected payment/advance/correction checks cover this source.

## Verification and final-source mapping

Financial fixtures were newly guarded disposable databases with complete 019/020/021 installed before operational cases. Browser copies used isolated configuration, sessions and synthetic evidence; working configuration/receipts were not copied. External browser requests were blocked. Final U2-B captures used **headless Microsoft Edge 155.0.4283.45**, isolated local PHP development servers and MariaDB 10.4.32.

Successful checks in this checkpoint:

- **487 integrated backend assertions**, completed October 13: Stage 1 71; Stage 2 96; Stage 3 CP1 48, CP2 59, CP3 53, CP4 64, CP5 96. Final journal/evidence/advance integrity passed. This run preceded the final JavaScript select-event fix; backend writers were unchanged. It is not claimed as a final-byte browser run.
- **82 pure adapter assertions:** CDB/CRB classification, invalid/opposite-side values, mismatches, projects, IDs/evidence preservation and GJ/transfer exclusion from simplification.
- **16 exact-reader assertions:** scoped identity/history/corrections, read-only access, evidence privacy/authorization and timestamp provenance. The reader was unchanged.
- **189 U2-B Edge browser/HTTP assertions:** receipt modes/meanings, errors, complex/restored data, review, evidence, inline masters, committed long labels, save/leave/recovery, GJ/transfers, scalar OCR fallback and role/flag boundaries.
- **121 payment regression assertions:** accepted U2-A behavior, review breakdown, saving and recovery under final shared code.
- **68 Stage 1 browser/HTTP assertions:** documents, draft ownership/resumption/filters, search, selectors, retries and flags.
- **56 Stage 2 browser/HTTP assertions:** release/liquidation/return, bound proof confirmation, lifecycle/register/print and Management restrictions.
- **48 Stage 3 CP4 browser/HTTP assertions:** correction bundles, blockers, identities, evidence/recovery, register/print and Management privacy.
- **54 Stage 3 CP5 browser/HTTP assertions:** ordinary CRB/CDB/GJ/transfer correction/recovery, paused advance actions, installed-schema privacy and role/feature gates.
- Changed PHP/JavaScript/Python syntax and `git diff --check` passed. `npm test` is a placeholder and was not counted.

These overlapping counts are not a count of unique client tasks. Successful browser runs had no uncaught JavaScript errors. Failed development runs are excluded from passing counts.

The [source manifest](usability-u2-b-source-manifest.json) records HEAD, hashes, guarded fixtures, run locations and screenshot hashes. **All 124 captured application files match the final workspace and all six successful browser application copies.** No application source changed after the final U2-B captures.

Two test-tool files changed afterward: `scripts/test_stage1_browser.py` now waits for a loaded control inside the optional disclosure rather than incorrectly requiring that hidden control to be visible, and expects focus on the actual common-project selector. Financial/evidence assertions remain; its final run passed 68 checks. `scripts/test_stage3_fixture.py` now explicitly selects TCP port 3306 with `--no-defaults`, because inherited client defaults incorrectly selected stopped port 3307. Five independent clones and their final payment/Stage 1/Stage 2/CP4/CP5 suites passed with the corrected utility. No server/configuration change was needed.

The first CP4 invocation used CP3 metadata and stopped at missing `release_draft` before financial actions. Its successful run used `stage3-checkpoint4-fixture.json`. These setup errors do not establish database corruption or an application posting defect.

### Successful commands and evidence

These record completed isolated execution, not instructions to test the working database or reuse mutated fixtures as a fresh acceptance dataset.

```text
C:\xampp\php\php.exe scripts/test_stage3_checkpoint5.php --database=atikha_test_stage1_u2ba0ea5a734e12
node scripts/test_usability_u2_adapter.js
C:\xampp\php\php.exe scripts/test_usability_u2_reader.php --fixture=.migration-private/stage1-core-0eac02f4f5/stage3-checkpoint5-fixture.json
python scripts/test_usability_u2_b_browser.py --fixture=.migration-private/stage1-core-0eac02f4f5/stage3-checkpoint5-fixture.json
python scripts/test_usability_u2_browser.py --fixture=.migration-private/cp5-clone-a2f22f698097/stage3-checkpoint5-fixture.json
python scripts/test_stage1_browser.py --fixture=.migration-private/cp5-clone-ae843b8ce117/stage3-checkpoint5-fixture.json
python scripts/test_stage2_browser.py --fixture=.migration-private/cp5-clone-d7466457f4fd/stage2-fixture.json
python scripts/test_stage3_checkpoint4_browser.py --fixture=.migration-private/cp5-clone-63bc31bf1c46/stage3-checkpoint4-fixture.json
python scripts/test_stage3_checkpoint5_browser.py --fixture=.migration-private/cp5-clone-ee1c44d9fc94/stage3-checkpoint5-fixture.json
```

Final U2-B results/capture manifest: `.migration-private/u2b-browser-98b5d72c29/`. Backend log: `.migration-private/u2b-core-current.log`. Consolidated verification: `.migration-private/u2b-final-verification.json`. Other run locations appear in the source manifest. Private application/configuration/session/evidence files and SQL dumps must not be included in reviewer packages.

## Screenshots and layout inspection

Open the [48-image local gallery](images/usability-u2-b/index.html) in Edge/Chrome, or browse `docs/images/usability-u2-b`. All PNGs were verified byte-for-byte against the final synthetic capture run. All 48 layouts were inspected through twelve contact sheets, with original laptop receipt/GJ Review images inspected at readable size.

Each case includes 1366×768 and 1920×1080 viewports. Images capture the **full page** and can exceed viewport height. They do not establish physical-device/cross-browser compatibility. Simple receipt Review and Record actions fit the captured laptop viewport; longer editors/validation/evidence/journals can require vertical scrolling. The captured cases passed horizontal page-overflow assertions. This is layout evidence, not comprehensive accessibility or contrast certification.

Receipt cases (22 images):

- [Enter](images/usability-u2-b/receipt-enter-1366.png), [validation](images/usability-u2-b/receipt-validation-1366.png), [Review](images/usability-u2-b/receipt-review-1366.png), [Recorded](images/usability-u2-b/receipt-recorded-1366.png), [exact transaction](images/usability-u2-b/receipt-exact-1366.png).
- [Split](images/usability-u2-b/receipt-split-1366.png), [advanced](images/usability-u2-b/receipt-advanced-1366.png), [closed long label](images/usability-u2-b/receipt-long-label-1366.png), [partial support](images/usability-u2-b/receipt-support-1366.png), [unknown](images/usability-u2-b/receipt-unknown-1366.png), [recovered](images/usability-u2-b/receipt-recovered-1366.png).

General Journal cases (16 images):

- [Enter](images/usability-u2-b/journal-enter-1366.png), [validation](images/usability-u2-b/journal-validation-1366.png), [Review](images/usability-u2-b/journal-review-1366.png), [Recorded](images/usability-u2-b/journal-recorded-1366.png), [unbalanced](images/usability-u2-b/journal-unbalanced-1366.png), [partial support](images/usability-u2-b/journal-support-1366.png), [unknown](images/usability-u2-b/journal-unknown-1366.png), [recovered](images/usability-u2-b/journal-recovered-1366.png).

Transfer cases (10 images):

- [Review](images/usability-u2-b/transfer-review-1366.png), [Recorded](images/usability-u2-b/transfer-recorded-1366.png), [rejected noncash line](images/usability-u2-b/transfer-invalid-1366.png), [unknown](images/usability-u2-b/transfer-unknown-1366.png), [recovered](images/usability-u2-b/transfer-recovered-1366.png).

The gallery includes `-1920.png` for every laptop image above. Existing 24 corrected U2-A screenshots remain dated payment evidence and were not replaced.

## Limits and next action

U2-B source implementation and focused isolated verification are complete. Working-browser acceptance with actual accounts/drafts/evidence, Atikha observation, broader accessibility checks, cross-browser/physical-device testing and live OCR/SMTP remain separate. Synthetic assertions and screenshots do not establish those results.

The advance-comparison delay remains **U-PERF-01**. This work neither profiled nor fixed it and establishes no working-XAMPP performance improvement.

Next: review U2-B's delivery and actual screens. **U2-C requires separate user authorization** for task-based global navigation and its integration handoff. U3–U5 and original Stages 4–7 remain pending.
