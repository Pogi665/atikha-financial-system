# U2-A targeted review corrections

October 8, 2026. **Payment presentation corrections implemented; isolated verification complete. U2-B has not started.** This supplements the [initial October 7 delivery](usability-u2-delivery.md), rather than replacing its dated evidence.

The user supplied Gemini's delivery review and ChatGPT's report and all-22-screenshot reviews, then authorized: “Then lets do the corrections first before U2-B.” ChatGPT's required change was to expose the paying account and actual allocation details before confirmation. The smaller wording refinements and final-source coverage clarification were included in this same narrow scope. These are selected presentation requirements, not new client accounting policies.

## Revised payment review

The ordinary-payment Review screen now shows **Pay from**, the cash account's actual project, and **Cash paid**. An always-visible allocation table shows each noncash account, amount and actual line project. It uses the authoritative successful server review response, not the default-project context or a locally reconstructed journal. Credit allocations are explicitly marked as credits. Text is rendered through `textContent`.

The split example visibly shows **₱600 + ₱400 = ₱1,000**, with distinct actual projects. The test deliberately assigns a different default-project context, proving that the summary does not substitute that context for a line's project.

Withholding still shows **₱4,500 gross costs − ₱500 liability credits = ₱4,000 cash paid**, with **₱4,500** as the evidence denominator. Both allocation rows are visible, including the liability's credit side. The complete journal opens automatically for Advanced mode, more than three noncash allocations, or any noncash credit. Small straightforward entries retain the expandable full journal beneath their visible allocation breakdown.

The simple payment's confirmation fits the 1366 × 768 laptop viewport. Longer labels, split entries and expanded complex journals may require scrolling; information is not hidden to make every case fit one screen. An initial layout check caught the simple confirmation below the viewport; scoped spacing was corrected and the final check passed.

## Wording and state corrections

- Quick-mode amount errors now say “Enter a positive payment amount with at most two decimal places.” Detailed-mode errors retain the accounting-side explanation.
- Interrupted payment requests use a useful connection explanation instead of displaying the browser's raw “Failed to fetch.” An unknown posting result still warns that recording may have succeeded and retains the same-request recovery action.
- Header instructions now match Enter, Review, Recorded and uncertain-result states. Recorded screens no longer instruct staff to enter payment details.
- The exact transaction page displays **Reference: Not provided** when no reference was supplied.
- New closed-selector screenshots demonstrate that a committed long payee label remains fully available beneath the search input.

No posting formulas, server eligibility rules, evidence requirements, project semantics, privacy rules, feature gates or durable recovery identities were changed. Application changes are confined to `assets/js/accounting_workspace.js`, scoped workspace CSS, and the exact page's missing-reference label. Receipt/GJ and dedicated advance/correction layouts remain outside this checkpoint's redesign.

## Fresh verification and final-change coverage

Verification used guarded disposable databases with complete 019/020/021 schemas, private copied applications/configuration/sessions/evidence and blocked external browser calls. No working draft or receipt was used as a fixture. The browser is headless Microsoft Edge **154.0.4258.62**; this is not a fresh cross-browser claim.

- **121 U2-A browser/HTTP assertions passed**, including the original 96 cases and the new visible-review, wording, closed-label and timestamp assertions. No uncaught page errors were reported.
- **16 exact-reader backend assertions passed**, including two added timestamp-provenance cases.
- **42 pure presentation-adapter assertions passed**. The adapter source is unchanged.
- **68 affected Stage 1 browser/HTTP assertions passed** on an independent full-schema copy after correcting its fixture assumptions and loading wait.
- PHP/JavaScript syntax, Python syntax and `git diff --check` passed.

The final U2-A application and U2 test files were unchanged after the 121-case capture. Later edits affected only the Stage 1 test harness; its fresh 68-case run covers those edits. The [revision source manifest](usability-u2-a-revision-source-manifest.json) identifies both captures and their sole test-file difference. The inspected current repository HEAD was `d316817710ec9d933823d03cd23dfc0b6961f8d8`, plus this revision's uncommitted changes; HEAD alone does not identify the tested source.

Coverage of the initial delivery's later changes is now explicit:

- **Additive review account type/cash metadata:** the visible Pay from and every allocation cell are compared with the captured authoritative response, including split projects and withholding's gross denominator.
- **Final posted-control guard:** the U2 suite verifies every payment mutation control is disabled after posting; Stage 1 additionally reloads posted drafts and checks read-only searchable controls and preserved snapshot labels after master rename/disable.
- **Timestamp conversion/provenance:** the reader preserves raw stored values and distinguishes durable UTC from unknown legacy timezone. HTTP checks independently calculate Manila time from a synthetic UTC database timestamp and verify that legacy display retains its raw stored timestamp and unknown-timezone label. Reads leave the database manifest unchanged.
- **Authenticated recovery:** the browser loses the response after a real isolated commit, reauthenticates, then checks that draft ID, revision, submission key, payload and review token are identical across retries and no duplicate journal is created.
- **Scoped exact reader/privacy:** reader and HTTP cases cover complete lines, correction links, relevant evidence associations, invalid IDs, GET-only access, authorization, Management redaction with flags on/off and no-write reads.
- **Shared selectors/evidence/navigation:** the fresh Stage 1 run covers keyboard selection, search cancellation without dirtying, inline creation/focus, document reservations, partial coverage, legacy OCR protections, durable retries, full-entry cash-book search/totals and saved-date draft filtering/ownership.

