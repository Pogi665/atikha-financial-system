<?php

/**
 * Shared sidebar navigation for authenticated pages.
 *
 * Expects $activePage to be set before include (e.g. 'dashboard', 'financial_records').
 */

require_once __DIR__ . '/user_roles.php';

if (!isset($activePage)) {
    $activePage = '';
}

$navRole = $_SESSION['Role'] ?? '';
$navIsAdmin = $navRole === 'Admin';
$navIsExecutive = $navRole === 'Management';
$navCanUseWorkspace = in_array($navRole, ['Staff', 'Admin'], true);

$navRoleLabel = htmlspecialchars(user_role_label($navRole), ENT_QUOTES, 'UTF-8');

function nav_link_class(string $page, string $activePage, bool $executive = false): string
{
    $base = 'block rounded-lg px-4 py-2.5 text-sm transition';
    $inactive = $base . ' text-slate-300 hover:bg-slate-700/50';
    $activeExecutive = $base . ' bg-blue-900 text-white font-medium';
    $activeDefault = $base . ' bg-slate-700 text-white font-medium';
    $activeWorkspace = $base . ' bg-emerald-600 text-white font-medium';

    if ($page !== $activePage) {
        return $inactive;
    }

    if ($executive) {
        return $activeExecutive;
    }

    if (in_array($page, ['funds', 'expenses', 'ocr_expense'], true)) {
        return $activeWorkspace;
    }

    return $activeDefault;
}

$sidebarClass = $navIsExecutive
    ? 'fixed inset-y-0 left-0 w-64 bg-slate-900 text-slate-100 flex flex-col print:hidden'
    : 'fixed inset-y-0 left-0 w-64 bg-slate-800 text-slate-100 flex flex-col print:hidden';

?>
<aside class="<?= $sidebarClass ?>">
    <div class="px-6 py-6 border-b border-slate-700">
        <h2 class="text-lg font-bold tracking-tight">Atikha Finance</h2>
        <p class="text-slate-400 text-xs mt-1">
            <?= $navIsExecutive ? 'Executive Suite' : 'Management System' ?>
        </p>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-1">
        <a href="dashboard.php" class="<?= nav_link_class('dashboard', $activePage, $navIsExecutive) ?>">
            Dashboard
        </a>
        <a href="financial_records.php" class="<?= nav_link_class('financial_records', $activePage, $navIsExecutive) ?>">
            Financial Records
        </a>
        <?php if ($navCanUseWorkspace): ?>
            <a href="funds.php" class="<?= nav_link_class('funds', $activePage, $navIsExecutive) ?>">
                Incoming Funds
            </a>
            <a href="expenses.php" class="<?= nav_link_class('expenses', $activePage, $navIsExecutive) ?>">
                Expenses
            </a>
            <a href="ocr_expense.php" class="<?= nav_link_class('ocr_expense', $activePage, $navIsExecutive) ?>">
                Scan Receipt
            </a>
        <?php endif; ?>
        <a href="reports.php" class="<?= nav_link_class('reports', $activePage, $navIsExecutive) ?>">
            Reports
        </a>
        <?php if ($navIsAdmin): ?>
            <a href="admin_users.php" class="<?= nav_link_class('admin_users', $activePage, $navIsExecutive) ?>">
                User Management
            </a>
            <a href="audit_trail.php" class="<?= nav_link_class('audit_trail', $activePage, $navIsExecutive) ?>">
                Audit Trail
            </a>
        <?php endif; ?>
    </nav>
</aside>
