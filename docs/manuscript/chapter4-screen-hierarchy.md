# Screen Hierarchy

The following figures present the Administrator screens of the Atikha Financial Management System. The sidebar connects the financial overview, accounting workspaces, books of accounts, communications and administrative pages. Each figure explains the purpose of the displayed screen and the controls available in the captured state. Management and Board screens are role-specific and are not illustrated by these Administrator captures.

## Figure [SH01]. Dashboard Page (System Administrator)

[Insert supplied screenshot 1 here.]

Figure [SH01] shows the Dashboard page, which presents the financial overview of the Atikha Financial Management System. It displays summary cards for total income, total expenses and total assets, together with charts for the indicated reporting periods. The sidebar provides access to financial records, transaction entry, books of accounts, communications and administrative tools. The predictive forecast area indicates its current availability; in the illustrated state, it is waiting for additional history.

## Figure [SH02]. Financial Records Page (System Administrator)

[Insert supplied screenshot 2 here.]

Figure [SH02] shows the Financial Records page, which displays the Journal History / General Ledger as read-only posted journal lines. The administrator can narrow the records by date range, account type and account, then apply or reset the filters. The page also presents total debits, total credits and their difference for the selected records. These totals help users inspect the displayed activity. An account-filtered subset may differ even when its complete journals balance; the Trial Balance provides the separate all-account balance check.

## Figure [SH03]. External Email Page (System Administrator)

[Insert supplied screenshot 3 here.]

Figure [SH03] shows the External Email page, which organizes outgoing correspondence into Sent and Send Issues views. The administrator can select a message from the list to inspect its subject, sender, recipient, reply address, sending account, recorded acceptance time and message body. The New Email button provides access to message composition. The illustrated message is marked Sent. This status records the sending outcome shown by the application and does not independently confirm that the recipient opened or read the email.

## Figure [SH04]. Record a Payment Page (System Administrator)

[Insert supplied screenshot 4 here.]

Figure [SH04] shows the Record a Payment page, which guides the administrator through Enter details, Review and Recorded states. The Quick form presents the date, payee, purpose, paying cash or bank account, payment amount, counterpart account and project. Split allocations and Advanced journal controls retain access to more complex entries. Save draft preserves unfinished work, while Review payment prepares the entry for checking. Review does not itself record the payment or constitute Management approval; financial recording requires a separate final confirmation after successful validation.

## Figure [SH05]. General Journal Page (System Administrator)

[Insert supplied screenshot 5 here.]

Figure [SH05] shows the General Journal page, which provides a detailed workspace for preparing debit and credit entries. The administrator enters the transaction date and purpose, with an optional reference and party, and selects the entry kind. A default project can be applied to the journal lines, while individual line projects identify the corresponding project activity. Additional lines can be added as needed. The entry is saved as a draft and reviewed before posting, and the server requires a valid balanced journal. This page retains the detailed journal presentation shown in the screenshot.

## Figure [SH06]. Scan Receipt Page (System Administrator)

[Insert supplied screenshot 6 here.]

Figure [SH06] shows the Scan Receipt page, which provides the sequence Upload image, Check details and Review journal. The administrator selects a receipt image for reading and then checks the suggested merchant, date, amount and expense account. The illustrated screen is the initial upload state, so it does not display an extraction result. Accepted suggestions can be carried into General Journal, where the credit account and complete entry are checked. Uploading or reading an image does not automatically post a financial record.

## Figure [SH07]. Cash Receipts Book Page (System Administrator)

[Insert supplied screenshot 7 here.]

Figure [SH07] shows the Cash Receipts Book page, which presents posted entries originating from the cash-receipt workflow. Date scope, date range, account type and account filters support inspection of the book, while New cash receipt and My drafts links connect to entry preparation. Debit and credit totals summarize the selected originating activity. The book retains all lines of matching journals. Original and replacement entries contribute to gross book activity; linked General Journal reversals offset originals in the ledger, so the book total alone is not the corrected net cash amount.

