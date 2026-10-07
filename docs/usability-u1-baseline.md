# Usability U1-A: current-workflow baseline

October 7, 2026. Inspection and measurement under the user's authorization to start **U1-A only**. This report records the existing system; it does not implement the simpler screens. U1-B specifications, U1-C prototype and U2 application changes remain separate tasks.

## Findings that should drive the screen specification

The user's concerns have concrete support in the captured workflows. A simple payment requires entering the same amount twice, exposes three project selectors, and appends Review beneath the editing and supporting-document sections. The recorded result still leaves the editing form on screen. Navigation starts from an accounting book rather than a payment task.

The clearest defect is contradictory transaction status: after successful posting, the page simultaneously says **“Draft saved. No financial entry has been posted.”** and **“Posted journal #186” / “Entry posted.”** Reopening that posted draft clears the old message. Editing after Review also leaves the earlier “Draft saved” message above a label correctly saying “unsaved changes.” These are observed presentation defects; posting and dirty-state checks are separate concerns.

The isolated advance comparison also has a persistent delay: repeated small-dataset median HTTP response was **5.706 seconds for Admin** and **11.637 seconds for Management**. Most browser navigation time was spent waiting for the first byte. A loading message alone cannot remove that server response delay. No backend performance fix was attempted during U1-A.

## Scope, source and environment

- Actual inspected/captured HEAD: `73f7d5baa227b2a8ad133c63f6d881ec30986159` (`Fix error`). The earlier planning document named `7476316`; the newer commit changes documentation only. Application source still has a separately recorded manifest.
- Application manifest SHA-256: `348f3f0cb6e3005edb9a640398b8756222eec9bd50134e037de86da7addf8548`. It hashes a sorted JSON mapping of relative root PHP files excluding local configuration/connection secrets, plus files under `includes`, `assets` and `migrations`. The capture runs verified that this source set did not change.
- Runtime: PHP 8.2.12, MariaDB 10.4.32, Microsoft Edge 154.0.4258.53 through the existing Playwright harness. No browser or Python library was installed.
- Captures: 1366×768 laptop and selected 1920×1080 desktop states. These are headless Edge captures, not Chrome, assistive-technology or client walkthrough results.
- Server: isolated PHP built-in development server, one worker, localhost ephemeral port. This is **not a measurement of working XAMPP Apache**.
- Complete installed 019/020/021 synthetic schema; Stage 1/2/3 flags enabled in the isolated copy, OCR disabled. Configuration, sessions and evidence storage were independent. Non-local browser HTTP requests were blocked; no live Gemini/SMTP extraction was exercised.
- Dataset A began with 154 journals, 417 lines, 169 drafts, 29 advances, 60 advance operations, 14 operation reversals, 42 corrections, 48 staged images and 40 posted evidence associations. Correction integrity and per-control-account reconciliation returned no errors.
- Financial captures added synthetic ordinary receipt/payment and one connected advance lifecycle only in that new disposable database. No working-data clone, working draft/receipt operation, migration, activation, commit or push occurred.

The connected browser inventory exposed no enabled browser surface. The approved fallback was the existing isolated Edge harness. Default sandbox execution still failed before command launch; necessary commands ran with runtime approval. The Windows sandbox issue remains unresolved and is not an application defect.

The first capture helper stopped before evidence upload because Pillow was unavailable. Its completed timing samples and earlier captures were preserved. A focused continuation generated synthetic proof with the existing PHP GD extension and completed liquidation/return; application code was not changed to resolve the helper problem. A private inspector's initial incorrect account-column lookup was also corrected before fixture validation. Subsequent follow-up attempts needed a scoped My Drafts locator and waits for navigation/draft loading; the final follow-up completed without failures or console errors, with unchanged application source and empty integrity/reconciliation error lists. These helper failures are not counted as passing application checks.

## Journey inventory and counting method

