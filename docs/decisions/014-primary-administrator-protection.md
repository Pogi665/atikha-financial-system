# 014: Explicit Primary Administrator protection

- Status: Active
- Selected date: October 29, 2026.
- Source/evidence: Approved [final pre-defense plan](../final-predefense-oneshot-plan.md), [designation service](../../includes/primary_administrator.php), [additive component 026](../../migrations/026_primary_administrator.sql).

## Decision

Designate one explicitly selected existing active Admin using a singleton record with restrictive foreign keys and transactional audit. Never infer UserID 1 or silently choose a user. This is protected account metadata, not a third role, new approval tier or transfer workflow.

Supported user/reset writers lock the shared designation before actor/target rows in sorted order. Another Admin cannot change the Primary Administrator's identity, credentials or status. The Primary Administrator retains normal self-edit but cannot disable, delete or demote itself. Direct deletion of the referenced user is restricted. Absent, uninitialized or partial designation state blocks protected writers with setup guidance; no fallback bypass.

Initialization is explicit, audited and recoverable for the same designation. Conflicting initialization is rejected. No transfer UI, new mandatory reauthentication or added database trigger is introduced.

## Reason

Protect the system custodian without widening permissions or weakening existing account/session safeguards.

## Supersedes

The prior ability of any Admin to change another Admin is narrowed for the designated protected account only.

## Does not change

Stored Admin/Management roles, accounting approvals, MFA, normal nonprimary administration, client account selection or working deployment authorization. Component 026 and initialization require a separate reviewed working rollout; isolated application evidence is not deployment.

## Related files/docs

[Roles/privacy](002-role-and-privacy-model.md), [users](../../includes/users.php), [reset resolution](../../resolve_reset.php), [delivery](../final-predefense-oneshot-delivery.md).
