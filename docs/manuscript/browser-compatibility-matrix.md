# Table 2A: Cross-Browser Compatibility Testing Matrix

Prepared October 7, 2026. Separate manuscript testing plan; actual results remain unexecuted. Table labels 2A/2B are provisional, pending manuscript-wide numbering.

## Replacement introduction

Cross-browser compatibility testing will evaluate whether the system performs the same required operations in different browsers. Within each comparison group, the researchers will use the same computer, operating system, application build, server, synthetic data and browser viewport, changing the browser under test. Chrome, Edge and Firefox will be compared on Windows. Safari will be compared with Chrome on the same Mac so that a change of operating system is not mistaken for a browser-only difference.

## Table 2A

*Cross-Browser Compatibility Testing Matrix*

| Browser | Controlled test setup | Planned actions | Expected result | Actual result | Remarks |
| --- | --- | --- | --- | --- | --- |
| Google Chrome | Windows comparison group; same PC and OS as Edge/Firefox; 1366 x 768 and 1920 x 1080 viewports | Run C1-C8; verify navigation, selectors, financial forms, evidence, books, charts and downloads. | Applicable workflows complete with correct values and permissions; controls and content remain usable. | To be tested | Windows reference browser; record exact version and build. |
| Microsoft Edge | Same Windows PC, OS, fixtures and viewports as Chrome | Run C1-C8; include keyboard use, validation/recovery, dialogs, advance/correction details and print preview. | Same required results as the Windows reference; no blocking browser-specific errors or inaccessible actions. | To be tested | Earlier scoped Edge checks do not complete this final plan. |
| Mozilla Firefox | Same Windows PC, OS, fixtures and viewports as Chrome | Run C1-C8; include date inputs, selectors, evidence uploads, charts, downloads and Print to PDF. | Same required results as the Windows reference; forms retain correct values and report content is legible. | To be tested | Proposed additional browser coverage; record exact version. |
| Google Chrome | Mac comparison group; same Mac/macOS as Safari; 1440 x 900 viewport | Run C1-C8 to establish the Mac browser-comparison reference. | Applicable workflows preserve data and permissions; required controls and report content remain usable. | To be tested | Separate Mac reference; not an assumption that Chrome behaves identically across OSs. |
| Safari | Same Mac, macOS, fixtures and viewport as the Mac Chrome reference | Run C1-C8; include native date inputs, selectors, dialogs, uploads, downloads and print preview. | Same required results as the Mac reference; no blocking browser-specific errors or inaccessible controls. | To be tested | Requires actual Safari on macOS; a WebKit-engine run is separate evidence. |

Note. Browser versions, OS versions and the application build will be recorded when testing is executed. C1-C8 apply to every row, with both roles tested according to their permissions. Comparisons are within the Windows or Mac group, not between different platforms. Missing environments remain unexecuted; unavailable future features are not ready for testing.

Case key. C1 Authentication/permissions; C2 Ordinary entry; C3 Interaction/state; C4 Evidence/receipt assistance; C5 Advances/corrections; C6 Books/ledger; C7 Reports/budgets/controls; C8 Supporting modules.

## Shared case definitions

- **C1 - Authentication and permissions:** Login, MFA, logout and authorized Admin/Management navigation. Verify denied routes and private-draft/evidence restrictions using each role.
- **C2 - Ordinary financial entry:** Cash receipt, cash payment, General Journal and transfer; save/resume, edit, review, record, reopen and recover the same uncertain request. Verify balanced centavo amounts and no duplicate posting.
- **C3 - Interaction and state:** Mouse/keyboard searchable selectors, long labels, dates, validation recovery and unsaved changes. Confirm that saved, reviewed and recorded states are distinct and amounts/IDs/project tags survive interaction.
- **C4 - Evidence and receipt assistance:** Permitted image upload, preview, manual review, accepted amounts, coverage and authorized download. Test extraction success through an identified controlled fixture and failure/manual fallback separately; live-provider accuracy is a separate result.
- **C5 - Advances and corrections:** Release, supported liquidation, confirmed return, register/details, linked reversal/replacement, dependency blockers, retry recovery and Management redaction.
- **C6 - Books and ledger:** CRB/CDB complete-entry filtering/search and gross totals; Journal History line filtering; planned account ledger/opening/running/ending balances and permitted drilldown once implemented.
- **C7 - Reports, budgets and controls:** Dashboard charts, available reports and snapshots; once implemented, worksheet/statements, approved budgets, report review and period controls. Test configured exports and browser print preview; compare figures with the same known fixture.
- **C8 - Supporting modules:** Authorized communication, master/user maintenance and audit access. Cover only enabled capabilities; record controlled versus live external-service checks separately.

## Replacement table explanation

Table 2A identifies the browsers and controlled conditions for cross-browser testing. The same functional cases will be repeated within each comparison group to identify differences in rendering, input controls, workflow completion, downloads and printing. Actual results and defects will be recorded after execution. Conclusions will apply only to the tested browser versions, builds, cases and environments.

## Execution boundaries

Use known synthetic data and expected journals/balances in guarded disposable environments for posting tests. Record source/build identity, case, role, versions, viewport, test date and evidence. A pass requires an executed case with the expected outcome; partial, blocked, unexecuted and not-ready cases are not passed. Future reporting/budget/closing checks await implementation. Live MFA/SMTP/OCR, real camera use and physical printing remain separate from controlled fixtures and print-media checks. Existing scoped Edge tests are dated evidence, not completion of either final matrix. Current shared layout defaults to a 1,024-pixel minimum width; full mobile responsiveness remains unconfirmed. No compatibility tests are performed by preparing these plans.

Emulation/WebKit results do not establish physical-device/branded-Safari results. Method references: [Playwright browsers](https://playwright.dev/docs/browsers), [Playwright emulation](https://playwright.dev/docs/emulation).

[Open copyable HTML](browser-compatibility-matrix.html) | [View PNG](browser-compatibility-matrix.png) | [Separate-plan handoff](compatibility-testing-plans.md)
