# 001: Journal-based accounting foundation

- Status: Active
- Selected date: Recorded by October 6, 2026; exact original selection date is not established in the retained log.
- Source/evidence: [Legacy decisions](../history/project-decisions-legacy.md), [Stage 1 delivery](../stage1-delivery.md), [journal service](../../includes/journal.php).

## Decision

Journal entries/lines and exact-centavo server validation are authoritative. Every posting balances with transactional audit, shared lock order and durable idempotent recovery. Ordinary receipt/payment uses generated cash lines and validated counterparts; authorized adjustments/transfers use General Journal. Party/project snapshots preserve historical labels. Authoritative line project tags, not a default-project shortcut, define activity; Organization operations means no project.

Cash paid/received is not necessarily recognized expense/income. Source implementation does not prove correct client opening balances or supply missing accounting policy.

## Reason

The earlier Incoming Funds/Expenses forms could not represent complete double-entry activity.

## Supersedes

Legacy incoming/expense forms as the primary accounting authority are superseded. Historical clean-slate transition approval does not authorize future truncation or migration replay.

## Does not change

No working cutover, data deletion, new fund/tax policy, invented balances, changed security or financial formulas.

## Related files/docs

[Reader scopes](010-financial-reader-scopes.md), [immutability](003-posted-record-immutability.md), [accounting query](../../includes/accounting_query.php).
