# Client context and corrected conversation record

Recorded October 6, 2026 from the user messages available in this chat and the repository's plans/delivery reports. This is a curated requirements record, not a verbatim transcript of every turn or independently verified client policy. Earlier assistant responses and externally reviewed attachments are not all available as original files.

## Purpose and constraints

- Atikha is the NGO client. This university capstone is intended to computerize its internal finance work and become its regularly used system.
- The user initially asked for discussion only, identified poor cash receipt/disbursement usability, considered double entry a good foundation, and wanted receipt scanning improved. They then requested staged planning, external review, and explicitly authorized implementation checkpoint by checkpoint.
- The organization cannot disclose its actual financial documents. Staff supplied cropped Excel screenshots and visit notes instead. Do not fill missing facts with fictional client data.
- The client is busy and the user said they need to complete the system this week. No precise completion date was established. The advisor allowed dummy data close to Atikha's NGO activities; use clearly synthetic training, transport, donation, and similar examples in isolated environments.
- Replies should remain in English even when source notes are Tagalog.

## Current client workflow as described by the user

Accounting staff use Excel/loose-leaf accounting. They prepare and print an Excel form for higher management. The user was uncertain whether 'already completed' meant a completed form or an already completed payment; approval timing is unresolved. Earlier discussion included both submitting for approval and recording/reviewing completed payments. Do not infer a mandatory digital approval sequence or treat an accounting Review button as management approval.

The client uses other systems. If a particular project/funder requires Excel, QuickBooks, or another app, Atikha uses that tool for that project. This capstone is intended for regular organization use alongside those requirements. No automatic import/export integration with those systems was confirmed.

## Corrections supplied by the user after the visit notes

These interpretations supersede contrary assumptions drawn from the raw notes:

1. **'Walang accounts payable system namin':** Atikha's existing system does not currently have accounts payable functionality. It was not a statement that this capstone must exclude payable accounting. The cropped account list also contains Accounts Payable; exact current usage remains unverified.
2. **'Add button ... dropdown na ulit':** this comment referred to the older incoming-funds/expenses demonstration. It is not automatically a requirement for the current journal workspace.
3. **'Payee ay pwede wala na':** also arose in the older demonstration. The user clarified that payer/payee information should remain in the current system; do not remove it based on that note.
4. **'Gagamit ay kabuuan':** means they want the capstone as their regularly used overall system. It does not establish a special whole-amount accounting rule.

## Visit-note meaning retained for current design

- Cash advances are not expenses at release. The example is PHP 10,000 released, with potentially PHP 8,000 subsequently supported as expenditure and the remaining amount accounted for.
- Advances should link to their journals, show in financial records, and support liquidation and correction.
- Adjustments belong in a journal; a journal is not the Chart of Accounts. The notes ask for a general ledger encompassing accounts and individual monthly transactions.
- Archived/posted records should not be directly edited; adjusting entries preserve the correction. The exact reverse-and-replace algorithm was subsequently selected by the user, not established verbatim by the notes.
- Every posted transaction needs both debit and credit. Expense-only incoming forms were insufficient; liability credits may be needed.
- The client wants balances/reporting that can be compared with funder reports. The note about tax not showing in a report is ambiguous: it does not authorize omitting tax liabilities from the ledger or force a particular funder-report presentation.
- Excess cash returned to bank and donations involve receipts, but the notes do not prove complete document-review, exception, or approval policies.

## What the six Excel screenshots establish

The user supplied these cropped references; the original private image files are not copied into this repository:

- A **Statement of Cash Flows**, headed 'As of September 24, 2026', with excess/deficiency of revenues, gains and other supports over expenses; operating asset/liability changes; operating/investing/financing sections; net increase; and beginning/ending cash. The visible structure supports an indirect presentation. It contains no figures proving the calculations and no verified starting date.
- A June 2026 **Trial Balance worksheet** headed with Atikha's name, with seven debit/credit pairs: Beginning Balance, CRB, CDB, Journal, Ending Balance, Income Statement, and Balance Sheet. This establishes the column layout, not complete cash-book rows, routing, approvals, or monthly-versus-year-to-date statement formulas.
- Cropped expense/account labels include salaries/allowances, professional/consultant fees, staff development, training/meeting meals and accommodation, transportation, planning, monitoring, materials, office supplies, repairs, employee benefits, website/admin costs, utilities, communication, taxes/licenses, depreciation, and miscellaneous expense.
- Other labels include sponsorship, other income, interest income, gains/loss on forex, grants, donations, training/consultation fees, and fund balance.
- Cash on Hand, Petty Cash, Cash in Bank, Accounts Receivables, Prepaid Tax, equipment/furniture, accumulated depreciation, payables, Cash Advance - Employees, Employee's Advances, and Due to/from AWO/CCRC Project are visible.

