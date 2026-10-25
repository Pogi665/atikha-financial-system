# Authentication community-photo carousel delivery

October 25, 2026. User-authorized presentation refinement, locally complete and uncommitted. No application redesign, working rollout, provider acceptance or fresh human walkthrough is claimed.

## Baseline and exact scope

Main, local HEAD and cached origin/main: `8700657a06b84f067612b507196c36fffe76329e` (`Restore login and MFA appearance`). The restored local templates were the baseline, not an older GitHub copy. Preflight had only the eleven supplied untracked auth JPEGs. Current-state's stale uncommitted-restoration wording was corrected to the observed committed checkpoint. No reset/stash/clean/stage/commit/push occurred.

Modified production files: `login.php`, `mfa_verify.php`.

New production text assets: `assets/css/auth-carousel.css`, `assets/js/auth-carousel.js`.

Supplied originals remain unchanged in `assets/img/auth/` (eleven JPEGs, names listed below). Generated derivatives: `assets/img/auth/optimized/{development,discuss,doc_,image_1,image_2,login_management_board,plants,responsive,stakeholders,training,tree_planting}.webp` (eleven).

Documentation: rewrite `docs/current-state.md`, update the durable auth refinement in `docs/decisions/007-refined-client-experience.md`, add this delivery. Other tracked application, shared CSS/JS, tests, migrations, configuration and historical evidence remain byte-identical. No Tailwind rebuild, new dependency or schema change.

## Photos and captions

All eleven originals were opened/decoded and visually inspected. Pairings use visible interaction/activity only, with the exact user-supplied caption descriptions. No individual is identified and no event/date/location/result is inferred. Captions may repeat: unique captions were not forced where they would invent context. In particular, no reintegration claim was inferred from a cultivation photograph.

- `discuss.jpg` -> Collaborative Learning: Strengthening knowledge through discussion, reflection, and shared experience.
- `responsive.jpg` -> Participatory Planning: Working with communities to identify needs and shape meaningful programs.
- `doc_.jpg` -> Community Dialogue: Creating spaces where experiences and local perspectives can be heard.
- `development.jpg` -> Community Engagement: Bringing people together to share ideas, experiences, and solutions.
- `image_1.jpg` -> Learning Together: Connecting people through training, exchange, and collective participation.
- `image_2.jpg` -> Community Engagement: Bringing people together to share ideas, experiences, and solutions.
- `login_management_board.jpg` -> Community Dialogue: Creating spaces where experiences and local perspectives can be heard.
- `plants.jpg` -> Community Development: Supporting locally rooted initiatives for sustainable growth and resilience.
- `stakeholders.jpg` -> Partnerships in Action: Working alongside local stakeholders to support community development.
- `training.jpg` -> Capacity Building: Developing practical knowledge and skills that communities can carry forward.
- `tree_planting.jpg` -> Working Together: Turning shared knowledge and collaboration into meaningful community action.

Photo provenance is the user's supplied local assets; no hotlinks, AI images or independent licensing/client-evidence claim. Existing PHP GD generated RGB WebP quality 80, maximum width 1600px, aspect ratio preserved/no enlargement. JPEG filenames/bytes untouched. Originals total 6,286,391 bytes; derivatives total 1,622,278 bytes (about 74% less). No EXIF metadata is copied. Asset manifests record exact sizes, dimensions and SHA-256.

## Architecture, text and motion

Both pages load two narrowly scoped auth assets. All new styles are `.auth-media*`; the restored split sizing, right form and existing auth-restored accessibility styles stay. Two absolutely positioned decoded photo layers use centered `object-fit: cover` with a dark emerald/slate overlay. The initial responsive picture provides a stable no-JavaScript desktop photo; the form does not wait for the carousel. A 7-second hold schedules the next photograph, followed by an 800ms opacity fade (about 7.85 seconds start-to-start including transition cleanup). No slide, transform, zoom, parallax, arrows or dots.

The exact approved Login, Secure Access and Account Recovery eyebrow/headline/paragraph remain fixed within their state. Only photos/small captions rotate. A MutationObserver reads existing Login view visibility for recovery copy; it neither replaces handlers nor touches forms/credentials. Recovery success retains recovery copy until the existing return action. Narrow layout keeps the established 1024px breakpoint and downloads no photo images.

