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

$totalFunds = 0.0;
$totalExpenses = 0.0;

try {
    $stmt = $pdo->query('SELECT COALESCE(SUM(Amount), 0) AS total FROM Incoming_Funds');
    $totalFunds = (float) $stmt->fetch()['total'];

    $stmt = $pdo->query('SELECT COALESCE(SUM(Amount), 0) AS total FROM Expenses');
    $totalExpenses = (float) $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log('Dashboard totals failed: ' . $e->getMessage());
}

$netBalance = $totalFunds - $totalExpenses;

$isAdmin = ($_SESSION['Role'] ?? '') === 'Admin';
$fullName = htmlspecialchars($_SESSION['FullName'] ?? '', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['Role'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Atikha Financial System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen min-w-[1024px] bg-slate-100">
    <aside class="fixed inset-y-0 left-0 w-64 bg-slate-800 text-slate-100 flex flex-col">
        <div class="px-6 py-6 border-b border-slate-700">
            <h2 class="text-lg font-bold tracking-tight">Atikha Finance</h2>
            <p class="text-slate-400 text-xs mt-1">Management System</p>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1">
            <a
                href="dashboard.php"
                class="block rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-medium text-white"
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
                class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
            >
                Reports
            </a>
            <?php if ($isAdmin): ?>
                <a
                    href="audit_trail.php"
                    class="block rounded-lg px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-700/50 transition"
                >
                    Audit Trail
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="ml-64 flex flex-col min-h-screen">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
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

        <main class="flex-1 p-8 space-y-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Financial Overview</h1>
                <p class="text-slate-600 mt-2">Real-time summary of incoming funds and expenses.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Incoming Funds</p>
                    <p class="text-3xl font-bold text-green-600 mt-2">
                        <?= htmlspecialchars(formatPeso($totalFunds), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <p class="text-sm text-slate-500">Total Expenses</p>
                    <p class="text-3xl font-bold text-red-600 mt-2">
                        <?= htmlspecialchars(formatPeso($totalExpenses), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <p class="text-sm text-slate-500">Net Balance</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2">
                        <?= htmlspecialchars(formatPeso($netBalance), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Income vs Expenses</h2>
                <div class="relative h-80 w-full">
                    <canvas id="financeChart"></canvas>
                </div>
            </section>
        </main>
    </div>

    <script>
        const totalIncome = <?= json_encode($totalFunds) ?>;
        const totalExpenses = <?= json_encode($totalExpenses) ?>;

        new Chart(document.getElementById('financeChart'), {
            type: 'bar',
            data: {
                labels: ['Total Income', 'Total Expenses'],
                datasets: [{
                    data: [totalIncome, totalExpenses],
                    backgroundColor: ['rgb(22, 163, 74)', 'rgb(220, 38, 38)'],
                    borderRadius: 6,
                    maxBarThickness: 100,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ₱' + ctx.parsed.y.toLocaleString('en-PH', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '₱' + Number(value).toLocaleString('en-PH'),
                        },
                    },
                },
            },
        });
    </script>
</body>
</html>
