# Chapter III: Table 2 revision

**Superseded October 7, 2026:** the thesis advisor requires separate browser and platform/device plans. Use the [separate-plan handoff](compatibility-testing-plans.md), [Table 2A browser plan](browser-compatibility-matrix.md) and [Table 2B platform/device plan](platform-device-compatibility-matrix.md). The combined table below and its original PNG are historical drafts, not the current manuscript recommendation.

Prepared October 7, 2026. Manuscript revision only; no compatibility tests were executed for this task. The original PDF is unchanged.

## Suggested replacement introduction

Browser and platform compatibility testing will be performed to evaluate whether the completed system presents usable interfaces and preserves correct workflow behavior across the browser/platform combinations listed in Table 2. Testing will cover authentication, navigation, financial entry, supporting documents, cash advances, corrections, record inspection and reporting. Windows laptop and desktop configurations will be the primary test environments; macOS and Linux configurations will provide additional coverage when suitable devices are available. Phone configurations will be assessed separately as exploratory environments rather than assumed to provide full desktop functionality.

## Table 2

*Cross-Browser and Platform Compatibility Testing Matrix*

| Device / platform | Browser | Condition / setup | Planned actions | Expected result | Actual result | Remarks |
| --- | --- | --- | --- | --- | --- | --- |
| Windows PC / laptop | Google Chrome | 1366 x 768 and 1920 x 1080 browser viewports | Run C1-C8; check selectors, draft/review/recorded states, evidence uploads and report downloads. | Required controls remain readable and usable; selected records, amounts and dates are preserved; scoped workflows complete with correct results. | To be tested | Primary desktop coverage; record exact versions and evidence. |
| Windows PC / laptop | Microsoft Edge | 1366 x 768 and 1920 x 1080 browser viewports | Run C1-C8; include keyboard navigation, unsaved-change recovery, advance/correction details and print preview. | Same required results as Chrome; no blocking browser-specific errors or inaccessible actions; printed content remains legible. | To be tested | Primary desktop coverage; earlier scoped Edge checks do not complete this matrix. |
| Mac computer (macOS) | Safari | 1440 x 900 browser viewport; record actual OS/browser versions | Run C1-C8; check date inputs, searchable selectors, dialogs, uploads, downloads and print preview. | Core desktop workflows preserve data and permissions; controls work and required content is not clipped. | To be tested | Additional platform coverage; requires actual macOS/Safari access. |
| Linux computer | Firefox | 1920 x 1080 browser viewport; record distribution and versions | Run C1-C8; check forms, tables, charts, downloads and browser Print to PDF. | Core desktop workflows preserve data and permissions; report content is legible and repeated headings/page breaks are checked. | To be tested | Additional platform coverage; requires actual Linux/Firefox access. |
| Android phone | Google Chrome | Proposed 360 x 800 portrait and 800 x 360 landscape CSS viewports; record actual device | Assess C1, C3, C4, C6 and report/dashboard viewing in C7; test touch, scrolling, file selection and camera selection if offered. | Document whether sampled tasks are usable; images selected from the device retain their values; identify clipping, overflow and unavailable controls. | To be tested | Exploratory mobile assessment; full mobile support is not established. |
| iPhone (iOS) | Safari | Proposed 390 x 844 portrait and 844 x 390 landscape CSS viewports; record actual device | Assess C1, C3, C4, C6 and report/dashboard viewing in C7; test touch, orientation, virtual keyboard and file selection. | Document whether sampled tasks are usable; input values survive orientation changes; identify clipping, overflow and unavailable controls. | To be tested | Exploratory mobile assessment; real-device results are separate from emulation. |

Note. C1-C8 are defined below and apply to each desktop row. Mobile rows cover only the stated exploratory subset. Viewport dimensions are CSS pixels, not physical display resolution. Scrollable wide accounting tables are permitted when all values and controls remain accessible. Exact versions/device measurements are recorded at execution; proposed dimensions are not a device inventory. All actual-result cells remain "To be tested" for this final matrix.

## Shared test cases

- **C1 - Authentication and permissions:** Login, MFA, logout and authorized Admin/Management navigation. Verify denied routes and private-draft/evidence restrictions using each role.
- **C2 - Ordinary financial entry:** Cash receipt, cash payment, General Journal and transfer; save/resume, edit, review, record, reopen and recover the same uncertain request. Verify balanced centavo amounts and no duplicate posting.
- **C3 - Interaction and state:** Mouse/keyboard searchable selectors, long labels, dates, validation recovery and unsaved changes. Confirm that saved, reviewed and recorded states are distinct and amounts/IDs/project tags survive interaction.
- **C4 - Evidence and receipt assistance:** Permitted image upload, preview, manual review, accepted amounts, coverage and authorized download. Test extraction success through an identified controlled fixture and failure/manual fallback separately; live-provider accuracy is a separate result.
- **C5 - Advances and corrections:** Release, supported liquidation, confirmed return, register/details, linked reversal/replacement, dependency blockers, retry recovery and Management redaction.
- **C6 - Books and ledger:** CRB/CDB complete-entry filtering/search and gross totals; Journal History line filtering; planned account ledger/opening/running/ending balances and permitted drilldown once implemented.
- **C7 - Reports, budgets and controls:** Dashboard charts, available reports and snapshots; once implemented, worksheet/statements, approved budgets, report review and period controls. Test configured exports and browser print preview; compare figures with the same known fixture.
- **C8 - Supporting modules:** Authorized communication, master/user maintenance and audit access. Cover only enabled capabilities; record controlled versus live external-service checks separately.

