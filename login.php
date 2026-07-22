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
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-8">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-2xl px-10 py-10">
            <div class="flex flex-col items-center mb-8 text-center">
                <img src="assets/img/atikha-logo.png" alt="Atikha Logo" class="w-24 h-auto mb-4">
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Financial System</h1>
                <p class="text-gray-500 text-sm mt-2">Internal Financial Management</p>
            </div>

            <?php if ($showError): ?>
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                    <p class="text-sm text-red-600 font-medium">
                        <?= htmlspecialchars('Invalid email or password.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="POST" action="auth.php" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="username"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600 focus:ring-offset-0 outline-none transition"
                        placeholder="you@example.com"
                    >
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 placeholder-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600 focus:ring-offset-0 outline-none transition"
                        placeholder="••••••••"
                    >
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2.5 px-4 transition focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                >
                    Sign in
                </button>
            </form>
        </div>
        <p class="text-center text-slate-500 text-xs mt-6">Desktop access only — authorized personnel</p>
    </div>
</body>
</html>
