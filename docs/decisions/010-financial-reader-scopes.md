# 010: Distinct cash books, History, account ledger and exports

- Status: Active
- Selected date: Stage 1/3 recorded by October 6; dedicated ledger/completion contracts selected in the October 23 master.
- Source/evidence: [Legacy decisions](../history/project-decisions-legacy.md), [Stage4 annex](../stage4-plan.md), [master plan](../stages4-7-plan.md).

## Decision

CRB/CDB retain full matching journal entries and gross originating activity, including original/replacement entries; linked GJ reversals supply offsets. Journal History remains line-oriented with its specified filters. Never exclude corrected originals or subtract them twice.

General Ledger is account/period based: opening -> chronological movements -> debit/credit -> running/ending balance. Compute full-period balances before literal search/page. Search visibility does not change that basis. Preserve exact transaction drilldown, historical labels and line project tags; project activity does not promise independently balanced project ledgers.

Exports use validated full-result server models and truthful scopes. Ledger matching-row export and full-period print stay distinct. Label the advance register's page print, all-match totals and whole-account reconciliation scopes accurately.

## Reason

Similar financial tables answer different questions; coherent presentation must not silently change reader semantics.

## Supersedes

Equating all book totals with corrected net cash, replacing History with a ledger, or deriving full-period balances from searched/paged rows are superseded interpretations.

## Does not change

Exact centavos, filters, gross activity, privacy, CSV protection/precision rules, frozen snapshots or consistent reads.

## Related files/docs

[Ledger](../../general_ledger.php), [History/books](../../financial_records.php), [exports](../../includes/financial_export.php), [foundation](001-accounting-foundation.md).
