# Project memory and repository hygiene delivery

Completed October 25, 2026 under the user-approved documentation/hygiene plan and six implementation clarifications. No application, test, database/schema, migration, financial data, permission or provider change. No service startup, financial/browser suite, renormalization, untracking, deletion, commit or push.

## Baseline and scope

Branch main; local HEAD, cached origin/main and live remote verified at `4d59ff176edde0b74324093aec0a5aa90786188a` (`Redesigned`). Working tree was clean before execution. Documentation/hygiene changes are local and uncommitted. The [current state](current-state.md) owns the next action, not this delivery log.

The old default README + project-status read path contained 232,530 baseline Git bytes. The new first two reads, PROJECT-BRIEF + current-state, contain 5,960 UTF-8 bytes; decisions/client evidence are loaded only as relevant. README is a short introduction/pointer. AGENTS enforces the new reading order and rewrite-in-place current-state maintenance.

## Changed-file list

Modified (6):

- `.gitignore`
- `AGENTS.md`
- `README.md`
- `docs/client-context.md`
- `docs/project-decisions.md`
- `docs/project-status.md`

New (25):

- `.gitattributes`
- `PROJECT-BRIEF.md`
- `docs/current-state.md`
- `docs/presentation-redesign-review-approval.md`
- `docs/repository-artifact-policy.md`
- `docs/memory-hygiene-delivery.md`
- `docs/history/README.md`
- `docs/history/memory-archive-manifest.json`
- `docs/history/readme-legacy.md`
- `docs/history/client-context-legacy.md`
- `docs/history/project-decisions-legacy.md`
- `docs/history/project-status-legacy.md`
- `docs/decisions/README.md`
- `docs/decisions/001-accounting-foundation.md`
- `docs/decisions/002-role-and-privacy-model.md`
- `docs/decisions/003-posted-record-immutability.md`
- `docs/decisions/004-cash-advance-accounting.md`
- `docs/decisions/005-correction-model.md`
- `docs/decisions/006-save-and-close.md`
- `docs/decisions/007-refined-client-experience.md`
- `docs/decisions/008-board-retirement-and-email.md`
- `docs/decisions/009-evidence-and-assistive-ocr.md`
- `docs/decisions/010-financial-reader-scopes.md`
- `docs/decisions/011-reporting-and-period-controls.md`
- `docs/decisions/012-budget-revisions-and-actuals.md`

## Archive verification

Archives were created with Git object reads, not Copy-Item/checkout copies. Each equals its exact baseline Git blob; original paths, blob OIDs and hashes are in the [archive manifest](history/memory-archive-manifest.json). A narrow -text/unspecified-eol attribute preserves these frozen bytes. All original wording, encodings, chronology and unresolved interpretations remain unchanged.

- `docs/history/readme-legacy.md`: 55119 bytes; SHA-256 `7172b2d8cbd69438b06959362816304d68281eb09ccfe3567a902f673db0e02c`.
- `docs/history/client-context-legacy.md`: 20555 bytes; SHA-256 `640764b0e842f4e72f089bb3c04ea4767d63073d684db9df0d8baff906e66af3`.
- `docs/history/project-decisions-legacy.md`: 55594 bytes; SHA-256 `c86f587a24991a1e36a57a7b1ee24560761230e3f124ec48edaa7f932d28a8da`.
- `docs/history/project-status-legacy.md`: 177411 bytes; SHA-256 `dd8076abcf14102f2cac5444567d02c2a372648efdb408a6e88bc3a3434010b8`.

## Validation results

- PASS: only the six approved existing documentation/hygiene files changed; all other previously tracked file bytes match the preflight inventory. New files match the 25-file allowlist above. No deletions or staged changes.
- PASS: four of four archives equal baseline Git blobs and SHA-256/byte-size manifest entries.
- PASS: new/rewritten active-memory Markdown links resolve, including the delivery/approval/history entry points; the decision index names exactly twelve Active records with required structured fields. Frozen historical links retain original path context, explicitly documented rather than rewritten.
- PASS: current-state identifies main/Redesigned baseline, source completion, approved review provenance, pending fresh human work, unperformed rollout/provider acceptance and no Atikha/client acceptance claim. README/status/decisions contain no competing chronological live status log.
- PASS: all requested text extensions resolve text+LF; binary PDF/image/Office/ZIP extensions resolve -text/-diff/-merge; archive exception protects raw bytes. Git diff whitespace check passes. Existing application/other files were not normalized.
- PASS: future Python caches and disposable output are ignored; both permanent visual-evidence directory and descendant exceptions work. All 176 previously tracked output files and 14 tracked bytecode files remain tracked and byte-identical. Ignore rules do not retroactively untrack them.
- PASS: the 68,257,994-byte redesign review ZIP and all other previously tracked artifacts retain their preflight hashes. No frozen package or delivery was regenerated.

Checks used Git identity/status/diff, Git-object and SHA-256 comparisons, local Markdown/decision-index validation, git check-attr and git check-ignore. They did not start services or use client data. No financial or browser tests were necessary for this nonbehavioral task. Private verification output is retained locally; private Codex memory was not updated.

## Discrepancies and attribution

- Earlier committed memory/delivery said final redesign sign-off was pending. The [new approval record](presentation-redesign-review-approval.md) records the user-provided October 24, 2026 final external review outcome, including both bounded corrections and no remaining source blocker. It does not invent an independently retrieved reviewer artifact. Historical pending wording remains unchanged in archives/deliveries.
- Checkout files had CRLF/mixed endings while the committed blobs were LF. Archives use committed blob bytes, not checkout transformations. No repository-wide normalization occurred.
- Some original selected dates and client policy details are not established in retained material. Decision records state date limitations rather than invent dates; curated client-context retains unresolved fiscal/account/tax/funder/approval questions and distinguishes reported observations from confirmed facts.
- Tracked generated caches and the permanent output evidence were already in the pushed baseline. They remain intact; future hygiene rules do not rewrite that history.

## Handoff

Reading flow is PROJECT-BRIEF -> current-state -> relevant decisions -> client-context when relevant -> task-specific plans/deliveries/history only as needed. The old project-status/project-decisions URLs remain compatibility pointers. Current-state is rewritten, not prepended; execution detail stays in delivery/history.

Next major action is the fresh redesigned human usability walkthrough after this cleanup, using the existing [protocol](presentation-redesign-human-protocol.md) and a separately prepared guarded synthetic session. It has not begun here. Working rollout/configuration, real provider acceptance and client adoption remain separate. No commit or push performed.
