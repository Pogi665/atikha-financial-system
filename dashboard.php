<?php
session_start();

require_once __DIR__ . '/includes/require_role.php';
require_login();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/gemini_client.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/forecast_query.php';
require_once __DIR__ . '/includes/budget_query.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$activePage = 'dashboard';
$flags = layout_role_flags();
$isExecutive = $flags['isExecutive'];
$canRefresh = $flags['canRefresh'];
$aiConfigured = gemini_is_configured();

$totalFunds = 0.0;
$totalExpenses = 0.0;
$netBalance = 0.0;
$budgetUtil = ['spent' => 0.0, 'budgeted' => 0.0, 'pct' => 0.0, 'by_category' => []];
$budgetOverruns = [];
$budgetAllocations = [];
$budgetDataAvailable = false;
$totalsAvailable = false;
$cashFlowAvailable = false;
$breakdownAvailable = false;
$budgetUpcomingAvailable = false;
$budgetUpcoming = [];
$cashFlowSeries = [];
$expenseBreakdown = ['labels' => [], 'amounts' => []];

$currentYear = (int) date('Y');
$currentMonth = (int) date('n');

try {
    $stmt = $pdo->query('SELECT COALESCE(SUM(Amount), 0) AS total FROM Incoming_Funds');
    $totalFunds = (float) $stmt->fetch()['total'];

    $stmt = $pdo->query('SELECT COALESCE(SUM(Amount), 0) AS total FROM Expenses');
    $totalExpenses = (float) $stmt->fetch()['total'];

    $netBalance = $totalFunds - $totalExpenses;
    $totalsAvailable = true;

    if ($isExecutive) {
        $budgetUtil = budget_utilization($pdo, $currentYear, $currentMonth);
        $budgetDataAvailable = true;
        $budgetUpcoming = budget_upcoming_totals($pdo, 3);
        $budgetUpcomingAvailable = true;

        $history = forecast_fetch_history($pdo);
        $cashFlowSeries = $history['series'];
        $cashFlowAvailable = true;

        $start = date('Y-m-d', strtotime('-12 months'));
        $stmt = $pdo->prepare(
            'SELECT Category, SUM(Amount) AS Total
             FROM Expenses
             WHERE Date_Incurred >= :start
             GROUP BY Category
             ORDER BY Total DESC'
        );
        $stmt->execute(['start' => $start]);
        $rows = $stmt->fetchAll();

        $labels = [];
        $amounts = [];
        $other = 0.0;
        foreach ($rows as $index => $row) {
            $amount = (float) $row['Total'];
            if ($index < 8) {
                $labels[] = (string) $row['Category'];
                $amounts[] = round($amount, 2);
            } else {
                $other += $amount;
            }
        }
        if ($other > 0) {
            $labels[] = 'Other';
            $amounts[] = round($other, 2);
        }
        $expenseBreakdown = ['labels' => $labels, 'amounts' => $amounts];
        $breakdownAvailable = true;
    }
} catch (PDOException $e) {
    error_log('Dashboard totals failed: ' . $e->getMessage());
}

// Admin trends are independent of the all-time totals and forecast service.
if (!$isExecutive) {
    require_once __DIR__ . '/includes/dashboard_query.php';
    $kpiMonths = forecast_month_window(6);
    $kpiPeriod = (new DateTimeImmutable($kpiMonths[0] . '-01'))->format('M Y')
        . ' – ' . (new DateTimeImmutable(end($kpiMonths) . '-01'))->format('M Y');
    $kpiSeries = [];
    $kpiTrendsAvailable = false;
    try {
        $kpiSeries = dashboard_kpi_series($pdo, $kpiMonths);
        $kpiTrendsAvailable = true;
    } catch (Throwable $e) {
        error_log('Dashboard KPI trends failed: ' . $e->getMessage());
    }
}

$csrfToken = csrf_token();

$utilPct = $budgetUtil['pct'];
$utilColor = 'text-blue-800';
if ($utilPct !== null) {
    if ($utilPct > 100) {
        $utilColor = 'text-red-700';
    } elseif ($utilPct >= 80) {
        $utilColor = 'text-amber-600';
    } else {
        $utilColor = 'text-emerald-700';
    }
}

