# U4 combined plan: drafts, transaction inspection, corrections and evidence

Prepared October 22, 2026. **External plan review approved; combined implementation explicitly authorized by the user.** The user supplied ChatGPT's approval of the combined plan with four clarifications, incorporated below and recorded in [reviewer notes](usability-u4-reviewer-notes.md). The user selected one coordinated U4 delivery after U3 approval. The user subsequently replied "yes, i authorize" to the complete A-E implementation envelope. This authorizes its scoped implementation and disposable verification, not working financial operations, configuration/migration changes, commit/push or later stages.

## 1. Baseline and delivery structure

Local HEAD inspected: `b6e9ca8f26e66820f4deeb4c4d2361e7e3cd0302` (`U3-B,C,D`), with a clean working tree before this documentation task. Remote HEAD was not checked during planning. The user supplied ChatGPT's final U3-B/C/D exact-source approval; [the approval record](usability-u3-bcd-approval.md) distinguishes that from full Atikha acceptance. The approved U3 source and its review package are the baseline, not material to redesign during U4.

Governing context: [overall roadmap](usability-completion-plan.md), [client context](client-context.md), [decisions](project-decisions.md), [current status](project-status.md), [U3 delivery](usability-u3-bcd-delivery.md), and the Stage 1/2/3 contracts already implemented in the repository. The proposals below are implementation choices for the user's usability goal, not newly asserted client accounting policies.

Proposed sequence: **one combined plan review -> one explicit U4 implementation authorization -> ordered internal gates -> one final exact-source review package**. The internal names below organize this plan; they do not refer to previously delivered checkpoints:

1. **U4-A: My drafts.** Clear task identity, last-saved context and safe resume/discard.
2. **U4-B: Find and inspect.** Consistent entry points to complete transactions, books and correction history.
3. **U4-C: Correct a recorded transaction.** A dedicated correction presentation, review, result and uncertain-result recovery path.
4. **U4-D: Supporting evidence and Scan receipt.** Clear support/review states and preserved manual handoff.
5. **U4-E: Final integration and handoff.** Cross-workflow, role, gate and affected regression verification against final bytes.

Once the combined scope is approved and authorized, successful internal gates require no further external approval pauses. Fix in-scope defects and continue. A failed or unverified slice cannot be hidden in aggregate counts. Stop for genuine scope, privacy, integrity or environment blockers rather than bypassing them. Implementation stops after the final review ZIP; commit/push remains a separate instruction.

## 2. Intended outcome and source findings

An Administrator should be able to resume the right private task, find an actual posted transaction, inspect its complete journal, and understand a correction before recording it. Management should be able to inspect permitted financial information without acquiring draft, authoring or private evidence access.

Current source already provides substantial behavior to retain:

- My Drafts has owner-scoped reads, Asia/Manila last-saved filters, stale-request protection and separate ordinary/advance/correction routing.
- Financial Records has draft/applied filters, account selection, URL/history state and retained successful results after failures. CRB/CDB complete-entry filtering differs deliberately from Journal History line filtering.
- `journal_transaction.php` reads a specific journal through an authenticated exact-ID reader. Correction history already exposes original/reversal/replacement journals through a schema-backed read contract independent of authoring UI flags.
- Correction services already own eligibility, dependencies, reversal/replacement generation, evidence reuse, proof confirmation, review tokens, idempotent posting and historical advance identity.
- The shared controller currently excludes corrections from the ordinary and advance focused paths. It needs a separate correction presentation path rather than treating version 4 as an ordinary or version 3 advance draft.
- Scan receipt already retains a manual-entry fallback and a General Journal-only handoff, including the existing scalar route. These are not permission to implement itemized OCR or new cash-book handoffs.

U4 improves the connected presentation around those contracts. It does not add an accounting engine, running General Ledger, new approval policy, or new evidence-authenticity claim.

## 3. Expected files and service boundary

Expected presentation files, edited only where required:

