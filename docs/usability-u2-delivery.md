# Usability U2 delivery: Checkpoint U2-A

October 7, 2026. **U2-A source implementation and focused isolated verification complete. U2-B/U2-C, working acceptance and Atikha observation remain pending.**

The user explicitly authorized “Implement U2-A” after supplying Gemini's and ChatGPT's favorable reviews of the [U2 plan](usability-u2-plan.md). The corrected [U1-C prototype](prototypes/usability-u1-payment.html) is the accepted design reference. These are selected usability decisions, not evidence that Atikha staff have independently completed the new workflow.

## Delivered behavior

Ordinary cash payments now have distinct **Enter details → Review → Recorded** views. The editing form no longer dominates the recorded screen, and stale saved/unposted messages are cleared when the state changes. The minimal Admin **Record a payment** sidebar link opens `cash_disbursement.php`; existing book links remain available. Full task-group navigation belongs to U2-C.

The Quick form has one amount and one actual common project. Account labels put names before codes; codes remain searchable. Optional reference/default-project context and documents are expandable. Split allocations and Advanced journal remain available. The classifier preserves raw mismatched, negative, malformed, missing and complex values in a detailed representation; changing presentation alone does not rebalance, trim evidence, replace line IDs or change project tags. Costs, asset purchases and liability settlements retain their selected account meanings.

Missing fields have inline errors, clickable summary links and accessible associations on the visible searchable controls. Unrelated edits do not remove unresolved errors. Search queries do not select records or dirty the draft. Saving incomplete work remains possible. Review saves the current draft and displays server-validated lines; it explicitly says the payment is not recorded and that the review is not Management approval. Back to editing preserves the entry. Refresh review is available without inventing a new payment.

Controlled navigation offers Stay, Save and leave, or Leave without saving the latest edits. Save and leave waits for success; a failure retains edits and the destination intent. Clicking the current payment task preserves the form. Starting another payment does not delete an earlier private draft. Native window/tab closure uses the existing browser warning; this checkpoint adds neither autosave nor financial browser-local storage.

Confirm and record uses the existing durable posting contract. An interrupted or unverifiable response shows **Check recording result**, blocks editing/new requests, and retains the original draft, revision, submission key, payload and review token. Authenticated recovery refreshes only the session CSRF token and retries that same financial request. A recovered result identifies the existing journal without creating a duplicate. Recorded controls remain read-only.

## Exact recorded transaction access

`journal_transaction.php?journal_id=…` is a new GET-only posted-record page, used after a new payment, successful recovery and reopening its posted draft. It reads the requested journal's complete ordered lines and totals independently of list/date/account filters. It does not load all history or call the advance comparison path.

Database-backed active Admin/Management authorization precedes the read. Missing/unposted records, invalid scalar/array/overflow IDs, unsupported access and write methods are rejected. The reader uses a consistent read snapshot and performs no financial, draft or audit writes. Private/no-store responses and escaped labels are retained. Party/project snapshots are shown as historical labels; account names are explicitly labelled current names.

Coverage is calculated internally before Management evidence redaction. Sensitive attachments and private review details remain hidden with accounting UI flags disabled. Admin reused-evidence links use the relevant posted association. Corrected originals, generated reversals and replacements remain accessible and linked; the existing comparison page remains available separately. Cash-book gross activity is labelled and GJ correction offsets explained.

Known UTC timestamps from durable writers are displayed in Asia/Manila. Older creation timestamps with no recorded timezone are labelled as stored timestamps with unknown timezone; the reader does not guess historical timing.

## Accounting examples verified in isolation

- Simple payment: expense debit **₱1,000**, cash credit **₱1,000**; Review does not post, Confirm records one journal. Asset and liability counterparts retain their account identities.
- Split payment: counterpart debits **₱600 + ₱400**, cash credit **₱1,000**; all rows, amounts and IDs survive presentation changes.
- Withholding example: gross expense debit **₱4,500**, liability credit **₱500**, cash credit **₱4,000**. The focused summary shows all three amounts; evidence eligibility remains **₱4,500**, not ₱4,000.
- Loaded cash **₱1,000** versus counterpart **₱900**, missing-side amounts and negative/malformed opposite-side inputs remain visible without automatic repair.
- Reviewed **₱800** support followed by reducing the claimed cost to **₱600** fails review without resizing the evidence allocation.
- A response deliberately lost after a disposable commit recovers the original journal after session reauthentication, retaining the same request identity and leaving journal counts unchanged.

All of these are synthetic disposable examples, not working transactions or client opening balances.

## Changed components

Application changes:

