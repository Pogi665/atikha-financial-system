# U3-A correction: reconciliation unavailable before first success

October 21, 2026. **Scoped correction approved; U3-A source checkpoint closed.** The user supplied ChatGPT's final exact-source sign-off with no remaining blocker. [Approval record](usability-u3-a-approval.md) records the closure and separate acceptance/next-stage boundaries. The reviewed ZIP and manifest remain unchanged; this approval update is later documentation, not a new source/test revision.

Original correction context: The user supplied ChatGPT's exact-source review identifying one initial reconciliation-health presentation defect. No accounting, privacy, lifecycle or amount-calculation defect was reported. U3-B/C/D remain unstarted and unauthorized.

- [Corrected exact-source ZIP](usability-u3-a-revision-review-package.zip)
- [Application correction against the original review ZIP](usability-u3-a-revision-application.patch), [focused test correction](usability-u3-a-revision-tests.patch), and [complete application patch from U2-C](usability-u3-a-revision-from-u2c.patch)
- [Exact source/screenshot manifest](usability-u3-a-revision-manifest.json), [results](usability-u3-a-revision-results.json), [verification log](usability-u3-a-revision-verification-log.txt), [reviewer notes](usability-u3-a-revision-reviewer-notes.md) and [30 corrected screenshots](images/usability-u3-a-revision/index.html)

## Corrected behavior

Before this correction, a first request failure changed the reconciliation message but left the success-colored card and an empty details shortcut. The register now starts with a neutral **loading** health card, explicitly marked `data-state="loading"`. The full-reconciliation shortcut and disclosure are hidden until a successful response has been completely verified.

A failed or unverifiable first response becomes **Reconciliation unavailable — not verified**, with slate styling and `data-state="unavailable"`. The health card is distinct from both the verified green healthy state and the orange integrity-discrepancy state. Printing stays disabled; the shortcut/disclosure remain hidden, closed and guarded against programmatic opening. The initial basis text also explicitly says that no successful check exists.

A verified success removes the loading/unavailable styling, restores the appropriate healthy/discrepancy state and makes the full details available. If a later refresh fails, the previous rows, balances, applied date/filters, reconciliation contents, health styling and details availability remain the last successful snapshot, alongside the refresh error. Loading of that later request uses the existing register status and does not erase verified health.

## Exact scope

Only these production files changed in this correction:

- `cash_advances.php`: initial neutral/loading markup, initially hidden reconciliation link/disclosure and truthful unavailable basis.
- `assets/js/cash_advances.js`: one unverified-state helper, verified-state restoration, a guarded shortcut, and first-request loading/failure transitions gated by the absence of an applied snapshot.
- `assets/css/accounting_workspace.css`: scoped neutral loading/unavailable colors and reliable hiding of those two reconciliation elements. No global or ordinary/correction workspace selector changed and no Tailwind rebuild was needed.

Only `scripts/test_usability_u3_a_presentation.py` and `scripts/test_usability_u3_a_browser.py` changed as test sources. No accounting service, writer, lifecycle/privacy rule, endpoint, schema, migration, configuration or feature flag changed. No commit/push or later U3 checkpoint was performed. The XAMPP source can serve the corrected presentation on refresh; this task did not perform a working financial action or acceptance flow.

## Verification against final corrected bytes

- PHP lint passed `cash_advances.php`; Node syntax passed `assets/js/cash_advances.js`; Python compilation passed both changed runners; scoped Git whitespace checks passed.
- `python scripts/test_usability_u3_a_presentation.py` — **43 database-free Edge assertions passed**. New assertions verify neutral loading colors, no unverified details, unavailable versus healthy/discrepancy styling, guarded shortcut behavior, malformed initial response, verified retry, and preservation after both healthy and discrepancy refresh failures. Existing formatting, safe text, print, historical and role presentation assertions remain.
- `python scripts/test_usability_u3_a_browser.py --fixture=<fresh-guarded-clone>` — **91 authenticated Edge browser/HTTP assertions passed**. The new first-load HTTP failure and unverifiable-success cases verify computed neutral styling, explicit status, hidden/closed details and unavailable basis, disabled printing, programmatic shortcut protection and a real successful retry. All existing failed/malformed/stale refresh, pagination, amount, role, historical/lifecycle, print, deadline-success and stale-revision checks passed.

Both suites used Edge155.0.4283.62 and reported **zero uncaught JavaScript errors**. Desktop/laptop captures are1366×768 and1920×1080; representative unavailable and healthy/refresh screens were visually inspected. The gallery contains28 new focused-suite captures and2 new database-free unavailable captures. Failure/invalid-response screenshots are labeled doubles, not real database integrity findings.

The focused suite ran on a **new** isolated clone of the previously validated synthetic full-schema fixture, with private copied code, sessions and synthetic evidence. Its26 normal-API release preparations and deliberate deadline writes went only to that clone. All34 application tables remained unchanged during its read/navigation/print phase. Synthetic-session setup is followed by real application authentication/role/owner guards; it is not a new human MFA test.

External browser requests/provider endpoints were blocked and child-only PHP transport/mail restrictions were inherited. No migration/bootstrap or test inline-routine compatibility path ran. Before/after read-only working table/schema/object/permission fingerprints match; working configuration hashes and source hashes remained stable during the run. Tested copied application bytes match the delivered three corrected files. Working financial data and protected uploads were not fixtures, and no working data, users, audit or grants were written. No working server was started/stopped.

The separate Stage2/Stage3/readback suites were **not repeated**: the change adds only register first-result presentation transitions, and the final focused suite covers the affected shared register/detail, refresh, print and role behavior. Their earlier56/48/54/27 results, and the original269-check total, remain dated evidence against the pre-correction source. They are not added to these final43/91 results or represented as reruns. Migration009 error1304 remains a separate unresolved diagnostic.

## Review and resume boundary

The new ZIP includes exact source, correction/full-baseline patches, test tools, manifests and synthetic screenshots. The original36-screenshot review ZIP remains byte-identical and available as dated evidence. Public artifacts exclude private configuration, credentials, sessions, database exports, working receipts and raw financial fingerprints.

External U3-A exact-source approval is now recorded in the linked approval record. Full working/Atikha acceptance remains separate. Do not start U3-B/C/D. U2 follow-up before U5, U-PERF-01, U4/U5, original Stages4–7 and the separate migration009 diagnostic remain outstanding.

Private revision evidence: `.migration-private/u3a-revision-6d63454e20`, `.migration-private/u3a-browser-6d49051aa4` and `.migration-private/u3a-presentation-f9aaa14ae3`. The final source hashes and screenshot hashes are in the linked revision manifest.

Handoff verification: Windows line endings in the generated correction patches were normalized to canonical LF. Both application and test patches apply cleanly to files extracted from the exact previous review ZIP; all three final Git-normalized source blobs match the manifest. This packaging correction changed no application/test source bytes or test outcomes.
