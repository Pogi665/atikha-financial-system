# U2-B code-review corrections

Delivered October 14, 2026. The user supplied ChatGPT's exact-source review, identifying two corrections before U2-C. Both were fixed within the existing U2-B scope. Final external sign-off was pending at delivery; the approval addendum below supersedes that handoff. U2-C has not started.

The [correction review ZIP](review-packages/u2-b-corrections-review-20261014.zip) contains final application source, the two-file correction diff, cumulative four-file U2-B diff, changed tests, the report as delivered before the approval addendum, the revision manifest and two focused screenshots. That reviewed archive remains unchanged. Older source/review packages remain preserved as dated evidence.

## Corrections

**1. Blank CRB Split amounts now target the visible Credit/Amount control.** When both allocation amounts are blank or zero in focused Split mode, validation uses the expected counterpart side: CRB credit, CDB debit. The inline message and its `aria-invalid`/`aria-describedby` association belong to that visible input. The summary link points to it and focuses it. Invalid-value targeting and the prior Advanced/GJ rules remain unchanged; both accounting sides are visible in those modes. This changes presentation only, not amount parsing, balance equations or server validation.

**2. Unknown line properties are checked on every line before Split is returned.** The classifier now requires Advanced for unknown fields on any CRB/CDB line, including multi-line payloads. It does not mutate the payload. The new adapter test failed against the old implementation, then passed after the fix.

Application files changed in this correction:

- `assets/js/accounting_workspace.js`: visible expected-side target for blank/zero Split amounts.
- `assets/js/payment_presentation.js`: moves the unknown-field guard ahead of multi-line Split classification and checks every line.

Test files changed:

- `scripts/test_usability_u2_adapter.js`: unknown metadata on the first and second lines of both CDB and CRB; Advanced required, raw payload preserved.
- `scripts/test_usability_u2_b_browser.py`: realistic blank second CRB allocation with valid account/project; visible inline/accessible association, summary destination/focus, explicit zero, Advanced-mode target and correction of the amount.
- `scripts/test_usability_u2_browser.py`: CDB blank Split amount continues targeting and focusing its visible Debit/Amount input.

PHP/CSS, sidebar, server accounting/evidence/privacy rules, database configuration and feature flags were not edited in this correction. No currency/transfer-wording refinement was added. The user retains committing/pushing.

## Focused verification

Fresh guarded copies of the existing complete-schema synthetic fixture were created for separate receipt/GJ/transfer and payment runs. No migration was run. The source fixture and both targets are disposable `atikha_test_stage1_*` databases. Configuration, sessions and receipt files stayed private and isolated; working financial data was not used or modified. External browser requests remained blocked.

Final checks:

- **90 adapter assertions passed** (82 previous plus 8 for multi-line unknown fields and payload immutability).
- **199 U2-B Edge browser/HTTP assertions passed** (189 previous plus 10 focused validation/layout checks). The new CRB reproduction blocks Review, displays the error beside the visible credit amount, associates the visible input, links/focuses it from the summary, handles explicit zero and clears the resolved amount error after correction. Advanced CRB still has a visible Debit validation target. The full run retains GJ/transfers, documents, drafts, recovery, OCR fallback and role/flag checks.
- **124 payment Edge browser/HTTP regression assertions passed** (121 previous plus 3 CDB visible Debit-target/focus checks). Existing payment accounting presentation, evidence, private drafts, recovery and exact-record checks passed.
- Both final browser suites exited successfully with **no uncaught JavaScript errors**, using Edge **155.0.4283.45**.
- Changed JavaScript and Python syntax, plus `git diff --check`, passed.

Commands:

```text
node scripts/test_usability_u2_adapter.js
python scripts/test_usability_u2_b_browser.py --fixture=.migration-private/cp5-clone-8ea4d715696b/stage3-checkpoint5-fixture.json
python scripts/test_usability_u2_browser.py --fixture=.migration-private/cp5-clone-93eadf98cdd2/stage3-checkpoint5-fixture.json
```

The guarded targets were `atikha_test_stage1_u2brcrb20261014a7` and `atikha_test_stage1_u2brcdb20261014b9`. Private run artifacts are `.migration-private/u2b-browser-d0b855dc60` and `.migration-private/u2a-browser-63c1cdf039`; stdout/stderr logs are `.migration-private/u2b-revision-browser.log` and `.migration-private/u2b-revision-payment.log`.

The [revision source manifest](usability-u2-b-revision-source-manifest.json) verifies all 124 captured application files against both final browser copies. Only the two application JavaScript files differ from the initial U2-B manifest; all other application bytes remain unchanged. Test/source hashes and image hashes identify this corrected revision separately from earlier evidence. No application edits followed these successful runs.

The earlier 487 backend, 16 reader, Stage 1/2 and Stage 3 CP4/CP5 results remain dated evidence; they were not rerun or counted as new final-revision results. These two fixes affect focused ordinary validation/classification; dedicated advance/correction branches do not call those routines. The final U2-B suite checks their retained branch boundaries. Server writers/readers and their rules are unchanged.

## Focused screenshots

- [Laptop: blank second CRB Split amount and visible error/focus](images/usability-u2-b-revision/receipt-split-blank-validation-1366.png).
- [Desktop: same validation state](images/usability-u2-b-revision/receipt-split-blank-validation-1920.png).

Both synthetic screenshots were visually inspected and copied byte-for-byte from the final run. They capture the full page at 1366×768 and 1920×1080 viewports, so image height exceeds viewport height. The summary click scrolls to and focuses the visible amount; the screenshot also shows its inline message. This does not establish physical-device, cross-browser or Atikha task acceptance. The original 48 U2-B screenshots remain dated layout evidence; this supplement publishes the two correction-specific captures.

## Handoff

Both exact-source findings are resolved and ready for final reviewer sign-off. Working/client acceptance, U-PERF-01 and later stages remain pending. No working financial mutation, migration, configuration/permission change, commit or push occurred. U2-C still requires its own user instruction.

## External review received

Later on October 14, the user supplied ChatGPT's final corrected-source review: **U2-B approved, with no remaining blocker**. This supersedes the pending-sign-off statement above.

The reviewer reports independently reproducing all **90 adapter assertions**, passing syntax validation on the two application JS files, adapter suite, two Python browser runners and entry-page PHP, and matching all four application SHA-256 hashes to the revision manifest. They inspected the source, correction diff, tests and focused screenshots. These are supplied reviewer results, distinct from Codex's own execution evidence.

The reviewer did not rerun the private database-backed Edge suites. The **199 U2-B / 124 payment** results remain Codex's isolated checks. Read-only hash verification after receiving the review confirms that the current four application files still match the approved manifest. No application tests were rerun or code changed for this approval update.

U2-B source/review is complete. Working-system acceptance and Atikha observation remain separate. The reviewer recommended committing/pushing before U2-C; the user handles those actions. The next checkpoint begins with detailed U2-C planning when requested, then plan review and separate implementation authorization. U2-C implementation remains unstarted.