Counts below describe **logical committed field actions and progression/navigation controls**, not every keystroke, combobox query, scroll, automated page visit or mouse click. They are observed paths with pre-existing eligible accounts/parties and default date/project. They do not measure how quickly an uncoached person finds the controls. Optional creation, evidence and split allocations add work when needed; no percentage reduction is invented.

### Ordinary payment and receipt — Admin

The payment path was Dashboard → sidebar **Disbursements (CDB)** → **New cash payment**. That is two navigation controls and two page changes to reach the entry form. The receipt book exposes its corresponding **New cash receipt** action; its captured form path started from that book. Both sidebar destinations were verified in source; they are not direct entry links.

For a PHP 1,000 single-allocation payment, six field actions were needed with defaults retained: select payee, enter purpose, select cash/bank account, enter cash amount, select counterpart account, enter counterpart amount. Review and Post require two progression actions. A separate Save is **not** necessary: Review already saves before validation. The six-action receipt form mirrors this with a payer and credit counterpart.

The payment capture also exercised an invalid empty Review, Back to editing, a later purpose edit, cancellation of dirty navigation, fresh Review, posting, and reopening the posted draft. These diagnostic actions are excluded from the straightforward-path count. The successful receipt posted separately in isolation.

Observed screens:

- Empty payment document height: 1,641 px at laptop size, 1,266 px at desktop size. At the laptop starting viewport, allocation and progression controls are below the fold.
- Reviewed payment: 2,085 px laptop, 1,718 px desktop. The editing form and supporting documents remain above the journal preview and Post action.
- Recorded payment: 1,823 px laptop, 1,456 px desktop. The editing/document controls become read-only, but the earlier unposted status remains until reopening.
- Three project inputs are visible even for Organization operations: Default project, Cash line project and allocation Project. They have different data responsibilities; reducing their presentation later must preserve actual line tags.
- Optional supporting documents occupy a full card even without an attachment. Missing ordinary evidence is truthfully disclosed and does not block posting.
- Success links open the all-date book and Journal History lists, not this exact transaction. Finding the actual record requires a subsequent search/list selection and View action.

### Complex ordinary entries — Admin

- A withholding/split-project payment was reviewed with PHP 4,500 expenditure debit, PHP 500 liability credit and PHP 4,000 bank credit. It required Advanced debit/credit, Add line and an explicit expense-line project. The expense and cash tags differed intentionally. The coverage denominator remained PHP 4,500, not PHP 4,000. This draft was not posted during U1-A.
- An equipment payment reviewed successfully using a noncash Asset counterpart. “Payment” must not be simplified into “Expense” as an exclusive account meaning. This draft was not posted.
- General Journal begins with two manually entered account rows, debit/credit amounts and line projects. A PHP 100 internal transfer was reviewed with petty-cash debit and bank credit, the explicit Internal cash transfer entry kind and No party. It was not posted. Its dedicated accounting meaning must remain visible.
- A loaded split draft retains its lines, amount sides and distinct project tags. An interface shortcut cannot silently collapse it into the single-allocation form.

### Saved drafts and unsaved edits — Admin

My Drafts displays owner-private rows with Entry type, Last saved from/to, accounting date and **Last saved (Asia/Manila)**. The captured synthetic list had 44 rows, illustrating the long mixed ordinary/advance/correction list; this is fixture data, not Atikha's workload.

Review saved the payment. Later editing cleared its review panel and labelled the draft unsaved. A native `beforeunload` prompt appeared on attempted navigation; cancelling kept the workspace. The focused follow-up explicitly saved a split draft, edited its purpose again, accepted leaving without saving and resumed it through My Drafts. The saved purpose, PHP 4,500/500/4,000 amounts and distinct line projects returned; the later unsaved purpose did not. The old saved message still remained above the unsaved label while editing. Do not claim that the current application has no unsaved-change protection.

The inspected shared script marks committed financial/evidence changes dirty, clears review tokens on relevant changes, and excludes uncommitted selector search text. Save can retain incomplete drafts; Review invokes posting eligibility. These source contracts remain authoritative. U1-A is not a rerun of all stale-response, ownership, expiry or duplicate-recovery regressions.

