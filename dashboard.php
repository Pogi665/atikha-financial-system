<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

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

    if ($isExecutive) {
        $budgetUtil = budget_utilization($pdo, $currentYear, $currentMonth);
        $budgetOverruns = budget_overrun_categories($pdo, $currentYear, $currentMonth);
        $budgetUpcoming = budget_upcoming_totals($pdo, 3);

        $history = forecast_fetch_history($pdo);
        $cashFlowSeries = $history['series'];

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
    }
} catch (PDOException $e) {
    error_log('Dashboard totals failed: ' . $e->getMessage());
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

layout_begin('Dashboard', $activePage, ['https://cdn.jsdelivr.net/npm/chart.js']);

if ($isExecutive):
?>

<div>
    <h1 class="text-2xl font-bold text-slate-900">Executive Dashboard</h1>
    <p class="text-slate-600 mt-2">Leadership overview of financial health, cash flow, and predictive insights.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
    <div class="exec-card min-w-0">
        <p class="exec-kpi-label">Total Incoming Funds</p>
        <p class="exec-kpi-value text-emerald-700 mt-2"><?= htmlspecialchars(format_peso($totalFunds), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="exec-card min-w-0">
        <p class="exec-kpi-label">Total Expenditures</p>
        <p class="exec-kpi-value text-red-700 mt-2"><?= htmlspecialchars(format_peso($totalExpenses), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="exec-card min-w-0">
        <p class="exec-kpi-label">Net Balance</p>
        <p class="exec-kpi-value text-blue-900 mt-2"><?= htmlspecialchars(format_peso($netBalance), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="exec-card min-w-0">
        <p class="exec-kpi-label">Current Budget Utilization</p>
        <p class="exec-kpi-value <?= $utilColor ?> mt-2">
            <?= $utilPct !== null ? htmlspecialchars(number_format($utilPct, 1) . '%', ENT_QUOTES, 'UTF-8') : '—' ?>
        </p>
        <p class="text-xs text-slate-500 mt-2">
            <?= htmlspecialchars(format_peso($budgetUtil['spent']), ENT_QUOTES, 'UTF-8') ?>
            of <?= htmlspecialchars(format_peso($budgetUtil['budgeted']), ENT_QUOTES, 'UTF-8') ?> budgeted this month
        </p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <section class="exec-card overflow-hidden">
        <h2 class="exec-section-title mb-4">Cash Flow</h2>
        <p class="text-sm text-slate-500 mb-4">Monthly incoming funds vs. expenditures (last 12 months)</p>
        <div class="exec-chart-wrap">
            <canvas id="cashFlowChart"></canvas>
        </div>
    </section>
    <section class="exec-card overflow-hidden">
        <h2 class="exec-section-title mb-4">Expense Breakdown</h2>
        <p class="text-sm text-slate-500 mb-4">Share of spending by category (trailing 12 months)</p>
        <div class="exec-chart-wrap">
            <canvas id="expenseDoughnut"></canvas>
        </div>
    </section>
</div>

<section class="exec-card overflow-hidden !p-0">
    <div class="px-8 py-6 border-b border-slate-200 flex items-start justify-between gap-4">
        <div>
            <h2 class="exec-section-title">Predictive Analytics</h2>
            <p id="exec-forecast-meta" class="text-sm text-slate-500 mt-1">Loading AI expense forecast and budget outlook…</p>
        </div>
        <div class="text-right shrink-0">
            <button
                type="button"
                id="btn-refresh-forecast"
                <?= $canRefresh && $aiConfigured ? '' : 'disabled' ?>
                class="exec-btn-primary text-sm disabled:bg-slate-300 disabled:cursor-not-allowed"
            >
                Refresh Forecast
            </button>
            <?php if (!$aiConfigured): ?>
                <p class="text-xs text-slate-400 mt-1.5">Add a Gemini API key to config.php</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="exec-forecast-loading" class="px-8 py-16 flex flex-col items-center justify-center gap-3">
        <div class="h-8 w-8 rounded-full border-2 border-slate-200 border-t-blue-900 animate-spin"></div>
        <p class="text-sm text-slate-500">Analyzing financial history…</p>
    </div>

    <div id="exec-forecast-empty" class="hidden px-8 py-16 text-center">
        <p class="text-sm font-medium text-slate-700">Not enough history to forecast yet.</p>
        <p id="exec-forecast-empty-detail" class="text-sm text-slate-500 mt-1"></p>
    </div>

    <div id="exec-forecast-body" class="hidden px-8 pb-8 space-y-6">
        <div id="exec-forecast-note" class="hidden exec-alert-warning"></div>

        <div id="exec-budget-alerts" class="space-y-2"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-slate-50 rounded-xl border border-slate-200 p-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">Projected Outflow</h3>
                <div id="exec-projection-table" class="space-y-2 text-sm"></div>
                <div class="mt-4 pt-4 border-t border-slate-200">
                    <p class="text-xs text-slate-500">Cash runway</p>
                    <p id="exec-runway" class="text-lg font-semibold text-blue-900 mt-1">—</p>
                </div>
            </div>
            <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Budget Reallocation</p>
                        <span id="exec-forecast-risk" class="hidden rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"></span>
                    </div>
                    <p id="exec-forecast-reallocation" class="text-sm text-slate-700 leading-relaxed"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Funding Risk</p>
                    <p id="exec-forecast-funding-risk" class="text-sm text-slate-700 leading-relaxed"></p>
                </div>
            </div>
        </div>

        <div id="exec-predictive-budget" class="bg-slate-50 rounded-xl border border-slate-200 p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">Predictive Budget Comparison</h3>
            <div id="exec-budget-compare" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm"></div>
        </div>
    </div>
</section>

<?php
else:
?>

<div>
    <h1 class="text-2xl font-bold text-slate-900">Financial Overview</h1>
    <p class="text-slate-600 mt-2">Real-time summary of incoming funds and expenses.</p>
</div>

<section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Predictive Forecast</h2>
            <p id="forecast-meta" class="text-sm text-slate-500 mt-1">Projecting the next six months of outflow.</p>
        </div>
        <div class="text-right shrink-0">
            <button
                type="button"
                id="btn-refresh-forecast"
                <?= $canRefresh && $aiConfigured ? '' : 'disabled' ?>
                class="rounded-lg bg-indigo-700 hover:bg-indigo-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold py-2.5 px-4 text-sm transition"
            >
                Refresh Forecast
            </button>
            <?php if (!$aiConfigured): ?>
                <p class="text-xs text-slate-400 mt-1.5">Add a Gemini API key to config.php</p>
            <?php elseif (!$canRefresh): ?>
                <p class="text-xs text-slate-400 mt-1.5">Recalculation is limited to Management</p>
            <?php endif; ?>
        </div>
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

        <div class="border-t border-slate-200 bg-slate-50 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-slate-900">AI Financial Advisory</h3>
                <span id="forecast-risk" class="hidden rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"></span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-lg border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Budget Reallocation</p>
                    <p id="forecast-reallocation" class="text-sm text-slate-700 mt-2 leading-relaxed"></p>
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Funding Risk</p>
                    <p id="forecast-funding-risk" class="text-sm text-slate-700 mt-2 leading-relaxed"></p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <p class="text-sm text-slate-500">Total Incoming Funds</p>
        <p class="text-3xl font-bold text-green-600 mt-2"><?= htmlspecialchars(format_peso($totalFunds), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <p class="text-sm text-slate-500">Total Expenses</p>
        <p class="text-3xl font-bold text-red-600 mt-2"><?= htmlspecialchars(format_peso($totalExpenses), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <p class="text-sm text-slate-500">Net Balance</p>
        <p class="text-3xl font-bold text-blue-600 mt-2"><?= htmlspecialchars(format_peso($netBalance), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>

<section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Income vs Expenses</h2>
    <div class="relative h-80 w-full">
        <canvas id="financeChart"></canvas>
    </div>
</section>

<?php endif;

$scripts = '';

if ($isExecutive) {
    $jsCashFlow = json_encode($cashFlowSeries);
    $jsBreakdown = json_encode($expenseBreakdown);
    $jsOverruns = json_encode($budgetOverruns);
    $jsUpcoming = json_encode($budgetUpcoming);
    $jsByCategory = json_encode($budgetUtil['by_category']);
    $jsCsrf = json_encode($csrfToken);
    $jsCanRefresh = json_encode($canRefresh && $aiConfigured);

    $scripts = <<<JS
<script>
(function () {
    const cashFlow = {$jsCashFlow};
    const breakdown = {$jsBreakdown};
    const budgetOverruns = {$jsOverruns};
    const budgetUpcoming = {$jsUpcoming};
    const budgetByCategory = {$jsByCategory};

    const monthLabels = cashFlow.map(function (p) {
        const parts = String(p.month).split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, 1)
            .toLocaleDateString('en-PH', { month: 'short', year: '2-digit' });
    });

    new Chart(document.getElementById('cashFlowChart'), {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [
                {
                    label: 'Incoming Funds',
                    data: cashFlow.map(function (p) { return p.inflow; }),
                    backgroundColor: 'rgba(22, 163, 74, 0.85)',
                    borderRadius: 4,
                },
                {
                    label: 'Expenditures',
                    data: cashFlow.map(function (p) { return p.outflow; }),
                    backgroundColor: 'rgba(220, 38, 38, 0.85)',
                    borderRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 4, bottom: 4 } },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' ' + ctx.dataset.label + ': ₱' + Number(ctx.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (v) { return '₱' + Number(v).toLocaleString('en-PH'); },
                    },
                },
            },
        },
    });

    const doughnutColors = [
        'rgb(30, 58, 138)', 'rgb(37, 99, 235)', 'rgb(59, 130, 246)',
        'rgb(100, 116, 139)', 'rgb(148, 163, 184)', 'rgb(71, 85, 105)',
        'rgb(51, 65, 85)', 'rgb(15, 23, 42)', 'rgb(203, 213, 225)',
    ];

    new Chart(document.getElementById('expenseDoughnut'), {
        type: 'doughnut',
        data: {
            labels: breakdown.labels,
            datasets: [{
                data: breakdown.amounts,
                backgroundColor: doughnutColors.slice(0, breakdown.labels.length),
                borderWidth: 2,
                borderColor: '#ffffff',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12 } },
            },
        },
    });

    const csrfToken = {$jsCsrf};
    const canRefresh = {$jsCanRefresh};

    const loading = document.getElementById('exec-forecast-loading');
    const empty = document.getElementById('exec-forecast-empty');
    const emptyDetail = document.getElementById('exec-forecast-empty-detail');
    const body = document.getElementById('exec-forecast-body');
    const meta = document.getElementById('exec-forecast-meta');
    const note = document.getElementById('exec-forecast-note');
    const alerts = document.getElementById('exec-budget-alerts');
    const projectionTable = document.getElementById('exec-projection-table');
    const runway = document.getElementById('exec-runway');
    const riskBadge = document.getElementById('exec-forecast-risk');
    const reallocation = document.getElementById('exec-forecast-reallocation');
    const fundingRisk = document.getElementById('exec-forecast-funding-risk');
    const budgetCompare = document.getElementById('exec-budget-compare');
    const refreshButton = document.getElementById('btn-refresh-forecast');

    const riskClasses = {
        LOW: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        MEDIUM: 'bg-amber-50 text-amber-800 border-amber-300',
        HIGH: 'bg-red-50 text-red-700 border-red-300',
    };

    function peso(value) {
        return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function monthLabel(period) {
        const parts = String(period).split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, 1)
            .toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
    }

    function show(el, visible) { el.classList.toggle('hidden', !visible); }

    function renderStaticAlerts() {
        alerts.innerHTML = '';
        budgetOverruns.forEach(function (row) {
            const div = document.createElement('div');
            div.className = 'exec-alert-over';
            div.textContent = row.category + ' is over budget by ' + peso(row.over_by)
                + ' (' + peso(row.spent) + ' spent vs ' + peso(row.budgeted) + ' budgeted).';
            alerts.appendChild(div);
        });
    }

    function renderBudgetCompare(projection) {
        budgetCompare.innerHTML = '';
        projection.slice(0, 3).forEach(function (point, index) {
            const budgetTotal = budgetUpcoming[index] ? budgetUpcoming[index].total : 0;
            const projected = point.projected_outflow;
            const diff = projected - budgetTotal;
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg border border-slate-200 p-4';
            card.innerHTML = '<p class="font-medium text-slate-900">' + monthLabel(point.month) + '</p>'
                + '<p class="text-slate-600 mt-1">Projected: ' + peso(projected) + '</p>'
                + '<p class="text-slate-600">Budget: ' + peso(budgetTotal) + '</p>'
                + '<p class="mt-2 font-semibold ' + (diff > 0 ? 'text-red-700' : 'text-emerald-700') + '">'
                + (diff > 0 ? 'Over by ' : 'Under by ') + peso(Math.abs(diff)) + '</p>';
            budgetCompare.appendChild(card);
        });
    }

    function renderTrendWarnings(categories) {
        if (!Array.isArray(categories)) return;
        const budgetMap = {};
        budgetByCategory.forEach(function (row) { budgetMap[row.category] = row; });

        categories.forEach(function (cat) {
            if (cat.trend !== 'rising') return;
            const budgetRow = budgetMap[cat.category];
            if (!budgetRow || budgetRow.budgeted <= 0) return;
            if (budgetRow.spent >= budgetRow.budgeted * 0.85) {
                const div = document.createElement('div');
                div.className = 'exec-alert-warning';
                div.textContent = cat.category + ' is trending upward and approaching its monthly budget limit.';
                alerts.appendChild(div);
            }
        });
    }

    function renderExecutive(data) {
        show(loading, false);

        if (data.state === 'insufficient') {
            emptyDetail.textContent = 'Record expenses across at least two different months.';
            show(body, false);
            show(empty, true);
            meta.textContent = 'Waiting on more history';
            return;
        }

        show(empty, false);
        show(body, true);
        renderStaticAlerts();
        renderTrendWarnings(data.categories);

        if (data.note) {
            note.textContent = data.note;
            show(note, true);
        } else {
            show(note, false);
        }

        const topThree = (data.projection || []).slice(0, 3);
        projectionTable.innerHTML = topThree.map(function (p) {
            return '<div class="flex justify-between"><span>' + monthLabel(p.month) + '</span><span class="font-medium">' + peso(p.projected_outflow) + '</span></div>';
        }).join('');

        const runwayMonths = data.metrics && data.metrics.runway_months;
        runway.textContent = runwayMonths != null ? runwayMonths + ' months at current burn' : 'Not available';

        const advisory = data.advisory || {};
        const level = advisory.risk_level;
        if (level && riskClasses[level]) {
            riskBadge.className = 'rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ' + riskClasses[level];
            riskBadge.textContent = level + ' risk';
            show(riskBadge, true);
        } else {
            show(riskBadge, false);
        }

        reallocation.textContent = advisory.reallocation_suggestion || 'No reallocation advice available.';
        fundingRisk.textContent = advisory.funding_risk || 'No funding risk assessment available.';
        renderBudgetCompare(data.projection || []);

        meta.textContent = data.generated_at
            ? 'Forecast generated ' + data.generated_at
            : 'Six-month expense projection loaded';
    }

    function loadForecast(isRefresh) {
        const requestBody = new URLSearchParams();
        requestBody.set('action', isRefresh ? 'refresh' : 'load');
        requestBody.set('csrf_token', csrfToken);

        if (refreshButton) {
            refreshButton.disabled = true;
            refreshButton.textContent = isRefresh ? 'Refreshing…' : refreshButton.textContent;
        }

        fetch('forecast_ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: requestBody.toString(),
        })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: 'Forecast request failed.' }; })
            .then(function (payload) {
                if (refreshButton) {
                    refreshButton.disabled = !canRefresh;
                    refreshButton.textContent = 'Refresh Forecast';
                }
                if (!payload.ok) {
                    emptyDetail.textContent = payload.error || 'Unable to load forecast.';
                    show(body, false);
                    show(empty, true);
                    show(loading, false);
                    return;
                }
                renderExecutive(payload.data);
            });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', function () { loadForecast(true); });
    }
    loadForecast(false);
})();
</script>
JS;
} else {
    $jsIncome = json_encode($totalFunds);
    $jsExpenses = json_encode($totalExpenses);
    $jsCsrf = json_encode($csrfToken);
    $jsCanRefresh = json_encode($canRefresh && $aiConfigured);

    $scripts = <<<JS
<script>
    const totalIncome = {$jsIncome};
    const totalExpenses = {$jsExpenses};

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
                        label: (ctx) => ' ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (value) => '₱' + Number(value).toLocaleString('en-PH') },
                },
            },
        },
    });