First visible photo loads immediately; only the next photo preloads. Remaining photos load one ahead as the sequence progresses. The old verified opaque photo stays underneath until the incoming image loads/decodes and completes its fade; an image failure retains the old picture. Paused/hidden-tab/narrow/reduced-motion states stop future rotation. Reduced motion loads just the first photo and disables transitions; existing form reduced-motion rules remain. The layer dimensions are fixed by the panel, not image intrinsic sizes.

A quiet 44px keyboard-operable Pause photos / Resume photos control is the permitted accessibility exception to no autoplay controls. Automatically updating photo/caption content requires a persistent pause mechanism under [W3C SC 2.2.2 guidance](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide.html). It preserves focus and exposes pressed state; hidden when reduced motion already stops rotation. Decorative images have empty alt/aria-hidden, captions aria-live off. Existing labels, password-toggle ARIA, focus, MFA input semantics and right-side target sizes remain.

## Verification

- Both PHP syntax checks, JavaScript syntax and Git whitespace checks pass.
- 22 source/preservation checks: every PHP block raw-byte identical, both existing inline functional scripts raw-byte identical, complete right-side form panels raw-byte identical; original images unchanged; no unrelated tracked-file change; no staging; only approved new assets/docs. Original CRLF template endings preserved. Form actions/fields/IDs/names/autocomplete/patterns/CSRF, error handling, session/redirect and all auth/security processing remain unchanged.
- 173 focused installed-Edge headless browser checks pass against exact final production bytes. Actual PHP GET rendering uses private synthetic sessions, disconnected null PDO, no real database, external calls disabled, POST routes blocked and browser-mocked reset/resend JSON. No working data/credentials/email/provider traffic, Apache/MySQL startup or financial action. This is UI verification plus source-invariance proof, not real authentication/provider integration acceptance.
- 1366x768,1920x1080,390x844,768x1024: layout, existing split widths/form width, hidden photo panel, no horizontal overflow or broken files/requests. All eleven photos/captions exercised on Login and MFA, full wrap-around, fixed message/right-form position, keyboard pause/resume, dynamic reduced motion and first/next loading boundaries. Each final photo capture waits for actual compositor opacity to settle as well as virtual JS time; early private fixture captures did not and are excluded from delivery.
- Real wall-time rotation and midpoint captures prove approximately seven-second scheduling, 800ms opacity-only transition and a fully opaque old layer under the fading new layer. No white/blank midpoint or layout shift observed.
- Required Login normal/invalid/MFA-send-failure/password toggle, reset required/invalid email/mock error/success/return, MFA normal/invalid/expired/mock resend error/success/60-second cooldown states pass. Existing focus and synthetic session redirects pass. Both narrow MFA expired and reset states remain usable.
- Overlay contrast: conservative analytic brightest-white-image upper bound with minimum overlay opacity yields panel text ratios >=5.56:1 (headlines 6.86, body/captions 6.26). This applies across supplied images/transitions, with actual crops visually inspected. It is not an overall accessibility certification. Physical-device/screen-reader acceptance and fresh full-system human usability remain pending.

Private harness fixes were confined to test tooling: shell quoting, read access to existing Playwright requiring execution escalation, virtual-clock resource timing measurement order and allowing real compositor time for screenshots. They did not change authentication code or working configuration. The owned fixture server terminates after each run. No unrelated broad financial tests were rerun.

## Review artifacts and limitations

[Review gallery](../output/auth-photo-carousel-review-2026-10-25/index.html), [compact external-review ZIP](../output/auth-photo-carousel-review-2026-10-25.zip). Contents: changed source/docs, original+derived images, image/source manifests, full textual source patch against the current task baseline, final browser/source/contrast JSON, screenshots, review README and manifest verifier. Screenshot JPEGs are quality-90, same-resolution non-destructive review derivatives of private lossless PNG captures; PNG source hashes/viewport/state identities remain recorded. No sessions, tokens, credentials, private configuration or full-repository dump is included.

Bounded deviations: the allowed pause control is present for accessibility; image captions repeat where visually appropriate; the seven-second hold plus fade/cleanup is about 7.85 seconds between transition starts. Everything remains local/auth-only. Review package is ignored/untracked, not a commit. Fresh full-system human walkthrough remains pending.
