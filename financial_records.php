<?php
session_start();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/require_role.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/ledger_query.php';
require_once __DIR__ . '/includes/ledger_ui.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    exit('This page is read-only.');
}

$activePage = 'financial_records';
$flags = layout_role_flags();

$filters = ledger_parse_filters([]);
$filterError = '';
$totalRows = 0;
$records = [];
$ledgerError = false;
$completeness = ledger_completeness([]);

try {
    $filters = ledger_parse_filters($_GET);
    $view = ledger_view($pdo, $filters);
    $totalRows = $view['total'];
    $records = $view['rows'];
    $completeness = $view['completeness'];
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $filterError = $e->getMessage();
} catch (Throwable $e) {
    $ledgerError = true;
    error_log('Ledger query failed: ' . $e->getMessage());
}

$categoryOptions = ledger_category_options($pdo);
if ($filters['category'] !== '' && !in_array($filters['category'], $categoryOptions, true)) {
    $categoryOptions[] = $filters['category'];
    sort($categoryOptions);
}
$filterSummary = [];
if ($filters['category'] !== '') { $filterSummary[] = $filters['category']; }
if ($filters['type'] !== '') { $filterSummary[] = $filters['type'] === 'Incoming' ? 'Incoming Funds' : 'Expenses'; }
if ($filters['from'] !== '') { $filterSummary[] = 'From ' . $filters['from']; }
if ($filters['to'] !== '') { $filterSummary[] = 'Through ' . $filters['to']; }
$totalPages = max(1, (int) ceil($totalRows / LEDGER_PAGE_SIZE));

layout_begin(
    'Financial Records',
    $activePage,
    [],
    '',
    $flags['isExecutive']
        ? 'min-h-screen min-w-[1024px] bg-slate-50 executive-theme'
        : 'min-h-screen min-w-[1024px] bg-slate-50'
);
?>

<div>
    <h1 class="text-2xl font-bold text-slate-900">Financial Records</h1>
    <p class="text-slate-600 mt-2">
        Unified read-only ledger of incoming funds and expenses, newest first.
    </p>
</div>

<section class="<?= $flags['isExecutive'] ? 'exec-card' : 'bg-white rounded-xl border border-slate-200 shadow-sm p-6' ?>">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Filter Records</h2>
    <form method="GET" action="financial_records.php" class="flex flex-wrap items-end gap-4">
        <div>
            <label for="from" class="block text-sm font-medium text-slate-700 mb-1">From</label>
            <input
                type="date"
                id="from"
                name="from"
                value="<?= htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8') ?>"
                class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-blue-800 focus:ring-2 focus:ring-blue-800 focus:ring-offset-0 outline-none transition"
            >
        </div>
        <div>
            <label for="to" class="block text-sm font-medium text-slate-700 mb-1">To</label>
            <input
                type="date"
                id="to"
                name="to"
                value="<?= htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8') ?>"
                class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-blue-800 focus:ring-2 focus:ring-blue-800 focus:ring-offset-0 outline-none transition"
            >
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-slate-700 mb-1">Transaction Type</label>
            <select
                id="type"
                name="type"
                class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-blue-800 focus:ring-2 focus:ring-blue-800 focus:ring-offset-0 outline-none transition min-w-[160px]"
            >
                <option value="" <?= $filters['type'] === '' ? 'selected' : '' ?>>All</option>
                <option value="Incoming" <?= $filters['type'] === 'Incoming' ? 'selected' : '' ?>>Incoming</option>
                <option value="Expense" <?= $filters['type'] === 'Expense' ? 'selected' : '' ?>>Expense</option>
            </select>
        </div>
        <div>
            <label for="category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
            <select
                id="category"
                name="category"
                class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-blue-800 focus:ring-2 focus:ring-blue-800 focus:ring-offset-0 outline-none transition min-w-[180px]"
            >
                <option value="">All categories</option>
                <?php foreach ($categoryOptions as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button
            type="submit"
            class="<?= $flags['isExecutive'] ? 'exec-btn-primary' : 'rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition' ?>"
        >
            Apply Filters
        </button>
    </form>
</section>

<section class="<?= $flags['isExecutive'] ? 'exec-card' : 'bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden' ?>">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Ledger</h2>
        <p class="text-sm text-slate-500"><?= $ledgerError ? 'Unavailable' : (int) $totalRows ?> record<?= $totalRows === 1 ? '' : 's' ?></p>
    </div>

    <?php if ($filterSummary || $filterError !== ''): ?>
        <div class="flex flex-wrap items-center gap-3 p-4" role="status">
            <?php if ($filterError === ''): ?><span class="rounded-full bg-blue-50 px-3 py-1 text-sm text-blue-900">Showing transactions for: <?= htmlspecialchars(implode(' — ', $filterSummary), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
            <a href="financial_records.php?from=&amp;to=" class="text-sm text-blue-700 hover:underline">Clear Filter</a>
        </div>
    <?php endif; ?>
    <?php if ($filterError !== ''): ?>
        <p role="alert" class="p-4 text-red-700"><?= htmlspecialchars($filterError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php elseif ($ledgerError): ?>
        <p role="alert" class="p-4 text-red-700">Unable to load financial records. Please try again later.</p>
    <?php else: ?>
        <p class="text-sm text-slate-600">Balances represent the organization after each transaction, including transactions hidden by filters. They are not category budgets or project balances.</p>
        <?php ledger_completeness_notice($completeness); ledger_render_table($records); ?>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-200">
            <p class="text-sm text-slate-500">
                Page <?= (int) $filters['page'] ?> of <?= $totalPages ?>
            </p>
            <div class="flex gap-2">
                <?php if ($filters['page'] > 1): ?>
                    <a
                        href="?<?= htmlspecialchars(http_build_query(array_merge($filters, ['page' => $filters['page'] - 1])), ENT_QUOTES, 'UTF-8') ?>"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                    >Previous</a>
                <?php endif; ?>
                <?php if ($filters['page'] < $totalPages): ?>
                    <a
                        href="?<?= htmlspecialchars(http_build_query(array_merge($filters, ['page' => $filters['page'] + 1])), ENT_QUOTES, 'UTF-8') ?>"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                    >Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php layout_end(); ?>