</script>
<script>
(function () {
    const csrfToken = {$jsCsrf};
    const canRefresh = {$jsCanRefresh};
    const loading = document.getElementById('forecast-loading');
    const empty = document.getElementById('forecast-empty');
    const emptyDetail = document.getElementById('forecast-empty-detail');
    const body = document.getElementById('forecast-body');
    const meta = document.getElementById('forecast-meta');
    const note = document.getElementById('forecast-note');
    const riskBadge = document.getElementById('forecast-risk');
    const reallocation = document.getElementById('forecast-reallocation');
    const fundingRisk = document.getElementById('forecast-funding-risk');
    const refreshButton = document.getElementById('btn-refresh-forecast');
    const riskClasses = {
        LOW: 'bg-emerald-50 text-emerald-700 border-emerald-200',
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
    function show(element, visible) { element.classList.toggle('hidden', !visible); }
    function showNote(message) { note.textContent = message || ''; show(note, Boolean(message)); }

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
                    { label: 'Historical outflow', data: historical, borderColor: 'rgb(220, 38, 38)', backgroundColor: 'rgba(220, 38, 38, 0.08)', borderWidth: 2, pointRadius: 3, tension: 0.3, fill: true },
                    { label: 'Projected outflow', data: projected, borderColor: 'rgb(79, 70, 229)', backgroundColor: 'rgba(79, 70, 229, 0.06)', borderWidth: 2, borderDash: [6, 4], pointRadius: 3, tension: 0.3, fill: true },
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
        if (data.state === 'degraded') { meta.textContent = 'Trailing three-month average · AI advisory unavailable'; return; }
        const generated = data.generated_at ? new Date(data.generated_at.replace(' ', 'T')) : null;
        const stamp = generated && !isNaN(generated) ? generated.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
        meta.textContent = stamp ? 'Generated ' + stamp + ' · ' + (data.state === 'cached' ? 'cached for 24 hours' : 'just now') : 'Six-month projection · ' + (data.state === 'cached' ? 'cached for 24 hours' : 'just now');
    }

    function renderAdvisory(advisory) {
        const level = advisory.risk_level;
        if (level && riskClasses[level]) {
            riskBadge.className = 'rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide ' + riskClasses[level];
            riskBadge.textContent = level + ' risk';
            show(riskBadge, true);
        } else { show(riskBadge, false); }
        reallocation.textContent = advisory.reallocation_suggestion || 'No reallocation advice is available for this period.';
        fundingRisk.textContent = advisory.funding_risk || 'No funding risk assessment is available for this period.';
    }

    function render(payload, isRefresh) {
        show(loading, false);
        if (!payload.ok) {
            if (isRefresh && !body.classList.contains('hidden')) { showNote(payload.error); return; }
            emptyDetail.textContent = payload.error || 'The forecast could not be loaded.';
            show(body, false); show(empty, true); return;
        }
        const data = payload.data;
        if (data.state === 'insufficient') {
            emptyDetail.textContent = 'Record expenses across at least two different months and the projection will appear here.';
            meta.textContent = 'Waiting on more history';
            show(body, false); show(empty, true); return;
        }
        show(empty, false); show(body, true);
        showNote(data.note); renderMeta(data); renderChart(data.history, data.projection); renderAdvisory(data.advisory);
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
