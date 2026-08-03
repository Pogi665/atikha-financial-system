<?php
session_start();

if (empty($_SESSION['UserID'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/gemini_client.php';

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

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

$csrfToken = csrf_token();
$aiConfigured = gemini_is_configured();

// Recalculating spends API tokens, so Staff read the cached forecast without
// being able to trigger a new one.
$canRefresh = in_array($_SESSION['Role'] ?? '', ['Admin', 'Management'], true);
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

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Predictive Forecast</h2>
                        <p id="forecast-meta" class="text-sm text-slate-500 mt-1">
                            Projecting the next six months of outflow.
                        </p>
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
                            <span
                                id="forecast-risk"
                                class="hidden rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                            ></span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white rounded-lg border border-slate-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Budget Reallocation
                                </p>
                                <p id="forecast-reallocation" class="text-sm text-slate-700 mt-2 leading-relaxed"></p>
                            </div>
                            <div class="bg-white rounded-lg border border-slate-200 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Funding Risk
                                </p>
                                <p id="forecast-funding-risk" class="text-sm text-slate-700 mt-2 leading-relaxed"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

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

    <script>
        // Predictive Forecast card. Fetched after paint so the dashboard above
        // never waits on the AI round trip.
        (function () {
            const csrfToken = <?= json_encode($csrfToken) ?>;
            const canRefresh = <?= json_encode($canRefresh && $aiConfigured) ?>;

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
                return '₱' + Number(value).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            function monthLabel(period) {
                const parts = String(period).split('-');
                const date = new Date(Number(parts[0]), Number(parts[1]) - 1, 1);

                return date.toLocaleDateString('en-PH', { month: 'short', year: '2-digit' });
            }

            function show(element, visible) {
                element.classList.toggle('hidden', !visible);
            }

            function showNote(message) {
                note.textContent = message || '';
                show(note, Boolean(message));
            }

            function renderChart(history, projection) {
                const labels = history.map((point) => monthLabel(point.month))
                    .concat(projection.map((point) => monthLabel(point.month)));

                const historical = history.map((point) => point.outflow)
                    .concat(projection.map(() => null));

                // The projected series is padded with nulls across the historical
                // span except for its final point, so the dashed line grows out of
                // the solid one instead of floating away from it.
                const projected = history.map((point, index) => (
                    index === history.length - 1 ? point.outflow : null
                )).concat(projection.map((point) => point.projected_outflow));

                if (forecastChart) {
                    forecastChart.destroy();
                }

                forecastChart = new Chart(document.getElementById('forecastChart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Historical outflow',
                                data: historical,
                                borderColor: 'rgb(220, 38, 38)',
                                backgroundColor: 'rgba(220, 38, 38, 0.08)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointBackgroundColor: 'rgb(220, 38, 38)',
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Projected outflow',
                                data: projected,
                                borderColor: 'rgb(79, 70, 229)',
                                backgroundColor: 'rgba(79, 70, 229, 0.06)',
                                borderWidth: 2,
                                borderDash: [6, 4],
                                pointRadius: 3,
                                pointBackgroundColor: 'rgb(79, 70, 229)',
                                tension: 0.3,
                                fill: true,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, boxWidth: 8, padding: 16 },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        if (ctx.parsed.y === null) {
                                            return null;
                                        }

                                        return ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y);
                                    },
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
            }

            function renderMeta(data) {
                if (data.state === 'degraded') {
                    meta.textContent = 'Trailing three-month average · AI advisory unavailable';

                    return;
                }

                const generated = data.generated_at
                    ? new Date(data.generated_at.replace(' ', 'T'))
                    : null;
                const stamp = generated && !isNaN(generated)
                    ? generated.toLocaleString('en-PH', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                    })
                    : '';

                const freshness = data.state === 'cached' ? 'cached for 24 hours' : 'just now';

                meta.textContent = stamp
                    ? 'Generated ' + stamp + ' · ' + freshness
                    : 'Six-month projection · ' + freshness;
            }

            function renderAdvisory(advisory) {
                const level = advisory.risk_level;

                if (level && riskClasses[level]) {
                    riskBadge.className = 'rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide '
                        + riskClasses[level];
                    riskBadge.textContent = level + ' risk';
                    show(riskBadge, true);
                } else {
                    show(riskBadge, false);
                }

                reallocation.textContent = advisory.reallocation_suggestion
                    || 'No reallocation advice is available for this period.';
                fundingRisk.textContent = advisory.funding_risk
                    || 'No funding risk assessment is available for this period.';
            }

            function render(payload, isRefresh) {
                show(loading, false);

                if (!payload.ok) {
                    // A failed refresh keeps whatever is already on screen and
                    // explains itself, rather than blanking a usable forecast.
                    if (isRefresh && !body.classList.contains('hidden')) {
                        showNote(payload.error);

                        return;
                    }

                    emptyDetail.textContent = payload.error
                        || 'The forecast could not be loaded. Please try again.';
                    show(body, false);
                    show(empty, true);

                    return;
                }

                const data = payload.data;

                if (data.state === 'insufficient') {
                    emptyDetail.textContent = 'Record expenses across at least two different months '
                        + 'and the projection will appear here.';
                    meta.textContent = 'Waiting on more history';
                    show(body, false);
                    show(empty, true);

                    return;
                }

                show(empty, false);
                show(body, true);

                showNote(data.note);
                renderMeta(data);
                renderChart(data.history, data.projection);
                renderAdvisory(data.advisory);
            }

            function load(isRefresh) {
                const requestBody = new URLSearchParams();
                requestBody.set('action', isRefresh ? 'refresh' : 'load');
                requestBody.set('csrf_token', csrfToken);

                if (refreshButton) {
                    refreshButton.disabled = true;
                    if (isRefresh) {
                        refreshButton.textContent = 'Refreshing\u2026';
                    }
                }

                fetch('forecast_ai.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: requestBody.toString(),
                })
                    .then(function (response) { return response.json(); })
                    .catch(function () {
                        return { ok: false, data: null, error: 'The forecast request could not be completed. Please try again.' };
                    })
                    .then(function (payload) {
                        if (refreshButton) {
                            refreshButton.disabled = !canRefresh;
                            refreshButton.textContent = 'Refresh Forecast';
                        }

                        render(payload, isRefresh);
                    });
            }

            if (refreshButton) {
                refreshButton.addEventListener('click', function () {
                    showNote('');
                    load(true);
                });
            }

            load(false);
        })();
    </script>
</body>
</html>
