# U1-C targeted prototype revision

**Subsequent disposition:** the user approved these corrected screens and declined another external prototype review. Use them as the reference for the [U2 implementation plan](usability-u2-plan.md). The pending-screen-review wording below records the earlier delivery; Atikha observation and working acceptance remain pending.

October 7, 2026. **Requested corrections complete; revised screen approval and Atikha observation pending.** The user's instruction, “Then lets do the corrections first,” authorized this local prototype revision. It did not authorize U2 application work.

Open the [revised payment prototype](prototypes/usability-u1-payment.html) in Edge or Chrome using Ctrl+O. No server or login is required. This file uses synthetic data held only in its tab; reloading or closing clears the demonstration.

## Review findings and changes

ChatGPT's inspection of the original HTML and all 16 screenshots identified two real gaps that the original 94 assertions missed. Gemini's favorable report review did not independently verify those interactions. Both findings were confirmed against source before editing.

- **Validation:** errors now appear beside their affected fields and as clickable summary links. Links focus visible searchable inputs, including opening a collapsed document group when needed. Invalid controls expose `aria-invalid` and associated error text. Correcting a field updates its errors while preserving unrelated unresolved errors; search-query typing alone neither commits an ID nor clears its error. Detailed negative/malformed amounts and their errors survive unrelated edits and saved-draft restoration.
- **Saved work:** starting a payment from Dashboard, a book or the sidebar resumes the saved demo snapshot. Clicking the current payment navigation item preserves the current fields, unsaved edits and review state. Save-and-leave acknowledges and retains the snapshot before navigation. Leaving without the latest edits preserves earlier saved work. Loading a demo example preserves an earlier saved snapshot; Reset demo explicitly clears local state. The prototype still supports one saved draft, not a production multi-draft store.
- **Withholding review:** gross costs/asset purchases **₱4,500**, withholding liability **₱500**, and cash paid **₱4,000** appear together before confirmation. The full balanced journal remains available underneath. This is presentation of the synthetic example, not a change to accounting rules.
- **Account guidance:** account names precede codes, with the explanation “Choose the account for this cost, asset purchase or liability settlement.” Full long labels wrap beneath selectors and in choices; supporting text is now 13 px.

The [U1-B specification](usability-u1-design.md) carries these requirements into U2 planning. No production classifier, state adapter, journal reader, accounting validation or privacy behavior was changed.

## Fresh verification

The final run passed **124 assertions** in headless Microsoft Edge **154.0.4258.62**, using 1366 × 768 and 1920 × 1080 viewports. This is one fresh prototype suite, not a sum of old and new runs or a backend test count.

```powershell
python scripts/test_usability_u1_prototype.py --output=.migration-private/u1c-revision-verification
```

The [runner](../scripts/test_usability_u1_prototype.py) exercises the visible controls, original scenarios and the previously missed paths: individual error persistence/removal, linked focus, query versus selection, detailed invalid fields, saved-error restoration, task-start preservation, Save-and-leave into a book, current-task navigation during editing/review, and grouped withholding amounts. The existing save failure, expiry, rejection, repeated confirmation, uncertainty/recovery, exact local view and faithful mode checks passed again.

All **18 refreshed screenshots** were visually inspected. No horizontal page overflow, JavaScript errors or external network requests were observed. The simple laptop Review button ends at **735 px**, and Confirm and record at **734.5 px**, inside the 768-pixel viewport. Long, invalid and complex screens remain scrollable; their full-page captures preserve all content.

JavaScript/Python syntax, documentation-link checks, source/screenshot hash checks and whitespace checks passed. All **143 application files** still match U1-A's preservation manifest. The tests open a local HTML file, start no PHP server and connect to no database. These results do not establish backend correctness, cross-browser parity, screen-reader conformance, physical-device compatibility or client understanding.

Source HEAD: `73f7d5baa227b2a8ad133c63f6d881ec30986159`, with the existing uncommitted documentation/manuscript work preserved. Tested prototype SHA-256: `4569624a5d9d7e35e79767e7951253a86156cf929a8e643c0da102ad45a2952e`. Private results/source manifests are under `.migration-private/u1c-revision-verification`; they are not public attachments. The original 94-check report remains dated initial evidence. Screenshots at its linked paths have been refreshed and their current hashes belong to this run.

One intermediate harness run stopped because a new navigation scenario left the page on My Drafts while the next test expected Enter. The test sequence was corrected, then the final complete run passed. Necessary shell checks used the established approved fallback for the pre-existing sandbox-helper issue; no permission request or test failure remains pending.

## Screens and plugin reference

The [initial delivery's screenshot index](usability-u1-prototype-delivery.md#screenshots-for-review) now opens refreshed images. Additional evidence: [partially corrected validation](images/usability-u1-prototype/validation-partially-corrected-1366.png) and [preserved saved draft](images/usability-u1-prototype/saved-draft-preserved-1366.png). The [withholding review](images/usability-u1-prototype/withholding-review-1920.png) includes the full journal; [long labels](images/usability-u1-prototype/long-labels-1366.png) also use a full-page capture.

The [Product Design](C:/Users/ACER/.codex/plugins/cache/openai-curated-remote/product-design/0.1.56/skills/index/SKILL.md) and [MagicPath](C:/Users/ACER/.codex/plugins/cache/openai-curated-remote/app-6a883213fb288191aa91f89a5db31f60/2.0.0/skills/magicpath/SKILL.md) workflows were used for this targeted revision. The existing private MagicPath project `458535948603514880` received three synthetic revision annotations; readback confirmed **21 shapes**, including the original three frames. It was not published, reopened as a new project or supplied with private financial data. Canvas visibility in the host remains unverified. No new UX Pilot run occurred; its earlier advisory flow remains dated design assistance. The functioning artifact is the local HTML.

## Next action and boundaries

Review the revised HTML and refreshed images before adopting them as U2's reference. Then plan the separately authorized payment implementation slice. Existing requirements for protected exact-journal access and affected advance/correction regressions remain part of U2 planning.

No production PHP/JS/CSS, configuration, feature flag, migration, working financial record, commit or push changed. Atikha task observation, working acceptance/recovery, U-PERF-01 diagnosis, U2–U5 and original completion Stages 4–7 remain pending.
