# Planning / Forecasting bar clarity plan

Planning only. Prepared October 31, 2026. No implementation or operational activation is authorized by this document.

## 1. Branch and files inspected

- Branch: `main`.
- Local HEAD and cached `origin/main`: `1ce6d20537235f703c7f887f96caacaf3f845a0b` (`Document successful defense checkpoint`). Live remote was not queried for this local-source planning task.
- Defense tag `defense-success-2026-10-30` resolves to `02a71aee9132ac27c945b259df02aa06e13d82bd`. HEAD differs only by the checkpoint report. No rollback is proposed.
- Preflight: no tracked or non-ignored untracked working-tree changes. Ignored runtime/private artifacts were not inventoried or modified.
- Read context: [project brief](../PROJECT-BRIEF.md), [current state](current-state.md), [decision index](decisions/README.md), [presentation decision](decisions/007-refined-client-experience.md), [deterministic planning decision](decisions/013-deterministic-planning-and-audit-advisories.md).
- Inspected implementation: [page](../planning.php), [scoped stylesheet](../assets/css/planning.css), [selection JavaScript](../assets/js/planning.js), [reader](../includes/planning_query.php), [layout](../includes/layout.php), [presentation asset loader](../includes/presentation.php), and shared `assets/css/executive.css`, `assets/css/atikha_ui.css`, `assets/css/atikha_workspaces.css`.
- Inspected validation/reference material: [pure advisory tests](../scripts/test_predefense_advisories.php), [owner contracts](../scripts/test_owner_review_contracts.php), [pre-defense delivery](final-predefense-oneshot-delivery.md), [working rollout](final-owner-fixes-working-rollout.md). These deliveries remain dated evidence, not new test results.
- Source Git blobs: page `0162c46d8efee53f6ffb5e4c035d06be311e1380`; CSS `ff38018a6e919f71c65afb7a6c9360bb04efd433`; JS `1d7d03bac24956a660365cec6831736bac08b7e6`; reader `6defd3bcff584d4018a4d890152e16a973df28dc`.

## 2. Verified current behavior

- Both Admin and Management use the same Planning page and historical bar component. Admin additionally has eligible allocation selection/private-draft controls; Management does not.
- Bars occur in the collapsed **Recent Expense Trend** disclosure. They represent recorded historical monthly recognized expenses, not forecast confidence, budget utilization or completion percentages. Forecast/recommendation amounts are displayed elsewhere without bars.
- Each row renders month, track/fill, and signed monetary amount. The server computes fill width as `min(100, abs(monthly cents) / peak * 100)`, with peak initially 1 and raised to the largest absolute monthly value.
- `.planning-bar` supplies an 11px pale track. Its child has height, block display and rounded corners, but **no background color**. Width is provided inline in the page. The selection JavaScript does not paint or resize these bars.
- Above 650px the row has three columns: month / bar / amount. At 650px and below, the track spans a second row beneath month and amount. Thus the inspected desktop source does not yet put bars underneath the content at every width.
- The page already cache-busts its scoped CSS using `filemtime`. A Tailwind rebuild is unnecessary for this scoped fix.
- No live application, browser, session, provider or database access was performed for planning. Actual computed styles and screenshots remain implementation-time verification.

## 3. Problem diagnosis

**Verified source defect:** the fill element lacks a paint/background declaration. A sized transparent child leaves only the pale track visible. This is the direct source-level explanation for missing green fill; no forecasting formula or width-state bug was found in the inspected rendering path.

**Likely visible result:** a nonzero fill has width but appears empty. Browser inspection must confirm its computed background and dimensions before editing, including later-loaded shared styles and Management styling.

**Assumptions:** the requested bars are the Recent Expense Trend bars, the only bars in the current Planning page. The approved preference for bars beneath numbers will be applied consistently at desktop and narrow widths. No new forecast-versus-budget chart is inferred. If the owner means a different component or the running page differs from this source, identify that discrepancy before expanding scope.

## 4. Proposed UI/UX fix

1. Retain the existing disclosure and monthly data; do not move other module sections.
2. Place month left and its exact signed amount right on the first row, with the bar spanning the full row below at all breakpoints. Keep DOM reading order aligned with that presentation using a minimal markup reorder if needed.
3. Give the fill an explicit solid emerald background consistent with the current palette, for example `#087b61`. Keep a pale green/slate track, rounded ends and approximately the existing 11px thickness. Verify at least 3:1 graphical contrast between fill and adjacent track.
4. Preserve proportional widths and visible numeric labels. Do not add animation, arbitrary minimum fill widths, gradients, a chart library or decorative KPI cards.
5. Add one concise section-level explanation if needed: bars compare recorded monthly expense magnitudes, scaled to the largest absolute month shown. Preserve signed values; a negative month is a credit/refund effect, not positive spending or progress toward a target. Do not present the bar as budget consumption or forecast certainty.

## 5. Styling / state / rendering changes

