# Stage 3 checkpoint 2 delivery

Delivered October 6, 2026, following the approved checkpoint-1 delivery. Existing Stage 1/2 and checkpoint-1 changes remain uncommitted and were preserved.

**Checkpoint 2 implementation and focused validation are complete. Stage 3 remains disabled in the working application and its financial correction writer is not implemented.** No working migration, activation, financial posting, OCR request or protected-file change was performed. Migration 021 is unchanged from checkpoint 1 and was used only by disposable test fixtures.

## Delivered

- Private version-4 correction drafts: durable creation keys, owner-only loading/resume, bounded incomplete payloads, revision conflicts, immutable target/mode and explicit ordinary replacement-book switching. The original affected advance is derived on the server. Ordinary and advance routes reject v4; shared upload helpers reject it before storing files.
- `journal_correction.php` reuses the existing entry workspace and visible account/project/party selectors. It shows the immutable original, correction reason/date, generated exact GJ reversal and replacement preview. Reverse-only drafts have no replacement or evidence claim. Original/reversal cancellation and gross cash-book activity are explained separately from corrected ledger effects.
- Admin entry links in Journal History/cash-book details and advance operation timelines appear only when the Stage 3 feature is enabled. My Drafts identifies correction mode/target and uses dedicated resume/discard routes, retaining owner-only results and last-saved Manila date filters.
- New images use existing private unposted reservations. Reused posted images require a valid association belonging to the immediate correction target, a distinct correction reservation and fresh manual review. Previous review values are shown as reference. Changed purpose or declared/accepted amounts require a recorded re-review reason. Original bytes, primary journal, hashes, reviews and allocations remain intact.
- Owner-scoped draft image downloads validate the reservation and source association. Other Admins cannot read the private draft; Management cannot access draft data or image bytes. Existing flag-independent posted-evidence privacy remains in force.
- Replacement validation retains ordinary original inactive-party/project exceptions and narrow linked-settlement exceptions. Replacement advance releases require active employee/project/account references independently. Liquidation coverage counts gross expenditure/asset debits once and excludes the generated control credit. Informational images do not provide monetary coverage.
- Return replacement confirmation binds the correction draft, target, mode, original advance, current return amount, cash account, accounting date and reviewed proof hashes. Changing saved content clears the confirmation; financial review requires a matching current context.
- Remove/discard releases the appropriate reservation type. Only new unposted owner images may be soft-discarded. Deliberately released stale reuse reservations are tolerated only after verifying a valid competing correction for that exact target; unexplained missing reservations remain integrity failures.
- `correction_release_stale_reservations()` is an internal transactional helper for the later writer. It releases only competing reused-image reservations for the successfully corrected target, preserving drafts, entered values, private uploads and unrelated reservations. It is not exposed as a repair action and is not yet wired to a production correction writer.
- Stale drafts explain that the target has already been corrected, disable review and link to the permitted posted chain. A posted correction draft reopens its actual immutable bundle and owner-only snapshot. Posted financial chain summaries remain available without exposing private evidence to Management.
- Read-only inspection now checks the correspondence between v4 document payloads and reservations, including the narrowly permitted deliberate stale release. Target derivation validates balance, account references, advance usage, evidence provenance and original image bytes; it does not repair historical records.

Main additions are `includes/correction_drafts.php`, `journal_correction.php`, `journal_correction_actions.php`, `journal_corrections.php` and the two checkpoint-2 test scripts. Shared workspace, attachment, draft-list and advance preview adapters retain existing ordinary/v3 behavior.

## Validation

The final backend suite used new disposable database **`atikha_test_stage1_s3c2d20261006`** and exited successfully. It passed **277 assertions**: **59 checkpoint-2**, **49 checkpoint-1**, **73 Stage 1** and **96 Stage 2**. The prior suites run before 021; checkpoint-1 fixtures then establish valid posted correction chains, and checkpoint-2 checks exercise the new draft service against them.

Tests cover durable draft creation, changed-key conflicts, owner privacy, old-route rejection, stale revisions, immutable modes, invalid dates, exact reversal previews, fresh reused-image review, changed-review reasons, distinct reservation types, two-Admin reservation contention, rollback of stale cleanup, preservation of private/unrelated reservations, safe stale discard and detection of unrelated reservation corruption. They also verify gross liquidation coverage, current settlement availability, return confirmation/invalidation, release blockers and active replacement-release references. Draft/evidence operations preserve financial posting hashes.