### Connected advances — Admin

Observed navigation starts at Cash advances → Release an advance. Six committed field actions with today's accounting date/project retained: employee, purpose, designated control account, due date, cash/bank account and release amount. Review and Post follow. Release proof is optional. Control selection is currently a visible staff decision rather than an inferred default.

The released advance detail already links Liquidate and Return unused cash to the same identity. The capture opened those existing linked URLs, retaining the employee/control context; it did not count every detail-link click as an observed user action. It recorded a PHP 1,000 release, PHP 800 supported liquidation and PHP 200 unused-cash return. Final detail showed **PHP 0 outstanding / settled**, the three journals and reconciled control-account totals.

Liquidation required purpose, expenditure account and expenditure debit. Review without evidence was rejected. One new synthetic document required upload, monetary purpose, confirmed total, accepted amount, line allocation and the manual review checkbox before a successful Review and Post. Some amounts default empty rather than representing one editable common amount. The screenshot with one document and the review was 2,764 px tall at laptop width.

Return required purpose, receiving account and amount. Review without proof was rejected. A different synthetic document was uploaded/reviewed, **Confirm return proof** saved and bound the return context, then Review and Post succeeded. Those required evidence/confirmation steps are safeguards, not unnecessary clicks to remove. Their sequence and resulting amounts need clearer presentation in U1-B/U3.

The ordinary withholding capture and source inspection establish the gross-cost versus net-cash distinction. A fresh advance-withholding walkthrough is not claimed from that ordinary capture; the existing Stage 2/3 assertions remain dated evidence.

### Corrections and finding records — Admin

An eligible PHP 1,000 payment opened a correction workspace with the original journal, Reverse and replace / Reverse only choice, replacement book, reason, correction date and earlier-date explanation, followed by the full replacement form. The screen is 2,384 px tall at laptop width before a review. A focused follow-up reviewed its PHP 900 replacement with the PHP 1,000 GJ reversal and required reason; that correction was not posted. Original context and explicit correction intent must survive simplification.

A release with effective settlements visibly blocked correction and identified liquidation journal #144 and return #145. Its form/progression controls were disabled. This useful blocker should remain; it can be presented with clearer next actions rather than removed.

Posted advance comparison uses original #183, GJ reversal #184 and replacement #185, with original release PHP 100 and replacement PHP 90. Its chain length is one correction. It displays all three journals and explains gross cash-book activity versus the corrected ledger effect. Comparison is a permitted read, not a new correction posting.

All-date CDB and Journal History rendered successfully in the continuation. CDB retains complete originating entries and gross activity wording. Journal History is a line-oriented list with View for complete journal details. A fresh View action opened the synthetic release's complete lines and permitted correction link. The initial book screenshot was blank because capture happened before render; it is **rejected as visual evidence**, not reported as an empty-book defect. A return-correction draft was also first captured before its busy state finished; that first image is not accepted as a persistent disabled-control defect.

### Management

The Management sidebar omits ordinary entry, private drafts and setup controls. Register/detail/comparison reads remain available. The same settled advance showed full supported balances without private attachment links; a request for My Drafts returned HTTP 403. Journal History's View action for its image-bearing PHP 200 return displayed both journal lines and the confirmed-proof summary without attachment links or correction-authoring controls. The timing comparison target had no optional release proof, so its lack of image URLs alone does not test redaction of an image-bearing correction. The image-bearing settled detail and return journal supply the narrower privacy observations.

Management report interaction, direct attachment-denial requests, flags-off privacy and complete access-control regressions were not rerun. Keep the existing Stage 3 verification separate from these fresh U1 observations.

## Current field and state contracts

Source inspected: `includes/accounting_entry_page.php`, `assets/js/accounting_workspace.js`, `includes/accounting_workspace.php`, `includes/cash_advance.php`, correction draft/posting readers, `includes/nav.php`, My Drafts/register/Financial Records templates and scripts. Visible labels and server-requiredness are recorded separately:

