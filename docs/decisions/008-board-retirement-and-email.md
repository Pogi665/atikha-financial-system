# 008: Board archive, Email correspondence and financial Review Queue

- Status: Active
- Selected date: October 24, 2026 user-approved redesign direction.
- Source/evidence: [Redesign plan](../presentation-redesign-plan.md), [legacy decisions](../history/project-decisions-legacy.md), [archive reader](../../includes/legacy_communications_view.php).

## Decision

Board routes are authorized archive-only GET readers. Reject new Board writes before application upload storage/record creation and reject Board review mutations. Keep historical records/statuses/attachments/audits and notification links. Admin history remains owner-only; Management retains existing authorized historical access. Do not mark Requested history Reviewed just to retire the workflow.

Use the existing outgoing Email backend: New Email/Sent first, secondary Delivery Issues when needed. Sending, Failed and Unknown/Delivery Unconfirmed remain distinct. Uncertain sends require checking delivery state, not blind resend. No incoming mailbox/IMAP/synchronization.

Review Queue retains actual authorized budget/report decisions and exact-item Open, not Board messages. Email does not approve financial records. Close/Reopen remains a separate authorized system workflow.

## Reason

Correspondence and financial decisions have different authority/recovery requirements; retirement must preserve history.

## Supersedes

**Superseded:** active Message the Board/Board Inbox navigation, Board items in current review/counts, and the primary Send Issues label.

## Does not change

Archive/download authorization, historical data, SMTP recovery/security, financial review branches or approval hierarchy.

## Related files/docs

[Email](../../external_email.php), [review actions](../../review_actions.php), [reports/periods](011-reporting-and-period-controls.md).
