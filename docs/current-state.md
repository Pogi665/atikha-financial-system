# Current state

Updated October 25, 2026. This is the canonical live handoff. Rewrite it when truth changes; do not prepend a timeline.

## Source and review

- Branch: `main`. Local HEAD and cached `origin/main` at this task's preflight: `8700657a06b84f067612b507196c36fffe76329e` (`Restore login and MFA appearance`). The current local workspace is authoritative; live remote state was not needed for this task. Preflight had only the supplied untracked `assets/img/auth/` originals, with no unrelated local edits.
- Stages 1-7 source and the Refined Client Experience were implemented and pushed in the preceding `4d59ff176edde0b74324093aec0a5aa90786188a` (`Redesigned`) checkpoint. Its final external exact-source/browser approval, including bounded wording/UTF-8 fixes, is recorded in the [approval provenance](presentation-redesign-review-approval.md).
- Project-memory/repository-hygiene cleanup is committed and pushed at `984c014`, not an uncommitted task. Its [delivery](memory-hygiene-delivery.md) remains dated execution evidence.
- The approved Login/MFA split-layout restoration is committed in `8700657`; its [delivery](auth-appearance-restore-delivery.md) records the earlier validation. The user-authorized auth photo-carousel refinement is now implemented locally and uncommitted: restored split layout/right forms, local community imagery, dark overlay, fixed state-specific copy and changing photo captions. Originals are preserved; derived WebP copies and auth-only CSS/JS provide progressive loading, crossfade, pause and reduced-motion behavior. See [delivery, source proof and screenshots](auth-photo-carousel-delivery.md).
- Carousel verification: 173 isolated browser checks, 22 source/preservation checks, PHP/JavaScript syntax and whitespace checks pass. Every PHP block, existing inline functional script and complete right form panel remains byte-identical to the restored task baseline. This bounded refinement is not implicitly covered by the earlier redesign review; external review is pending.

## Pending acceptance and deployment

- Fresh redesigned human usability walkthrough: pending. The [protocol](presentation-redesign-human-protocol.md) is prepared, not executed. Old Stage 4-7 G7 remains paused/incomplete and does not establish redesigned usability.
- Working rollout/configuration of the remaining source/redesign: not performed. The [rollout procedure](presentation-redesign-rollout.md) is unperformed. Earlier stage deployment does not establish present full-system rollout, schema health or client configuration.
- Real provider acceptance: not performed for this delivery. Auth presentation checks used private synthetic sessions, a disconnected database fixture and mocked reset/resend JSON; no real login/OTP delivery, provider activation or client data was used.
- Atikha/client acceptance: not claimed. Actual screen-reader/physical zoom and broader browser/device acceptance remain unperformed as bounded in the [redesign delivery](presentation-redesign-delivery.md).

## Next action and authority

Review the bounded auth-carousel package, then conduct the fresh guarded redesigned human usability walkthrough under its protocol when authorized. It has not started here. Keep assistance/failures honest. No commit or push was performed. This handoff does not authorize working rollout/configuration, real provider calls or financial posting.

Read relevant [decisions](decisions/README.md), then [client context](client-context.md) when applicable. Detailed execution belongs in deliveries/history; [archived memory](history/README.md) and frozen review packages retain their original dates and bytes.