- **Date / `entry_date`:** required valid accounting date, initialized to Asia/Manila today. Cash receipts/payments and advances reject future dates. Correction date follows its existing original-date/today bounds; earlier dates require explanation. Saving a draft does not equal posting eligibility.
- **Purpose / `description`:** required nonempty journal description, maximum 2,000 characters. Label “Purpose” currently receives a generic “Enter a description…” error at the top rather than a field-specific required prompt.
- **Reference:** optional, maximum 100 characters. Preserve optionality.
- **Payer/payee / `party_id`:** required for ordinary CRB/CDB; ordinary GJ permits No party. New advance release requires an eligible active person. Linked settlements retain the original employee; they cannot switch accountable people.
- **Project:** Organization operations represents no tag. Default project is a shortcut/context; authoritative accounting tags are cash/line `fund_project_id`. Split tags and client line IDs must survive a presentation change.
- **Cash/bank account and amount:** CRB generates a debit cash line, CDB a credit cash line. A positive actual cash amount and eligible cash account are required; ordinary designated control accounts remain protected.
- **Counterpart accounts and amounts:** eligible active account IDs and exact balanced cents remain server validated. A counterpart may be expense, asset, liability or another permitted classification. Simple CRB uses the credit amount; simple CDB uses the debit amount. Advanced/GJ expose both sides. An existing mismatch cannot be silently repaired.
- **Release controls:** designated account and due date are required. Optional external approval fields are a recorded staff assertion, not digital Management approval; entering any requires the server's complete name/date context.
- **Supporting images:** optional for ordinary entries/release, but any attached image must be reviewed or removed before posting. Liquidation requires complete gross expenditure/asset-debit support; return requires reviewed informational proof and bound confirmation. Accepted/excluded amounts and allocations are separate accounting/evidence meanings.
- **Correction controls:** reason and deliberate intent are required; reverse-only has no fabricated replacement. Replacements and reversal date/book routing retain their approved rules and evidence lineage.
- **Actions:** Save stores a private draft; Review already saves, validates and prepares a journal/token; Post explicitly records it. Back to editing preserves contents and invalidates the review. Posted/recovered/reopened results must be read-only. The current stale status messages conflict with these otherwise distinct states.

## Timing baseline

One request at a time, no parallel test suite, the same valid comparison target per role, fixed server/flags, separate role sessions. Each role had one first load plus five repeats, each measured with a standalone HTTP GET followed by a browser navigation-to-visible-comparison. Thus Dataset A contains **24 completed requests**, not twelve samples measured from the same request. First-load timings are not described as cold-cache timings.

Dataset A, 29 advances / 60 operations / 14 operation reversals / 42 corrections:

- **Admin:** first HTTP 6,043.44 ms; first visible 6,395.92 ms. Repeated HTTP median 5,706.08 ms, slowest 5,824.49 ms; visible median 5,866.34 ms, slowest 6,349.98 ms. Response 16,055 bytes, all HTTP 200. Repeated browser TTFB ranged 5,559.60–6,232.90 ms.
- **Management:** first HTTP 11,612.61 ms; first visible 12,020.15 ms. Repeated HTTP median 11,637.08 ms, slowest 11,798.28 ms; visible median 12,010.54 ms, slowest 12,355.75 ms. Response 12,246 bytes, all HTTP 200. Repeated browser TTFB ranged 11,833.20–12,267.90 ms.

Dataset B used a separate clone of the synthetic A fixture and added valid service-posted releases, fully supported liquidations and confirmed returns until it contained **100 advances, 273 operations, 14 operation reversals, 42 corrections, 369 journals, 847 journal lines, 386 drafts, 190 images and 182 evidence associations**. The approximately 300-operation target corresponds to 287 operation/reversal events here; these are the actual counts, not a claim of 300 ordinary operations. Initial/final correction integrity and control-account reconciliation returned no errors. The same target #183/chain length one remained fixed. Building B finished before B's timing; no other fixture-building or browser suite ran alongside those timing requests.

