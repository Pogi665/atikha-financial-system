# Usability U1-C: payment prototype delivery

**Current disposition:** the user approved the [corrected prototype](usability-u1-prototype-revision.md) without another external review. [U2 planning](usability-u2-plan.md) is delivered; application implementation has not started. The earlier approval-pending statements below are dated evidence, not the current screen gate.

October 7, 2026. **Standalone prototype and focused isolated verification complete; user screen approval and Atikha validation pending.** This is a design artifact, not a deployed payment redesign. U2 has not started.

**Subsequent reviewer findings and completed revision:** ChatGPT's static HTML/all-16-images review identified field-error and saved-navigation gaps not covered by the initial 94 checks. The user subsequently authorized their correction. [Targeted revision delivery](usability-u1-prototype-revision.md) now records the fixes, **124 fresh assertions** and 18 refreshed inspected screenshots. It is the current verification handoff; the initial run below remains dated evidence. Review-summary/account-readability refinements are included. U2 remains unstarted.

## Open and try it

Open [usability-u1-payment.html](prototypes/usability-u1-payment.html) in Edge or Chrome. If VS Code shows source text, press **Ctrl+O in the browser**, select that file under the application's `docs/prototypes` folder, and open it. No server or login is required.

Start with **Record a payment** on the demo dashboard. Enter a synthetic payee, purpose, amount, cash/bank account and counterpart account; Organization operations is the explicit empty project choice. Choose **Review transaction**, then **Confirm and record payment**. Review saves and checks; it does not record or provide Management approval. The Recorded screen replaces the form and opens an exact read-only local journal when requested.

For a prefilled example, expand **Demo examples and failure scenarios**, select Simple payment, and choose Load example. These controls are a review aid, not proposed production controls. Try split allocations, mismatched amounts, withholding, different projects, negative/malformed values, long names or an unavailable saved account. Failure controls simulate save rejection, recording rejection, review expiry, lost responses and failed result checks.

Everything is held in this page's memory. Closing/reloading loses the demo; it supports one active saved demo draft and local recorded snapshots, not production draft storage, sessions or multi-user concurrency. It uses a fixed October 7 accounting date; displayed simulated save/record times use Asia/Manila.

## Delivered interaction

- Distinct Enter, Review and Recorded views with one current status. Optional reference, document simulation and journal preview are disclosed separately. The form has one amount and common project only where the entry is faithfully representable.
- Quick amount edits intentionally update both sides of the simple entry, including correcting invalid typed text. Existing mismatches, opposite-side amounts, multiple allocations and different line projects are never silently normalized. Mode switches preserve IDs, values, sides, projects and evidence allocations.
- Search inputs commit eligible IDs only after mouse selection or highlighted keyboard confirmation. Query cancellation restores the selected label. Required placeholders and unavailable saved selections are preserved; long committed labels and choices can wrap visibly.
- Explicit saving, saved-version timestamps and subsequent unsaved status. Leaving offers Stay, Save and leave, or Leave without the latest edits; save failure preserves contents and does not navigate away. Resume restores the acknowledged version.
- Focused review and truthful ordinary-document coverage; attached unreviewed documents block simulated recording. Partial ordinary support remains permitted. Liquidation and return workflows are not mocked or modified here.
- Deliberate recording with read-only completion, separate book/history lists and exact result access. Recorded payments no longer appear as editable demo drafts.
- An uncertain response pauses editing/navigation and retains the same request identity. Checking its result recovers the original local snapshot, including after a failed check; it does not make another recording request.

## Plugins actually used

The user explicitly requested available plugins be used during system completion. The Product Design and MagicPath skills supplied the scoped workflow. Repository specifications and the existing synthetic baseline supplied the brief; the Product Design context preflight found no saved plugin context.

**UX Pilot:** its read-only flowchart preview returned an unsaved advisory map with 18 nodes and 25 edges for navigation, saving, review, recording and recovery. This informed flow inspection; it is not a screen audit, user validation or a saved UX Pilot design. No design-confirmation widget or pending external action remains.

**MagicPath:** a private project named **Atikha U1-C — Payment flow review**, ID `458535948603514880`, contains three Enter/Review/Recorded frames and 15 content shapes. Canvas readback confirmed 18 shapes in total. Project creation explicitly disabled sharing/public listing. The canvas-open tool returned its ready-to-open result; no connected browser was available to confirm the host rendered it. This board is a screen reference; the local HTML is the functioning prototype. Only synthetic design descriptions were supplied, with no receipts, exports, sessions or production credentials.

One attempted MagicPath note shape rejected unsupported dimensions; it was replaced with a supported rectangle and subsequent readback confirmed the intended board. No unresolved plugin failure remains. No external publishing, generated production code, account changes or messaging to other people occurred.

## Verification and source identification

Initial focused run, before the targeted revision: **94 assertions passed** using headless Microsoft Edge `154.0.4258.62`, at 1366 × 768 and 1920 × 1080. The current [revision](usability-u1-prototype-revision.md) replaces that run for the edited artifact. These are prototype assertions, not accounting/backend tests. The runner is [test_usability_u1_prototype.py](../scripts/test_usability_u1_prototype.py):

