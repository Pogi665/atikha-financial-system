# First bounded visual-polish batch

October 28, 2026. Locally implemented; awaiting user visual inspection. No commit, push, deployment or human usability acceptance.

## Baseline and scope

Branch `main`; local HEAD and cached `origin/main`: `1bb6a074f99c42569c88bf73a3d2a54dd3f532d2`. No tracked or non-ignored untracked changes were present before this batch. Ignored walkthrough preparation was preserved.

Nine production files changed:

- `dashboard.php`: remove per-KPI information icons; one concise secondary basis disclosure; display-only attention priority and task-specific actions; compact Quick Action tiles and primary task-start styling.
- `new_transaction.php`: primary payment/receipt tasks, secondary accounting tasks and separate draft continuation, retaining routes and gates.
- `accounting_drafts.php`: compact header shortcuts and a scoped wrapper for responsive styling.
- `assets/js/accounting_drafts.js`: CSS classes only for Continue/Discard controls.
- `includes/nav.php`: accessible rail control, navigation labels/flyout headings and current-parent context.
- `includes/presentation.php`: four decorative SVG icon definitions.
- `assets/js/atikha_ui.js`: local desktop preference, compact flyouts/tooltips, synchronized disclosures and separate mobile keyboard/focus behavior.
- `assets/css/atikha_ui.css`: scoped launcher/draft/sidebar presentation, 72px desktop rail, mobile reflow and focus states.
- `assets/css/atikha_dashboard.css`: compact attention rows, quick tiles and CTA styling.

Handoff documentation: this delivery, `current-state.md` and decision 007. No authentication, service, schema, migration, provider or financial-model source changed. No compiled Tailwind rebuild was needed.

## Behavior preserved

Dashboard projection/queries and KPI values are unchanged. Only a copied attention array is sorted for display; original counts, statuses and destinations are retained. Required errors remain visible. Entry routes and role/feature gates are unchanged. Continue preserves the draft ID/route. Discard confirmation, CSRF, revision checks, owner privacy and unknown-result recovery are unchanged. Removing the added CSS class assignments reconstructs the complete baseline draft JavaScript exactly.

Expanded navigation keeps branding and current parent/child context. The compact rail uses labeled controls and focus/hover tooltips, with group flyouts rather than forcing the full sidebar open. Escape closes flyouts and restores the opener. Mobile stays a separate drawer, with background focus isolation and keyboard wrapping. Reduced motion removes transitions. Presentation preference uses localStorage only and tolerates unavailable storage.

## Verification

- PHP syntax passed for all five changed PHP files; JavaScript syntax passed for both changed JS files; `git diff --check` passed.
- 180 focused Edge checks passed on the actual isolated app at 1366x768, 1920x1080, 768x1024 and 390x844. These cover both roles, KPI cleanup, attention/task routes, all four group chevrons, compact persistence/flyouts/focus/Escape, mobile drawer, launcher hierarchy, draft Continue, cancelled discard, mocked uncertain discard/read recovery, failed-filter preservation, reduced motion and print.
- 38 supplemental checks passed: Space/Enter, hover, outside dismissal, breakpoint preference separation, shared-shell routes (ledger, reports, budgets, advances, setup, users and Review Queue as authorized), direct write rejection and source/preservation contracts.
- Existing redesign shell/dashboard and Stage 1 draft assertions were reused/adapted in a private harness for the current prepared synthetic identities. Old fixed-source/fixture suites were not blindly replayed; their obsolete copy expectations were not edited to claim a pass. No financial suites or migrations were needed for this presentation-only diff.
- Model/auth/guard preludes and role-specific navigation destinations/gates compare unchanged. All protected source/config/evidence files outside the approved production scope matched the preparation preservation manifest before handoff documentation was rewritten.
- 30 PNG captures plus a dashboard print PDF are in ignored `output/visual-polish-2026-10-28/`. Representative full-page desktop, narrow, task-launcher, draft and flyout images were visually inspected. Validation JSON and final preservation information are alongside them.

During verification, the draft header/filter row needed narrow-width containment. The harness also needed correct handling of PHP string `0`, real keyboard modality for focus-visible, and viewport-only capture of an open fixed flyout; full-page capture temporarily resizes the viewport. Compact scrolling was adjusted to retain/reposition the open flyout. These were bounded checks/fixes, not human task results.

## Isolation and preview

