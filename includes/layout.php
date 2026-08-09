<?php

/**
 * Page shell helpers for authenticated views.
 *
 * Usage:
 *   layout_begin('Page Title', 'dashboard', ['chart.js'], $extraHead);
 *   ... page content ...
 *   layout_end($extraScripts);
 */

require_once __DIR__ . '/user_roles.php';

function layout_role_flags(): array
{
    $role = $_SESSION['Role'] ?? '';

    return [
        'role'            => $role,
        'isAdmin'         => $role === 'Admin',
        'isExecutive'     => $role === 'Management',
        'canUseWorkspace' => in_array($role, ['Staff', 'Admin'], true),
        'canRefresh'      => in_array($role, ['Admin', 'Management'], true),
        'fullName'        => htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8'),
        'roleLabel'       => htmlspecialchars(user_role_label($role), ENT_QUOTES, 'UTF-8'),
    ];
}

/**
 * @param string[] $headScripts Extra script URLs for <head> (e.g. Chart.js)
 * @param string   $extraHead   Raw HTML appended inside <head>
 */
function layout_begin(
    string $title,
    string $activePage,
    array $headScripts = [],
    string $extraHead = '',
    string $bodyClass = ''
): void {
    $flags = layout_role_flags();
    $isExecutive = $flags['isExecutive'];

    $defaultBody = $isExecutive
        ? 'min-h-screen min-w-[1024px] bg-slate-50 executive-theme'
        : 'min-h-screen min-w-[1024px] bg-slate-100';

    if ($bodyClass !== '') {
        $defaultBody = $bodyClass;
    }

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if ($isExecutive): ?>
        <link rel="stylesheet" href="assets/css/executive.css">
    <?php endif; ?>
    <?php foreach ($headScripts as $scriptUrl): ?>
        <script src="<?= htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
    <?= $extraHead ?>
</head>
<body class="<?= htmlspecialchars($defaultBody, ENT_QUOTES, 'UTF-8') ?>">
    <?php include __DIR__ . '/nav.php'; ?>

    <div class="ml-64 flex flex-col min-h-screen print:ml-0">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between print:hidden">
            <div>
                <p class="text-sm text-slate-500">Signed in as</p>
                <p class="text-slate-900 font-semibold">
                    <?= $flags['fullName'] ?>
                    <span class="text-slate-400 font-normal">·</span>
                    <span class="text-slate-600 font-medium text-sm"><?= $flags['roleLabel'] ?></span>
                </p>
            </div>
            <a
                href="logout.php"
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
            >
                Logout
            </a>
        </header>

        <main class="flex-1 p-8 space-y-8 print:p-0">
    <?php
}

/** @param string $extraScripts Raw HTML before </body> */
function layout_end(string $extraScripts = ''): void
{
    ?>
        </main>
    </div>
    <?= $extraScripts ?>
</body>
</html>
    <?php
}

function format_peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}
