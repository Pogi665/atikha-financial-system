# Bounded Login/MFA visual-restoration delivery

October 25, 2026. User-approved two-page presentation restoration complete locally; no commit or push. The fresh redesigned human walkthrough remains pending. This is not working rollout or provider/client acceptance.

## Baseline, target and exact changed-file list

Preflight was clean on main; local HEAD/cached origin/main and read-only live GitHub main matched `984c0140c69ce3a6a145357cdbf99b8a2813d4b5` (`Refactor Memory and Repository Hygiene`). That memory/hygiene checkpoint is committed/pushed; current-state was rewritten accordingly.

Production changes, exactly two:

- `login.php`: remove page participation in shared redesign CSS, restore the earlier feature/form layout, Login animated emerald/teal/slate gradient and fade-slide-up, with current truthful OCR/manual-review, guided accounting, audit/reporting text.
- `mfa_verify.php`: restore earlier gradient security panel/form layout without the nonexistent login-bg.png asset request. Preserve the existing six-digit/expiry/resend presentation and logic.

Documentation changes:

- `docs/current-state.md`: rewrite current checkpoint, bounded result and pending human/rollout state.
- `docs/decisions/007-refined-client-experience.md`: record the user's bounded authentication presentation exception without changing other design contracts.
- `docs/auth-appearance-restore-delivery.md`: this execution record.

Visual source: pre-redesign commit `db15b542a45c3c6e88ee9ccab78e8adc9c700f4c`, login blob `3b726ca213ac930e1f548e6dcfd149fcd81bc032`, MFA blob `6f726d82843d7a4ff9f63d5655d48cd35f7fb8c5`; earlier selected 1366 screenshots in output/system-visual-review. Reused existing Tailwind and logo unchanged. No Tailwind rebuild, shared CSS/JS/layout, service, route or asset change.

## Presentation and accessibility

Wide viewports retain the earlier 60/40 split and 384px form column; the left panel hides below 1024px as before. Login includes its original 20-second animated gradient and 0.6-second fade-slide-up. MFA uses only the old visible gradient fallback, with no missing image. Both pages have minimal page-local focus/reduced-motion/44px input+submit and password-toggle target styles to retain current accessibility while removing global redesign CSS. Password reset stays a Login-page state with its original behavior.

The prior feature-copy/layout contrast is intentional: obsolete forecasting claims were not restored. Current text describes human-reviewed OCR assistance, guided receipts/payments/journals/advances, and tamper-evident audit/reporting. There is no bank-feed, synchronization or autonomous-provider claim.

## Verification and evidence boundary

- PASS PHP syntax for both changed templates; git diff whitespace check.
- PASS exact authentication PHP prefix comparison against pre-change source: includes/guards/session handling, POST validation, CSRF processing, DB/auth/audit operations and redirects unchanged in both files.
- PASS all non-presentation PHP blocks, including view CSRF/error handling, are raw-byte identical. Only stylesheet-version expressions were removed and the decorative copyright year restored. Original CRLF checkout endings were retained for exact comparison.
- PASS all inline functional JavaScript is byte-identical in both files. No presentation-only JavaScript modification was needed.
- PASS source and actual PHP-rendered form/control/field/ID/name/link/CSRF/ARIA contracts match before and after, apart from permitted class/style presentation. Input names/autocomplete, numeric mode/pattern/length, endpoints, hidden CSRF and dynamic scripts remain.
- PASS 72 focused headless installed-Edge browser checks at 1366x768, 1920x1080, 390x844 and 768x1024: original split/form width, responsive hiding, no horizontal overflow, all images loaded and no unexpected network/JavaScript errors.
- PASS normal motion: running gradient background position changes and actual feature-panel PNG pixels differ across captured frames; fade-slide-up has active initial keyframes. Reduced-motion disables both animations and keeps the panel position stable.
- PASS keyboard password toggle retains value/accessible state and 44px target; focus indicator and reset focus remain. Reset error/success/return states and MFA invalid/expired message views render; original resend failure/success focus and 60-second cooldown re-enable after simulated clock advancement. Synthetic session redirect guards retain original destinations.
- PASS all other production/application assets/services/tests/migrations/configuration and previously tracked artifacts remain byte-identical to preflight. Shared Admin/Management shell, workspaces, dashboards and Reports therefore retain their source/assets unchanged. No broad unrelated suite was rerun.

The browser fixture copied the exact two pages and unchanged dependencies into a private owned loopback PHP app. It allowed GET rendering only, used private synthetic session files, supplied a null disconnected PDO variable, blocked external network/send functions and mocked reset/resend JSON in the browser. No database (working or disposable), actual credential authentication, real OTP expiry/SMTP delivery or provider acceptance was exercised. Source equality proves unchanged backend behavior; UI mocks are not represented as a complete authentication integration test. The owned server was terminated. No Apache/MySQL startup, working configuration/data mutation, provider activation or financial action occurred.

An early verifier quoting issue and a selector matching both visible and intentionally hidden reset alerts were corrected in the private harness; production did not require a fix. The final 72-check run passes. No test file in the repository was modified.

## Screenshots and visual inspection

Generated ignored evidence: [gallery](../output/auth-visual-restoration-2026-10-25/index.html), [screenshots ZIP](../output/auth-visual-restoration-2026-10-25.zip). Contains 20 current PNG captures plus two earlier 1366 references, browser results, source-contract hashes, animation evidence and manifest.

Primary captures (all visually inspected at their actual viewport):

- [Login1366](../output/auth-visual-restoration-2026-10-25/screenshots/login-1366.png), [MFA1366](../output/auth-visual-restoration-2026-10-25/screenshots/mfa-1366.png).
- [Login1920](../output/auth-visual-restoration-2026-10-25/screenshots/login-1920.png), [MFA1920](../output/auth-visual-restoration-2026-10-25/screenshots/mfa-1920.png).
- [Login390](../output/auth-visual-restoration-2026-10-25/screenshots/login-390.png), [MFA390](../output/auth-visual-restoration-2026-10-25/screenshots/mfa-390.png).
- [Login768](../output/auth-visual-restoration-2026-10-25/screenshots/login-768.png), [MFA768](../output/auth-visual-restoration-2026-10-25/screenshots/mfa-768.png).

Screenshots show synthetic masked email only; no credentials/session/CSRF data is distributed. The ZIP/gallery are ignored generated artifacts, not permanent Git evidence. Prior frozen packages, archives and selected output/system-visual-review evidence remain unchanged. Actual screen-reader/mobile-device/physical-browser zoom and fresh human usability acceptance are not claimed. User review of these bounded screens and the fresh walkthrough are next; this record grants no deployment, posting or commit/push authority.
