# Planning historical bar clarity delivery

Implemented October 31, 2026 under the approved [bounded plan](planning-forecast-bars-fix-plan.md). Awaiting owner review; nothing staged, committed or pushed.

## Baseline and scope

- Branch `main`; HEAD and cached `origin/main`: `1ce6d20537235f703c7f887f96caacaf3f845a0b`.
- Successful-defense tag remains on `02a71aee9132ac27c945b259df02aa06e13d82bd`.
- Pre-existing untracked approved plan was preserved byte-for-byte. No other non-ignored changes were present at implementation preflight.
- Application changes: `assets/css/planning.css` and `planning.php` only. Handoff: `docs/current-state.md`; new delivery: this file.

## Rendering change

The before-render confirmed that the fill was transparent despite a nonzero width. It now uses the existing shared `--ui-brand` green (`#164b3b`), with the approved `#087b61` fallback. The pale track and approximately 11px thickness/rounded ends remain. Fill-to-track contrast is approximately **8.96:1**; this graphical contrast measurement is not accessibility certification.

Each row now reads month, exact signed amount, then the decorative full-width track underneath. A scoped two-column grid applies at every width; obsolete narrow-only grid overrides were removed. The track is `aria-hidden="true"`; no progressbar semantics were added. One short note explains relative historical magnitude and retained credit/refund signs. No animation, minimum fill width, gradient, library or new chart was introduced.

The peak calculation, `min(100, abs(value) / peak * 100)` width expression and signed money formatter are unchanged. All page bytes outside the Recent Expense Trend section are unchanged. Forecast/query/services, dates, fingerprints, budget creation, permissions and selection JavaScript were not edited.

## Verification

- PHP syntax for `planning.php`: passed.
- Scoped CSS parsed successfully with existing PostCSS tooling. Browser computed styles confirmed the intended green and 11px fill/track height.
- Existing pure `test_predefense_advisories.php`: **29 passed**, including four exact forecast benchmarks. Existing pure `test_owner_review_contracts.php`: **11 passed**. Both were run before and after, with identical JSON results (selected method `trend6`, average error `1774.44` on their fixed benchmark history).
- Database-free before/after models for both role renders were identical, including history, forecast, approved budget and source fingerprint. This is fixed-fixture/source-preservation evidence, not a newly queried working-database reconciliation.
- Headless Microsoft Edge: actual Planning template presentation body, original pure money/forecast helpers, unchanged current selection JS and actual styles in their normal load order. A local fixture supplied the model and shell-width wrapper; authentication, database connection, navigation and notification handlers were not executed.
- Both Admin and Management: Recent Expense Trend opened and captured at **1366x768**, **1920x1080**, **390x844**. Inspected desktop/mobile and signed/zero screenshots. Green fills, maximum full width, proportional smaller widths, labels above tracks, readable layout and no page overflow passed.
- Both roles: additional isolated zero, signed/very-small/equal-value, empty-history, insufficient-history and large-value fixtures passed. Zero remains empty; negative amounts remain negative; absolute widths remain proportional. Extremely small positive widths may round to zero browser subpixels; no artificial minimum was added.
- Both roles: **200%-zoom-equivalent reflow** at 683 CSS pixels and device scale 2 passed for the 1366px desktop basis. This was not a manual browser-chrome zoom test.
- Disclosure Enter/Space operation and visible keyboard focus passed. Admin checkboxes still updated the unchanged selection summary; Management rendered no selection/private-draft controls. No budget-creation button was activated.
- No page JavaScript errors in the primary role/viewport matrix. Fixture server accepted only static local GET requests; no application controller, database or external-provider request ran. Test server/browser were shut down after checks.
- Initial edge-fixture assertion incorrectly required an extremely small fill to survive subpixel rounding. The test was corrected to check the positive CSS percentage and permit normal subpixel rounding; no source adjustment was needed. Original explanation is preserved with private evidence.
- `git diff --check`: passed. Existing CRLF checkout warnings are informational; original production-file line endings were preserved and no renormalization was performed.
- Raw SHA-256 comparison: **all 1,155 other tracked files were unchanged before handoff documentation updates**. Final allowed differences additionally include only `docs/current-state.md`. In particular, `planning.js`, forecasting/query/budget services, shared CSS, configuration, migrations and tests remain byte-identical.

## Evidence and limits

Private ignored evidence directory: `.migration-private/planning-bars-20261031/`. It contains before/after template/CSS copies, fixture renderer/browser checks, models, source hash inventory, result JSON and screenshots. These are supporting technical fixtures, not human task outcomes. Main screenshots include `after-admin-1366.png`, `after-admin-1920.png`, `after-admin-390.png`, corresponding Management captures, signed/zero examples and zoom-equivalent full-page captures.

Authenticated working Admin/Management browser acceptance was **not performed**: no signed-in working view was available, and no working session/user was fabricated. Physical browser zoom, real screen reader and non-Edge cross-browser acceptance remain unperformed. The role checks above verify actual template branches and existing selection behavior, not a new authorization-service audit.

No database was accessed or modified, including `atikha_finance`, participant, rehearsal or preview data. No budget was created/submitted; no migration, seeding, provider activation, Apache/MySQL restart or authentication change occurred. Forecast outputs are unchanged for the same inputs/clock by source preservation and the fixed-fixture/pure checks above.

## Final state

The bounded fix is present in the actual local application source. Only the expected production/handoff files and the approved plan/delivery are non-ignored working-tree differences. Review the actual Planning page before approving any Git checkpoint; no commit or push was performed.