Test-only fixture builders create synthetic posted records to exercise successful-correction and posted-reopening readers. Those builders are not application correction writers. The expected `fixture audit failure` stderr message comes from the existing audit-rollback test; the suite's PHP exit code was **0**.

The final **Edge** run passed **39 browser/HTTP checks** on a private copied application and disposable database. It verifies visible selector cancellation, comparison layouts, fresh manual review restoration, protected image-byte delivery, lost upload-response recovery, new-image removal, ordinary/advance endpoint rejection, disabled correction posting, Management/other-Admin privacy, My Drafts routing, explicit book conversion, reversal-only layout, supported liquidation preview, return confirmation, stale-target messaging, immutable posted-bundle reopening and feature-off restrictions. It reports no JavaScript errors and no journal-count change across browser actions.

Screenshots at **1366×768** and **1920×1080** confirm the comparison fits without page overflow. The final artifacts are private and excluded from Git:

- `.migration-private/stage3-cp2-final-backend.log`
- `.migration-private/stage1-core-3e328e94a7/stage3-checkpoint2-fixture.json`
- `.migration-private/stage3-cp2-browser-0b1523f975/comparison-1366.png`
- `.migration-private/stage3-cp2-browser-0b1523f975/comparison-1920.png`
- `.migration-private/stage3-cp2-browser-0b1523f975/reverse-only-1366.png`

Changed PHP files passed syntax checks; the affected JavaScript files and Python browser script passed syntax checks, and `git diff --check` passed. Subsequent current-source reader/inspection checks verify target provenance/bytes, posted bundle projection and reservation integrity. Chrome and full integrated Stage 3 browser acceptance remain unverified.

## Working deployment remains unchanged

Read-only working preflight reports **021 not applied**, `schema_ready_for_021=true`, `problems=[]`, `checks_passed=true` and `writes_performed=false`. Working counts remain the checkpoint-1 counts: **2 journals, 4 lines, 1 receipt, 2 drafts and 27 audit rows**, with no advances or posted evidence associations. This is database-readiness evidence, not backup-restoration or working-system acceptance evidence. Local `config.php` was not edited.

Do not enable Stage 3 to use this checkpoint in the working system. The review screen explicitly says posting belongs to later checkpoints; even a crafted `post` request returns HTTP 503 without a financial write. No migration or configuration command is required from the user for this delivery.

## Remaining work

- **Checkpoint 3:** ordinary atomic reversal/replacement writer, posting-time validation, durable successful retries, writer-integrated stale cleanup and full history/book correction navigation.
- **Checkpoint 4:** advance correction writers, new replacement-release identities, dated dependency/balance simulation and correction-aware operational register/aging/reconciliation, including existing Stage 2 settlement writers. The current correction preview adds back the target settlement for current availability; it does **not** certify the final historical bundle. Ordinary Stage 2 posting still uses its existing validation.
- **Checkpoint 5:** integrated Stage 3 validation and final delivery/acceptance matrix, including correction posting/rollback/retry and cross-browser behavior. Earlier deferred Stage 1/2 working acceptance and later financial statements remain pending.
- Working-data restoration rehearsal, explicitly authorized migration 021, activation and working acceptance remain pending.

After all checkpoints pass, take a fresh post-020 database/application/configuration/protected-file backup and rehearse restoration/migration on a disposable copy. DDL may implicitly commit; stop and inspect/recover on an error rather than rerun a partially applied migration. Never rerun 015, 019, 020 or an already-applied 021 on the working database. A disabled UI flag does not undo schema or posted history. Record any deferred recovery rehearsal as unverified.

## Reproduce

From the application folder, with a **new** disposable database name:

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint2.php --database=atikha_test_stage1_s3c2_newrun
```

The suite prints its private fixture path. Use that path for isolated browser checks:

```powershell
python scripts/test_stage3_checkpoint2_browser.py --fixture=".migration-private\<printed-run-directory>\stage3-checkpoint2-fixture.json" --browser=msedge
```

The browser script copies code into a private app, writes a test-only configuration, uses private sessions, blocks external requests and starts a temporary loopback PHP server. It never copies or edits the working config or working upload directory. It may discard prior browser drafts only for its specified disposable fixture target so repeated runs do not compete for the same original image.
