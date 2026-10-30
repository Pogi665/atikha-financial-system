# 002: Two operational roles and server privacy

- Status: Active
- Selected date: Exact original selection date is not established here; the active role model is source-supported at the Redesigned baseline.
- Source/evidence: [Role vocabulary](../../includes/user_roles.php), [role guard](../../includes/require_role.php), [legacy decisions](../history/project-decisions-legacy.md), [redesign plan](../presentation-redesign-plan.md).

## Decision

Stored roles are Admin and Management; display job titles do not create permissions or approval tiers. Admin authors authorized accounting/configuration. Management has its own dashboard/Review Queue, exact financial review/approval/period decisions, permitted readers and Email, not Admin authoring/setup tools.

The October 29 final pre-defense direction standardizes display labels to System Administrator and Management & Board without changing either stored role. The explicit [Primary Administrator designation](014-primary-administrator-protection.md) protects one Admin account; it is not an additional operational role.

Drafts are owner-private. Management receives permitted posted financial/aggregate coverage, not private evidence images, hashes, per-document review context or mutation controls. Enforce this across HTML, JSON, print, export, metadata, downloads, direct/forged requests and flags-off paths. Preserve retired actors/identities without restoring login access.

## Reason

Navigation simplification must not replace server authorization or expose private evidence.

## Supersedes

Operational Staff-role assumptions in legacy material are superseded by the existing two-role model. Historical Staff identities do not imply a third active role.

## Does not change

CSRF/MFA/password/account protection, reviewer authority, owner/download restrictions and historical audit retention.

## Related files/docs

[Refined experience](007-refined-client-experience.md), [Board/Email](008-board-retirement-and-email.md), [evidence](009-evidence-and-assistive-ocr.md).
