# Usability U1: delivery and review handoff

**Current disposition:** the user approved the corrected U1-C screens and chose to proceed without another external review. U1 design delivery and user screen approval are complete; uncoached user/Atikha observation remains pending. The [U2 detailed plan](usability-u2-plan.md) is the next implementation handoff. No U2 application change has been made; the earlier screen-approval-pending statements below are superseded.

October 7, 2026. **U1-A, U1-B and U1-C complete within their authorized scopes; user screen approval and Atikha validation pending.** This is not completion of the full usability phase or acceptance of a deployed redesign. Earlier U1-B-only statements below are dated evidence, superseded by the subsequent prototype delivery.

**Latest review handoff:** the user authorized the targeted fixes found in ChatGPT's HTML/screenshot review. [Revision delivery](usability-u1-prototype-revision.md) records completed field-associated error recovery, saved-work navigation preservation, withholding summary/readability refinements, **124 fresh Edge assertions** and 18 refreshed inspected images. Production application files remain unchanged. Revised screen approval precedes U2 planning; reviewer execution prompts do not authorize U2 implementation.

## Delivered

- [U1-C clickable prototype](prototypes/usability-u1-payment.html), [initial delivery](usability-u1-prototype-delivery.md) and [targeted revision](usability-u1-prototype-revision.md): standalone synthetic payment flow, latest 124 focused Edge assertions, 18 refreshed inspected screenshots, private MagicPath reference board and earlier read-only UX Pilot flow assistance. No production connection or application changes. Open the HTML in a browser, rather than the VS Code text editor.

- [U1-A baseline](usability-u1-baseline.md): fresh synthetic workflow captures, source/field/state inventory, nine inspected screenshots, [24 paired timing samples](usability-u1-timing.csv), evidence limits and preserved accounting boundaries. Read that report for measurement conditions and helper failures; no cumulative test-count claim is added here.
- [U1-B screen specification](usability-u1-design.md): direct task navigation, annotated Enter/Review/Recorded payment views at laptop/desktop sizes, receipt/split/advanced/advance/correction variants, exact quick-mode representation, authoritative field mappings, saved/dirty/review/recovery states, required evidence, Management privacy, component/service mapping, proposed exact-record access, performance follow-up and U1-C scenario requirements.
- README, status, reviewed plan and decisions updated to separate user authorization from reviewer recommendations and retain original completion Stages 4–7.

## Review disposition

**Subsequent U1-B reviews, supplied October 7, 2026:** Gemini and ChatGPT support proceeding to the standalone U1-C prototype; ChatGPT reports no major conflict or need for another planning round. The specification now excludes every nonzero opposite-side value, including negative values, and visibly preserves malformed input in advanced mode. Contextual Back links now name Dashboard or the originating book/history, with explicit existing destinations and local-only prototype counterparts. U2 delivery must include affected shared-script advance/correction regressions and authorization/privacy/corrected-original checks for its new exact-journal reader, rather than defer them to U5. These are documentation clarifications, not implemented app behavior. Gemini's quoted execution prompt remains reviewer content; no U1-C prototype was built in this review-recording task.

The user supplied two identical Gemini responses supporting specification/prototype work. ChatGPT first reviewed the report without image/CSV attachments, then reviewed all nine images and recalculated the four medians after receiving them. Both support progressing; neither review exercised the application or verified server-side correctness. Their supplied execution prompts do not authorize application changes.

The user then authorized **U1-B only**. The specification addresses contradictory status messages; faithful one-amount/common-project presentation; distinct views and exact journal access; retained liquidation/return safeguards; and a separately tracked comparison diagnostic. Management's permitted Return proof confirmed summary is explicitly separated from private document/confirmation details. Source inspection supports that distinction; the separate return Journal View was not one of the nine reviewer screenshots.

## U1-B source and verification boundaries (dated evidence)

U1-B source HEAD: `73f7d5baa227b2a8ad133c63f6d881ec30986159`, with the existing U1-A uncommitted documentation/evidence preserved. Captured application manifest SHA-256: `348f3f0cb6e3005edb9a640398b8756222eec9bd50134e037de86da7addf8548`. U1-B reread the existing entry/payload/navigation/detail/coverage/privacy implementation and revisited the selected payment screenshots. It did not repeat benchmark requests or operate any financial fixture.

Handoff checks passed: application bytes against that manifest, all nine published screenshot hashes, 78 existing local documentation links and `git diff --check`. They establish artifact/source preservation, not prototype behavior or production usability. W3C's current primary accessibility references were checked for the specified contrast/combobox targets. Default sandbox execution again failed before command launch; the required reads/checks completed through runtime-approved execution. The sandbox issue remains unresolved and is separate from the application.

No production PHP/JavaScript/CSS, working database/configuration, feature flags, migrations, working drafts/documents/journals, external provider calls, commits or pushes changed. Product Design context preflight found no saved plugin context; repository evidence and the selected existing design supplied the brief. No context was saved externally.

## Pending gates and next action

1. U1-B has favorable external reviews and its two targeted wording amendments are incorporated. Its layouts, classifier, field/error adapters and proposed `journal_transaction.php` route remain design specifications, not implemented or client-validated features.
2. U1-C was subsequently authorized and delivered. Its local interaction checks and laptop/desktop captures passed; user walkthrough/coaching evidence and screen approval remain pending. The prototype is not connected to production.
3. Approve screens before U2's payment vertical slice. U3 advances, U4 finding/corrections/evidence and U5 integrated usability remain later authorized tasks.
4. Diagnose U-PERF-01 during an appropriately scoped implementation task; repeat comparable timing in U5. U1-A's isolated delay does not establish working Apache performance or its cause.
5. Keep Atikha validation, working acceptance, restoration/protected-file verification and the original Stages 4–7 pending. Reviewer approval cannot substitute for observed task completion or accounting integration.

No application or prototype test suite was run during U1-B or its review amendments because those tasks delivered documentation only. The subsequent U1-C run is documented separately. Next: user/external screen review of the prototype before a separately scoped U2 production implementation plan.