The initial **487 backend checks**, **56 Stage 2**, **48 Stage 3 CP4** and **54 CP5 browser checks** remain dated initial-delivery evidence, not newly executed revision counts. Unchanged financial writers and provenance readers did not justify repeating every suite. This revision does not claim a fresh complete Stage 1/2/3 matrix or working/client acceptance.

### Test-harness issues discovered and resolved

The additional Stage 1 rerun initially failed because its historical fixture already contained renamed masters, a previous payment with the same description and project code `BROWSER2`. Its old assertion expected particular names and its search counted prior synthetic payments. The test now records actual original names, performs a distinct rename, and uses unique payment/search/project-code values. A separate loading race used `includes('saved')`, which also matched “Unsaved draft”; it now waits for the acknowledged saved label of the requested draft. Random project codes also follow the server's uppercase normalization. These fixes strengthen the test without weakening its financial or snapshot assertions. Failed development runs are not added to passing totals. No application defect was established by those fixture failures.

### Commands and private evidence

From the repository root, the focused checks were:

```powershell
node scripts/test_usability_u2_adapter.js
C:\xampp\php\php.exe scripts/test_usability_u2_reader.php --fixture=.migration-private/cp5-clone-64d3868ef4db/u2a-fixture.json
python scripts/test_usability_u2_browser.py --fixture=.migration-private/cp5-clone-64d3868ef4db/u2a-fixture.json
python scripts/test_stage1_browser.py --fixture=.migration-private/cp5-clone-f4b2dd402f7b/stage3-checkpoint5-fixture.json
```

These paths identify existing disposable evidence, not instructions to run against `atikha_finance`. A future verification should first create a new guarded disposable fixture and substitute its private path. The U2 capture's results and source hashes are in `.migration-private/u2a-browser-261d8749d1/`; the final Stage 1 run's copied application and artifacts are in `.migration-private/stage1-browser-8f8f0b63f9/`. The revision changes six of the initial manifest's 184 source/test entries; the other 178 retain their hashes. Private logs, configuration, sessions, fixture exports and uploaded evidence must remain excluded from commits.

## Revised screenshots

All **24 synthetic full-page screenshots** were visually inspected. Laptop and desktop browser viewports were 1366 × 768 and 1920 × 1080; full-page captures can be taller. The original 22 captures remain untouched.

- Empty: [laptop](images/usability-u2-a-revision/payment-empty-1366.png), [desktop](images/usability-u2-a-revision/payment-empty-1920.png).
- Filled: [laptop](images/usability-u2-a-revision/payment-filled-1366.png), [desktop](images/usability-u2-a-revision/payment-filled-1920.png).
- Validation: [laptop](images/usability-u2-a-revision/payment-invalid-1366.png), [desktop](images/usability-u2-a-revision/payment-invalid-1920.png).
- Long label, choices open: [laptop](images/usability-u2-a-revision/payment-long-label-1366.png), [desktop](images/usability-u2-a-revision/payment-long-label-1920.png).
- Long label, committed and closed: [laptop](images/usability-u2-a-revision/payment-long-label-closed-1366.png), [desktop](images/usability-u2-a-revision/payment-long-label-closed-1920.png).
- Simple Review: [laptop](images/usability-u2-a-revision/payment-review-1366.png), [desktop](images/usability-u2-a-revision/payment-review-1920.png).
- Split Review: [laptop](images/usability-u2-a-revision/payment-split-1366.png), [desktop](images/usability-u2-a-revision/payment-split-1920.png).
- Withholding: [laptop](images/usability-u2-a-revision/payment-withholding-1366.png), [desktop](images/usability-u2-a-revision/payment-withholding-1920.png).
- Recorded: [laptop](images/usability-u2-a-revision/payment-recorded-1366.png), [desktop](images/usability-u2-a-revision/payment-recorded-1920.png).
- Unknown result: [laptop](images/usability-u2-a-revision/payment-unknown-1366.png), [desktop](images/usability-u2-a-revision/payment-unknown-1920.png).
- Recovered result: [laptop](images/usability-u2-a-revision/payment-recovered-1366.png), [desktop](images/usability-u2-a-revision/payment-recovered-1920.png).
- Exact transaction: [laptop](images/usability-u2-a-revision/payment-exact-1366.png), [desktop](images/usability-u2-a-revision/payment-exact-1920.png).

## Working-system boundary and next action

No working database migration, configuration/flag change, synthetic working posting, commit or push occurred. No Tailwind rebuild, dependency or external design-service write was needed. The changed files are in the existing XAMPP application folder; refreshing an enabled payment workspace serves the revised source without a new activation. That fact does not establish working-browser acceptance.

Next: review the revised payment screens/report before separately authorizing **U2-B**. U2-C, U3–U5, the comparison-performance diagnostic **U-PERF-01**, restoration/working acceptance and Atikha observation remain pending. Original completion Stages 4–7 remain retained. Do not rerun applied working migrations 015/019/020/021.
