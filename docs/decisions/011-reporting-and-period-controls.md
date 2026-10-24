# 011: Explicit reporting setup, immutable submissions and period integrity

- Status: Active
- Selected date: October 23, 2026 combined plan/authorization; preserved by October 24 redesign.
- Source/evidence: [Master plan](../stages4-7-plan.md), [plan approval](../stages4-7-plan-approval.md), [redesign plan](../presentation-redesign-plan.md), [period service](../../includes/accounting_periods.php).

## Decision

Normal Reports has four primary views and secondary Accounting Details. Distinguish live Working Figures from exact immutable Submitted Copies. Retain captured configuration/mappings/approved-budget basis, identities and review events; reports.php?snapshot_id still resolves the exact frozen revision.

Reporting Setup is separate Admin work. Do not infer missing cash/account/fund/tax classifications: incomplete/ambiguous policy blocks completeness rather than inventing treatment. January-December is an initial reviewed engineering default, not confirmed client fiscal policy; arithmetic remains date/month based. Preserve reviewed monthly versus accumulated-result contracts.

Review is not period closing. Close/Reopen preserves fingerprints, downstream dependencies, atomic audit and shared writer lock/chronology rules. Identical committed retries recover their original result before a new closed-period rejection. Monthly close is a posting lock, not automatic annual closing journals.

## Reason

Frozen review must identify the figures actually reviewed; period locks must coordinate with every authorized financial writer.

## Supersedes

**Superseded:** competing normal Reports destinations. Earlier checkpoint exclusions of complete bundles/period controls were limited stage scope, not permanent financial rules; retain those documents as dated history.

## Does not change

Client classifications/opening balances, approval hierarchy, snapshot identities, posting formulas, privacy or working deployment/configuration authorization.

## Related files/docs

[Report workspace](../../includes/report_workspace.php), [report bundles](../../report_bundles.php), [client questions](../client-context.md), [budgets](012-budget-revisions-and-actuals.md).