- `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, `assets/css/accounting_workspace.css`: explicitly guarded ordinary-CDB presentation, state, validation, navigation and recovery.
- New `assets/js/payment_presentation.js`: pure exact-centavo representation checks, not financial posting authority.
- `includes/accounting_workspace.php`: additive validated account-type/cash metadata in ordinary Review results for the summary; financial validation and posting mathematics are unchanged.
- `accounting_actions.php`: guarded authenticated read-only session-token refresh for same-request recovery.
- `includes/accounting_query.php`: shared complete-entry projection and scoped exact posted reader; existing full-entry book and line-filtered history semantics retained.
- `includes/journal_corrections.php`: correction metadata restricted to requested journal IDs instead of scanning every correction.
- New `journal_transaction.php`: authorized exact posted-record page.
- `includes/nav.php`: minimal gated Admin payment link only.

Verification changes: new `scripts/test_usability_u2_adapter.js`, `scripts/test_usability_u2_reader.php` and `scripts/test_usability_u2_browser.py`; `scripts/test_stage1_browser.py` now exercises the visible payment modes, expandable sections and focused Review state without weakening its financial assertions. Stage 2 and Stage 3 browser suites were run unchanged.

No migration, draft-version change, dependency installation, Tailwind rebuild, configuration/feature activation, working test posting, commit or push occurred. Ordinary receipt/GJ/transfer and v3/v4 dedicated layouts are retained for their authorized later checkpoints. Existing manuscript, prototype and unrelated documentation work is preserved.

## Verification inventory and source identity

Verification used fresh guarded disposable databases, complete migrations **019/020/021 before operational cases**, private synthetic evidence, generated isolated configurations and sessions, and blocked external browser calls. The working database, working drafts and working receipts were not fixtures. Edge version: **154.0.4258.62**.

- Integrated backend run: **487 passed** — Stage 1 71, Stage 2 96, Stage 3 CP1 48, CP2 59, CP3 53, CP4 64 and CP5 96. This fresh integration run followed the shared-reader extraction but preceded the final additive Review metadata, timestamp presentation and payment-only UI fixes. It is not a byte-for-byte final-source regression claim. No migration-transition results are added to this count.
- Final pure payment adapter: **42 passed**; exact cents, faithful representation boundaries and no input mutation.
- Final focused posted reader: **14 passed**; identity/full lines/totals, corrected chain, association-aware evidence, roles, redaction and no-write preservation.
- Final U2-A browser/HTTP run: **96 passed**, no uncaught JavaScript errors, **22 screenshots**. Includes ordinary evidence, inline masters, mismatches/invalid text, focus/errors, navigation failure recovery, real lost-response recovery, exact reads, privacy with flags off and retained receipt/correction presentation.
- Affected Stage 1 browser suite: **68 passed**; ordinary evidence/reservations/retries, owner/privacy, saved-date filters, full-entry cash-book search/totals and history behavior.
- Affected Stage 2 browser suite: **56 passed**; advance drafts/lifecycle/confirmation, evidence/retries, register and Management restrictions.
- Stage 3 CP4 browser suite: **48 passed**; advance corrections, original/new identities, blockers, bound proof, register and privacy.
- Stage 3 CP5 browser suite: **54 passed**; original CRB/CDB/GJ/transfer request replay after correction, duplicate/conflict checks and feature/provenance restrictions.

The affected regression browser runs preceded the last ordinary-payment-only read-only guard; the final 96-case payment run exercises that guard. Counts are assertion counts with overlapping coverage, not independent user tasks or a proof of defect-free software. Earlier U1 prototype checks are not included. Full cross-browser/device testing, live OCR/SMTP, working-data restoration, client observation and response-time acceptance are excluded.

Changed PHP/JavaScript/Python syntax and `git diff --check` passed. Default sandbox execution still failed before launch; necessary commands completed through runtime-approved execution. No enabled connected browser surface was available; the established isolated headless Edge harness was used. No approval remained pending at completion.

Repository HEAD is `73f7d5baa227b2a8ad133c63f6d881ec30986159`, **plus uncommitted U2-A changes**. [Final source manifest](usability-u2-a-source-manifest.json) identifies the 184 captured source/test files; the private capture additionally contains eight generated Python cache entries, excluded from this source-only inventory; local configuration/private artifacts are excluded. Final browser manifest SHA-256: `72711e80d41687f06bce9047df570cea937e958a0503ffdd277007b66a6daa35`. Hash verification after the final browser run found no application/test source drift. Generated Python cache cleanup is not an application change. Against U1-A's 143 application entries, exactly the eight existing application files listed above changed; the other **135 remained unchanged**. New application files are separately captured in the final manifest.

Private result locations (not public exports): `.migration-private/u2a-browser-6bb82547a1`, `stage1-browser-58638f19c9`, `stage2-browser-9b739a7fc3`, `stage3-cp4-browser-1013674903`, `stage3-cp5-browser-f94ee11bfd`; integration fixture parent `stage1-core-3c0fa472f7`. Private fixtures/configuration/sessions/evidence must remain excluded from Git.

### Commands used

Run financial suites only with a **new guarded disposable target** and private generated fixtures. The identifiers below record this execution; do not blindly rerun a database-creating suite against an existing target.

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint5.php --database=atikha_test_stage1_u2a071
node scripts/test_usability_u2_adapter.js
C:\xampp\php\php.exe scripts/test_usability_u2_reader.php --fixture=.migration-private/cp5-clone-3f4af41cc6d5/stage3-checkpoint5-fixture.json
python scripts/test_usability_u2_browser.py --fixture=.migration-private/cp5-clone-6b8f6cd78d6a/u2a-fixture.json
python scripts/test_stage1_browser.py --fixture=.migration-private/cp5-clone-3f4af41cc6d5/stage3-checkpoint5-fixture.json
python scripts/test_stage2_browser.py --fixture=.migration-private/cp5-clone-9111af9958c1/stage2-fixture.json
python scripts/test_stage3_checkpoint4_browser.py --fixture=.migration-private/cp5-clone-607db708460b/stage3-checkpoint4-fixture.json
python scripts/test_stage3_checkpoint5_browser.py --fixture=.migration-private/cp5-clone-607db708460b/stage3-checkpoint5-fixture.json
```

