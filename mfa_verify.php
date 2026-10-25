<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/mfa.php';

session_start();

if (!empty($_SESSION['UserID'])) {
    header('Location: dashboard.php');
    exit;
}

$pendingUserId = mfa_pending_user_id();

if ($pendingUserId <= 0) {
    header('Location: login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        header('Location: mfa_verify.php?error=invalid');
        exit;
    }

    $code = trim((string) ($_POST['mfa_code'] ?? ''));

    if (!preg_match('/^\d{6}$/', $code)) {
        header('Location: mfa_verify.php?error=invalid');
        exit;
    }

    try {
        $result = mfa_check_code($pdo, $pendingUserId, $code);

        if ($result !== 'valid') {
            header('Location: mfa_verify.php?error=' . $result);
            exit;
        }

        $stmt = $pdo->prepare(
            'SELECT UserID, FullName, Role, Email
             FROM Users
             WHERE UserID = :user_id AND Is_Active = 1
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $pendingUserId]);
        $user = $stmt->fetch();

        if (!$user) {
            mfa_clear_pending();
            header('Location: login.php?error=invalid');
            exit;
        }

        mfa_clear_code($pdo, $pendingUserId);
        session_regenerate_id(true);

        $_SESSION['UserID'] = (int) $user['UserID'];
        $_SESSION['FullName'] = (string) $user['FullName'];
        $_SESSION['Role'] = (string) $user['Role'];
        mfa_clear_pending();

        log_system_action(
            $pdo,
            (int) $user['UserID'],
            AUDIT_ACTION_LOGIN,
            'Auth',
            null,
            null,
            ['email' => $user['Email'], 'role' => $user['Role']]
        );

        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        error_log('MFA verification failed: ' . $e->getMessage());
        header('Location: mfa_verify.php?error=invalid');
        exit;
    }
}

$errorParam = isset($_GET['error']) ? (string) $_GET['error'] : '';
if ($errorParam === 'invalid') {
    $error = 'Invalid verification code. Please try again.';
} elseif ($errorParam === 'expired') {
    $error = 'Your verification code has expired. Please request a new code.';
}