The separate owned loopback preview uses only `atikha_test_stage1_s5rehearse4bd8c99474f4`. It has its own app/session directory and `ATIKHA_VISUAL_PREVIEW` cookie, avoiding replacement of the participant cookie across loopback ports. Its guarded connector verifies `SELECT DATABASE()`. The router blocks all non-GET/HEAD requests before controllers; private/direct-upload paths are blocked. SMTP/Gemini settings remain empty, URL fetching is disabled and network/provider PHP functions are disabled.

All 50 rehearsal table fingerprints match their pre-check state. No data was seeded or changed. Neither `atikha_finance` nor `atikha_test_stage1_s5human4bd8c99474f4` was accessed in this batch. The original participant app remains byte-identical to its prepared source pins. No migrations ran. Root application configuration and existing evidence bytes are unchanged. The preview remains available for inspection; private entry URLs are provided in the completion response, not committed here.

## Human evidence and limits

A01 remains paused/incomplete. An append-only pause event preserves its original start; the mutable task index now reflects the pause. No human result was fabricated and no later task was attempted. The original participant environment was not repointed to this source.

This is automated desktop Edge evidence, not a human usability pass or accessibility certification. Real screen-reader use, physical browser zoom, other browsers/devices and client acceptance were not performed. The browser harness blocked external resources, including the existing chart CDN, so Management captures exercise the existing offline chart fallback; chart implementation is unchanged. Financial tables retain their own horizontal scroll regions. The preview is deliberately read-only, so Save/Record/Discard requests cannot persist there.

Next action: user visual inspection. Do not resume the human walkthrough or commit/push automatically.

## Sidebar scrollbar follow-up - October 28, 2026

The user requested hiding the visible shared navigation scrollbar without disabling scrolling. This follow-up changes only `assets/css/atikha_ui.css` in production: scoped standard `scrollbar-width: none`, legacy overflow-style compatibility and Chromium/WebKit scrollbar pseudo-elements hide the track on the navigation and its scrollable child panels. Vertical overflow, widths, gutters/padding, controls, routes and all JavaScript remain unchanged. The existing read-only rehearsal preview was refreshed; the participant app/database were not accessed or changed.

83 focused Edge checks passed at 1366x768 and 1920x1080, including expanded/compact wheel scrolling, keyboard traversal to offscreen items, explicit emulated touch gestures, stable navigation width, all four disclosure hit targets, unobscured compact flyouts, no horizontal scrollbar/page overflow, persisted compact preference and narrow/mobile drawer scrolling/focus/Escape. Overflow was deliberately forced in the browser nav region to test scrolling even where the normal rail fits. Four updated desktop screenshots and `scrollbar-validation.json` are in the same ignored evidence directory. The earlier 218 checks remain dated pre-follow-up evidence, rather than being represented as rerun.

Firefox is not installed in this environment, so its standard CSS treatment was included but not runtime-tested. Physical touchpad/touch hardware was not tested; automated wheel and touch-event pathways passed. `git diff --check` passed. No production PHP/JS, database, provider, permission or route changes; no commit/push. A01 remains paused/incomplete.

## Private preview Review diagnosis - October 28, 2026

The manual payment Review attempt encountered the preview's intentionally broad write block, not a changed payment controller. Review first sends an internal JSON draft `save` POST to `accounting_actions.php`, then requests `review` only after acknowledged saving. The router intercepted the first POST before controller/database code and returned HTTP 405, `text/html; charset=UTF-8`, with the plain literal `This read-only visual preview does not save or record changes.` JSON parsing consequently produced the existing interrupted-response message. The old router did not retain the user's actual POST body/status trace; the request sequence was established from unchanged source and the same guard response was verified with harmless OPTIONS, without replaying the action. No PHP error/warning was present in the preview log. Authenticated GET accounting API responses were HTTP 200 JSON.

All 50 rehearsal table fingerprints still matched their pre-preview state at diagnosis: no draft, audit or journal write occurred from that attempt. The app/probe confirmed only the rehearsal database, with providers disabled. Payment page/controller/workspace JS/service files match HEAD and the preview copy; the visual-polish changes did not alter that request path.

Only the private router and a private policy helper were changed to permit ordinary workspace draft `save` and `review` requests. Existing application authentication, ownership, CSRF and validation still execute. Posting, other mutations and provider sending remain blocked; blocked actions now return structured JSON. Fourteen offline policy cases, private PHP syntax and a harmless live OPTIONS check passed. No HTTP save/review/post was sent by the agent. The live preview required no restart, rebuild or reseed. A user retry may now legitimately save a private synthetic rehearsal draft before showing Review; it still cannot financially post. Existing populated inputs remain in the user's tab if the tab has not been reloaded/closed. The participant environment and human observation outcomes remain unchanged.
