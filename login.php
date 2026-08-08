<?php
session_start();

require_once __DIR__ . '/includes/csrf.php';

if (!empty($_SESSION['UserID'])) {
    header('Location: dashboard.php');
    exit;
}

$showError = isset($_GET['error']) && $_GET['error'] === 'invalid';
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Atikha Financial System</title>
    <link href="assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Feature panel -->
        <section
            class="relative hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between overflow-hidden bg-slate-900 bg-cover bg-center px-12 py-14 xl:px-16"
            style="background-image: linear-gradient(to bottom right, rgba(15,23,42,0.92), rgba(6,78,59,0.82)), url('assets/img/login-bg.png');"
        >
            <div class="max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight text-white text-balance">
                    Securing the future of overseas Filipinos and their communities.
                </h2>
                <p class="mt-5 text-base leading-relaxed text-white/70 text-pretty">
                    Track incoming grants, manage operational expenses, and maintain accountability with our international partners. A secure platform built to support our advocacy for overseas Filipino workers and their families.
                </p>

                <ul class="mt-10 space-y-4 text-sm text-white/80">
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40">
                            <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        </span>
                        AI-powered OCR for instant receipt data extraction
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40">
                            <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        </span>
                        Predictive analytics and real-time budget forecasting
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40">
                            <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        </span>
                        Tamper-evident audit trails and automated reporting
                    </li>
                </ul>
            </div>

            <p class="text-xs text-white/50">&copy; <?= date('Y') ?> Atikha. Authorized personnel only.</p>
        </section>

        <!-- Form panel -->
        <main class="flex w-full flex-1 items-center justify-center px-6 py-12 sm:px-10 lg:w-1/2 xl:w-2/5">
            <div class="w-full max-w-sm">

                <!-- Logo placeholder -->
                <div class="mb-8 flex flex-col items-center text-center lg:items-start lg:text-left">
                    <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-slate-900 shadow-sm ring-1 ring-slate-900/5">
                        <img src="assets/img/atikha-logo.png" alt="Atikha organization logo" class="h-12 w-12 object-contain">
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
                    <p class="mt-2 text-sm text-slate-500">Sign in to the Atikha Financial System.</p>
                </div>

                <div id="login-view" class="transition-opacity duration-300 ease-in-out" aria-hidden="false">
                    <?php if ($showError): ?>
                        <div class="mb-6 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3" role="alert">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 112 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
                            <p class="text-sm font-medium text-red-600">
                                <?= htmlspecialchars('Invalid email or password.', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="auth.php" class="space-y-5">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                autocomplete="username"
                                class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder-slate-400 shadow-sm transition focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/60"
                                placeholder="you@atikha.org"
                            >
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder-slate-400 shadow-sm transition focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/60"
                                placeholder="••••••••"
                            >
                        </div>

                        <div class="flex items-center justify-end">
                            <button
                                type="button"
                                id="show-reset-form"
                                class="text-sm font-medium text-emerald-700 transition hover:text-emerald-800 hover:underline"
                            >
                                Forgot password?
                            </button>
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                        >
                            Sign in
                        </button>
                    </form>

                    <p class="mt-8 text-center text-xs text-slate-400 lg:text-left">
                        Protected system — access is monitored and logged.
                    </p>
                </div>

                <div id="reset-view" class="hidden opacity-0 transition-opacity duration-300 ease-in-out" aria-hidden="true">
                    <h2 class="text-lg font-semibold text-slate-900">Request Password Reset</h2>
                    <p class="mt-2 text-sm text-slate-500">Enter your account email and we will notify the System Administrator.</p>

                    <div id="reset-error" class="mt-4 hidden flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 112 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
                        <p id="reset-error-text" class="text-sm font-medium text-red-600"></p>
                    </div>

                    <form id="reset-form" class="mt-6 space-y-5">
                        <div>
                            <label for="reset-email" class="block text-sm font-medium text-slate-700">Email address</label>
                            <input
                                type="email"
                                id="reset-email"
                                name="email"
                                required
                                autocomplete="email"
                                class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder-slate-400 shadow-sm transition focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/60"
                                placeholder="you@atikha.org"
                            >
                        </div>

                        <button
                            type="submit"
                            id="reset-submit"
                            class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                        >
                            Request Reset
                        </button>

                        <div class="text-center">
                            <button
                                type="button"
                                id="show-login-form"
                                class="text-sm font-medium text-emerald-700 transition hover:text-emerald-800 hover:underline"
                            >
                                Back to Login
                            </button>
                        </div>
                    </form>
                </div>

                <div id="reset-success" class="hidden opacity-0 transition-opacity duration-300 ease-in-out" aria-hidden="true">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-6 text-center shadow-sm">
                        <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 ring-1 ring-emerald-200">
                            <svg class="h-6 w-6 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
                        </span>
                        <p class="text-sm font-medium leading-relaxed text-emerald-800">
                            Reset request sent. Please contact the System Administrator to receive your new credentials.
                        </p>
                        <button
                            type="button"
                            id="success-back-to-login"
                            class="mt-5 text-sm font-medium text-emerald-700 transition hover:text-emerald-800 hover:underline"
                        >
                            Back to Login
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            const loginView = document.getElementById('login-view');
            const resetView = document.getElementById('reset-view');
            const resetSuccess = document.getElementById('reset-success');
            const resetForm = document.getElementById('reset-form');
            const resetEmail = document.getElementById('reset-email');
            const resetSubmit = document.getElementById('reset-submit');
            const resetError = document.getElementById('reset-error');
            const resetErrorText = document.getElementById('reset-error-text');

            function hideResetError() {
                resetError.classList.add('hidden');
                resetErrorText.textContent = '';
            }

            function showResetError(message) {
                resetErrorText.textContent = message;
                resetError.classList.remove('hidden');
            }

            function showPanel(panel) {
                [loginView, resetView, resetSuccess].forEach(function (element) {
                    const isTarget = element === panel;
                    element.classList.toggle('hidden', !isTarget);
                    element.setAttribute('aria-hidden', isTarget ? 'false' : 'true');

                    if (isTarget) {
                        element.classList.remove('opacity-0');
                    } else {
                        element.classList.add('opacity-0');
                    }
                });
            }

            function showLoginView() {
                hideResetError();
                resetForm.reset();
                showPanel(loginView);
            }

            function showResetView() {
                hideResetError();
                showPanel(resetView);
                resetEmail.focus();
            }

            function showSuccessView() {
                hideResetError();
                showPanel(resetSuccess);
            }

            document.getElementById('show-reset-form').addEventListener('click', showResetView);
            document.getElementById('show-login-form').addEventListener('click', showLoginView);
            document.getElementById('success-back-to-login').addEventListener('click', showLoginView);

            resetForm.addEventListener('submit', function (event) {
                event.preventDefault();
                hideResetError();

                const email = resetEmail.value.trim();
                if (email === '') {
                    showResetError('Please enter a valid email address.');
                    return;
                }

                const defaultLabel = resetSubmit.textContent;
                resetSubmit.disabled = true;
                resetSubmit.textContent = 'Sending…';

                const requestBody = new URLSearchParams();
                requestBody.set('email', email);
                requestBody.set('csrf_token', csrfToken);

                fetch('request_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: requestBody.toString(),
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .catch(function () {
                        return { ok: false, error: 'Unable to process your request. Please try again later.' };
                    })
                    .then(function (payload) {
                        resetSubmit.disabled = false;
                        resetSubmit.textContent = defaultLabel;

                        if (payload.ok) {
                            showSuccessView();
                            return;
                        }

                        showResetError(payload.error || 'Unable to process your request. Please try again later.');
                    });
            });
        })();
    </script>
</body>
</html>
