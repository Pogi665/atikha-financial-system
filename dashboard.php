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

$totalFunds = '0.00';
$totalExpenses = '0.00';
$netBalance = '0.00';
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

$today=accounting_today();
$currentYear=(int)substr($today,0,4);$currentMonth=(int)substr($today,5,2);
$kpiMonths=accounting_months(6);$kpiSeries=[];$kpiTrendsAvailable=false;
$historyMonths=accounting_months(12);$breakdownRows=[];
$kpiPeriod=(new DateTimeImmutable($kpiMonths[0].'-01'))->format('M Y').' – '.(new DateTimeImmutable(end($kpiMonths).'-01'))->format('M Y');
require_once __DIR__.'/includes/dashboard_query.php';
try {
    accounting_read($pdo,function()use($pdo,$isExecutive,$today,$currentYear,$currentMonth,$kpiMonths,$historyMonths,
        &$totalFunds,&$totalExpenses,&$netBalance,&$totalsAvailable,&$budgetUtil,&$budgetDataAvailable,&$budgetUpcoming,&$budgetUpcomingAvailable,
        &$cashFlowSeries,&$cashFlowAvailable,&$breakdownRows,&$expenseBreakdown,&$breakdownAvailable,&$kpiSeries,&$kpiTrendsAvailable){
        try{$k=accounting_kpis($pdo,$today);$totalFunds=$k['income'];$totalExpenses=$k['expenses'];$netBalance=$k['assets'];$totalsAvailable=true;}
        catch(Throwable $e){error_log('Dashboard KPIs: '.$e->getMessage());}
        if($isExecutive){
            try{$budgetUtil=budget_utilization($pdo,$currentYear,$currentMonth);$budgetDataAvailable=true;}
            catch(Throwable $e){error_log('Dashboard budget: '.$e->getMessage());}
            try{$budgetUpcoming=budget_upcoming_totals($pdo,3);$budgetUpcomingAvailable=true;}
            catch(Throwable $e){error_log('Dashboard upcoming budget: '.$e->getMessage());}
            try{$cashFlowSeries=accounting_monthly($pdo,$historyMonths);$cashFlowAvailable=true;}
            catch(Throwable $e){error_log('Dashboard monthly activity: '.$e->getMessage());}
        }else{
            try{$kpiSeries=dashboard_kpi_series($pdo,$kpiMonths);$kpiTrendsAvailable=true;}
            catch(Throwable $e){error_log('Dashboard trends: '.$e->getMessage());}
        }
        try{
            $breakdownRows=accounting_expenses($pdo,$historyMonths[0].'-01',substr($today,0,7).'-01');
            $expenseBreakdown=['labels'=>array_column($breakdownRows,'category'),'amounts'=>array_map(static fn($r)=>(float)$r['total'],$breakdownRows),'decimals'=>array_column($breakdownRows,'total')];
            $breakdownAvailable=true;
        }catch(Throwable $e){error_log('Dashboard expense breakdown: '.$e->getMessage());}
    });
}catch(Throwable $e){$totalsAvailable=$budgetDataAvailable=$cashFlowAvailable=$breakdownAvailable=$kpiTrendsAvailable=false;error_log('Dashboard snapshot: '.$e->getMessage());}

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
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
        <p class="text-sm font-semibold text-slate-500">Total Income</p>
        <p class="text-xl xl:text-2xl font-black tracking-tighter text-slate-800 mt-1 break-words"><?= htmlspecialchars($totalsAvailable ? accounting_money($totalFunds) : 'Unavailable', ENT_QUOTES, 'UTF-8') ?></p>
        <p id="kpi-income-caption" class="text-xs text-slate-500 mt-4">Monthly income · <?= htmlspecialchars($kpiPeriod, ENT_QUOTES, 'UTF-8') ?></p>
        <div id="kpi-income-chart" class="relative h-16 w-full mt-4<?= $kpiTrendsAvailable ? '' : ' hidden' ?>">
            <canvas id="kpiIncomeChart" role="img" aria-labelledby="kpi-income-caption" aria-describedby="kpi-income-values"></canvas>
        </div>
        <p id="kpi-income-status" role="status" class="text-xs text-slate-500 mt-4<?= $kpiTrendsAvailable ? ' hidden' : '' ?>">Trend unavailable</p>
        <ul id="kpi-income-values" class="sr-only">
            <?php foreach ($kpiSeries as $point): ?>
            <li><?= htmlspecialchars((new DateTimeImmutable($point['month'] . '-01'))->format('M Y') . ': ' . accounting_money($point['income_decimal']), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
        <p class="text-sm font-semibold text-slate-500">Total Expenses</p>
        <p class="text-xl xl:text-2xl font-black tracking-tighter text-slate-800 mt-1 break-words"><?= htmlspecialchars($totalsAvailable ? accounting_money($totalExpenses) : 'Unavailable', ENT_QUOTES, 'UTF-8') ?></p>
        <p id="kpi-expenses-caption" class="text-xs text-slate-500 mt-4">Monthly expenses · <?= htmlspecialchars($kpiPeriod, ENT_QUOTES, 'UTF-8') ?></p>
        <div id="kpi-expenses-chart" class="relative h-16 w-full mt-4<?= $kpiTrendsAvailable ? '' : ' hidden' ?>">
            <canvas id="kpiExpensesChart" role="img" aria-labelledby="kpi-expenses-caption" aria-describedby="kpi-expenses-values"></canvas>
        </div>
        <p id="kpi-expenses-status" role="status" class="text-xs text-slate-500 mt-4<?= $kpiTrendsAvailable ? ' hidden' : '' ?>">Trend unavailable</p>
        <ul id="kpi-expenses-values" class="sr-only">
            <?php foreach ($kpiSeries as $point): ?>
            <li><?= htmlspecialchars((new DateTimeImmutable($point['month'] . '-01'))->format('M Y') . ': ' . accounting_money($point['expenses_decimal']), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
        <p class="text-sm font-semibold text-slate-500">Total Assets</p>
        <p class="text-xl xl:text-2xl font-black tracking-tighter text-slate-800 mt-1 break-words"><?= htmlspecialchars($totalsAvailable ? accounting_money($netBalance) : 'Unavailable', ENT_QUOTES, 'UTF-8') ?></p>
        <p id="kpi-balance-caption" class="text-xs text-slate-500 mt-4">Month-end assets · <?= htmlspecialchars($kpiPeriod, ENT_QUOTES, 'UTF-8') ?></p>
        <div id="kpi-balance-chart" class="relative h-16 w-full mt-4<?= $kpiTrendsAvailable ? '' : ' hidden' ?>">
            <canvas id="kpiBalanceChart" role="img" aria-labelledby="kpi-balance-caption" aria-describedby="kpi-balance-values"></canvas>
        </div>
        <p id="kpi-balance-status" role="status" class="text-xs text-slate-500 mt-4<?= $kpiTrendsAvailable ? ' hidden' : '' ?>">Trend unavailable</p>
        <ul id="kpi-balance-values" class="sr-only">
            <?php foreach ($kpiSeries as $point): ?>
            <li><?= htmlspecialchars((new DateTimeImmutable($point['month'] . '-01'))->format('M Y') . ': ' . accounting_money($point['balance_decimal']), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-6 border-b border-slate-200 flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Predictive Forecast</h2>
            <p id="forecast-meta" class="text-sm text-slate-500 mt-1">Projecting the next six months of net recognized expenses.</p>
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
            Record positive net expenses in at least two completed months and the projection will appear here.
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
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 m-0">Income / Expense Coverage</h3>
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
    $jsBreakdown = json_encode($breakdownAvailable ? array_map(static fn($r)=>['category'=>$r['category'],'total'=>(float)$r['total'],'total_decimal'=>$r['total']],$breakdownRows) : null, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    $jsKpiSeries = json_encode($kpiSeries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

    $scripts = <<<JS
<script>
(function () {
    const series = {$jsKpiSeries};
    const charts = [
        { key: 'income', id: 'kpiIncomeChart', label: 'Monthly income', color: '#059669', fill: 'rgba(5, 150, 105, 0.08)' },
        { key: 'expenses', id: 'kpiExpensesChart', label: 'Monthly expenses', color: '#e11d48', fill: 'rgba(225, 29, 72, 0.08)' },
        { key: 'balance', id: 'kpiBalanceChart', label: 'Month-end assets', color: '#4f46e5', fill: 'rgba(79, 70, 229, 0.08)' },
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
        breakdownList.replaceChildren(); show(breakdownWrap,false); show(breakdownList,false);
        if (!Array.isArray(categories)) { breakdownMessage('Expense breakdown is currently unavailable.'); return; }
        const rows=categories.filter(r=>r.total!==0);
        if(!rows.length){breakdownMessage('No expense data for these completed months.');return;}
        const total=rows.reduce((sum,r)=>sum+r.total,0), signed=rows.some(r=>r.total<0);
        const exact=value=>{const c=BigInt(value.replace('.','')),a=c<0n?-c:c;return(c<0n?'-':'')+'₱'+(a/100n).toLocaleString('en-PH')+'.'+String(a%100n).padStart(2,'0');};
        rows.forEach(r=>{const li=document.createElement('li');li.textContent=r.category+': '+exact(r.total_decimal)+(!signed&&total>0?' ('+(r.total/total*100).toFixed(1)+'%)':'');breakdownList.append(li);});
        show(breakdownList,true);
        if(signed||total<=0){breakdownMessage('Category shares are unavailable for signed amounts. Exact amounts are listed below.');return;}
        if(typeof Chart!=='function'){breakdownMessage('Chart unavailable. Exact amounts remain listed below.');return;}
        const displayed=rows.slice(0,8);if(rows.length>8)displayed.push({category:'Other categories',total:rows.slice(8).reduce((sum,r)=>sum+r.total,0)});
        breakdownMessage('');show(breakdownWrap,true);
        breakdownChart=new Chart(document.getElementById('expenseBreakdownChart'),{type:'doughnut',data:{labels:displayed.map(r=>r.category),datasets:[{data:displayed.map(r=>r.total),backgroundColor:['#4f46e5','#0ea5e9','#14b8a6','#8b5cf6','#06b6d4','#10b981','#a855f7','#64748b','#94a3b8']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
    }

    function renderChart(history, projection) {
        if(typeof Chart!=='function'){showNote('Forecast chart unavailable.');return;}
        const labels = history.map((p) => monthLabel(p.month)).concat(projection.map((p) => monthLabel(p.month)));
        const historical = history.map((p) => p.expenses).concat(projection.map(() => null));
        const projected = history.map((p, i) => (i === history.length - 1 ? p.expenses : null)).concat(projection.map((p) => p.projected_expenses));
        if (forecastChart) forecastChart.destroy();
        forecastChart = new Chart(document.getElementById('forecastChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Historical Net Expenses', data: historical, borderColor: '#e11d48', backgroundColor: 'rgba(225, 29, 72, 0.08)', borderWidth: 2, pointRadius: 3, tension: 0.3, fill: true },
                    { label: 'Projected Net Expenses', data: projected, borderColor: '#8b5cf6', backgroundColor: 'rgba(139, 92, 246, 0.06)', borderWidth: 2, borderDash: [6, 4], pointRadius: 3, tension: 0.3, fill: true },
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
        meta.textContent='Six-month net-expense projection · '+(data.state==='cached'?'matching cached inputs':data.state==='degraded'?'trailing three-month baseline':'new forecast')+' · history through '+data.as_of;
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
            if (isRefresh && !body.classList.contains('hidden')) { showNote(payload.error); return; }
            show(offlineBadge, false);
            emptyDetail.textContent = payload.error || 'The forecast could not be loaded.';
            show(body, false); show(empty, true); return;
        }
        const data = payload.data;
        if(data.version!==2||data.basis!=='net_expenses_v1'||!Array.isArray(data.projection)||data.projection.some(p=>!Number.isFinite(p.projected_expenses))) { showNote('The forecast response is invalid.');return; }
        show(offlineBadge, data.state === 'degraded');
        if (data.state === 'insufficient') {
            emptyDetail.textContent = 'Record positive net expenses in at least two completed months and the projection will appear here.';
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
    try { renderBreakdown({$jsBreakdown}); } catch(error) { breakdownMessage('Chart unavailable. Recorded amounts remain listed below.'); }
    load(false);
})();
</script>
JS;
}

layout_end($scripts);
