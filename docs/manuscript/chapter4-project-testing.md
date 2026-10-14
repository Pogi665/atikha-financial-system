# Project Testing and Evaluation Results

## Project Testing

Testing of the Atikha Financial Management System compared observed behavior with the requirements for transaction preparation, balanced journal recording, evidence handling, access control and correction history. Developer verification used guarded disposable databases, synthetic records and identified source versions so that testing did not require fictitious postings in the working database. The results below distinguish executed scoped checks from final-system cases that still require testing. Screenshot availability establishes the displayed screen state, but does not by itself establish that every control or workflow passed.

## Functionality Testing

The functionality matrix records the expected outcome, purpose, action and available result for each workflow. A passed result applies only to the cited cases, source version and environment. Partial coverage means that some relevant behavior was checked but the complete matrix row is not accepted. Pending and not-yet-tested cases are excluded from passed totals. Administrator preparation and recording are separated from Management’s permitted inspection and privacy checks.

The October 7 integrated Stage 3 delivery recorded 694 complete-schema backend assertions, 19 migration or partial-schema assertions, 370 mixed browser/HTTP assertions and 10 additional presentation assertions [E2]. These categories total 1,093 assertions, rather than 1,093 independent user tasks. The October 8 payment revision recorded 121 browser/HTTP assertions, 16 exact-reader assertions, 42 presentation-adapter assertions and 68 affected Stage 1 browser/HTTP assertions [E1]. These runs cover different dated source captures and overlap with earlier coverage; their counts are not added together as one final acceptance total.

### Table [T01]. Functionality Testing in the Administrator Workflows

The copyable table is in the companion HTML. The editable case records follow.

#### F01 Save draft and resume

- **Expected result:** Preserve the acknowledged entry and its owner’s access.
- **Function:** Retain unfinished work.
- **Action:** Enter details; save; reopen.
- **Actual result and evidence:** Saved versions and owner restrictions matched the expected results in isolated cases [E1, E2].
- **Status:** Passed in cited scope

#### F02 Review invalid payment

- **Expected result:** Identify invalid fields and retain unrelated unresolved errors.
- **Function:** Validate input before recording.
- **Action:** Submit invalid details; correct individual fields.
- **Actual result and evidence:** Inline errors, summary links and error persistence passed the revised payment checks [E1].
- **Status:** Passed in cited scope

#### F03 Review payment

- **Expected result:** Show paying account and actual allocation accounts, amounts and projects without posting.
- **Function:** Check the complete proposed transaction.
- **Action:** Review a valid quick, split or complex payment.
- **Actual result and evidence:** The visible summary matched the authoritative response, including split projects and withholding [E1].
- **Status:** Passed in cited scope

#### F04 Confirm and record payment

- **Expected result:** Create one balanced journal after deliberate confirmation.
- **Function:** Record the reviewed payment.
- **Action:** Confirm the reviewed synthetic entry.
- **Actual result and evidence:** Recording, journal identity and read-only posted controls passed the isolated payment cases [E1].
- **Status:** Passed in cited scope

#### F05 Recover uncertain recording

- **Expected result:** Recover the original result without another journal.
- **Function:** Prevent duplicate payment recording.
- **Action:** Interrupt the response; retry the same request.
- **Actual result and evidence:** The original request identity survived reauthentication and no duplicate journal was created [E1].
- **Status:** Passed in cited scope

#### F06 View exact transaction

- **Expected result:** Display complete lines, totals and linked correction information without writes.
- **Function:** Inspect a specific posted journal.
- **Action:** Open the exact journal link.
- **Actual result and evidence:** Reader and HTTP cases passed complete-line, authorization, correction-link and no-write checks [E1].
- **Status:** Passed in cited scope

#### F07 Receipt, General Journal and transfer

- **Expected result:** Preserve balanced entries and durable original-request recovery.
- **Function:** Record other ordinary entries.
- **Action:** Use isolated posting and retry cases.
- **Actual result and evidence:** Core workflow checks and original-request replay after correction passed [E2]. Their U2 redesign remains pending.
- **Status:** Passed in dated scope

#### F08 Book and history filters

- **Expected result:** Return the correct matching lines and totals without losing required journal context.
- **Function:** Locate posted activity.
- **Action:** Apply filters; search; change pages.
- **Actual result and evidence:** Full-entry CRB/CDB behavior, history behavior and saved-date boundaries passed scoped checks [E1, E2].
- **Status:** Passed in cited scope

