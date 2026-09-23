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
$navCanUseWorkspace = in_array($navRole, ['Admin'], true);

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
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
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

            <!-- Pending Accounting Modules Section -->
            <div class="pt-5 pb-1">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-4">
                    Accounting (Pending COA)
                </p>
            </div>

            <!-- Disbursement (Placeholder) -->
            <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium text-slate-400 hover:text-slate-300 cursor-not-allowed opacity-75 transition-colors group">
                <svg class="w-5 h-5 mr-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3-3m0 0l3 3m-3-3v8m0-13a9 9 0 110 18 9 9 0 010-18z"></path>
                </svg>
                Disbursement
                <span class="ml-auto text-[10px] bg-slate-800 text-slate-500 px-2 py-0.5 rounded">TBA</span>
            </a>

            <!-- Receipt (Placeholder) -->
            <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium text-slate-400 hover:text-slate-300 cursor-not-allowed opacity-75 transition-colors group">
                <svg class="w-5 h-5 mr-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Receipt
                <span class="ml-auto text-[10px] bg-slate-800 text-slate-500 px-2 py-0.5 rounded">TBA</span>
            </a>

            <!-- Journal (Placeholder) -->
            <a href="#" class="flex items-center px-4 py-2.5 text-sm font-medium text-slate-400 hover:text-slate-300 cursor-not-allowed opacity-75 transition-colors group">
                <svg class="w-5 h-5 mr-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477-4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                Journal
                <span class="ml-auto text-[10px] bg-slate-800 text-slate-500 px-2 py-0.5 rounded">TBA</span>
            </a>
            
            <div class="pb-2"></div>
        <?php endif; ?>
        <a href="reports.php" class="<?= nav_link_class('reports', $activePage, $navIsExecutive) ?>">
            Reports
        </a>
        <?php if ($navCanUseWorkspace): ?>
            <a href="board_messages.php" class="<?= nav_link_class('board_messages', $activePage, $navIsExecutive) ?>">
                Message the Board
            </a>
        <?php endif; ?>
        <?php if ($navIsExecutive): ?>
            <a href="management_reviews.php" class="<?= nav_link_class('management_reviews', $activePage, $navIsExecutive) ?>">
                Review Queue
            </a>
            <a href="board_inbox.php" class="<?= nav_link_class('board_inbox', $activePage, $navIsExecutive) ?>">
                Board Inbox
            </a>
        <?php endif; ?>
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