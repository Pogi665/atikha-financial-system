# 007: Refined Client Experience presentation contracts

- Status: Active
- Selected date: October 24, 2026 approved design/amendments and implementation direction; final review outcome dated October 24.
- Source/evidence: [Governing redesign plan](../presentation-redesign-plan.md), [review approval](../presentation-redesign-review-approval.md), [legacy decisions](../history/project-decisions-legacy.md), [navigation](../../includes/nav.php).

## Decision

Simple on the outside, powerful underneath: information first; explanation secondary. Put meaningful numbers, statuses and primary actions first, accessible basis/help second, and keep blockers visible. Use guided forms for accounting tasks and familiar worksheet/table presentation for financial review. Posted financial data remains immutable/read-only; legitimate editable budget/configuration drafts retain explicit validated states.

Forecasting is removed from normal dashboards, including automatic forecast requests. Retained Gemini forecasting services remain dormant dependencies, not provider activation or financial authority. The October 29 [separate deterministic planning module](013-deterministic-planning-and-audit-advisories.md) supersedes only the broader assumption that all planning is dormant. Admin Money Received / Payments measure cash movements; Management Income / Expenses measure recognized accounting. Cash Balance uses recorded balances of configured cash/bank accounts; it is not a live bank-feed balance.

Admin primary navigation: Dashboard, New Transaction, Transactions, Cash Advances, Budgets, Reports, Email. Secondary Setup contains Accounting Setup, Reporting Setup, Chart of Accounts; Administration contains User Management, Audit Trail. New Transaction is a task hub; other groups need no empty click-through hubs. Expand only the current group where practical. Management: Dashboard, Review Queue, Reports, Budgets, Cash Advances, Email; no authoring/setup/admin actions.

One obvious Reports experience preserves exact legacy snapshot/JSON/print contracts. Progressive disclosure never conceals unresolved required fields without an attention indicator. Written rules and explicit U5/user decisions override incidental mockup text.

### Authentication presentation exception - October 25, 2026

The user approved restoring the earlier Login/MFA split layouts from db15b542 while retaining truthful current feature text and existing accessibility. This exception affects only those two presentation templates: dark left panels, right-side forms, Login gradient/fade animation and original responsive behavior. Do not revive forecasting/live-bank/unsupported provider claims or the missing MFA background-image request. Current authentication/reset/MFA PHP, scripts, routes, validation, CSRF, sessions and resend cooldown remain unchanged. Shared redesigned application surfaces stay intact. See the [bounded delivery](../auth-appearance-restore-delivery.md).

### Authentication community photos - October 25, 2026

The user approved a bounded photo-carousel refinement on the restored Login/MFA split layout, including Login's existing recovery state. This supersedes only the plain/animated left-panel background and former feature copy, not the restored form layout. Use supplied local community imagery without renaming/overwriting originals, a dark emerald/slate overlay, fixed state-specific copy and small photo-associated captions. Crossfade photos calmly; never rotate the main message. Keep the existing below-1024px hiding/reflow. Load one photograph and one ahead rather than all originals at startup; reduced motion shows one stable photo. A quiet keyboard-operable Pause photos control stops automatic changes; captions are not live announcements. Current authentication PHP, existing functional scripts and right-side form panels remain unchanged. No inferred event/person/policy facts or obsolete forecasting/bank-feed claims. See [delivery and asset mapping](../auth-photo-carousel-delivery.md).

### Bounded dashboard and navigation polish - October 28, 2026

The user selected simple dashboard KPI cards without individual information icons; concise accounting-basis context remains in one secondary disclosure. Needs Attention uses compact status/action rows, presentation-only urgency ordering and task-specific actions. Quick Actions and New Transaction use a clear primary/secondary task hierarchy. My Drafts uses button-style Continue/Discard actions without changing its save, revision, confirmation or uncertain-result safeguards.

Desktop navigation supports an expanded sidebar and a 72px icon rail with accessible labels, focus/hover tooltips and keyboard-operable group flyouts. Remember only the local presentation preference; do not add account/database state. Mobile retains its separate drawer. Role-specific destinations, permissions, feature gates and financial semantics are unchanged. This refines presentation only; the original human walkthrough remains paused and incomplete. See the [batch delivery](../visual-polish-batch1-delivery.md).

### Consolidated transaction presentation - October 28, 2026

The user selected a Transactions hub with separate parent-link/disclosure controls, matching the existing New Transaction pattern. Ordinary Payment/Receipt entry starts simple; master creation belongs in Setup. Advanced Accounting Entry deliberately opens Multiple Allocations or Detailed Debit/Credit using the existing draft/posting engine. Resuming a complex draft automatically reveals its required interface without flattening independent values.

Keep the single Save and close decision. Review is neutral and unrecorded, with labelled business fields and accessible complete Accounting details. Recorded completion provides an exact result identity and explicit next actions. Exact transaction and quick-preview surfaces prioritize business context; correction actions and evidence accounting detail remain secondary and authorized. General Journal keeps explicit debit/credit entry with a compact desktop editor and stacked narrow layout. Format ordinary dates naturally without changing their accounting day or time basis. See [reader scopes](010-financial-reader-scopes.md) and the [batch delivery](../transaction-ux-batch2-delivery.md). This does not complete or resume the paused human walkthrough.

## Reason

The October 29 final pre-defense direction additionally selects row-based draft budget allocations, compact operational tables with reachable View actions, focused master editors, integrated manual receipt review, friendly dates and explicit PDF/Excel/CSV/Print scopes. Planning is a secondary destination for both permitted roles; Audit Alerts remain Admin-only. Existing guided financial writers and immutable readers remain authoritative. The [delivery](../final-predefense-oneshot-delivery.md) records implementation and verification separately from owner acceptance. Earlier paused participant observations remain historical evidence; they are not retroactively completed and a new formal study is not the current prerequisite.

Observed user confusion and information density prompted a coherent presentation. External-software familiarity supports familiar tables, not cloning an external system.

## Supersedes

**Superseded:** competing normal navigation, normal forecasting panels, redundant small detail status and incidental Save Draft text contradicting the U5 selection. Older walkthroughs do not establish redesigned usability.

## Does not change

Financial formulas, roles/security/privacy, evidence/OCR authority, immutable history, exact submissions, period/retry contracts, external integration or client acceptance.

## Related files/docs

[Shared presentation](../../includes/presentation.php), [dashboard projection](../../includes/presentation_queries.php), [Save and close](006-save-and-close.md), [fresh protocol](../presentation-redesign-human-protocol.md).
