# Stage 3 checkpoint 1 delivery

Delivered October 6, 2026 against the Stage 1/2 baseline `a5a681b`.

**Checkpoint 1 implementation and focused validation are complete. Stage 3 as a whole is not complete or activated.** Migration 021 was applied only to disposable synthetic databases. The working database `atikha_finance` remains at migration 020; local `config.php` was not edited. No working financial records, receipt files or snapshots were created or changed.

## Delivered changes

- `migrations/021_journal_corrections.sql`: additive correction records, exact reversal-line mappings, advance-operation reversals and correction evidence reservations. Draft context and evidence provenance extensions preserve old rows. Restrictive foreign keys, unique links, JSON/context checks and immutable-history triggers are included. There is no `USE`, data seeding, historical inference, cleanup or foreign-key disabling.
- `includes/stage3_common.php`: absent/partial/complete schema detection, unique-key/constraint/trigger checks, exact mapping and correction-family validation, source-association lineage validation and flag-independent sensitive-family classification.
- `includes/journal_corrections.php`: authenticated financial chain summaries and read-only integrity inspection. Summaries omit draft contents, hashes, private review/approval snapshots and evidence. Version-4 replacement operations are inspected against their correction links; replacement releases use new advance identities while their drafts retain the original advance ID.
- Existing receipt readers use the relevant association's review and journal URL. Primary ownership, file/hash identity and previous review/allocation rows remain unchanged. Historical primary links remain compatible without duplicate attachment rows.
- The attachment endpoint uses trusted active viewer identity and a validated posted association. Management privacy applies across the family and across all associations of an image. Existing books/history calculate coverage internally before removing private attachment details. Generated reversals have a reference-only coverage label.
- Existing receipt-action guards recognize correction reservations. The actual correction draft lifecycle and transactional stale-reservation cleanup writer remain later checkpoints; this checkpoint detects stale/inconsistent reservations rather than repairing them.
- `scripts/preflight_stage3.php`: read-only prerequisite/schema/integrity checks and preservation manifests. It does not load working configuration, migrate, repair rows, process OCR or delete files. `preflight_stage2.php` recognizes legitimate correction-linked evidence and version-4 operations rather than requiring every receipt association to match its primary journal.
- `config.example.php` documents a disabled Stage 3 flag. This is an example-file addition, not working activation.

## Validation results

The final disposable run used `atikha_test_stage1_s3c1c20261006` and passed **218 backend assertions**: **49 checkpoint-1 checks, 73 Stage 1 regression checks and 96 Stage 2 regression checks**. These are the actual CLI suites run for this checkpoint, not the earlier delivery's browser-inclusive totals.

The regression suites run before 021. Checkpoint checks then exercise ordinary posting/recovery and advance release after 021, plus synthetic correction records assembled by a test-only fixture builder. That builder is not a production correction posting service.

Verified cases include:

- Existing financial/evidence/draft/advance/audit counts and original-field hashes match before and after 021. Preflight manifest hashes exclude only newly added nullable draft/evidence columns so schema additions do not produce false preservation differences.
- Complete schema is accepted; a missing immutable trigger produces a partial state and failed preflight. Trigger restoration is tested only on the disposable database. Read-only preflight never recommends blind reruns.
- Posted journal headers/lines and correction/mapping/operation/evidence rows reject forbidden mutations. Existing posted-header-before-line insertion remains supported.
- Exact ordinary reversal/replacement and repeated correction chains validate; a missing mapping is detected and Management access fails closed.
- Reused images retain their primary journal. Original and replacement associations return their own accepted amounts and URLs; guessed unrelated journal downloads fail.
- Replacement releases reconcile a new advance ID independently from the correction draft's original ID. Replacement liquidations retain the original advance and validate both-direction operation/control-line links.
- Management receives permitted financial chain data without private snapshots. Existing book/history responses retain full gross liquidation coverage while hiding images and review details.
- Primary and reused private-image download policies deny Management; active Admin access uses database identity rather than a supplied/session role string.
- A separate reader process with **all three workspace/advance/correction flags disabled** still hides private metadata and downloads while reporting full coverage.
- Eligible correction reservations pass inspection; stale reservations are detected. Existing receipt-action guards reject reserved images. Fixture rollback preserves original records.
- Earlier Stage 2 preflight accepts a committed, valid synthetic correction chain with reused evidence and version-4 operation links.

All 11 changed/new PHP files passed syntax checks; `git diff --check` passed. A separate standalone receipt-reader/download-policy smoke check also passed without loading the correction workspace service, verifying the attachment endpoint's dependency path. Two earlier disposable runs were superseded: the initial narrower run passed, and an expanded run stopped on a test-script function-name error that was corrected before the successful final run.

## Working-system observation

Read-only `preflight_stage3.php --database=atikha_finance` reports:

```text
deployment_state: 021 not applied
schema_complete: false
schema_ready_for_021: true
problems: []
checks_passed: true
writes_performed: false
file_bytes_verified: false
```

The report verifies its selected database and includes counts/hashes. This is deployment readiness evidence, not proof of backup restoration, receipt-byte recovery or Stage 3 working acceptance.

## Explicitly pending

- Checkpoint 2: version-4 private draft lifecycle, correction workspace, explicit evidence reuse/review and return confirmation.
- Checkpoint 3: production atomic reversal/replacement writer, durable correction retries, stale reservation cleanup and history/book navigation.
- Checkpoint 4: operational advance register/status/aging integration and historical validation inside existing settlement writers. Checkpoint 1's read-only inspection is not a replacement for those changes.
- Checkpoint 5: full integrated Stage 3 backend/HTTP/browser verification, screenshots and final delivery/acceptance matrix.
- Working-data restoration rehearsal, working migration 021, activation and working acceptance.
- Earlier deferred Stage 1/2 acceptance and later financial-statement integration.

No new browser UI was delivered in checkpoint 1. Browser screenshots, actual HTTP image-byte delivery and complete correction posting/rollback/retry behavior have not been verified by these checks. Download-policy checks exercise the shared function used by the endpoint; existing authentication/file-handle hash/MIME/no-store protections remain in that endpoint.

## Reproduce and deploy later

From the application folder, the read-only inspection command is:

```powershell
C:\xampp\php\php.exe scripts/preflight_stage3.php --database=atikha_finance
```

The isolated test command requires a **new**, explicitly named disposable database; it never loads the working config or upload root:

```powershell
C:\xampp\php\php.exe scripts/test_stage3_checkpoint1.php --database=atikha_test_stage1_s3c1_newrun
```

Do not deploy or enable Stage 3 at checkpoint 1. After all checkpoints pass, take a fresh post-020 database/application/configuration/protected-file backup and rehearse restoration/migration on a copy. Migration 021 uses the explicitly selected database and DDL implicitly commits; stop on error and inspect/recover rather than rerun.

Working migration and activation require separate authorization. Never rerun 015, 019, 020 or 021 on an already-migrated working database. Turning off a feature flag is a UI rollback; it does not undo schema or posted corrections. If restoration rehearsal is deferred, record that recovery remains unverified.

Existing uncommitted `docs/stage2-delivery.md` changes and `docs/stage3-plan.md` were preserved.
