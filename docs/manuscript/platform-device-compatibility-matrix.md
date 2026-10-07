# Table 2B: Platform and Device Compatibility Testing Matrix

Prepared October 7, 2026. Separate manuscript testing plan; actual results remain unexecuted. Table labels 2A/2B are provisional, pending manuscript-wide numbering.

## Replacement introduction

Platform and device compatibility testing will evaluate the system on different operating systems, device types and viewport sizes. The application build, server and synthetic data will remain consistent. A reference browser will be used for each environment to avoid repeating the complete browser-comparison plan. Chrome will be used where available and Safari will be the reference browser on the iPhone. These cases evaluate the complete device/platform configuration; they do not isolate operating-system effects from hardware or browser differences.

## Table 2B

*Platform and Device Compatibility Testing Matrix*

| Platform / device | Test setup and reference browser | Planned actions | Expected result | Actual result | Remarks |
| --- | --- | --- | --- | --- | --- |
| Windows laptop | Google Chrome; proposed 1366 x 768 CSS viewport; record laptop model and OS/browser versions | Run C1-C8; check navigation, form readability, keyboard access, selectors, tables, review/recorded states and exports. | Required actions remain accessible; text does not overlap; data and permissions remain correct. Wide tables can scroll without hiding controls. | To be tested | Primary laptop configuration; repeat selected tasks at increased browser zoom. |
| Windows desktop | Google Chrome; proposed 1920 x 1080 CSS viewport; record device and versions | Run C1-C8; check larger-screen layout, journal/advance/correction details, charts, reports and print preview. | Required content remains readable and complete; wider layouts preserve values and workflow behavior. | To be tested | Primary desktop configuration; record display/viewport/scale separately. |
| Mac computer / macOS | Google Chrome reference; proposed 1440 x 900 CSS viewport; record model and versions | Run C1-C8; check input, keyboard/file-picker interaction, uploads, downloads and print preview. | Core desktop workflows remain usable; data and role restrictions match the known fixture; permitted files open correctly. | To be tested | Additional platform coverage; Safari comparison belongs in Table 2A. |
| Linux computer | Google Chrome reference; proposed 1920 x 1080 CSS viewport; record distribution/device and versions | Run C1-C8; check input, dialogs, charts, tables, downloads and browser Print to PDF. | Core desktop workflows remain usable; data and permissions match the fixture; generated content remains legible. | To be tested | Additional platform coverage; requires an available Linux device/environment. |
| Android phone | Chrome reference; proposed 360 x 800 portrait / 800 x 360 landscape CSS viewports; record real device | Assess C1, C3, C4, C6 and viewing in C7; check touch, scrolling, orientation, virtual keyboard and image selection/camera if offered. | Record whether sampled tasks are usable and preserve entered values; document overflow, clipping and unavailable controls. | To be tested | Exploratory phone assessment; full mobile support is unconfirmed. |
| iPhone / iOS | Safari reference; proposed 390 x 844 portrait / 844 x 390 landscape CSS viewports; record real device | Assess C1, C3, C4, C6 and viewing in C7; check touch, orientation, virtual keyboard and image selection. | Record whether sampled tasks are usable and preserve entered values; document overflow, clipping and unavailable controls. | To be tested | Exploratory; browser and hardware differences limit OS-only comparisons. |

Note. Dimensions are proposed browser viewports in CSS pixels, not verified physical screen resolutions or a client device inventory. Desktop rows use C1-C8; phone rows use the stated exploratory subset. Record actual device/OS/browser, zoom, pixel ratio, network and server conditions. A real-device result is distinct from emulation. Required controls must remain accessible; controlled scrolling of wide accounting tables is permitted.

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

Table 2B identifies the proposed device types, operating systems, reference browsers and viewport conditions for platform/device testing. It emphasizes layout, interaction, data preservation and access to system functions. Phone cases are exploratory because complete mobile usability has not been established. Actual results will document the observed capabilities and limitations of each tested configuration, without claiming compatibility with every device.

## Execution boundaries

Use known synthetic data and expected journals/balances in guarded disposable environments for posting tests. Record source/build identity, case, role, versions, viewport, test date and evidence. A pass requires an executed case with the expected outcome; partial, blocked, unexecuted and not-ready cases are not passed. Future reporting/budget/closing checks await implementation. Live MFA/SMTP/OCR, real camera use and physical printing remain separate from controlled fixtures and print-media checks. Existing scoped Edge tests are dated evidence, not completion of either final matrix. Current shared layout defaults to a 1,024-pixel minimum width; full mobile responsiveness remains unconfirmed. No compatibility tests are performed by preparing these plans.

Emulation/WebKit results do not establish physical-device/branded-Safari results. Method references: [Playwright browsers](https://playwright.dev/docs/browsers), [Playwright emulation](https://playwright.dev/docs/emulation).

[Open copyable HTML](platform-device-compatibility-matrix.html) | [View PNG](platform-device-compatibility-matrix.png) | [Separate-plan handoff](compatibility-testing-plans.md)
