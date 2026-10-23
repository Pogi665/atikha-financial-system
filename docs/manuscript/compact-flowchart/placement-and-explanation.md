# Compact overall system flowchart

This is the proposed replacement for Figure 3 in the supplied manuscript. It preserves the earlier figure's intended completed-system scope while summarizing detailed procedures as subprocesses. It does not establish that remaining reports, budget functions, period controls or exports are implemented or accepted.

## Insert into the manuscript

Use `overall-system-flowchart-compact.png` for a high-resolution image or `overall-system-flowchart-compact.svg` for a vector figure. Replace only the existing overall-system figure. Keep the separate login and module flowcharts for detailed decisions, validation and recovery.

In Word, use **Insert > Pictures**, set the figure to **In Line with Text**, center it and enable **Lock aspect ratio**. Set its width to **5.5 inches**; the proportional height is approximately **7.56 inches**. The 1,800 by 2,475 pixel PNG provides about 327 pixels per inch at that size. Do not set width and height independently. Keep the figure and its caption on the same page. Placement in the full manuscript still needs checking in Word against its actual margins and caption spacing; the original DOCX has not been modified or repaginated.

The minimum diagram text is approximately 10.2 points at 5.5 inches wide. If the usable page width permits 6 inches, proportional placement gives approximately 11.2-point minimum text and an 8.25-inch figure height; check that the caption also fits. Avoid shrinking the figure to the original narrow placement.

## Caption and scope note

**Figure 3**

*Flowchart of the Overall System*

Note. Double-sided rectangles represent subprocesses described in the corresponding module flowcharts. The figure presents the intended completed-system workflow; remaining capabilities are subject to their implementation and validation stages.

## Suggested replacement explanation

Figure 3 presents the overall workflow of the Internal Financial Management and Automated Reporting System for Atikha. After account and role authentication and multi-factor verification, the user enters a role-based dashboard and selects an authorized task. The workflow groups the system's activities into financial recording, information viewing and other permitted operations. Detailed authentication, validation and recovery procedures are represented as subprocesses and explained in the corresponding module flowcharts.

Financial recording covers cash receipts, cash payments, General Journal entries, cash advances and linked corrections. Optional receipt extraction assists preparation, with manual entry and evidence review available. Users may save unfinished work as a private draft or review the proposed entry and explicitly confirm its recording. A saved draft does not create a financial posting. Validation and required evidence checks precede recording, while linked correction entries preserve the original transaction history.

The information path provides permitted access to cash books, the ledger, reports, dashboards, advisory forecasts and print or export outputs. Viewing information does not create a financial posting. Other authorized workflows include budget and report review, accounting-period controls, setup, audit access and communications, each subject to its own permissions and validation. The system displays the permitted result and any applicable notifications. The user may then return to the dashboard for another task or log out to terminate the session.

## Retained details behind the subprocesses

- Authentication errors remain part of the separate login/MFA procedure; the dashboard follows successful verification.
- Financial entry retains saved drafts, Review, explicit confirmation, validation errors, rejected requests and recovery of uncertain results without automatic duplicate posting.
- Receipt extraction does not post a record; its selected accounting handoff remains a reviewed General Journal draft.
- Advance release creates an asset; liquidation and return remain distinct linked operations with their existing evidence requirements.
- Corrections preserve original journals through the approved reversal/replacement workflow.
- Viewing, printing and export are separated from financial posting. Management receives only authorized read-only financial projections.
- Alerts are conditional on applicable configured events. The diagram does not require a new alert for every read or operation.

The original detailed diagram and supplied manuscript remain unchanged. This revision concerns manuscript presentation only; it adds no application feature, accounting policy or acceptance result.