## Suggested replacement explanation

Table 2 specifies the proposed browser/platform combinations, viewport conditions, planned actions and expected results. The researchers will record the application build, operating-system and browser versions, device, viewport, test date, user role and evidence for each executed case. Actual results will be entered only after execution, and remarks will identify pass, fail, partial, blocked or unexecuted cases. Features still under development will be marked not ready for testing rather than passed. Compatibility conclusions will be limited to the functions and environments actually tested; the matrix does not establish compatibility with every browser or device.

## Execution and interpretation notes

- Use one identified application build, equivalent synthetic fixtures and expected journals/balances for comparison. Actual posting tests belong in guarded disposable environments, never the working database. Do not run tests merely by preparing this matrix.
- Exercise both Admin and Management according to their permissions; Management is not expected to perform Admin-only financial posting. Live SMTP/MFA/OCR behavior and physical printing are separate from mocked responses and print-media rendering.
- Run the shared desktop cases across every desktop combination; do not assign OCR only to Chrome or reporting only to Firefox. Browser-specific actions in the rows are additional focus areas.
- A task passes when its scoped actions and expected results have evidence. A row is complete only when all applicable case/role/viewport checks have outcomes. Missing hardware is unexecuted/blocked, not passed; partial checks do not establish full compatibility. Record defect IDs, failure details, retested build and any justified not-applicable cases.
- Physical display resolution, browser viewport, zoom and device pixel ratio are distinct. Record actual viewport, browser zoom and scale. Include a desktop zoom/readability check; controlled table scrolling must not hide required controls.
- Do not promise smooth or lag-free operation without measurements and an agreed criterion. Record navigation-to-ready times under stated server/network/load conditions. The existing advance-comparison delay remains a tracked diagnostic task; it has not been fixed by this table.
- macOS/Linux/phones are browser clients accessing a reachable test server, not presumed local XAMPP installations. A phone cannot reach the laptop using its own localhost. Record test URL/network setup; camera-specific behavior requires the real device and appropriate browser permissions.
- Current source evidence: includes/layout.php defaults to min-w-[1024px]. U1/U5 design gates target 1366 x 768 and 1920 x 1080. Full phone responsiveness is therefore unconfirmed; preparing mobile rows does not authorize new mobile implementation.
- Existing Stage 3 delivery records scoped headless Edge verification in isolated environments. Those dated results remain valid within their scope, but do not mark the final redesigned/completed-system matrix passed. No full macOS/Safari, Linux/Firefox or real-phone pass is claimed here.
- Playwright WebKit is a patched engine, not branded Safari. Emulated viewport/user-agent/touch behavior must be labelled emulation and cannot be reported as testing an actual Mac or phone. See [Playwright browsers](https://playwright.dev/docs/browsers) and [Playwright emulation](https://playwright.dev/docs/emulation).

## Source and handoff

The September 11 manuscript pages 67-70 supply the Table 2 title, seven-column structure and six platform/browser combinations. Legacy "fund tables", unmeasured "without lag" claims and unconditional mobile/no-horizontal-scroll guarantees have been replaced. The introductory/explanatory wording is prospective because this final compatibility matrix is not yet executed.

The user clarified that Chapters I-III were written for the earlier Incoming Funds/Expenses system. This explains historical wording; it does not restore those workflows or authorize changes to the application. Table 1 also contains older workflow/role terminology and should be flagged to the group leader for separate revision; it is not revised here.

Readable/copyable version: [HTML document](cross-browser-platform-matrix.html). Image preview: [PNG table](cross-browser-platform-matrix.png). The HTML includes the caption, table, shared-case definitions and replacement paragraphs. Open it in a browser, then copy the rendered table into Word; preserve its case definitions/note. Use landscape layout or a continued table if needed rather than shrinking text until unreadable.

Project evidence: [U1-B design](../usability-u1-design.md), [completion roadmap](../usability-completion-plan.md), [Stage 3 delivery](../stage3-delivery.md), [project status](../project-status.md). Usability remains paused; no U1-C or application/deployment work was started.