## Figure [SH08]. Cash Disbursements Book Page (System Administrator)

[Insert supplied screenshot 8 here.]

Figure [SH08] shows the Cash Disbursements Book page, which presents posted entries originating from the cash-payment workflow. The administrator can filter the displayed activity and use the New cash payment or My drafts links to prepare or resume an entry. Summary cards show total debits, total credits and their difference. As with the Cash Receipts Book, the displayed totals represent gross originating book activity. Linked reversal and replacement records preserve the correction history rather than removing the original payment from the book.

## Figure [SH09]. Cash Advance Register Page (System Administrator)

[Insert supplied screenshot 9 here.]

Figure [SH09] shows the Cash Advance Register page, which supports monitoring of released funds, supported liquidations and returned cash. The administrator can filter by as-of date, employee, originating project, control account, settlement status and overdue condition, or search by advance number, employee, purpose or reference. Links provide access to releasing an advance, private drafts and printing. These filters help identify advances that require follow-up. An advance release is tracked as an asset until the applicable settlement entries reduce the outstanding amount.

## Figure [SH10]. Reports Page (System Administrator)

[Insert supplied screenshot 10 here.]

Figure [SH10] shows the Reports page, which displays the Trial Balance for the selected reporting period. The illustrated report identifies its as-of date, generation time, account codes, account names, account types, debit balances and credit balances. Accounts are grouped into balance-sheet and income-statement categories for presentation. The report uses cumulative posted balances through the specified month-end. Income and expense accounts remain unclosed until closing entries are posted.

## Figure [SH11]. Message the Board Page (System Administrator)

[Insert supplied screenshot 11 here.]

Figure [SH11] shows the Message the Board page, which allows the administrator to compose an internal message to Management using a subject, message body and optional attachment. The adjacent sent-message list displays previously submitted correspondence, recorded dates, review status and attachment links. The Send to Management button submits the message through the internal communication workflow. A Reviewed status refers to the handling of that message; it should not be interpreted as approval of a journal entry or financial transaction.

## Figure [SH12]. Accounting Setup Page (System Administrator)

[Insert supplied screenshot 12 here.]

Figure [SH12] shows the Accounting Setup page, which provides maintenance controls for projects, payers and payees, and accounts reserved for advance workflows. The illustrated Projects section contains name, project code, description and status fields, together with New record and Save controls. A list below the form provides access to existing project records. The Chart of Accounts link opens account maintenance separately. These reference records support transaction preparation; they do not themselves post a financial entry.

## Figure [SH13]. User Management Page (System Administrator)

[Insert supplied screenshot 13 here.]

Figure [SH13] shows the User Management page, which allows the administrator to manage authorized application accounts and password-reset requests. Users can be located by name or email and filtered by role or status. The list displays each account’s role, active status and creation date, with controls for adding or editing users and other permitted account actions. The illustrated role labels distinguish System Administrator from Management and Board. These are application roles, and access to restricted functions remains subject to authorization.

## Figure [SH14]. Chart of Accounts Page (System Administrator)

[Insert supplied screenshot 14 here.]

Figure [SH14] shows the Chart of Accounts page, which organizes the accounts used for double-entry journal posting. The administrator can search accounts and filter them by account type or status. The list groups accounts into Asset, Liability, Equity, Income and Expense categories, and the Add New Account control opens account creation. Account definitions guide the classification of journal lines, while the account groups help the administrator locate the relevant records.

## Figure [SH15]. System Audit Trail Page (System Administrator)

[Insert supplied screenshot 15 here.]

Figure [SH15] shows the System Audit Trail page, which displays recorded system activities with their timestamps, users, actions, modules and available record references. Search, user, module, action and date filters help the administrator locate relevant log entries. The page also provides controls for anomaly scanning, audit-summary generation and CSV export. The illustrated activity list includes login events. This screen supports inspection of the recorded history and identification of activities requiring further review.
