# 013: Deterministic planning and explainable audit advisories

- Status: Active
- Selected date: October 29, 2026.
- Source/evidence: User-authorized [final pre-defense plan](../final-predefense-oneshot-plan.md), [planning reader](../../includes/planning_query.php), [methods](../../includes/planning_methods.php), [alert policy](../../includes/advisory_policy.php).

## Decision

Planning & Forecasting is a separate permitted reader for Admin and Management & Board, not a dashboard forecast panel. Automatically choose up to twelve complete recorded months, excluding the ambiguous first recording month and the current partial month. Use signed recognized expenses, preserve corrections/refunds, and compare Last Month, three-month moving average, weighted moving average and six-month trend with earlier-only rolling absolute error and deterministic ties. Missing or insufficient history is not zero.

The organization forecast is calculated independently. Account/project estimates have their own history and cannot be represented as reconciling components of that overall estimate. Disclose partial coverage. Admin may select eligible suggestions and explicitly create an editable owner-private budget draft. This neither posts nor submits nor replaces effective approval. The server recomputes eligible values and checks the source identity; identical committed retries recover their original draft.

The October 29 owner selection makes the next month the default planning period; the current month is a Current Month Outlook. Incomplete current-month history remains excluded. Separate positive Recommended Allocations, Needs Manual Planning and collapsed Other Detailed Estimates. A calculated zero is not an explicit budget allocation and cannot prefill a draft. Show selection count/total separately from the independent organization forecast and approved budget. With effective approval, copy it into a private revision and overlay only selected positive recommendations; do not delete unrelated allocations. Confirm the month, count, total and new/revision intent before creation.

Audit Alerts are Admin-only read-only, explainable patterns with source links, bounded queries and disclosed engineering thresholds. Operational budget/advance notices are not fraud evidence. No automatic corrective action, risk verdict or new notification persistence is introduced. Failed sign-in, operating-hours and monetary-change rules remain unavailable/disabled without authoritative source fields or reviewed policy. Gemini forecast/audit calls remain dormant; OCR retains its separate assistive boundary.

## Reason

The final defense direction requires understandable, repeatable advisory results without inventing client policy or financial authority.

## Supersedes

The assumption that all planning must remain dormant is superseded for this deterministic separate module. Dashboard forecasting remains removed. Old AI forecast/audit endpoints give protected non-generating responses.

## Does not change

Recognized-expense arithmetic, posted data, budget approval, reporting classification, privacy, provider activation or Atikha acceptance.

## Related files/docs

[Presentation](007-refined-client-experience.md), [budgets](012-budget-revisions-and-actuals.md), [delivery](../final-predefense-oneshot-delivery.md).
