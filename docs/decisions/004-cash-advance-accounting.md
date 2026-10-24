# 004: Dedicated advance release, liquidation and return

- Status: Active
- Selected date: Recorded by October 6, 2026; the October 22 user walkthrough explanation reiterates the example distinction.
- Source/evidence: [Legacy decisions](../history/project-decisions-legacy.md), [Stage 2 plan](../stage2-plan.md), [Stage 2 delivery](../stage2-delivery.md), [client evidence](../client-context.md).

## Decision

Release creates an asset: control debit/cash credit, CDB. Require explicit eligible control-account selection; do not infer it from similar client labels. Liquidation uses eligible expenditure/asset debits, optional liability credits and original-control credit in GJ. Net advance reduction = gross eligible debits minus liability credits; required monetary support covers gross eligible debits. Return is receiving cash debit/control credit, CRB, with reviewed proof and explicit confirmation bound to advance/amount/bank/date/documents.

Validated partial settlements are permitted. Outstanding derives from linked posted control lines with atomic balance/chronology/reconciliation checks, not drafts/evidence totals. Settlement status and overdue can coexist; historical views must not initiate current settlements. One release per advance; extra funds require a new advance. Effective settlements block release correction.

## Reason

The PHP 10,000 released/PHP 8,000 used/PHP 2,000 returned example must not treat the release as expense or return proof as expenditure support.

## Supersedes

Release-as-expense and return-as-liquidation interpretations are superseded. Similar advance labels are not automatically merged/designated.

## Does not change

Gross evidence rules, due-date history, inactive-original exceptions, lifecycle locks, Management privacy or client tax/payroll policy.

## Related files/docs

[Corrections](005-correction-model.md), [evidence](009-evidence-and-assistive-ocr.md), [advance page](../../cash_advances.php).
