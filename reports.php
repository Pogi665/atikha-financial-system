<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';

function formatPeso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

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

$monthNames = [
    '01' => 'January',
    '02' => 'February',
    '03' => 'March',
    '04' => 'April',
    '05' => 'May',
    '06' => 'June',
    '07' => 'July',
    '08' => 'August',
    '09' => 'September',
    '10' => 'October',
    '11' => 'November',
    '12' => 'December',
];

$revenues = [];
$expenses = [];

try {
    $stmt = $pdo->prepare(
        'SELECT Category, SUM(Amount) AS Total
         FROM Incoming_Funds
         WHERE MONTH(Date_Received) = :m AND YEAR(Date_Received) = :y
         GROUP BY Category
         ORDER BY Category ASC'
    );
    $stmt->execute(['m' => $monthInt, 'y' => $year]);
    $revenues = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT Category, SUM(Amount) AS Total
         FROM Expenses
         WHERE MONTH(Date_Incurred) = :m AND YEAR(Date_Incurred) = :y
         GROUP BY Category
         ORDER BY Category ASC'
    );
    $stmt->execute(['m' => $monthInt, 'y' => $year]);
    $expenses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Report query failed: ' . $e->getMessage());
}

$totalRevenues = 0.0;
foreach ($revenues as $row) {
    $totalRevenues += (float) $row['Total'];
}

$totalExpenses = 0.0;
foreach ($expenses as $row) {
    $totalExpenses += (float) $row['Total'];
}

$netIncome = $totalRevenues - $totalExpenses;

$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                background: white;
            }
        }
    </style>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-100 print:bg-white">
    <aside class="fixed inset-y-0 left-0 w-64 bg-slate-800 text-slate-100 flex flex-col print:hidden">
        <div class="px-6 py-6 border-b border-slate-700">
            <h2 class="text-lg font-bold tracking-tight">Atikha Finance</h2>
            <p class="text-slate-400 text-xs mt-1">Management System</p>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1">
            <a
                href="dashboard.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Dashboard
            </a>
            <a
                href="funds.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Incoming Funds
            </a>
            <a
                href="expenses.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Expenses
            </a>
            <a
                href="ocr_expense.php"
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Scan Receipt
            </a>
            <a
                href="reports.php"
                class="block rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-medium text-white"
            >
                Reports
            </a>
        </nav>
    </aside>

    <div class="ml-64 flex flex-col min-h-screen print:ml-0">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between print:hidden">
            <div>
                <p class="text-sm text-slate-500">Signed in as</p>
                <p class="text-slate-900 font-semibold">
                    <?= $fullName ?>
                    <span class="text-slate-400 font-normal">·</span>
                    <span class="text-slate-600 font-medium text-sm"><?= $role ?></span>
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
            <div class="print:hidden">
                <h1 class="text-2xl font-bold text-slate-900">Automated Reporting</h1>
                <p class="text-slate-600 mt-2">Generate monthly income statements by category.</p>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 print:hidden">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Filter Report</h2>
                <form method="GET" action="reports.php" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="month" class="block text-sm font-medium text-slate-700 mb-1">Month</label>
                        <select
                            id="month"
                            name="month"
                            required
                            class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition min-w-[160px]"
                        >
                            <?php foreach ($monthNames as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $month === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="year" class="block text-sm font-medium text-slate-700 mb-1">Year</label>
                        <select
                            id="year"
                            name="year"
                            required
                            class="rounded-lg border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-slate-600 focus:ring-2 focus:ring-slate-600 focus:ring-offset-0 outline-none transition min-w-[120px]"
                        >
                            <?php foreach ($yearOptions as $yearOption): ?>
                                <option value="<?= (int) $yearOption ?>" <?= $year === $yearOption ? 'selected' : '' ?>>
                                    <?= (int) $yearOption ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 transition focus:outline-none focus:ring-2 focus:ring-slate-600 focus:ring-offset-2"
                    >
                        Generate Statement
                    </button>
                </form>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-8 print:shadow-none print:border-0">
                <div class="flex items-start justify-end mb-8 print:hidden">
                    <button
                        type="button"
                        onclick="window.print()"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                    >
                        Print Document
                    </button>
                </div>

                <div class="max-w-2xl mx-auto">
                    <div class="text-center mb-10">
                        <h2 class="text-xl font-bold text-slate-900">Income Statement</h2>
                        <p class="text-slate-600 mt-1"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">Revenues</h3>
                        <div class="space-y-2">
                            <?php if (empty($revenues)): ?>
                                <p class="text-sm text-slate-500 italic">No records for this period.</p>
                            <?php else: ?>
                                <?php foreach ($revenues as $row): ?>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-slate-700">
                                            <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="text-slate-900">
                                            <?= htmlspecialchars(formatPeso((float) $row['Total']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 mt-4 pt-3 font-bold text-slate-900">
                            <span>Total Revenues</span>
                            <span><?= htmlspecialchars(formatPeso($totalRevenues), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">Expenses</h3>
                        <div class="space-y-2">
                            <?php if (empty($expenses)): ?>
                                <p class="text-sm text-slate-500 italic">No records for this period.</p>
                            <?php else: ?>
                                <?php foreach ($expenses as $row): ?>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-slate-700">
                                            <?= htmlspecialchars($row['Category'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="text-slate-900">
                                            <?= htmlspecialchars(formatPeso((float) $row['Total']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 mt-4 pt-3 font-bold text-slate-900">
                            <span>Total Expenses</span>
                            <span><?= htmlspecialchars(formatPeso($totalExpenses), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <div class="flex justify-between border-t-2 border-slate-300 pt-4 text-lg font-bold text-blue-600">
                        <span>Net Income</span>
                        <span><?= htmlspecialchars(formatPeso($netIncome), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
