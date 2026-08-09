<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/layout.php';

$activePage = 'reports';
$flags = layout_role_flags();
$isExecutive = $flags['isExecutive'];

$currentYear = (int) date('Y');
$currentMonth = date('m');
$yearOptions = range($currentYear, $currentYear - 4);

$month = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$year = isset($_GET['year']) ? (int) $_GET['year'] : $currentYear;

if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
    $month = $currentMonth;
}
if (!in_array($year, $yearOptions, true)) {
    $year = $currentYear;
}

$monthInt = (int) $month;
$periodLabel = date('F Y', mktime(0, 0, 0, $monthInt, 1, $year));
$generatedAt = date('F j, Y \a\t g:i A');

$monthNames = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December',
];

$revenues = [];
$expenses = [];

try {
    $stmt = $pdo->prepare(
        'SELECT Category, SUM(Amount) AS Total
         FROM Incoming_Funds
         WHERE MONTH(Date_Received) = :m AND YEAR(Date_Received) = :y
         GROUP BY Category ORDER BY Category ASC'
    );
    $stmt->execute(['m' => $monthInt, 'y' => $year]);
    $revenues = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT Category, SUM(Amount) AS Total
         FROM Expenses
         WHERE MONTH(Date_Incurred) = :m AND YEAR(Date_Incurred) = :y
         GROUP BY Category ORDER BY Category ASC'
    );
    $stmt->execute(['m' => $monthInt, 'y' => $year]);
    $expenses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Report query failed: ' . $e->getMessage());
}

$totalRevenues = array_sum(array_map(static fn ($r) => (float) $r['Total'], $revenues));
$totalExpenses = array_sum(array_map(static fn ($r) => (float) $r['Total'], $expenses));
$netIncome = $totalRevenues - $totalExpenses;

$printCss = <<<'CSS'
<style>
    @media print {
        body { background: white !important; }
        aside, header, .no-print { display: none !important; }
        main { margin: 0 !important; padding: 24px !important; }
        .statement { max-width: 100% !important; font-size: 12pt; }
        .statement-header { border-bottom: 2px solid #1e3a8a; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    }
    @page { margin: 1.5cm; }
</style>
CSS;

layout_begin('Reports', $activePage, [], $printCss);

$cardClass = $isExecutive ? 'exec-card' : 'bg-white rounded-xl border border-slate-200 shadow-sm p-6';
$stmtClass = $isExecutive ? 'exec-card statement' : 'bg-white rounded-xl border border-slate-200 shadow-sm p-8 print:shadow-none print:border-0 statement';
$btnPrimary = $isExecutive ? 'exec-btn-primary' : 'rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition';
?>

<div class="no-print">
    <h1 class="text-2xl font-bold text-slate-900">Automated Reporting</h1>
    <p class="text-slate-600 mt-2">Generate professional monthly income statements by category.</p>
</div>

<section class="<?= $cardClass ?> no-print">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Report Controls</h2>
    <form method="GET" action="reports.php" class="flex flex-wrap items-end gap-4">
        <div>
            <label for="month" class="block text-sm font-medium text-slate-700 mb-1">Month</label>
            <select id="month" name="month" required class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-blue-800 focus:ring-2 focus:ring-blue-800 outline-none min-w-[160px]">
                <?php foreach ($monthNames as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $month === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="year" class="block text-sm font-medium text-slate-700 mb-1">Year</label>
            <select id="year" name="year" required class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-blue-800 focus:ring-2 focus:ring-blue-800 outline-none min-w-[120px]">
                <?php foreach ($yearOptions as $yearOption): ?>
                    <option value="<?= (int) $yearOption ?>" <?= $year === $yearOption ? 'selected' : '' ?>><?= (int) $yearOption ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="<?= $btnPrimary ?>">Generate Statement</button>
    </form>
</section>

<section class="<?= $stmtClass ?>">
    <div class="flex items-start justify-end mb-6 no-print">
        <button type="button" onclick="window.print()" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
            Print / Export
        </button>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-10 statement-header">
            <p class="text-xs uppercase tracking-widest text-slate-500">Atikha Financial System</p>
            <h2 class="text-2xl font-bold text-slate-900 mt-2">Income Statement</h2>
            <p class="text-slate-600 mt-1"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-slate-400 mt-2">Generated on <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div class="mb-8">
            <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">Revenues</h3>
            <div class="space-y-2">
                <?php if ($revenues === []): ?>
                    <p class="text-sm text-slate-500 italic">No records for this period.</p>
                <?php else: ?>
                    <?php foreach ($revenues as $row): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-700"><?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-slate-900"><?= htmlspecialchars(format_peso((float) $row['Total']), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="flex justify-between border-t border-slate-200 mt-4 pt-3 font-bold text-slate-900">
                <span>Total Revenues</span>
                <span><?= htmlspecialchars(format_peso($totalRevenues), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">Expenses</h3>
            <div class="space-y-2">
                <?php if ($expenses === []): ?>
                    <p class="text-sm text-slate-500 italic">No records for this period.</p>
                <?php else: ?>
                    <?php foreach ($expenses as $row): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-700"><?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-slate-900"><?= htmlspecialchars(format_peso((float) $row['Total']), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="flex justify-between border-t border-slate-200 mt-4 pt-3 font-bold text-slate-900">
                <span>Total Expenses</span>
                <span><?= htmlspecialchars(format_peso($totalExpenses), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="flex justify-between border-t-2 border-blue-900 pt-4 text-lg font-bold text-blue-900">
            <span>Net Income</span>
            <span><?= htmlspecialchars(format_peso($netIncome), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <p class="text-center text-xs text-slate-400 mt-10 no-print">
            Read-only report · Atikha Finance
        </p>
    </div>
</section>

<?php layout_end(); ?>
