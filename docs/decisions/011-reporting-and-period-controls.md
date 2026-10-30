# 011: Explicit reporting setup, immutable submissions and period integrity

- Status: Active
- Selected date: October 23, 2026 combined plan/authorization; preserved by October 24 redesign.
- Source/evidence: [Master plan](../stages4-7-plan.md), [plan approval](../stages4-7-plan-approval.md), [redesign plan](../presentation-redesign-plan.md), [period service](../../includes/accounting_periods.php).

## Decision

Normal Reports has four primary financial statements and four Supporting Accounting Schedules. Together these form the Complete Report Package. Distinguish live Working Figures from exact immutable Submitted Copies. Retain captured configuration/mappings/approved-budget basis, identities and review events; reports.php?snapshot_id still resolves the exact frozen revision.

Reporting Setup is separate Admin work. Do not infer missing cash/account/fund/tax classifications: incomplete/ambiguous policy blocks completeness rather than inventing treatment. January-December is an initial reviewed engineering default, not confirmed client fiscal policy; arithmetic remains date/month based. Preserve reviewed monthly versus accumulated-result contracts.

Review is not period closing. Close/Reopen preserves fingerprints, downstream dependencies, atomic audit and shared writer lock/chronology rules. Identical committed retries recover their original result before a new closed-period rejection. Monthly close is a posting lock, not automatic annual closing journals.

October 29 presentation labels use Live Report and Submitted Report / Revision for the same existing live/frozen contracts. Native PDF and Excel join CSV and Print through validated full-result server models. Ledger matching-row export and full-period print remain distinct. Oversized binary exports fail explicitly without returning a partial file; full-result CSV remains available. Snapshot deep links retain exact frozen Trial Balance identity.

The October 29 owner corrections require live actual reports to stop at the current accounting date for the current month, at month-end for past months, and to reject future actual-report periods. Frozen revisions retain their captured cutoff. Normal report printing uses validated server output in a temporary same-page print surface and restores the normal view after dismissal. Selected-report printing excludes the setup appendix; complete-package printing includes it only by deliberate choice. Technical print routes remain available.

## Reason

Frozen review must identify the figures actually reviewed; period locks must coordinate with every authorized financial writer.

## Supersedes

**Superseded:** competing normal Reports destinations. Earlier checkpoint exclusions of complete bundles/period controls were limited stage scope, not permanent financial rules; retain those documents as dated history.

## Does not change

Client classifications/opening balances, approval hierarchy, snapshot identities, posting formulas, privacy or working deployment/configuration authorization.

## Related files/docs

[Report workspace](../../includes/report_workspace.php), [report bundles](../../report_bundles.php), [client questions](../client-context.md), [budgets](012-budget-revisions-and-actuals.md).