#### F09 Advance release

- **Expected result:** Create an advance asset linked to its release journal.
- **Function:** Track released accountable funds.
- **Action:** Save; review; confirm an isolated release.
- **Actual result and evidence:** Release identity, control-account protection and register behavior passed Stage 2 cases [E2].
- **Status:** Passed in dated scope

#### F10 Liquidation and return

- **Expected result:** Apply supported settlements and context-bound return confirmation; reject invalid excess.
- **Function:** Reduce the advance correctly.
- **Action:** Review and record supported liquidation and confirmed return.
- **Actual result and evidence:** Support, settlement, proof-binding, retry and reconciliation cases passed [E2].
- **Status:** Passed in dated scope

#### F11 Journal correction

- **Expected result:** Post a linked reversal and any selected replacement while preserving the original.
- **Function:** Correct recorded activity through audit-preserving entries.
- **Action:** Review; confirm; inspect the chain; retry.
- **Actual result and evidence:** Ordinary and advance correction, dependency-blocker and recovery cases passed [E2].
- **Status:** Passed in dated scope

#### F12 Scan Receipt controlled workflow

- **Expected result:** Require user review and preserve evidence/privacy before posting.
- **Function:** Assist receipt entry.
- **Action:** Use synthetic upload, provider response and failure cases.
- **Actual result and evidence:** Controlled provider-response and failure/manual-handoff cases passed. Live extraction accuracy was not established [E2].
- **Status:** Passed in controlled scope

#### F13 Trial Balance calculations

- **Expected result:** Derive balances from the known posted journal fixture.
- **Function:** Check account balances.
- **Action:** Run calculation and snapshot checks.
- **Actual result and evidence:** Ledger, Trial Balance and frozen-snapshot checks passed the isolated accounting profile [E2]. This is not the future report-bundle acceptance.
- **Status:** Passed in dated scope

#### F14 External Email controls

- **Expected result:** Compose correspondence and report sending outcomes correctly.
- **Function:** Send authorized external messages.
- **Action:** Create and send a controlled message; inspect issues.
- **Actual result and evidence:** The supplied figure shows a recorded Sent message. A final-build control-by-control result has not been supplied for this matrix.
- **Status:** Pending final matrix

#### F15 Message the Board controls

- **Expected result:** Submit messages and expose attachments only to permitted users.
- **Function:** Support internal correspondence.
- **Action:** Compose; send; open; inspect review status.
- **Actual result and evidence:** The supplied figure shows sent messages and statuses. A final-build end-to-end result has not been supplied for this matrix.
- **Status:** Pending final matrix

#### F16 Setup and account maintenance

- **Expected result:** Maintain permitted reference records while protecting historical and control-account meaning.
- **Function:** Manage projects, parties and accounts.
- **Action:** Add; edit; filter; test invalid or restricted changes.
- **Actual result and evidence:** Inline masters and control protections have scoped coverage [E1, E2]. Complete maintenance-page acceptance remains unrecorded here.
- **Status:** Partial coverage

#### F17 User management

- **Expected result:** Apply permitted account changes and enforce authorization.
- **Function:** Manage access and reset requests.
- **Action:** Add or edit a test user; inspect restrictions.
- **Actual result and evidence:** The user list is visible in the supplied screenshot. No final-build account-management result has been supplied for this matrix.
- **Status:** Pending final matrix

#### F18 Audit and forecast controls

- **Expected result:** Display logged activity and explain unavailable or failed analysis.
- **Function:** Inspect logs and available analytical output.
- **Action:** Filter/export logs; exercise controlled analysis cases.
- **Actual result and evidence:** The screenshots show logs and a forecast waiting for more history. They do not establish a final export, anomaly-accuracy or forecast-accuracy result.
- **Status:** Pending final matrix

### Table [T02]. Functionality Testing in the Management Workflows

The copyable table is in the companion HTML. The editable case records follow.

#### M01 Exact journal access

- **Expected result:** Permit appropriate posted-journal reading; restrict unauthorized entry actions.
- **Function:** Provide read-only financial inspection.
- **Action:** Open valid, invalid and restricted routes as Management.
- **Actual result and evidence:** Exact-reader and HTTP authorization cases passed [E1].
- **Status:** Passed in cited scope

#### M02 Evidence privacy

