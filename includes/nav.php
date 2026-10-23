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
$navCanScanReceipt = false;
$navStage1 = false;
if ($navCanUseWorkspace) {
    require_once __DIR__ . '/receipt_ocr.php';
    // layout_begin() includes this file inside its own scope.
    $navConnection = $pdo ?? $GLOBALS['pdo'] ?? null;
    try { $navCanScanReceipt = $navConnection instanceof PDO && receipt_enabled($navConnection); }
    catch (Throwable $e) { error_log('Receipt navigation unavailable.'); }
    try { $navStage1 = $navConnection instanceof PDO && stage1_enabled($navConnection); }
    catch (Throwable $e) { error_log('Accounting navigation unavailable.'); }
}

$navStage2=false;
if($navIsAdmin||$navIsExecutive){require_once __DIR__.'/stage1_common.php';$navConnection=$pdo??$GLOBALS['pdo']??null;try{$navStage2=$navConnection instanceof PDO&&stage2_enabled($navConnection);}catch(Throwable $e){error_log('Advance navigation unavailable.');}}

function nav_link_class(string $page, string $activePage, bool $executive = false): string
{
    $base = 'flex items-center rounded-lg px-4 py-2.5 text-sm transition';
    if ($page !== $activePage) return $base . ' text-slate-300 hover:bg-slate-700/50 hover:text-white';
    if ($executive) return $base . ' bg-blue-900 text-white font-medium';
    return $base . ($page === 'general_journal' ? ' bg-emerald-600' : ' bg-slate-700') . ' text-white font-medium';
}

// Fixed destinations only. Group headings are labels, never additional routes.
$navGroups = [];
if ($navIsAdmin) {
    $tasks = [];
    if ($navStage1) {
        $tasks[] = ['payment', 'cash_disbursement.php', 'Record a payment', 'journal'];
        $tasks[] = ['receipt', 'cash_receipt.php', 'Record money received', 'journal'];
    }
    $tasks[] = ['general_journal', 'general_journal.php', 'Other journal entry (General Journal)', 'journal'];
    if ($navCanScanReceipt) $tasks[] = ['ocr_expense', 'ocr_expense.php', 'Scan receipt', 'journal'];
    if ($navStage1) $tasks[] = ['accounting_drafts', 'accounting_drafts.php', 'My drafts', 'journal'];
    $navGroups[] = ['Transactions', $tasks];
}
if ($navStage2) $navGroups[] = ['Cash advances', [['cash_advances', 'cash_advances.php', 'Cash advances', 'book']]];
$navGroups[] = ['Books & reports', [
    ['financial_records', 'financial_records.php', 'Find a transaction (Journal History)', 'records'],
    ['crb', 'financial_records.php?view=crb', 'Cash Receipts Book (CRB)', 'book'],
    ['cdb', 'financial_records.php?view=cdb', 'Cash Disbursements Book (CDB)', 'book'],
    ['general_ledger', 'general_ledger.php', 'General Ledger', 'book'],
    ['budgets', 'budgets.php', 'Monthly budgets', 'reports'],
    ['financial_statements', 'financial_statements.php', 'Monthly financial reports', 'reports'],
    ['report_bundles', 'report_bundles.php', 'Complete reports / month controls', 'reports'],
    ['reports', 'reports.php', 'Reports', 'reports'],
]];
if ($navIsAdmin || $navIsExecutive) {
    $messages = [['external_email', 'external_email.php', 'External Email', 'email']];
    if ($navIsAdmin) $messages[] = ['board_messages', 'board_messages.php', 'Message the Board', 'message'];
    if ($navIsExecutive) {
        $messages[] = ['management_reviews', 'management_reviews.php', 'Review Queue', 'review'];
        $messages[] = ['board_inbox', 'board_inbox.php', 'Board Inbox', 'inbox'];
    }
    $navGroups[] = ['Communications', $messages];
}
if ($navIsAdmin) {
    $administration = [];
    if ($navStage1) $administration[] = ['accounting_setup', 'accounting_setup.php', 'Accounting setup', 'review'];
    $administration[] = ['report_setup', 'report_setup.php', 'Reporting setup', 'review'];
    $administration[] = ['admin_accounts', 'admin_accounts.php', 'Chart of Accounts', 'review'];
    $administration[] = ['admin_users', 'admin_users.php', 'User management', 'users'];
    $administration[] = ['audit_trail', 'audit_trail.php', 'Audit trail', 'audit'];
    $navGroups[] = ['Administration', $administration];
}
$navIcons = [
    'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>',
    'journal' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h3m2 0h3"/>',
    'records' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
    'book' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477-4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>',
    'reports' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
    'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
    'message' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>',
    'review' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>',
    'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>',
    'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>',
    'audit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>',
];
$navEscape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$navRenderLink = static function(array $link) use ($activePage, $navIsExecutive, $navIcons, $navEscape): void {
    [$key, $url, $label, $icon] = $link;
    ?>
    <a href="<?= $navEscape($url) ?>" data-nav-key="<?= $navEscape($key) ?>" class="<?= nav_link_class($key, $activePage, $navIsExecutive) ?>"<?= $key === $activePage ? ' aria-current="page"' : '' ?>>
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><?= $navIcons[$icon] ?></svg>
        <span class="sidebar-link-label"><?= $navEscape($label) ?></span>
    </a>
    <?php
};
$sidebarClass = $navIsExecutive
    ? 'fixed inset-y-0 left-0 w-64 bg-slate-900 text-slate-100 flex flex-col print:hidden'
    : 'fixed inset-y-0 left-0 w-64 bg-slate-800 text-slate-100 flex flex-col print:hidden';
?>
<link rel="stylesheet" href="assets/css/sidebar.css?v=<?= filemtime(__DIR__ . '/../assets/css/sidebar.css') ?>">
<aside class="app-sidebar <?= $sidebarClass ?>">
    <div class="sidebar-brand">
        <h2 class="text-lg font-bold tracking-tight">Atikha Finance</h2>
        <p class="text-slate-400 text-xs mt-1"><?= $navIsExecutive ? 'Executive Suite' : 'Management System' ?></p>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto" aria-label="Main navigation">
        <?php $navRenderLink(['dashboard', 'dashboard.php', 'Dashboard', 'dashboard']); ?>
        <?php foreach ($navGroups as [$heading, $links]): if (!$links) continue; ?>
            <div class="sidebar-section"><h2><?= $navEscape($heading) ?></h2></div>
            <?php foreach ($links as $link) $navRenderLink($link); ?>
        <?php endforeach; ?>
    </nav>
</aside>
