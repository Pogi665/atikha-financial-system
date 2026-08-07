<?php
session_start();

if (!empty($_SESSION['UserID'])) {
    header('Location: dashboard.php');
    exit;
}

$showError = isset($_GET['error']) && $_GET['error'] === 'invalid';
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
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/20 backdrop-blur">
                    <img src="assets/img/atikha-logo.png" alt="Atikha" class="h-8 w-8 object-contain">
                </span>
                <span class="text-sm font-semibold uppercase tracking-[0.2em] text-white/80">Atikha</span>
            </div>

            <div class="max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight text-white text-balance">
                    Financial stewardship, made transparent.
                </h2>
                <p class="mt-5 text-base leading-relaxed text-white/70 text-pretty">
                    Track funds, manage expenses, and keep every transaction accountable — a secure internal platform built for our mission.
                </p>

                <ul class="mt-10 space-y-4 text-sm text-white/80">
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40">
                            <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        </span>
                        Bank-grade audit trail on every entry
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40">
                            <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        </span>
                        Real-time fund and expense reporting
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40">
                            <svg class="h-3.5 w-3.5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        </span>
                        Role-based access for your team
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

                <?php if ($showError): ?>
                    <div class="mb-6 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 112 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
                        <p class="text-sm font-medium text-red-600">
                            <?= htmlspecialchars('Invalid email or password. Please try again.', ENT_QUOTES, 'UTF-8') ?>
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

                    <div class="flex items-center justify-between">
                        <label for="remember" class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                value="1"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-2 focus:ring-emerald-600/60"
                            >
                            Remember me
                        </label>
                        <a href="#" class="text-sm font-medium text-emerald-700 transition hover:text-emerald-800 hover:underline">Forgot password?</a>
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
        </main>
    </div>
</body>
</html>
