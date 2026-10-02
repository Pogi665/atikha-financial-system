<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/ledger_query.php';
require_once __DIR__ . '/includes/ledger_ui.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET'); http_response_code(405); exit('This page is read-only.');
}
$flags = layout_role_flags();
$context = 'records';
$filters = ledger_records_filters([])['filters'];
$records = $categoryOptions = [];
$filterError = '';
$ledgerError = false;
try {
    $parsed = ledger_records_filters($_GET);
    $context = $parsed['context'];
    $filters = $parsed['filters'];
    $records = ledger_records_dataset($pdo, $filters, (int) $_SESSION['UserID'], $flags['role']);
    $categoryOptions = ledger_records_category_options($pdo, $filters['type']);
} catch (InvalidArgumentException $e) {
    http_response_code(400); $filterError = $e->getMessage();
    if (isset($_GET['view']) && is_string($_GET['view']) && in_array($_GET['view'], ['crb', 'cdb'], true)) { $context = $_GET['view']; }
} catch (Throwable $e) {
    $ledgerError = true; error_log('Ledger query failed: ' . $e->getMessage());
}
if ($filters['category'] !== '' && !in_array($filters['category'], $categoryOptions, true)) {
    $categoryOptions[] = $filters['category']; sort($categoryOptions);
}
$title = ['crb' => 'Cash Receipts Book', 'cdb' => 'Cash Disbursements Book'][$context] ?? 'Financial Records';
$activePage = ['crb' => 'crb', 'cdb' => 'cdb'][$context] ?? 'financial_records';
$clearUrl = 'financial_records.php' . ($context === 'records' ? '' : '?view=' . $context);
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dateScope = $filters['from'] === '' && $filters['to'] === '' ? 'all'
    : ($filters['from'] === date('Y-m-01') && $filters['to'] === date('Y-m-d') ? 'month' : 'custom');
$summary = ['Date scope: ' . ($dateScope === 'all' ? 'All dates' : ($dateScope === 'month' ? 'This month through today' : 'Custom range'))];
if ($filters['from'] !== '') { $summary[] = 'From ' . $filters['from']; }
if ($filters['to'] !== '') { $summary[] = 'Through ' . $filters['to']; }
if ($filters['category'] !== '') { $summary[] = $filters['category']; }
if ($filters['type'] !== '') { $summary[] = $filters['type']; }
$extraHead = '<link rel="stylesheet" href="assets/vendor/datatables/2.3.8/dataTables.dataTables.min.css"><link rel="stylesheet" href="assets/css/financial_records.css">';
layout_begin($title, $activePage, [], $extraHead, 'min-h-screen min-w-[1024px] bg-slate-50 financial-records-page' . ($flags['isExecutive'] ? ' executive-theme' : ''));
?>
<div class="records-page" data-context="<?= $escape($context) ?>">
<h1 class="text-2xl font-bold text-slate-900"><?= $escape($title) ?></h1>
<p>Read-only recorded receipts and disbursements, newest first.</p>
<section class="records-card <?= $flags['isExecutive'] ? 'exec-card' : '' ?>">
<h2>Filter Records</h2>
<form method="GET" action="financial_records.php" id="records-filters">
<?php if ($context !== 'records'): ?><input type="hidden" name="view" value="<?= $escape($context) ?>"><?php endif; ?>
<label>Date scope <select id="date-scope">
<?php foreach (['all' => 'All dates', 'month' => 'This month through today', 'custom' => 'Custom range'] as $value => $label): ?>
<option value="<?= $value ?>" <?= $dateScope === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
</select></label>
<label>From <input type="date" id="from" name="from" value="<?= $escape($filters['from']) ?>"></label>
<label>Through <input type="date" id="to" name="to" value="<?= $escape($filters['to']) ?>"></label>
<?php if ($context === 'records'): ?>
<label>Transaction type <select name="type" id="type">
<?php foreach (['' => 'All', 'Incoming' => 'Incoming', 'Expense' => 'Expense'] as $value => $label): ?>
<option value="<?= $value ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
</select></label>
<?php endif; ?>
<label>Category <select name="category" id="category"><option value="">All categories</option>
<?php foreach ($categoryOptions as $category): ?>
<option value="<?= $escape($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= $escape($category) ?></option>
<?php endforeach; ?>
</select></label>
<button type="submit">Apply Filters</button><a href="<?= $escape($clearUrl) ?>">Clear filters</a>
</form>
<?php if ($filterError === ''): ?><p class="records-filter-summary">Showing transactions for: <?= $escape(implode(" \u{2014} ", $summary)) ?></p><?php endif; ?>
</section>
<?php if ($filterError !== ''): ?>
<p role="alert"><?= $escape($filterError) ?></p>
<?php elseif ($ledgerError): ?>
<p role="alert">Unable to load financial records. Please try again later.</p>
<?php else: ?>
<section class="records-summaries" aria-label="Matching transaction totals">
<?php foreach (['incoming' => 'Total Receipts', 'expense' => 'Total Disbursements', 'net' => 'Net Recorded Cash Flow'] as $key => $label): ?>
<?php if (($context === 'crb' && $key !== 'incoming') || ($context === 'cdb' && $key !== 'expense')) { continue; } ?>
<div class="records-card"><h2><?= $label ?></h2><p id="records-total-<?= $key ?>">Calculating...</p></div>
<?php endforeach; ?>
</section>
<section class="records-card <?= $flags['isExecutive'] ? 'exec-card' : '' ?>">
<h2>Transactions</h2><p>Summaries and completeness follow all filters and search, across every page.</p>
<?php ledger_records_table($context, $clearUrl); ?>
</section>
<script id="records-data" type="application/json"><?= json_encode(['rows' => $records, 'page' => $filters['page'], 'monthStart' => date('Y-m-01'), 'today' => date('Y-m-d')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?>
</div>
<?php layout_end('<script src="assets/vendor/jquery/3.7.1/jquery.min.js"></script><script src="assets/vendor/datatables/2.3.8/dataTables.min.js"></script><script src="assets/js/financial_records.js"></script>'); ?>