The crops are not a complete approved Chart of Accounts. They supply no verified codes, opening balances, fiscal-year rules, donor restrictions, or proof that every listed account is active.

Keep unresolved classifications explicit:

- The two advance labels may represent different arrangements; do not merge or automatically designate both.
- Due to/from project labels do not establish whether balances are assets or liabilities, or whether projects are within one entity.
- Donations appears in more than one crop; its placement near Fund Balance does not prove that all donations/grants are equity.
- Accumulated depreciation can use Asset type with Credit normal balance and deduction in presentation; detailed report grouping remains to be configured.
- 'Withholding Tax - Expanded' in the cash-flow crop does not establish its account classification/sign by itself.

## Background documents and external reviews

The user mentioned `Newly-Revised-Chapter-1-3-ATIKHA.docx` and a visit/system video. Their complete contents were not read for this context-file task. Treat the manuscript as background until a relevant task inspects it; do not claim its incomplete text establishes a new module.

The original whole-system plan was downloaded as `plan_complete.md`. Reviews also referenced `stage_1.md` and `stage_2.md`; available repository counterparts are linked from [README](../README.md). Do not assume differently named attachments are identical unless compared.

Initial external reviews lacked the Excel screenshots; later reviews received them. That improved evidence attribution, account questions, and report-layout validation, but did not establish undocumented client policies. User-supplied reviewer praise is not proof of implementation correctness. Code reviews apply only to the inspected commit/files, not unpushed changes or the working database.

## Raw visit notes supplied by the user

The following original notes preserve the source wording. Apply the user corrections above before deriving requirements from them.

```text
Liquidate
Cash advance ex. 10,000
Disbursement ilalagay numbers
Nailabas
So 10, 000(di sya expense) magiging 8,000( possible na magiging resibo/expense)
Magpapakita sa financial records
Kung magkaka error san sya ilalagay
Kailangan may isang journal kung saan papasok yung error
May sariling ledger yung adjustments
Cash advance ay di expenses
Paano sya ili-liquidate
Paano sya itatama
Sa journal entry nakalink sa cash advance
Adjustable dapat na entry
Archives hindi na nae-edit
May adjusting entry
Saming system diretso
Pag may labis, cash in bank, yung pinapasok is may resibo
Donations ay resibo
Gagamit ay kabuuan
Basta ipasok sa kanila expense(similar to what system they use)
Kung tama ang balanse sa dulo dapat tatama sya sa report ng funder
May tax pero di magpapakita sa report
Di pa mahbabalanse kung ganun at need din sya pag magfu-fund request
Journal(originally nasa book of accounts)(ibabalik?) ay hindi chart of accounts
Ang laman ng journal yung adjustment entry(dito ilalagay adjusted entry)
Ledger(kabuuan ng transaction) ex. Cash disbursement for that month, magkano debit
Lahat ng accounts ay nasa ledger
Individual transaction per month,
Walang accounts payable system namin
Lagyan sa expenses ng system
Puro debit kami, dapat may credit
What if may payable sya
Sa category may accounts payable (naka credit kasi nasa kabilang side)
Pwede add button sa may category sa expenses (may napili ka)
Tapos drop down na ulit
Sa journal entry ay reference na lang purpose,
Payee ay pwede wala na(pindot na lang)
Kung malilink ang cash advance sana
Kailangan mare-recognize sya as debit or credit sa expenses
Kailangan may transaction na halimabawa may transaction, para makita kung tutugma
Samin puro incoming expenses lang
Dapat may credit at debit lagi ang lahat ng transaction
Lahat may credit at debit
General ledger na lang
```

## Maintaining this record

Add later client/user corrections with their date and source, and identify which interpretation they supersede. Do not rewrite unresolved facts as confirmed requirements just because implementation needs a default. Put selected defaults in [project decisions](project-decisions.md) and progress in [project status](project-status.md).