- **Expected result:** Withhold private receipt images and details, including when UI flags are disabled.
- **Function:** Protect confidential supporting evidence.
- **Action:** Request journal/evidence views and protected downloads.
- **Actual result and evidence:** Reader and browser/HTTP redaction and attachment restrictions passed scoped cases [E1, E2].
- **Status:** Passed in cited scope

#### M03 Advance inspection

- **Expected result:** Show permitted advance totals and confirmation summaries without private proof details.
- **Function:** Monitor accountable funds.
- **Action:** Inspect register, timeline and corrected advance cases.
- **Actual result and evidence:** Register, correction and Management restriction cases passed in the dated Stage 2/3 suites [E2].
- **Status:** Passed in dated scope

#### M04 Review Queue and Board Inbox

- **Expected result:** Present authorized review items and messages and preserve the distinction between message and financial review.
- **Function:** Support Management review and communication.
- **Action:** Open items; review; inspect permitted attachments.
- **Actual result and evidence:** These Management screens were not among the supplied captures. A final-build workflow result has not been supplied for this matrix.
- **Status:** Pending final matrix

Tables [T01] and [T02] show that the cited financial-workflow and privacy cases met their expected outcomes in the isolated environments. They also identify incomplete final-build coverage for supporting screens and Management review/communication. Receipt extraction used a controlled provider response for the cited cases, so live OCR accuracy is a separate question. The recorded checks do not establish that Atikha staff can complete the workflows independently, that all remaining modules are finished, or that performance is acceptable under working conditions.

An additional usability baseline identified an advance-comparison delay. With 29 synthetic advances, median HTTP response times were 5.706 seconds for Administrator and 11.637 seconds for Management; with 100 advances, they were 6.146 and 12.227 seconds respectively [E4]. The measurements used an isolated PHP development server. They warrant a separate diagnostic task and do not establish normal XAMPP performance or an acceptable working response time.

## Browser Compatibility Testing

Browser compatibility testing assesses the selected workflows in different browsers while controlling the application build, data, computer, operating system and viewport within each comparison group. Windows Chrome, Edge and Firefox form one group. Mac Chrome and Safari form a separate group, consistent with the Chapter III plan. The current results contain scoped Edge execution and supplied Chrome appearance evidence. The researcher has reported no additional browser tests; Firefox and the Mac group remain untested.

### Table [T03]. Browser Compatibility Testing

#### Google Chrome on Windows

- **Expected result:** Required pages, inputs, workflow states and authorized results match the controlled reference.
- **Actual result:** The 15 supplied screenshots show Administrator pages rendered in Chrome. Browser version and controlled interaction results were not supplied.
- **Status or result:** Appearance observed; functional comparison pending
- **Recommended:** Final recommendation pending

#### Microsoft Edge on Windows

- **Expected result:** The same specified cases complete with correct values, state and permissions.
- **Actual result:** Edge 154.0.4258.62 passed 121 revised U2-A and 68 affected Stage 1 browser/HTTP assertions in isolated copies [E1]. Other Stage 2/3 results are dated [E2].
- **Status or result:** Passed scoped checks; complete matrix pending
- **Recommended:** Candidate for desktop use; final recommendation pending

#### Mozilla Firefox on Windows

- **Expected result:** Controls, dates, selectors, uploads, downloads and reports remain usable in the same Windows comparison setup.
- **Actual result:** No executed Firefox comparison result was supplied.
- **Status or result:** Not yet tested
- **Recommended:** Pending

#### Google Chrome on macOS

- **Expected result:** Establish a Mac reference using the same available cases and data as Safari.
- **Actual result:** No executed Mac Chrome result was supplied.
- **Status or result:** Not yet tested
- **Recommended:** Pending

#### Safari on macOS

- **Expected result:** Complete the required cases against the Mac Chrome reference, including native inputs and downloads.
- **Actual result:** No executed branded Safari result was supplied.
- **Status or result:** Not yet tested
- **Recommended:** Pending

Table [T03] separates a successful scoped check from completion of the cross-browser matrix. The supplied Chrome screenshots show the listed Administrator screens, but do not document keyboard behavior, upload/download handling, uncertain-posting recovery or every browser control. The Edge runs provide stronger interaction evidence for their specified cases, without proving parity across every browser or module. Browser versions, OS versions, test date, application source identity and case results must be recorded for the remaining comparisons before final browser recommendations are made.

## Device Compatibility Testing

