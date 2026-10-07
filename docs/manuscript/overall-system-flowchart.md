# Chapter III: proposed overall system flowchart

Prepared October 7, 2026 for manuscript revision. This describes the **intended completed system**, after the usability phase and remaining system stages. It is not an assertion that all modules are implemented, deployed, tested or accepted. The supplied September 11 manuscript is a reference, not authorization to change application requirements or code.

## Figure identification and scope

Open the [exported PNG image](overall-system-flowchart.png) to view the diagram without a Mermaid preview extension. The white-background export is 1,552 × 4,517 pixels and was visually checked for visible labels, arrows and unclipped diagram boundaries. This detailed figure is tall; assess text size when placing it in the manuscript. It describes intended completed scope, not completed testing.

Retain **Figure 3 — Flowchart of the Overall System**, replacing its current diagram on manuscript page 37 and revising its explanation on pages 38–39. Model user control flow, not the software architecture or every database operation. Boxed subprocesses summarize workflows detailed elsewhere. The [editable Mermaid source](overall-system-flowchart.mmd) can be used in a compatible diagram editor and exported as a vector figure for the manuscript.

Read with the [completion roadmap](../usability-completion-plan.md), [U1-B specification](../usability-u1-design.md), [client context](../client-context.md) and [selected decisions](../project-decisions.md). This draft retains original Stages 4–7 and U1–U5; no stage is implemented by preparing a manuscript figure.

## Changes from the attached manuscript

- Replace legacy Incoming Funds / Expenses-only assumptions with balanced receipts, payments and General Journal workflows. Payments may record assets or settle liabilities, not only expenses.
- Include the linked cash-advance lifecycle and immutable correction workflow.
- Keep optional receipt extraction separate from financial posting. Its selected handoff remains General Journal; manual fallback remains available.
- Separate read-only books/reports/dashboard/export access from financial recording. Viewing records does not create ledger entries.
- Include the intended monthly budgets, report review/frozen bundles and report-dependent period controls, marked as future scope in the accompanying description.
- Distinguish saving a private draft, reviewing an entry and confirming its recording. Show validation, rejected-post and uncertain-result recovery paths.
- Allow returning to another task rather than forcing logout after every operation.
- Use role-based access consistent with two current account roles: Admin and Management. Accounting/administrative staff are functional users operating through the authorized Admin workflow; they are not automatically a third software role. Management/Board use permitted Management functions. The manuscript's older three-role wording needs alignment with this choice; no new role is created here.

## How to read the subprocesses

1. **Login and MFA:** validate credentials/account status and complete the required second factor before an authenticated session. Failed verification returns to authentication; a role-based dashboard follows success. The separate login flowchart can show code delivery, retries and expiry.
2. **Receipts, payments and General Journal:** ordinary receipt originates in CRB; payment in CDB; other entries/transfers in GJ. Enter details, optionally save/resume a private draft, review and deliberately record. Accounting review is not Management approval.
3. **Receipt scan:** store protected evidence, optionally extract proposed information, review/correct it or enter it manually, then prepare a GJ draft. Upload/extraction alone never posts. AI does not prove authenticity or choose an authoritative accounting classification without staff review.
4. **Cash advances:** one release creates an asset; supported liquidation reduces it through recognized expenditure/eligible asset lines; unused-cash return records money received. All operations remain linked to the advance, with current/historical balance protection, required liquidation evidence and bound return-proof confirmation. Release → CDB; liquidation → GJ; return → CRB.
5. **Corrections:** select the original, give a reason/date, preview a GJ reversal and optional correctly routed replacement, and record the bundle atomically. Preserve original journals/evidence. A release with effective settlements remains blocked until those dependencies are resolved. An actual cash return is not an accounting reversal.
6. **Books, ledger, reports and analytics:** inspect complete originating cash books, Journal History and the planned account ledger; generate the monthly worksheet and financial statements, project/advance/budget reports; view dashboards/advisory forecasts and print/export permitted results. Reports derive accounting amounts from posted journals; forecasts are advisory, not actual spending or approved budgets. Corrected originals remain in the ledger alongside their reversals/replacements.
7. **Budgets, report review and period controls:** prepare monthly organization/project/account budgets and their approved revisions; preserve frozen report bundles and review history; close/reopen periods only under the later defined authority and prerequisites. Budget approval and period closing are selected future workflows, not policies proved by the Excel crops. Report/budget prerequisites precede closing; do not interpret this overview as bypassing them.
8. **Communications, setup and audit:** permitted external/internal messages, account/project/party maintenance, users and audit history. This subprocess can read or write its own data as applicable; it never forces every task to create a financial transaction. Existing advisory anomaly review may remain within audit access where enabled; the figure introduces no new automated audit verdict or financial approval.