## Final screen evidence

All 22 final captures were visually inspected. They use synthetic accounts/parties/projects; IDs are disposable identifiers. The simple blank/filled form's Review action and focused Review/Recorded actions fit the first 1366×768 viewport. Validation and long labels can extend the page; no horizontal overflow was observed or detected at either tested size. This is layout/interaction evidence, not a contrast audit or Atikha task-completion study.

- Enter: [blank laptop](images/usability-u2-a/payment-empty-1366.png), [desktop](images/usability-u2-a/payment-empty-1920.png); [filled laptop](images/usability-u2-a/payment-filled-1366.png), [desktop](images/usability-u2-a/payment-filled-1920.png).
- Validation: [laptop](images/usability-u2-a/payment-invalid-1366.png), [desktop](images/usability-u2-a/payment-invalid-1920.png).
- Long labels/visible choices: [laptop](images/usability-u2-a/payment-long-label-1366.png), [desktop](images/usability-u2-a/payment-long-label-1920.png).
- Review: [laptop](images/usability-u2-a/payment-review-1366.png), [desktop](images/usability-u2-a/payment-review-1920.png).
- Split review: [laptop](images/usability-u2-a/payment-split-1366.png), [desktop](images/usability-u2-a/payment-split-1920.png).
- Withholding review: [laptop](images/usability-u2-a/payment-withholding-1366.png), [desktop](images/usability-u2-a/payment-withholding-1920.png).
- Recorded: [laptop](images/usability-u2-a/payment-recorded-1366.png), [desktop](images/usability-u2-a/payment-recorded-1920.png).
- Unknown response: [laptop](images/usability-u2-a/payment-unknown-1366.png), [desktop](images/usability-u2-a/payment-unknown-1920.png).
- Recovered result: [laptop](images/usability-u2-a/payment-recovered-1366.png), [desktop](images/usability-u2-a/payment-recovered-1920.png).
- Exact transaction: [laptop](images/usability-u2-a/payment-exact-1366.png), [desktop](images/usability-u2-a/payment-exact-1920.png).

## Remaining boundaries and next action

The source files are in the shared application folder; an existing enabled Stage 1 installation can serve the new payment page without a migration or new flag. This task did not perform working-browser acceptance or reverify the working schema/configuration. Refresh the page to see the source changes; financial demonstration postings belong in an isolated environment.

U2-A is complete within its implementation/isolation boundary. Review this report and the actual screens before authorizing **U2-B: receipts and ordinary General Journal/transfer integration**. U2-C navigation, U3–U5, U-PERF-01's comparison diagnosis and original completion Stages 4–7 remain retained. This checkpoint does not fix the slow advance comparison; the exact reader avoids that path. Working acceptance, coordinated restoration/protected-file verification and uncoached user/Atikha walkthroughs remain pending. Do not rerun working migrations 015/019/020/021.