- `accounting_drafts.php`, `assets/js/accounting_drafts.js`.
- `financial_records.php`, `assets/js/financial_records.js` and its existing scoped stylesheet if needed.
- `journal_transaction.php`, `journal_correction.php`, `journal_corrections.php`.
- `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, `assets/css/accounting_workspace.css`.
- An optional pure `assets/js/correction_presentation.js` adapter for correction-specific labels, state and exact-money formatting.
- `ocr_expense.php`, `assets/js/ocr_expense.js` and its existing scoped stylesheet if needed.
- Focused synthetic fixtures/runners, documentation, screenshots and sanitized manifests.

Reuse current shell components and task navigation. Do not redesign the sidebar or mobile navigation. Existing trusted active-key behavior stays intact. Scoped CSS needs no Tailwind rebuild; rebuild only if actual utility changes require it, and inspect resulting CSS diffs.

No production reader/query/service change is proposed. In particular, do not change `accounting_query.php`, accounting/advance/correction writers, canonical input, request hashes, review tokens, lifecycle calculations, lock order, schema, grants or feature flags merely to simplify presentation. Existing authorized reads and correction review projections supply the required data. A secondary hash, lineage field or other metadata disclosure may use only values already present in an authorized projection; omit it when unavailable. Do not widen an embedded payload or expose raw objects to obtain it, especially for Management. If source implementation exposes a genuine need for a new response projection, query, endpoint or service change, report a narrow plan amendment before expanding scope.

## 4. U4-A: My drafts

Keep My Drafts owner-private and available through its existing navigation. Align creation/resume labels with the task vocabulary: Record money received, Record a payment, Other journal entry, Release an advance, Liquidate an advance, Return unused cash, and Correct a recorded transaction. Show the persisted draft ID, task type, accounting date, purpose/reason where supplied, and last saved time. Do not invent actor names or workflow facts absent from the response.

The source-book filter remains a **book** filter, not a mislabeled workflow filter. Retain CRB/CDB/GJ semantics and inclusive date boundaries converted correctly from Asia/Manila. Do not add backend search, pagination or workflow filters as a convenience; the current list contract does not provide them.

Resume using the persisted workflow kind and existing destination: version 2 ordinary entry, version 3 dedicated advance, version 4 correction. Never infer an advance or correction solely from its book. Keep the saved mode, target, revision and submission identity; do not convert a resumed draft into another task. Ineligible/stale resources must explain the server response while preserving saved work; no silent rebase or automatic new draft.

Preserve prior successful rows and applied scope on failed or stale loads, distinguish an empty successful list from an unavailable list, and retain entered filters. Validate a response before replacing the list. Discard must name the selected private draft and explain that it does not delete a posted transaction. Use the correct endpoint, revision and CSRF contract. An uncertain discard must not be declared successful; re-query the same identity through existing reads. If discard is confirmed but the list refresh fails, do not revive a working Continue link for that discarded row.

**Internal gate:** owner/cross-owner authorization, all three draft versions, book/date boundaries, committed filters versus failed refresh, stale loads, stale revision, discard success/uncertainty and correct destination tests pass. No read-only navigation changes financial tables.

## 5. U4-B: Find and inspect an actual transaction

Use Find a transaction with Journal History as explanatory context. Remove wording that implies the unfinished running General Ledger is complete. Keep CRB/CDB names and gross activity explanations accurate for both permitted roles.

Preserve the existing Financial Records request validation, account selector behavior, DataTables search/order/pagination, applied filter badges, URL/popstate restoration and last successful snapshot. Do not replace these controls or change search/totals semantics. CRB/CDB match complete entries and show their full lines; Journal History retains its specified line filtering. Corrected originals remain in financial calculations.

From a matched row/modal, offer a clear **View complete transaction** link to the exact journal ID. The complete view shows the accounting date, actual source book, purpose, payer/payee where recorded, reference, all journal lines, exact totals, historical project labels and permitted documents. Current account names and historical snapshots must remain distinguishable. Missing legacy facts remain explicitly unavailable; do not infer timezone, party or project history.

Expose correction entry only using existing trusted availability metadata and role/gate checks. `correction_eligible` in a journal projection is not a complete dependency check: the authoring target service remains authoritative and may identify blockers. Do not promise eligibility before it runs. An unavailable correction should explain the server's actual reason or direct the user to its existing availability check, without inventing a new query. Management receives no authoring action.

Give correction comparison headings exact links to each original, reversal and replacement journal. Maintain chain identity, dates, reasons, complete lines and permitted evidence. Read-only correction history must continue working when authoring UI flags are off but its required schema and authorization remain valid. Preserve useful existing local book/history context without accepting arbitrary return URLs.

**Internal gate:** filtered search -> exact journal -> comparison links resolve the same IDs; totals/search scope/URL state are unchanged; corrected originals remain included; both roles and flags-off read paths pass; missing/inaccessible IDs fail safely without extra data exposure.

## 6. U4-C: Dedicated correction workflow

### Entry, editing and navigation

Use a separate correction-focused presentation branch. Do not widen the existing ordinary `focused` flag or route corrections through the advance adapter. Preserve form IDs, payload version 4, saved draft identity, detailed debit/credit lines, independent project allocations and all correction service validation.

Offer two plainly described modes, retaining their actual accounting meaning:

- **Correct a recorded transaction:** linked reversal plus replacement; the original remains in history.
- **Reverse an entry recorded by mistake:** linked reversal only; this does not record returned cash.

Actual unused-cash returns continue through the dedicated advance return workflow. Show the selected original journal before editing, its identity/book/date/purpose, mode, correction reason and accounting date. Existing backdate explanation and replacement-book controls remain where applicable; no new date restriction or approval requirement is introduced.

Organize editing, supporting evidence, review and recorded result into a coherent flow on the existing workspace. Keep detailed journal controls accessible instead of forcing Quick/Split assumptions onto corrections. Review already saves as needed: do not add a redundant required Save step. Preserve entered values after validation/network failures. A stale draft or changed target must be resolved through the server contract; never silently overwrite, regenerate identity or discard edits.

Navigation derives from trusted correction provenance, including reverse-only: any target advance operation highlights Cash advances; otherwise original CRB -> `crb`, original CDB -> `cdb`, original GJ/unknown legacy provenance -> `financial_records`. Never infer this from the generated GJ reversal, replacement book, `$advanceMode` or the presence of `$advanceConfig`.

Correction mode is immutable once a version 4 draft exists: retain the disabled selector and persisted mode. Never convert an existing saved draft between `reverse_only` and `reverse_replace` or silently regenerate its draft/submission identity. A different mode means explicitly leaving/preserving the old task and starting a separate task through the existing supported contract. Discard occurs only if deliberately chosen by the user. Existing permitted replacement-book behavior remains subject to its current contract; it does not authorize a mode conversion.

Dirty navigation must cover supported book changes, leaving to start another mode/task, sidebar links, back/leave and new-task actions. Offer Save/Stay/Leave only where the existing draft can actually be saved, preserving failures. Stay retains all inputs. During an uncertain financial result, block transitions that could lose or replace the frozen request.

### Eligibility and linked advances

Explain the server's existing blockers: already corrected original, generated reversal, malformed lineage, or effective settlement operations preventing release correction. When supplied, link exact blocking journals/advance details. Do not calculate alternate eligibility in JavaScript, bypass dependencies, or treat Stage 2's UI flag as a prerequisite for otherwise eligible ordinary corrections.

For advance-linked corrections, keep original and replacement advance identities distinct. A corrected release may create a replacement advance; a reversal-only release does not. Liquidation/return corrections retain the existing lifecycle and historical date basis. Never describe these as ordinary payments/receipts simply because they use CDB/CRB. Inactive historical resources remain governed by the existing trusted-target rules.

### Review and proof

Review must make the original journal and full reversal/replacement previews inspectable. Only the original already has a posted journal ID and may have an exact-journal link before posting. Label generated reversal and replacement lines as previews, with their dates and exact debit/credit totals separately; do not fabricate IDs or transaction links. The actual reversal/replacement journals are inserted during posting, and their exact links appear only after a validated successful post/recovery response. Show the reason and server-provided explanation of net effect. Reverse-only must not display an empty replacement as an unfinished required step. Do not sum formatted numbers or fabricate a net balance in JavaScript.

Reuse the existing correction `advance_effect` timeline and its accounting/basis dates. Its version 4 shape differs from U3's version 3 projection; do not substitute the newer adapter or invent before/after values. Gross supported expense/assets and net advance reduction remain separate, including withholding/liability cases.

Reused evidence needs its existing lineage, reservation and fresh-review reason. Reference coverage on reversal-only is not a new evidence-upload requirement. Return replacement proof remains a separate server-owned confirmation. Preserve version 4's stricter invalidation contract: a saved payload change can clear proof confirmation. Do not transplant U3's version 3 rule that unrelated wording may retain confirmation. The server remains authoritative even if the browser shows stale state.

### Recorded result and uncertain result

Before posting, freeze the exact correction financial request: draft ID, revision, submission key, payload, review token and target/mode context. An ambiguous response freezes editing and presents **Recording result unknown** with **Check recording result**. Refresh only CSRF through the existing `accounting_actions.php?action=session`, then replay the same request to `journal_correction_actions.php`. Do not resave, re-review, change submission key, use the advance endpoint or infer success from a journal lookup. Reload recovery must retain the existing durable server identity and must not create another task merely to check the first one.

Validate the actual correction response before showing Recorded: positive correction/target/reversal IDs, replacement ID when the mode requires it, returned primary journal matching reversal/replacement as appropriate, and consistent advance identities for linked operations. The successful response is a bundle, not merely a positive `id`. Reject incomplete or mismatched identities as unverified rather than inventing success.

Provide View original, View reversal, View replacement when present, Compare correction and appropriate advance links. A successful financial response followed by a failed detail read is **recorded, details unavailable**; retry the read without inviting a new financial request. Posted draft reload must use its actual public chain/bundle contract: `posted_result` is not a duplicate of the full posting response. Keep financial identity and secondary detail-read state separate. Starting another correction is explicit and creates a new request only after the prior result is resolved.

**Internal gate:** ordinary CRB/CDB/GJ and advance release/liquidation/return, reverse-only and reverse-and-replace, blockers, historical/partial settlements, staleness, evidence/proof invalidation, navigation provenance, full response validation and real lost-response-after-commit replay pass. Replay must add no second journal, correction, operation or audit write. Preserve the exact financial request across CSRF refresh and failures.

## 7. U4-D: Evidence and Scan receipt

Place support guidance beside the lines it explains. Use plain distinctions between original document, declared document amount, reviewed/accepted support, allocated support, excluded amount/reason and unsupported amount. Preserve exact centavos, support side, existing allocation IDs and review invalidation. Generated control credits and liability credits do not become fake evidence targets. A scanned amount or image upload is not proof of payment, authenticity or approval.

Keep technical metadata available to authorized users in a secondary disclosure; original file access and provenance must remain intact. Correct ambiguous separators/labels without changing stored names, hashes or associations. Do not label unreviewed evidence Verified, silently check review, auto-allocate support, or change reuse/ownership/privacy rules.

Management restrictions remain server enforced across HTML, JSON, modal, print and direct download, including with UI flags off. Hiding fields in CSS is insufficient. Test rendered text, attributes, embedded payloads and download responses for private filename/hash/reviewer/evidence leakage. Never expose owner-private drafts through a new link.

On Scan receipt, preserve original upload, extraction attempt, retry identity, failure/manual fallback and explicitly reviewed General Journal handoff. Describe loading, extraction unavailable, ready for review and posted/discarded states truthfully. The existing scalar receipt route remains valid when the ordinary Stage 1 workspace is unavailable. No new OCR provider, endpoint, extraction policy, automatic posting, itemized OCR, export or advance/CRB/CDB handoff is included.

An interrupted Retry Extraction or Discard response is an uncertain mutation, not confirmed success or permission for a blind second action. Preserve the existing receipt/request identity, block accidental duplicate actions, and re-read receipt/extraction state through existing authorized contracts where permitted before offering another mutation. If the current contracts cannot resolve it, explain the unresolved state without creating a new endpoint/query or assuming success. Cover response loss, state re-read and safe subsequent action in focused tests.

**Internal gate:** declared/reviewed/allocated/excluded presentation, gross support, partial support, lineage/reuse/stale reservation, proof changes and both-role privacy pass. OCR success/failure/manual handoff and retry/discard retain existing server identities and financial results.

## 8. Verification and database safeguards

Implementation starts with a fresh source/status preflight. Preserve unrelated work and pin the actual approved baseline. Verify the test environment is usable through bounded existing checks; do not rerun old recovery helpers or repeatedly start a failing server. The historical recovery passed bounded SQL/login acceptance; this is not a claim that today's processes, data or hardware have been rechecked during planning.

Use only new guarded disposable `atikha_test_*` databases, isolated application/configuration, sessions and protected evidence. Financial tests use clearly synthetic NGO data, never working drafts/receipts. Do not automatically migrate, seed, repair, reconfigure or replace the working database. Do not treat an old recovery archive as today's truth. A test bootstrap must target an empty verified disposable database, never an existing working/schema target. Reuse the established guarded schema/fixture approach; migration 009 diagnosis and normal migration/deployment success are outside this checkpoint.

Block/stub server-side email/OCR/provider calls as well as unexpected browser requests. Browser network blocking alone cannot prevent PHP from making an external call. Fail closed if isolation cannot be established. Keep private outputs, SQL exports, sessions, credentials, MFA and receipt bytes out of public reports/ZIPs.

Verification layers:

1. Syntax and pure presentation/state checks, including exact money formatting, bundle validation, separate workflow dispatch and loading/error/unknown/result transitions.
2. Focused authenticated API/browser tests for each internal slice, including true post-commit response loss on disposable fixtures. For correction families cover ordinary and advance-linked reversal/replacement, not just mocked fetch failures.
3. Admin, Management and second-owner cases; Stage 1/2/3 and independent OCR combinations; complete/partial schemas failing closed; displayed links compared with direct route authorization. Ordinary corrections remain eligible with Stage 2 UI off where existing services allow; advance corrections retain their actual dependencies. Posted readers retain their flag-independent schema/role contract.
4. Read-only page/list/inspection fingerprints proving no financial writes. Explicit draft/discard/post tests have separately expected writes; navigation does not inherit those allowances.
5. Failure matrix: first-load unavailable, successful empty, malformed response, later failed refresh, stale/out-of-order A-B-A responses, invalid dates/accounts, stale revision, expired CSRF, failed detail read, uncertain post, successful duplicate recovery and blocked dirty navigation.
6. Visual/keyboard inspection at 1366x768 and 1920x1080: long purpose/account/project labels, focus/focus return, headings, errors, expanded full journals/evidence, print and scroll reachability. These are named desktop browser results, not physical-device or cross-browser compatibility acceptance.

Run affected established suites against final application bytes: U2 payment and receipt/GJ shared-controller checks, U2-C navigation, exact reader, U3 adapters/B-D/register/privacy readback, Stage 2, Stage 3 ordinary-correction checkpoints 2/3, advance-correction checkpoint 4 and gate/privacy checkpoint 5, and relevant receipt/manual-handoff tests. Inspect each runner's setup before execution. Add focused U4 coverage where current suites do not exercise the new state. Keep substantial financial/privacy assertions when updating obsolete selectors or labels.

Record each actual command, result and scope. `npm test` is a placeholder and is not evidence. Old U2/U3 counts remain dated; do not reuse them as U4 passes. After a defect fix rerun affected checks, then ensure final cross-workflow evidence corresponds to the final production hashes. No arbitrary repeated unrelated testing, invented counts or unreported failed slices.

## 9. Delivery, exclusions and stop conditions

The final handoff must report U4-A/B/C/D gates separately, then U4-E combined integration, exact source identities, remaining failures and acceptance limits. Include a baseline-to-final production patch, exact changed source, test runners/fixtures, safe reproduction instructions, SHA-256 and Git-normalized blob manifest, inspected screenshots and their hashes. Include unchanged pure-test dependencies when needed so the packaged adapter tests are runnable; identify dependencies intentionally supplied by the baseline. Exclude secrets/private fixtures and database exports. Freeze the package only after final-byte checks.

Update README, current status, U4 delivery and overall roadmap with what is source-complete, externally approved, deployed and still unaccepted. Preserve old reports as dated evidence. Provide source rollback guidance to the approved checkpoint without executing reset or changing historical journals. No U4 migration/configuration deployment is planned.

Excluded: U5 acceptance work; the retained U2 follow-up/action-count baseline and U-PERF-01; original Stages 4-7 (running ledger/books, reports, budgets, later OCR/export); migration-009 diagnosis; database/hardware investigation; backup/health routine implementation; new accounting/approval/period-close policy; full Atikha acceptance. A small verified-backup/health/restore checkpoint remains recommended separately. It can be scheduled before heavy future database work under its own scope; U4 planning does not silently implement it or reopen the resolved incident.

Stop and report if preservation/isolation fails, current database startup is unstable, required identities cannot be safely established, a financial/privacy invariant fails outside the approved presentation scope, or a new query/service/schema/policy/writer/configuration change is genuinely required. Do not waive a required invariant to finish the combined stage. Otherwise continue through successful authorized internal gates without repeated permission questions.

**Execution:** the user explicitly authorized the complete A-E envelope. Follow [the active delivery record](usability-u4-delivery.md); successful internal gates continue without external pauses. The earlier planning/approval tasks performed no operational verification.