Every task preserves server authorization and evidence privacy; module selection is not a permission grant. Management sees permitted summaries/report reviews and configured planning controls, while private drafts and sensitive advance/correction documents remain restricted. Approval authority for later modules must be finalized in their detailed plans.

Financial recording rechecks eligibility, balances, evidence, linked context and concurrent state before committing journals/links/audits together. A confirmed result displays its exact record. A rejected request retains editable details. An uncertain result checks the **same durable request identity** until its outcome is established; it must not create another submission or treat a successful prior post as an unrecorded review.

Configured notifications follow applicable events and approved conditions only; not every transaction must trigger a notification and not every read requires a write. Show results and permit another task or logout. Database storage and detailed validation belong within the boxed subprocesses; this is a flowchart, not a data-flow diagram.

## Suggested Figure 3 caption

*Flowchart of the Overall System*

Optional figure note while development is incomplete: **Note. The figure presents the intended workflow of the completed system. Budget/report integration, period controls and other remaining modules are subject to their detailed implementation and validation stages.**

## Suggested replacement explanation for Chapter III

Figure 3 presents the intended overall workflow of the Internal Financial Management and Automated Reporting System for Atikha. The process begins with user authentication and multi-factor verification. After successful account verification, the system displays a role-based dashboard and provides access to the tasks permitted for the authenticated user. Authorized accounting users can prepare cash receipts, cash payments and General Journal entries; manage employee cash advances; and correct recorded transactions through linked reversal and replacement entries. Optional receipt extraction assists data entry, while staff review and manual entry remain available.

Financial entry follows an Enter Details–Review–Record sequence. Users may save incomplete work as private drafts without posting to the ledger. Before recording, the system validates the proposed entry and the supporting evidence required for its workflow. Review presents the accounting effects without constituting Management approval. Explicit confirmation initiates a final validation and an atomic recording of the journals, related links and audit history. Invalid submissions return for correction, while uncertain outcomes are checked using the same posting request to prevent duplicate records. Posted transactions remain preserved; corrections create linked entries rather than overwrite the original records.

Separate pathways support viewing cash books, the General Ledger, financial reports, dashboards and advisory forecasts, as well as printing or exporting permitted information. The completed scope also includes monthly budget preparation and approval, frozen report review and accounting-period controls, together with authorized communications, configuration and audit access. These activities follow their own permissions and validation requirements; viewing information does not create a financial posting. When a configured notification condition is met, the system alerts the authorized recipients. Users can inspect the result, return to another permitted task or log out to terminate their session.

## Manuscript alignment and pending work

The attached manuscript's project-design explanation (pages 35–36) still names three user roles, and Figures 5/6 describe legacy incoming-funds/expense paths. Flag these to the group leader for later alignment; they are not silently rewritten by this task. Avoid claims that the design guarantees accuracy, full audit compliance or universal platform compatibility. Actual results belong to verified testing/evaluation evidence.

Table 2 revision is the next manuscript task; it has not been revised here. Its current rows are proposed Windows/Mac/Linux/mobile/browser cases, not proof that those combinations passed. The usability task remains paused at delivered U1-B, with U1-C unstarted. No application code, database, configuration, deployment or usability prototype was changed to prepare this draft.
