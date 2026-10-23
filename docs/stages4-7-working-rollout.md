# Working rollout procedure — unperformed

Prepared for source consultation on October 23, 2026. This is a procedure for a later separately authorized rollout, not an instruction to execute now. G7 is paused at the user's request and final G8/source review is pending. No working migration, configuration or activation has been performed for Stages 4–7.

## Preconditions and explicit authorization

Complete the actual human walkthrough and affected retests, regenerate the final exact-source/evidence package, and obtain its external review. Confirm which working target and specific migration/configuration actions the user authorizes. Source approval does not authorize a rollout by itself. Inspect the existing working 019/020/021 records rather than replaying them; never rerun migration 015. Migration 009 diagnosis and broader health/recovery remain separate work.

Inspect normal database availability, engine/version, schema, trigger definitions, application/configuration identity, privileges, protected evidence storage and free space using a bounded preflight. Stop on instability, recovery mode, unexplained drift, partial installed new schemas or unresolved financial/privacy/integrity problems. Do not repair, restart servers or change global PHP/MariaDB settings to obtain a pass.

Obtain a current consistent backup of the database, protected originals and associations, audit/identity history and relevant configuration, with a verified restore rehearsal to an isolated target. Keep backups, credentials and images private. Record source/version/backup identities and recovery ownership before any authorized DDL. A read-only preservation comparison is not a backup or restore rehearsal.

## Rehearsal and migration order

Rehearse the exact reviewed additive SQL on a proven isolated copy under separately authorized data-handling safeguards. Synthetic implementation tests do not replace rehearsal of the actual working schema. Verify before/after definitions, foreign keys, unique/check constraints, installed-schema guards, permissions and preservation of old posted/identity/evidence history.

Apply these new additive files once in order:

1. `022_report_configuration.sql`: three report-configuration tables and five triggers.
2. `023_monthly_budget_revisions.sql`: five budget tables and ten triggers, after 022.
3. `024_report_bundles_periods.sql`: five bundle/period tables and eight triggers, after 022/023.
4. `025_evidence_item_reviews.sql`: three private item-review tables and five triggers, after the existing receipt/draft prerequisites.

The four files intentionally have no hardcoded working database selection. Confirm the target connection explicitly before each authorized apply. MariaDB DDL can commit implicitly; do not promise transactional rollback of a partial migration. On unexpected failure preserve state and stop for a reviewed partial-DDL recovery plan. Do not blindly rerun, drop tables/triggers, disable guards or restore over working history. A working restore requires its own explicit authorization.

## Configuration and acceptance

Deploy only exact reviewed source under a separately authorized change window. Do not weaken existing flags or authorization to reveal a screen. Verify installed-schema fail-closed paths and every financial writer's period enforcement, including ordinary UI flags off. Feature flags remain distinct from schema integrity; this procedure invents no new flag and enables no provider.

Have an authorized accounting participant select actual cash accounts, statement mappings, cash-flow classifications, exceptional allocations, reporting boundaries and any genuine opening/cutover decisions. Do not seed synthetic defaults or infer unresolved client policy. January–December is the initial convention, not independently confirmed Atikha fiscal policy. Unmapped material activity stays incomplete. Legacy Budgets remain approval-unverified; never promote them automatically.

Use normal authorized Admin/Management login and MFA for role-specific post-rollout checks. Verify readable tables/actions, owner-private drafts, Management financial read-only behavior, private evidence denial across direct URLs/JSON/print/downloads, complete fingerprints, approved budgets and frozen identity. Provider extraction remains assistive; real calls need separate provider/secret/network authorization. Exports do not claim a named external-system format.

Do not generate test postings in working financial data. Scope any explicitly approved acceptance records, expected financial effects and correction constraints separately; prefer isolated tests. Closing/reopening, budget approval and report submission require applicable operational authorization and genuine source/configuration state. Never close an incomplete/current month for a demonstration.

## Handoff

Record actual applied SQL/source hashes, outcomes, configuration decisions, preservation and authorized operational observations/limits. Conduct the separate Atikha/client-readiness and adoption pass with appropriate participants. Source completion, synthetic G7, external review, working rollout and regular-use acceptance are different milestones. Forecast redesign, migration-009 diagnosis and backup/health/recovery remain separately scoped.