- Scope selectors to `.planning-page .planning-history` and its track/fill. Use an explicit direct-child fill selector to avoid affecting labels or shared spans.
- Two-column month/amount header with a full-width second-row track; maintain readable wrapping and `min-width: 0` without page overflow. Avoid conflicting duplicate mobile grid rules.
- Expected template change is limited to label/amount/bar reading order, optional concise scale wording and decorative `aria-hidden="true"` on the visual track. Textual month and exact amount remain accessible. Do not add `role="progressbar"`: these are historical comparisons, not task progress.
- Keep the existing absolute-magnitude width calculation, clipping at 100%, zero-width behavior, empty history and insufficient-history messages. Do not invent values or change signed recognition. Use a signed fixture to confirm that wording prevents misleading interpretation.
- Do not change `planning.js`, `planning_actions.php`, reader/method/budget services, model fingerprints, dates, selection state or permissions. No migration, data change, provider call or configuration change is expected.

## 6. Validation plan

Before implementation, recheck branch/HEAD/status and preserve unrelated work. Inspect the actual component in a permitted read-only working view; do not create synthetic users/sessions or financial fixtures in the working database. If role access is unavailable, report that limit and use an isolated presentation render with the current source for supporting checks.

- Capture before/after Planning screenshots for Admin and Management at 1366x768 and 1920x1080, plus 390px narrow width. Open Recent Expense Trend and inspect the full section, not just the first viewport.
- Inspect computed fill color, track/fill dimensions and final stylesheet precedence in both roles. Positive nonzero values must have visible green fill; maximum absolute month fills the track; other widths remain proportional; zero remains empty.
- Use isolated presentation-only fixtures for positive, zero, negative, very small, equal and large values, empty history and insufficient history. Never modify working financial data to create edge cases.
- Check bars below labels at every viewport; no overlap, clipping, horizontal page overflow or layout shift. Test long month/amount content and 200% zoom/reflow.
- Keyboard-check disclosure activation, visible focus and reading order. Month/amount must communicate the same information without color. Decorative fills must not add misleading progress announcements. No animation is introduced; reduced motion remains unaffected.
- Verify Admin allocation checkboxes, selection summary and private-draft button retain their existing state; Management retains read-only presentation. Do not submit/create a budget as part of working visual verification.
- Compare pre/post read-model results for the same fixed source/clock in an isolated fixture if PHP rendering changes; no financial service edits are expected. Run `php scripts/test_predefense_advisories.php` and `php scripts/test_owner_review_contracts.php` as focused pure contracts after confirming they remain non-mutating. Do not run broad financial/seeding suites for a CSS/markup fix.
- Run `C:\xampp\php\php.exe -l planning.php` if modified; JavaScript syntax checks only if scope is separately expanded to JS. Check CSS validity with available tooling/browser parsing, `git diff --check`, changed-file inventory and unchanged hashes for forecasting/services/shared styles.
- Record screenshots, browser/version, viewport, source identity, actual checks and limitations in a short delivery. Automated visual checks are not human usability acceptance or accessibility certification.

## 7. Manual acceptance checklist

- [ ] Green fills are clearly visible against tracks in both roles.
- [ ] Month and exact amount sit above each bar on desktop and narrow screens.
- [ ] Different values produce understandable proportional comparisons.
- [ ] Zero/negative/insufficient states remain truthful; no completion/budget-use claim is introduced.
- [ ] Disclosure remains compact, readable, keyboard-operable and secondary.
- [ ] Organization forecast, methods, error, approved budget, remaining amount and recommendation values are unchanged for an identical source/clock.
- [ ] Admin-only actions and Management restrictions remain intact.
- [ ] No other page or shared shell changes visually.

## 8. Risks / stop conditions

- Stop for unexpected local drift, a live/source component mismatch, a required global CSS edit, incorrect/non-finite rendered widths, or evidence that the issue originates in financial data/calculations rather than presentation.
- Do not compensate for zero/unavailable data with fake fill. If signed presentation cannot be clarified without changing business meaning, report it before implementation expansion.
- No database writes, migrations, provider activation, authentication changes, protected participant/rehearsal changes, commits or pushes. Do not restart services merely for this planning task.
- Existing current-state documentation describes the checkpoint process in dated terms; Git establishes the actual current baseline above. No unrelated handoff rewrite is proposed in this plan.

## 9. Expected file inventory

- Planning task: only this new `docs/planning-forecast-bars-fix-plan.md`.
- Expected implementation: `assets/css/planning.css` and a minimal `planning.php` markup/wording adjustment. CSS alone is sufficient to restore color; the template change supports correct visual/reading order and truthful scale context.
- After authorized implementation: a short `docs/planning-forecast-bars-fix-delivery.md`; update `docs/current-state.md` only to reflect the completed bounded change. Review screenshots/check output remain private/ignored under the existing artifact policy.
- No new permanent test harness is expected; use focused existing pure contracts and targeted browser checks. No shared CSS, JavaScript, financial service, data, migration, configuration or dependency files are expected to change.

Stop after this plan. Implementation awaits the owner's approval.
