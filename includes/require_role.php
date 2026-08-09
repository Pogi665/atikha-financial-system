<?php

/**
 * Shared login and role gates for authenticated pages.
 *
 * Callers must have started the session before invoking either function.
 */

/**
 * Redirect unauthenticated visitors to the login page.
 */
function require_login(): void
{
    if (empty($_SESSION['UserID'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Refuse access unless the signed-in role is one of $allowed.
 *
 * Renders a bright-themed Access Denied page and exits. Use this for HTML
 * pages; JSON endpoints should return their own {ok, error} 403 envelope.
 *
 * @param string[] $allowed Role literals from Users.Role (Admin, Staff, Management).
 */
function require_role(array $allowed, string $featureName): void
{
    $role = $_SESSION['Role'] ?? '';

    if (in_array($role, $allowed, true)) {
        return;
    }

    http_response_code(403);
    $deniedName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
    $feature = htmlspecialchars($featureName, ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied — Atikha Financial System</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
        <div class="max-w-md bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center">
            <h1 class="text-xl font-bold text-slate-900">Access Denied</h1>
            <p class="text-slate-600 mt-3 text-sm">
                <?= $feature ?> is restricted to Accounting/Administrative Staff and System Administrators.
                <?= $deniedName !== '' ? 'You are signed in as ' . $deniedName . '.' : '' ?>
            </p>
            <a
                href="dashboard.php"
                class="inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 text-sm mt-6 transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
            >
                Back to Dashboard
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