Device compatibility testing assesses the complete device and platform configuration, including display size, input method, orientation and access to required controls. It is reported separately from browser compatibility testing. Existing layout checks used browser viewports of 1366 × 768 and 1920 × 1080 CSS pixels. These are viewport configurations and must not be relabelled as tests on two independently identified physical devices.

### Table [T04]. Scoped Browser Viewport Verification

#### 1366 × 768 CSS pixels

- **Test configuration:** Browser viewport on the existing Windows test host; not a separate physical laptop.
- **Actual result:** The revised simple-payment confirmation was accessible within the viewport. Complex entries and long labels could require vertical scrolling [E1].
- **Status:** Scoped payment layout checks passed

#### 1920 × 1080 CSS pixels

- **Test configuration:** Browser viewport on the existing Windows test host; not a separate physical desktop.
- **Actual result:** Revised Enter, validation, Review, Recorded, recovery and exact-record captures were inspected. The supplied Chrome images separately show the 15 current screens [E1, E3].
- **Status:** Scoped layout evidence; no device-wide pass

### Table [T05]. Device and Platform Compatibility Testing

#### Windows laptop

- **Configuration to record:** Record model, Windows/browser versions, actual viewport, display scale and zoom; use the selected reference browser.
- **Expected result:** Required controls remain reachable and values survive input, scrolling and task navigation.
- **Actual result:** 1366 × 768 viewport evidence exists, but a separately identified physical-laptop result was not supplied.
- **Status or result:** Physical-device test pending

#### Windows desktop

- **Configuration to record:** Record model, Windows/browser versions, viewport, display scale and input method.
- **Expected result:** Required pages remain readable and sampled workflows complete with correct data.
- **Actual result:** 1920 × 1080 viewport and Chrome screenshot evidence exists. It does not identify or independently test a separate desktop device.
- **Status or result:** Physical-device test pending

#### Mac computer

- **Configuration to record:** Record model and macOS version; use Chrome as the platform reference.
- **Expected result:** Applicable desktop workflows, file selection and downloads remain usable.
- **Actual result:** No physical Mac result was supplied.
- **Status or result:** Not yet tested

#### Linux computer

- **Configuration to record:** Record device and distribution/version; use the stated Chrome reference.
- **Expected result:** Applicable desktop workflows preserve data and permissions and present readable output.
- **Actual result:** No Linux device/environment result was supplied.
- **Status or result:** Not yet tested

#### Android phone

- **Configuration to record:** Record model, Android/Chrome versions, CSS viewport, orientation and touch/keyboard behavior.
- **Expected result:** Assess whether selected viewing and receipt-selection tasks are usable; document inaccessible controls or overflow.
- **Actual result:** No real Android result was supplied. Phone coverage remains exploratory.
- **Status or result:** Not yet tested

#### iPhone

- **Configuration to record:** Record model, iOS/Safari versions, CSS viewport, orientation and touch/keyboard behavior.
- **Expected result:** Assess selected viewing and image-selection tasks without assuming desktop-equivalent workflow support.
- **Actual result:** No real iPhone result was supplied. Phone coverage remains exploratory.
- **Status or result:** Not yet tested

Table [T04] records the available viewport evidence, while Table [T05] retains the physical-device and platform cases from the Chapter III plan. No additional physical-device results were supplied. Phone assessment remains exploratory; the current shared layout uses a 1,024-pixel minimum width, so complete phone usability must not be assumed [E5]. A physical-device pass requires observed completion of the selected tasks on the recorded hardware and software configuration. Device model, OS/browser versions, actual CSS viewport, display scale, zoom, input method and test date should accompany each result. Until those checks are executed, compatibility recommendations remain provisional.

## Testing Evidence Notes

- **[E1] October 8, 2026:** [U2-A targeted review corrections: revised payment and exact-reader checks, source manifests and 24 synthetic captures.](../usability-u2-a-revision.md)
- **[E2] October 7, 2026:** [Stage 3 final integrated delivery: suite inventory, source identity, full-schema/transition separation and browser/HTTP limits.](../stage3-delivery.md)
- **[E3] Supplied for this request:** The 15 Administrator screenshots: Chrome appearance evidence; no additional interaction or physical-device results reported by the user.
- **[E4] October 7, 2026:** [U1-A baseline and linked timing CSV: isolated advance-comparison workload and response times.](../usability-u1-baseline.md)
- **[E5] Source inspected October 8, 2026:** [Shared layout source specifies a 1,024-pixel minimum width; this is a limitation, not a phone-test result.](../../includes/layout.php)
