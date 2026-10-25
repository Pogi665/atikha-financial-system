# 007: Refined Client Experience presentation contracts

- Status: Active
- Selected date: October 24, 2026 approved design/amendments and implementation direction; final review outcome dated October 24.
- Source/evidence: [Governing redesign plan](../presentation-redesign-plan.md), [review approval](../presentation-redesign-review-approval.md), [legacy decisions](../history/project-decisions-legacy.md), [navigation](../../includes/nav.php).

## Decision

Simple on the outside, powerful underneath: information first; explanation secondary. Put meaningful numbers, statuses and primary actions first, accessible basis/help second, and keep blockers visible. Use guided forms for accounting tasks and familiar worksheet/table presentation for financial review. Posted financial data remains immutable/read-only; legitimate editable budget/configuration drafts retain explicit validated states.

Forecasting is removed from normal dashboards, including automatic forecast requests. Retained forecasting services are dormant dependencies, not provider activation or financial authority. Admin Money Received / Payments measure cash movements; Management Income / Expenses measure recognized accounting. Cash Balance uses recorded balances of configured cash/bank accounts; it is not a live bank-feed balance.

Admin primary navigation: Dashboard, New Transaction, Transactions, Cash Advances, Budgets, Reports, Email. Secondary Setup contains Accounting Setup, Reporting Setup, Chart of Accounts; Administration contains User Management, Audit Trail. New Transaction is a task hub; other groups need no empty click-through hubs. Expand only the current group where practical. Management: Dashboard, Review Queue, Reports, Budgets, Cash Advances, Email; no authoring/setup/admin actions.

One obvious Reports experience preserves exact legacy snapshot/JSON/print contracts. Progressive disclosure never conceals unresolved required fields without an attention indicator. Written rules and explicit U5/user decisions override incidental mockup text.

### Authentication presentation exception - October 25, 2026

The user approved restoring the earlier Login/MFA split layouts from db15b542 while retaining truthful current feature text and existing accessibility. This exception affects only those two presentation templates: dark left panels, right-side forms, Login gradient/fade animation and original responsive behavior. Do not revive forecasting/live-bank/unsupported provider claims or the missing MFA background-image request. Current authentication/reset/MFA PHP, scripts, routes, validation, CSRF, sessions and resend cooldown remain unchanged. Shared redesigned application surfaces stay intact. See the [bounded delivery](../auth-appearance-restore-delivery.md).

## Reason

Observed user confusion and information density prompted a coherent presentation. External-software familiarity supports familiar tables, not cloning an external system.

## Supersedes

**Superseded:** competing normal navigation, normal forecasting panels, redundant small detail status and incidental Save Draft text contradicting the U5 selection. Older walkthroughs do not establish redesigned usability.

## Does not change

Financial formulas, roles/security/privacy, evidence/OCR authority, immutable history, exact submissions, period/retry contracts, external integration or client acceptance.

## Related files/docs

[Shared presentation](../../includes/presentation.php), [dashboard projection](../../includes/presentation_queries.php), [Save and close](006-save-and-close.md), [fresh protocol](../presentation-redesign-human-protocol.md).