layout_begin('Dashboard', $activePage, ['https://cdn.jsdelivr.net/npm/chart.js'],
    $isExecutive ? '<link rel="stylesheet" href="assets/css/management-dashboard.css?v=' . filemtime(__DIR__ . '/assets/css/management-dashboard.css') . '">' : '');

if ($isExecutive):
    include __DIR__ . '/includes/management_dashboard.php';

else:
?>

<div class="bg-slate-50 p-6 rounded-xl space-y-8">
<div>
    <h1 class="text-2xl font-bold text-slate-900">Financial Overview</h1>
    <p class="text-slate-600 mt-2">Real-time summary of incoming funds and expenses.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
        <p class="text-sm font-semibold text-slate-500">Total Incoming Funds</p>
        <p class="text-xl xl:text-2xl font-black tracking-tighter text-slate-800 mt-1 break-words"><?= htmlspecialchars(format_peso($totalFunds), ENT_QUOTES, 'UTF-8') ?></p>
        <p id="kpi-income-caption" class="text-xs text-slate-500 mt-4">Monthly incoming funds · <?= htmlspecialchars($kpiPeriod, ENT_QUOTES, 'UTF-8') ?></p>
        <div id="kpi-income-chart" class="relative h-16 w-full mt-4<?= $kpiTrendsAvailable ? '' : ' hidden' ?>">
            <canvas id="kpiIncomeChart" role="img" aria-labelledby="kpi-income-caption" aria-describedby="kpi-income-values"></canvas>
        </div>
        <p id="kpi-income-status" role="status" class="text-xs text-slate-500 mt-4<?= $kpiTrendsAvailable ? ' hidden' : '' ?>">Trend unavailable</p>
        <ul id="kpi-income-values" class="sr-only">
            <?php foreach ($kpiSeries as $point): ?>
            <li><?= htmlspecialchars((new DateTimeImmutable($point['month'] . '-01'))->format('M Y') . ': ' . format_peso($point['income']), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
        <p class="text-sm font-semibold text-slate-500">Total Expenses</p>
        <p class="text-xl xl:text-2xl font-black tracking-tighter text-slate-800 mt-1 break-words"><?= htmlspecialchars(format_peso($totalExpenses), ENT_QUOTES, 'UTF-8') ?></p>
        <p id="kpi-expenses-caption" class="text-xs text-slate-500 mt-4">Monthly expenses · <?= htmlspecialchars($kpiPeriod, ENT_QUOTES, 'UTF-8') ?></p>
        <div id="kpi-expenses-chart" class="relative h-16 w-full mt-4<?= $kpiTrendsAvailable ? '' : ' hidden' ?>">
            <canvas id="kpiExpensesChart" role="img" aria-labelledby="kpi-expenses-caption" aria-describedby="kpi-expenses-values"></canvas>
        </div>
        <p id="kpi-expenses-status" role="status" class="text-xs text-slate-500 mt-4<?= $kpiTrendsAvailable ? ' hidden' : '' ?>">Trend unavailable</p>
        <ul id="kpi-expenses-values" class="sr-only">
            <?php foreach ($kpiSeries as $point): ?>
            <li><?= htmlspecialchars((new DateTimeImmutable($point['month'] . '-01'))->format('M Y') . ': ' . format_peso($point['expenses']), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
        <p class="text-sm font-semibold text-slate-500">Net Balance</p>
        <p class="text-xl xl:text-2xl font-black tracking-tighter text-slate-800 mt-1 break-words"><?= htmlspecialchars(format_peso($netBalance), ENT_QUOTES, 'UTF-8') ?></p>
        <p id="kpi-balance-caption" class="text-xs text-slate-500 mt-4">Month-end balance · <?= htmlspecialchars($kpiPeriod, ENT_QUOTES, 'UTF-8') ?></p>
        <div id="kpi-balance-chart" class="relative h-16 w-full mt-4<?= $kpiTrendsAvailable ? '' : ' hidden' ?>">
            <canvas id="kpiBalanceChart" role="img" aria-labelledby="kpi-balance-caption" aria-describedby="kpi-balance-values"></canvas>
        </div>
        <p id="kpi-balance-status" role="status" class="text-xs text-slate-500 mt-4<?= $kpiTrendsAvailable ? ' hidden' : '' ?>">Trend unavailable</p>
        <ul id="kpi-balance-values" class="sr-only">
            <?php foreach ($kpiSeries as $point): ?>
            <li><?= htmlspecialchars((new DateTimeImmutable($point['month'] . '-01'))->format('M Y') . ': ' . format_peso($point['balance']), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-6 border-b border-slate-200 flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Predictive Forecast</h2>
            <p id="forecast-meta" class="text-sm text-slate-500 mt-1">Projecting the next six months of outflow.</p>
            <span id="forecast-offline" role="status" class="hidden inline-block mt-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">Trailing 3-Month Baseline (Offline Mode)</span>
        </div>
        <?php if ($canRefresh): ?>
        <div class="text-right shrink-0">
            <button
                type="button"
                id="btn-refresh-forecast"
                <?= $aiConfigured ? '' : 'disabled' ?>
                class="rounded-lg bg-indigo-700 hover:bg-indigo-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold py-2.5 px-4 text-sm transition"
            >
                Refresh Forecast
            </button>
            <?php if (!$aiConfigured): ?>
                <p class="text-xs text-slate-400 mt-1.5">Add a Gemini API key to config.php</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div id="forecast-loading" class="px-6 py-16 flex flex-col items-center justify-center gap-3">
        <div class="h-8 w-8 rounded-full border-2 border-slate-200 border-t-slate-700 animate-spin"></div>
        <p class="text-sm text-slate-500">Analyzing your last 12 months of activity…</p>
    </div>

    <div id="forecast-empty" class="hidden px-6 py-16 text-center">
        <p class="text-sm font-medium text-slate-700">Not enough history to forecast yet.</p>
        <p id="forecast-empty-detail" class="text-sm text-slate-500 mt-1">
            Record expenses across at least two different months and the projection will appear here.
        </p>
    </div>

    <div id="forecast-body" class="hidden">
        <div class="p-6">
            <div id="forecast-note" class="hidden mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"></div>
            <div class="relative h-80 w-full">
                <canvas id="forecastChart"></canvas>
            </div>
        </div>

    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div id="forecast-advisory" class="hidden lg:col-span-2 min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-900">AI Financial Advisory</h3>
            <span id="forecast-risk" class="hidden rounded-md border px-2 py-1 text-xs font-bold uppercase tracking-wide"></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="min-w-0 bg-slate-50 border border-slate-100 rounded-xl p-5 text-left">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" style="background-color: #fef3c7; color: #d97706;">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 m-0">Budget Reallocation</h3>
                </div>
                <p id="forecast-reallocation" class="text-sm text-slate-600 leading-relaxed break-words"></p>
            </div>
            <div class="min-w-0 bg-slate-50 border border-slate-100 rounded-xl p-5 text-left">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" style="background-color: #e0f2fe; color: #0284c7;">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 m-0">Funding Risk</h3>
                </div>
                <p id="forecast-funding-risk" class="text-sm text-slate-600 leading-relaxed break-words"></p>
            </div>
        </div>
    </div>

    <section class="lg:col-span-1 min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6" aria-labelledby="expense-breakdown-title">
        <div class="flex items-center justify-between mb-2">
            <h2 id="expense-breakdown-title" class="text-base font-bold text-slate-800 truncate" style="max-width: 65%;">Expense Breakdown</h2>
            <a href="reports.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 whitespace-nowrap shrink-0">View Report &rarr;</a>
        </div>
        <p class="text-xs text-slate-500 mb-4 pb-2 border-b border-slate-100">Spending by category &middot; last 12 completed months.</p>
        <p id="expense-breakdown-status" class="text-sm text-slate-500" role="status">Loading expense breakdown…</p>
        <div id="expense-breakdown-chart" class="hidden relative h-64 w-full">
            <canvas id="expenseBreakdownChart" role="img" aria-label="Expense breakdown by category. Category amounts are listed below."></canvas>
        </div>
        <ul id="expense-breakdown-list" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm" aria-label="Expense category amounts"></ul>
    </section>
</div>

</div>

<?php endif;

$scripts = '';

if ($isExecutive) {
    $managementData = json_encode([
        'cashFlow' => $cashFlowSeries,
        'cashFlowAvailable' => $cashFlowAvailable,
        'breakdown' => $expenseBreakdown,
        'breakdownAvailable' => $breakdownAvailable,
        'budgetUpcoming' => $budgetUpcomingAvailable ? $budgetUpcoming : [],
        'budgetByCategory' => $budgetDataAvailable ? $budgetUtil['by_category'] : [],
        'csrfToken' => $csrfToken,
        'canRefresh' => $canRefresh && $aiConfigured,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    $scripts = '<script type="application/json" id="management-dashboard-data">' . $managementData . '</script>'
        . '<script src="assets/js/management-dashboard.js?v=' . filemtime(__DIR__ . '/assets/js/management-dashboard.js') . '"></script>';

} else {
    $jsIncome = json_encode($totalFunds);
    $jsExpenses = json_encode($totalExpenses);
    $jsCsrf = json_encode($csrfToken);
    $jsCanRefresh = json_encode($canRefresh && $aiConfigured);
    $jsKpiSeries = json_encode($kpiSeries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

    $scripts = <<<JS
<script>
(function () {
    const series = {$jsKpiSeries};
    const charts = [
        { key: 'income', id: 'kpiIncomeChart', label: 'Monthly incoming funds', color: '#059669', fill: 'rgba(5, 150, 105, 0.08)' },
        { key: 'expenses', id: 'kpiExpensesChart', label: 'Monthly expenses', color: '#e11d48', fill: 'rgba(225, 29, 72, 0.08)' },
        { key: 'balance', id: 'kpiBalanceChart', label: 'Month-end balance', color: '#4f46e5', fill: 'rgba(79, 70, 229, 0.08)' },
    ];
    charts.forEach(function (chart) {
        const canvas = document.getElementById(chart.id);
        const wrap = document.getElementById('kpi-' + chart.key + '-chart');
        const status = document.getElementById('kpi-' + chart.key + '-status');
        if (!canvas || !wrap || !status) return;
        try {
            if (typeof Chart !== 'function' || !Array.isArray(series) || series.length !== 6 ||
                series.some((point) => !point || !/^\d{4}-\d{2}$/.test(point.month) ||
                    typeof point[chart.key] !== 'number' || !Number.isFinite(point[chart.key]))) {
                throw new Error('KPI trend unavailable');
            }
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: series.map(function (point) {
                        const parts = point.month.split('-');
                        return new Date(Number(parts[0]), Number(parts[1]) - 1, 1)
                            .toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{ label: chart.label, data: series.map((point) => point[chart.key]),
                        borderColor: chart.color, backgroundColor: chart.fill, borderWidth: 2,
                        tension: 0, fill: true, pointRadius: 0, pointHoverRadius: 0, pointHitRadius: 8 }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false }, tooltip: { callbacks: {
                        label: (ctx) => ctx.dataset.label + ': ₱' + Number(ctx.parsed.y)
                            .toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    } } },
                    scales: {
                        x: { display: false, grid: { display: false }, border: { display: false } },
                        y: { display: false, grid: { display: false }, border: { display: false } },
                    },
                },
            });
        } catch (error) {
            if (typeof Chart === 'function' && typeof Chart.getChart === 'function') {
                const partial = Chart.getChart(canvas);
                if (partial) partial.destroy();
            }
            wrap.classList.add('hidden');
            status.classList.remove('hidden');
        }
    });
})();

(function () {
    const csrfToken = {$jsCsrf};
    const canRefresh = {$jsCanRefresh};
    const loading = document.getElementById('forecast-loading');
    const empty = document.getElementById('forecast-empty');
    const emptyDetail = document.getElementById('forecast-empty-detail');
    const body = document.getElementById('forecast-body');
    const advisoryCard = document.getElementById('forecast-advisory');
    const meta = document.getElementById('forecast-meta');
    const note = document.getElementById('forecast-note');
    const offlineBadge = document.getElementById('forecast-offline');
    const breakdownStatus = document.getElementById('expense-breakdown-status');
    const breakdownWrap = document.getElementById('expense-breakdown-chart');
    const breakdownList = document.getElementById('expense-breakdown-list');
    let breakdownChart = null;
    const riskBadge = document.getElementById('forecast-risk');
    const reallocation = document.getElementById('forecast-reallocation');
    const fundingRisk = document.getElementById('forecast-funding-risk');
    const refreshButton = document.getElementById('btn-refresh-forecast');
    const riskClasses = {
        LOW: 'bg-emerald-50 text-emerald-600 border-emerald-200',
        MEDIUM: 'bg-amber-50 text-amber-800 border-amber-300',
        HIGH: 'bg-red-50 text-red-700 border-red-300',
    };
    let forecastChart = null;

    function peso(value) {
        return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function monthLabel(period) {
        const parts = String(period).split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, 1).toLocaleDateString('en-PH', { month: 'short', year: '2-digit' });
    }
    function show(element, visible) {
        element.classList.toggle('hidden', !visible);
        if (element === body) advisoryCard.classList.toggle('hidden', !visible);
    }
    function showNote(message) { note.textContent = message || ''; show(note, Boolean(message)); }

    function breakdownMessage(message) {
        breakdownStatus.textContent = message;
        show(breakdownStatus, Boolean(message));
    }

    function renderBreakdown(categories) {
        if (breakdownChart) { breakdownChart.destroy(); breakdownChart = null; }
        breakdownList.replaceChildren();
        show(breakdownWrap, false); show(breakdownList, false);
        if (!Array.isArray(categories) || categories.some((row) =>
            !row || typeof row.category !== 'string' || !row.category.trim() ||
            typeof row.total !== 'number' || !Number.isFinite(row.total) || row.total < 0
        )) {
            breakdownMessage('Expense breakdown is currently unavailable.'); return;
        }
        const positive = categories.filter((row) => row.total > 0).slice().sort((a, b) => b.total - a.total);
        if (!positive.length) { breakdownMessage('No expense data for this period'); return; }
        const displayed = positive.slice(0, 8).map((row) => ({ category: row.category, total: row.total }));
        if (positive.length > 8) {
            displayed.push({ category: 'Other categories', total: positive.slice(8).reduce((sum, row) => sum + row.total, 0) });
        }
        const total = displayed.reduce((sum, row) => sum + row.total, 0);
        if (!Number.isFinite(total)) { breakdownMessage('Expense breakdown is currently unavailable.'); return; }
        const colors = ['#4f46e5', '#0ea5e9', '#14b8a6', '#8b5cf6', '#06b6d4', '#10b981', '#a855f7', '#64748b', '#94a3b8'];
        displayed.forEach(function (row, index) {
            const item = document.createElement('li');
            item.className = 'flex items-start gap-2 min-w-0';
            const swatch = document.createElement('span');
            swatch.className = 'mt-1 h-3 w-3 rounded-sm shrink-0';
            swatch.style.backgroundColor = colors[index];
            swatch.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.className = 'min-w-0 break-words text-slate-600';
            label.textContent = row.category + ': ' + peso(row.total) + ' (' + (row.total / total * 100).toFixed(1) + '%)';
            item.append(swatch, label);
            breakdownList.append(item);
        });
        breakdownMessage('');
        show(breakdownWrap, true); show(breakdownList, true);
        breakdownChart = new Chart(document.getElementById('expenseBreakdownChart'), {
            type: 'doughnut',
            data: {
                labels: displayed.map((row) => row.category),
                datasets: [{ data: displayed.map((row) => row.total), backgroundColor: colors.slice(0, displayed.length), borderColor: '#ffffff', borderWidth: 2 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, generateLabels: function (chart) {
                        return Chart.overrides.doughnut.plugins.legend.labels.generateLabels(chart).map(function (label) {
                            label.text = label.text.length > 32 ? label.text.slice(0, 29) + '…' : label.text;
                            return label;
                        });
                    } } },
                    tooltip: { callbacks: { label: function (ctx) {
                        return ctx.label + ': ' + peso(ctx.parsed) + ' (' + (ctx.parsed / total * 100).toFixed(1) + '%)';
                    } } },
                },
            },
        });
    }

    function renderChart(history, projection) {
        const labels = history.map((p) => monthLabel(p.month)).concat(projection.map((p) => monthLabel(p.month)));
        const historical = history.map((p) => p.outflow).concat(projection.map(() => null));
        const projected = history.map((p, i) => (i === history.length - 1 ? p.outflow : null)).concat(projection.map((p) => p.projected_outflow));
        if (forecastChart) forecastChart.destroy();
        forecastChart = new Chart(document.getElementById('forecastChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Historical outflow', data: historical, borderColor: '#e11d48', backgroundColor: 'rgba(225, 29, 72, 0.08)', borderWidth: 2, pointRadius: 3, tension: 0.3, fill: true },
                    { label: 'Projected outflow', data: projected, borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.06)', borderWidth: 2, borderDash: [6, 4], pointRadius: 3, tension: 0.3, fill: true },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (ctx) { return ctx.parsed.y === null ? null : ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y); } } } },
                scales: { y: { beginAtZero: true, ticks: { callback: (v) => '₱' + Number(v).toLocaleString('en-PH') } } },
            },
        });
    }

    function renderMeta(data) {
        if (data.state === 'degraded') { meta.textContent = 'Projecting the next six months of outflow.'; return; }
        const generated = data.generated_at ? new Date(data.generated_at.replace(' ', 'T')) : null;
        const stamp = generated && !isNaN(generated) ? generated.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
        meta.textContent = stamp ? 'Generated ' + stamp + ' · ' + (data.state === 'cached' ? 'cached for 24 hours' : 'just now') : 'Six-month projection · ' + (data.state === 'cached' ? 'cached for 24 hours' : 'just now');
    }

    function renderAdvisory(advisory) {
        const level = advisory.risk_level;
        if (level && riskClasses[level]) {
            riskBadge.className = 'rounded-md border px-2 py-1 text-xs font-bold uppercase tracking-wide ' + riskClasses[level];
            riskBadge.textContent = level + ' risk';
            show(riskBadge, true);
        } else { show(riskBadge, false); }
        reallocation.textContent = advisory.reallocation_suggestion || 'No reallocation advice is available for this period.';
        fundingRisk.textContent = advisory.funding_risk || 'No funding risk assessment is available for this period.';
    }

    function render(payload, isRefresh) {
        show(loading, false);
        if (!payload.ok) {
            breakdownMessage(isRefresh && breakdownChart ? 'Expense breakdown was not refreshed. Showing previously loaded data.' : 'Expense breakdown is currently unavailable.');
            if (isRefresh && !body.classList.contains('hidden')) { showNote(payload.error); return; }
            show(offlineBadge, false);
            emptyDetail.textContent = payload.error || 'The forecast could not be loaded.';
            show(body, false); show(empty, true); return;
        }
        const data = payload.data;
        renderBreakdown(data.categories);
        show(offlineBadge, data.state === 'degraded');
        if (data.state === 'insufficient') {
            emptyDetail.textContent = 'Record expenses across at least two different months and the projection will appear here.';
            meta.textContent = 'Waiting on more history';
            show(body, false); show(empty, true); return;
        }
        show(empty, false); show(body, true);
        showNote(data.state === 'degraded' ? '' : data.note); renderMeta(data); renderChart(data.history, data.projection); renderAdvisory(data.advisory);
    }

    function load(isRefresh) {
        const requestBody = new URLSearchParams();
        requestBody.set('action', isRefresh ? 'refresh' : 'load');
        requestBody.set('csrf_token', csrfToken);
        if (refreshButton) { refreshButton.disabled = true; if (isRefresh) refreshButton.textContent = 'Refreshing…'; }
        fetch('forecast_ai.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: requestBody.toString() })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: 'The forecast request could not be completed.' }; })
            .then(function (payload) {
                if (refreshButton) { refreshButton.disabled = !canRefresh; refreshButton.textContent = 'Refresh Forecast'; }
                render(payload, isRefresh);
            });
    }

    if (refreshButton) refreshButton.addEventListener('click', function () { showNote(''); load(true); });
    load(false);
})();
</script>
JS;
}

layout_end($scripts);