- **Admin:** first HTTP 6,753.81 ms; first visible 6,338.94 ms. Repeated HTTP median 6,146.42 ms, slowest 6,559.62 ms; visible median 6,276.43 ms, slowest 6,459.05 ms. Response 16,056 bytes, all HTTP 200. Repeated browser TTFB ranged 6,039.00–6,349.10 ms.
- **Management:** first HTTP 12,236.58 ms; first visible 12,544.86 ms. Repeated HTTP median 12,227.25 ms, slowest 12,549.01 ms; visible median 12,520.16 ms, slowest 12,573.12 ms. Response 12,247 bytes, all HTTP 200. Repeated browser TTFB ranged 12,230.70–12,473.10 ms.

Across A/B, **48 measured requests completed with HTTP 200 and no timeout**. The near-constant multi-second delay and approximately twofold role difference warrant profiling, but these observations do not identify its cause. No provider call or artificial latency was injected. Do not substitute the later captured 30-advance fixture for the 29-advance fixture at A's measurement time. The selected 100-advance workload is synthetic, not a confirmed client volume. A 120-second request timeout is an observation ceiling, not a performance target or proof of acceptability. [Per-sample public timing data](usability-u1-timing.csv) contains numeric measurements without sessions or private paths.

## Evidence package and limitations

Selected inspected screenshots are published under `images/usability-u1-baseline/`; every published image uses synthetic accounts, people, purposes and documents, never working receipts. Raw sessions, fixture files, source manifests, logs, additional captures and individual timing samples remain ignored/private.

- [Payment empty, laptop](images/usability-u1-baseline/payment-empty-1366.png) and [desktop](images/usability-u1-baseline/payment-empty-1920.png).
- [Payment review, laptop](images/usability-u1-baseline/payment-review-1366.png).
- [Payment recorded, desktop](images/usability-u1-baseline/payment-recorded-1920.png): conflicting saved/unposted and posted messages.
- [Supported liquidation review](images/usability-u1-baseline/advance-liquidation-review-1366.png).
- [Settled advance](images/usability-u1-baseline/advance-detail-settled-1366.png) and [Management detail](images/usability-u1-baseline/management-advance-detail-1366.png).
- [Release correction blocker](images/usability-u1-baseline/release-correction-blocker-1366.png).
- [Management posted comparison](images/usability-u1-baseline/comparison-management-1920.png).

Full-page screenshots show a fixed sidebar at the browser's capture scroll position; that artifact is not used to assert a displaced sidebar in ordinary viewport use. Additional raw screenshots are not automatically accepted merely because a PNG exists. Initial blank/busy captures are explicitly excluded above.

Final artifact checks verified all nine published image hashes, 24 CSV samples and their four repeated HTTP medians, unchanged application files against the private source manifest, 56 local documentation links and `git diff --check`. Only repository documentation and synthetic evidence were added or edited; no full regression-suite pass is claimed from these artifact checks.

No user/Atikha task success, coaching need, accessibility certification, real OCR accuracy, restore readiness, production response time or whole-system acceptance is established here. The walkthrough and complete feature regressions belong to their separate review/implementation gates.

## Handoff to U1-B

Use the inspected findings to specify direct task access, faithful single-amount/common-project presentation only for representable ordinary entries, distinct editing/review/recorded screens, truthful saved status, field-specific validation and contextual advance/correction next actions. Preserve required verification, authoritative line tags, evidence allocations, immutable history and durable recovery.

Carry the measured comparison delay as a concrete implementation follow-up requiring diagnosis and an approved fix, then repeat the timing protocol in U5. Do not silently expand a visual redesign into unreviewed accounting/service changes.

ChatGPT's reviewer clarification is accepted for the remaining U1 scope: the small safe clickable payment prototype is **required at U1-C**, while external design services remain optional. It has not been built in U1-A. No U1-B design document or U1-C/U2 application implementation is included in this delivery. Original completion Stages 4–7 remain retained.
