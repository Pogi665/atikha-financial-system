# 010: Distinct cash books, History, account ledger and exports

- Status: Active
- Selected date: Stage 1/3 recorded by October 6; dedicated ledger/completion contracts selected in the October 23 master; primary transaction-list presentation selected by the user on October 28, 2026.
- Source/evidence: [Legacy decisions](../history/project-decisions-legacy.md), [Stage4 annex](../stage4-plan.md), [master plan](../stages4-7-plan.md).

## Decision

CRB/CDB retain full matching journal entries and gross originating activity, including original/replacement entries; linked GJ reversals supply offsets. Never exclude corrected originals or subtract them twice.

The October 28 user-selected primary presentation shows one originating journal per cash-book result and one journal per Find a Transaction result. Account filters select matching journals while preserving all counterpart lines; project filtering matches line tags. Search, count and pagination operate on these transaction results. A single cash amount is shown only when the complete projection safely supports it. Transfers, multiple cash movements and unclassified history keep accurate alternate summaries. Do not turn a gross recorded amount into a corrected net-cash claim.

The existing detailed History JSON and legacy detailed CSV retain their original line-filter contracts. New explicit Transaction list and Detailed journal lines export choices use the complete matching transaction selection, across all pages. The latter includes every counterpart line. Exact transaction inspection always preserves the complete journal, evidence privacy and linked corrections. These are presentation/read-model choices, not changes to postings or balances.

General Ledger is account/period based: opening -> chronological movements -> debit/credit -> running/ending balance. Compute full-period balances before literal search/page. Search visibility does not change that basis. Preserve exact transaction drilldown, historical labels and line project tags; project activity does not promise independently balanced project ledgers.

Exports use validated full-result server models and truthful scopes. Ledger matching-row export and full-period print stay distinct. Label the advance register's page print, all-match totals and whole-account reconciliation scopes accurately.

## Reason

Similar financial tables answer different questions; coherent presentation must not silently change reader semantics.

## Supersedes

Equating all book totals with corrected net cash, replacing History with a ledger, or deriving full-period balances from searched/paged rows are superseded interpretations. The October 28 user instruction supersedes line-oriented rows as the normal Find a Transaction presentation only; it does not supersede the retained detailed JSON/export contracts or historical evidence describing them.

## Does not change

Exact centavos, filters, gross activity, privacy, CSV protection/precision rules, frozen snapshots or consistent reads.

## Related files/docs

[Ledger](../../general_ledger.php), [History/books](../../financial_records.php), [exports](../../includes/financial_export.php), [foundation](001-accounting-foundation.md).