$maskedEmail = mfa_pending_email();
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login — Atikha Financial System</title>
    <link href="assets/css/tailwind.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="assets/css/auth-carousel.css?v=1" rel="stylesheet">
    <script src="assets/js/auth-carousel.js?v=1" defer></script>
    <style>
        /* Page-local presentation; auth handlers and shared redesign assets stay unchanged. */
        .auth-restored :focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; }
        .auth-restored input:not([type=hidden]), .auth-restored button[type=submit] { min-height: 44px; }
        .auth-restored #toggle-password { min-width: 44px; }
        @media (prefers-reduced-motion: reduce) {
            .auth-restored *, .auth-restored *::before, .auth-restored *::after {
                animation: none !important; transition: none !important; scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body class="auth-restored min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Feature panel: local decorative photos; form logic stays independent. -->
        <section data-auth-carousel data-photo-index="0"
            class="auth-media relative hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between overflow-hidden px-12 py-14 xl:px-16">
            <div class="auth-media__photos" aria-hidden="true">
                <picture>
                    <source media="(min-width: 1024px)" srcset="assets/img/auth/optimized/discuss.webp">
                    <img data-auth-photo class="auth-media__photo is-current" alt="" width="1600" height="994" decoding="async" fetchpriority="high">
                </picture>
                <img data-auth-photo class="auth-media__photo" alt="" width="1600" height="994" decoding="async">
            </div>
            <div class="auth-media__copy">
                <div class="auth-media__state">
                    <p class="auth-media__eyebrow">SECURE ACCESS</p>
                    <h2 class="auth-media__headline">Protecting the Work Behind Every Program</h2>
                    <p class="auth-media__description">A verification code helps ensure that only authorized users can access Atikha’s financial records and reporting tools.</p>
                </div>
            </div>
            <div class="auth-media__bottom">
                <div class="auth-media__caption" aria-live="off">
                    <strong data-photo-title>Collaborative Learning</strong>
                    <span data-photo-description>Strengthening knowledge through discussion, reflection, and shared experience.</span>
                </div>
                <div class="auth-media__footer">
                    <p class="auth-media__legal">&copy; <?= date('Y') ?> Atikha. Authorized personnel only.</p>
                    <button data-photo-pause class="auth-media__pause" type="button" aria-pressed="false" hidden>Pause photos</button>
                </div>
            </div>
        </section>

        <!-- Form panel -->
        <main class="flex w-full flex-1 items-center justify-center px-6 py-12 sm:px-10 lg:w-1/2 xl:w-2/5">
            <div class="w-full max-w-sm">

                <div class="mb-8 flex flex-col items-center text-center lg:items-start lg:text-left">
                    <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-slate-900 shadow-sm ring-1 ring-slate-900/5">
                        <img src="assets/img/atikha-logo.png" alt="Atikha organization logo" class="h-12 w-12 object-contain">
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verify your identity</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        We sent a 6-digit code to
                        <span class="font-medium text-slate-700"><?= htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') ?></span>.
                        It expires in 10 minutes.
                    </p>
                </div>

                <?php if ($error !== null): ?>
                    <div class="mb-6 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 112 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
                        <p class="text-sm font-medium text-red-600">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div id="resend-success" class="mb-6 hidden flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3" role="status">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                    <p class="text-sm font-medium text-emerald-800">A new verification code has been sent.</p>
                </div>

                <div id="resend-error" class="mb-6 hidden flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3" role="alert">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 112 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
                    <p id="resend-error-text" class="text-sm font-medium text-red-600"></p>
                </div>

                <form method="POST" action="mfa_verify.php" class="space-y-5">
                    <?= csrf_field() ?>

                    <div>
                        <label for="mfa_code" class="block text-sm font-medium text-slate-700">Verification code</label>
                        <input
                            type="text"
                            id="mfa_code"
                            name="mfa_code"
                            required
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-lg tracking-[0.35em] text-slate-900 caret-black cursor-text placeholder-slate-400 shadow-sm transition focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/60"
                            placeholder="000000"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        Verify code
                    </button>
                </form>

                <div class="mt-6 flex flex-col items-center gap-3 text-sm lg:items-start">
                    <button
                        type="button"
                        id="resend-code"
                        class="font-medium text-emerald-700 transition hover:text-emerald-800 hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
                    >
                        Resend code
                    </button>
                    <a
                        href="logout.php"
                        class="font-medium text-slate-500 transition hover:text-slate-700 hover:underline"
                    >
                        Back to login
                    </a>
                </div>

                <p class="mt-8 text-center text-xs text-slate-400 lg:text-left">
                    Protected system — access is monitored and logged.
                </p>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            const resendButton = document.getElementById('resend-code');
            const resendSuccess = document.getElementById('resend-success');
            const resendError = document.getElementById('resend-error');
            const resendErrorText = document.getElementById('resend-error-text');
            const codeInput = document.getElementById('mfa_code');

            function hideResendMessages() {
                resendSuccess.classList.add('hidden');
                resendError.classList.add('hidden');
                resendErrorText.textContent = '';
            }

            function showResendError(message) {
                hideResendMessages();
                resendErrorText.textContent = message;
                resendError.classList.remove('hidden');
            }

            function showResendSuccess() {
                hideResendMessages();
                resendSuccess.classList.remove('hidden');
            }

            function startResendCooldown(seconds) {
                const defaultLabel = 'Resend code';
                let remaining = seconds;
                resendButton.disabled = true;
                resendButton.textContent = 'Resend code (' + remaining + 's)';

                const timer = window.setInterval(function () {
                    remaining -= 1;
                    if (remaining <= 0) {
                        window.clearInterval(timer);
                        resendButton.disabled = false;
                        resendButton.textContent = defaultLabel;
                        return;
                    }
                    resendButton.textContent = 'Resend code (' + remaining + 's)';
                }, 1000);
            }

            resendButton.addEventListener('click', function () {
                hideResendMessages();

                const defaultLabel = resendButton.textContent;
                resendButton.disabled = true;
                resendButton.textContent = 'Sending…';

                const requestBody = new URLSearchParams();
                requestBody.set('csrf_token', csrfToken);

                fetch('mfa_resend.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: requestBody.toString(),
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .catch(function () {
                        return { ok: false, error: 'Unable to resend the code. Please try again later.' };
                    })
                    .then(function (payload) {
                        if (payload.ok) {
                            showResendSuccess();
                            startResendCooldown(60);
                            codeInput.focus();
                            return;
                        }

                        resendButton.disabled = false;
                        resendButton.textContent = defaultLabel;
                        showResendError(payload.error || 'Unable to resend the code. Please try again later.');
                    });
            });

            codeInput.focus();
        })();
    </script>
</body>
</html>
