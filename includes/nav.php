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
    // Added 'flex items-center' here so the icons align perfectly with the text
    $base = 'flex items-center rounded-lg px-4 py-2.5 text-sm transition';
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
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            Dashboard
        </a>
        <a href="financial_records.php" class="<?= nav_link_class('financial_records', $activePage, $navIsExecutive) ?>">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Financial Records
        </a>
        
        <?php if ($navCanUseWorkspace): ?>
            <a href="funds.php" class="<?= nav_link_class('funds', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                Incoming Funds
            </a>
            <a href="expenses.php" class="<?= nav_link_class('expenses', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                Expenses
            </a>
            <a href="ocr_expense.php" class="<?= nav_link_class('ocr_expense', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
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
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Reports
        </a>
        
        <?php if ($navCanUseWorkspace): ?>
            <a href="board_messages.php" class="<?= nav_link_class('board_messages', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                Message the Board
            </a>
        <?php endif; ?>
        
        <?php if ($navIsExecutive): ?>
            <a href="management_reviews.php" class="<?= nav_link_class('management_reviews', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                Review Queue
            </a>
            <a href="board_inbox.php" class="<?= nav_link_class('board_inbox', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                Board Inbox
            </a>
        <?php endif; ?>
        
        <?php if ($navIsAdmin): ?>
            <a href="admin_users.php" class="<?= nav_link_class('admin_users', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                User Management
            </a>
            <a href="audit_trail.php" class="<?= nav_link_class('audit_trail', $activePage, $navIsExecutive) ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Audit Trail
            </a>
        <?php endif; ?>
    </nav>
</aside>