```powershell
python scripts/test_usability_u1_prototype.py
```

It uses the existing private Playwright installation and local Edge executable, opens the HTML as a file, starts no PHP server and connects to no database. Detailed results and screenshot hashes are in `.migration-private/u1c-verification/results.json`. The local tool dependencies are prerequisites, not new application dependencies.

The scenario inventory covers visible mouse/keyboard selectors, no matches and cancellation, required empty choices, inline creation/focus, one-amount/common-project editing, invalid review/focus, save and leave success/failure, saved-version resume, faithful loaded complex modes, unavailable records, evidence-link preservation, partial/unreviewed support, review expiry, rejected recording, rapid repeat confirmation, uncertain recovery/failure, recorded read-only access, contextual Back and separate lists. Safety checks found **no external network requests or browser JavaScript errors**. They do not establish actual backend idempotency, authorization, privacy, concurrency, provider availability or cross-session recovery.

The final simple laptop case places the Review button's bottom at **720.5 px** and Confirm and record at **734.5 px**, both inside the 768-pixel viewport. Complex, validation and long-label screens can require scrolling; details remain available rather than being omitted to fit. This is layout evidence, not measured client task completion.

JavaScript syntax, Python runner syntax and `git diff --check` passed. Documentation links and published artifact hashes were checked at handoff. These checks do not extend browser coverage to other engines.

Source HEAD remains `73f7d5baa227b2a8ad133c63f6d881ec30986159`; existing uncommitted documentation/manuscript work remains preserved. Prototype SHA-256: `bf97ad38a8bc3a91d3aa69fcab21ae73908769b06cd71b3e304f6045d07944c8`. The run rechecked all **143 application files** against U1-A's preservation manifest; their bytes remain unchanged. No production PHP/JavaScript/CSS, database, config, flags, migration, working financial record, commit or push changed.

During development, the error summary's focus was initially superseded by scheduled heading focus; this was corrected. A test wait initially conflicted with the prototype's no-eval CSP; the harness now supplies function predicates without weakening the CSP. Visual inspection prompted moving optional details below the main actions. The final run replaces those earlier incomplete runs; no unresolved interaction failure remains. The pre-existing sandbox helper problem remains separate: necessary shell verification completed through runtime-approved execution. No enabled connected browser surface was available, so the authorized isolated Edge harness was used.

## Screenshots for review

Eighteen current captures are provided following the targeted revision. The sixteen initial capture names below were refreshed; additional [partially corrected validation](images/usability-u1-prototype/validation-partially-corrected-1366.png) and [saved-draft preservation](images/usability-u1-prototype/saved-draft-preserved-1366.png) captures demonstrate the corrected paths. Laptop names denote a 1366 × 768 viewport; negative/malformed and long-label screenshots capture the full page. Desktop names denote 1920 × 1080, with the withholding capture extended to show the full journal. Current images were visually inspected; their hashes belong to the revision run, not the original results file.

- Enter: [blank laptop](images/usability-u1-prototype/enter-empty-1366.png), [filled laptop](images/usability-u1-prototype/enter-filled-1366.png), [filled desktop](images/usability-u1-prototype/enter-filled-1920.png).
- Review: [laptop](images/usability-u1-prototype/review-1366.png), [desktop](images/usability-u1-prototype/review-1920.png), [withholding desktop](images/usability-u1-prototype/withholding-review-1920.png).
- Recorded: [laptop](images/usability-u1-prototype/recorded-1366.png), [desktop](images/usability-u1-prototype/recorded-1920.png), [exact local journal](images/usability-u1-prototype/exact-record-1920.png).
- Recovery: [uncertain outcome](images/usability-u1-prototype/uncertain-1366.png), [original result recovered](images/usability-u1-prototype/recovered-1366.png).
- Boundaries: [validation](images/usability-u1-prototype/validation-1366.png), [unsaved-leave dialog](images/usability-u1-prototype/unsaved-leave-1366.png), [negative opposite side](images/usability-u1-prototype/negative-1366.png), [malformed opposite side](images/usability-u1-prototype/malformed-1366.png), [long labels](images/usability-u1-prototype/long-labels-1366.png).

## Next review and limits

The user can now judge whether the starting page, fields, Review meaning and Recorded outcome are easier to understand. Suggested walkthrough: record one simple synthetic payment without coaching; explain whether Review records it; edit after saving and leave; recover an uncertain response; locate the exact result. Record actual hesitations and coaching needed when that walkthrough happens. It has not happened yet and is not claimed as passed.

Bring the prototype, specification, this report and screenshots to the external reviewers. Screen approval precedes a separately planned/authorized U2 payment slice. U2 must implement actual state/field adapters, protected exact-journal access and affected ordinary/advance/correction regression checks. U3–U5 and original Stages 4–7 remain retained.

Atikha walkthroughs, working acceptance, restoration/protected-file verification, Chrome/Firefox/Safari parity, full accessibility validation and physical device testing remain pending. U-PERF-01's advance-comparison delay remains a separate diagnostic task; this prototype neither measures nor fixes it.
