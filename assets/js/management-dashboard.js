/* Management-only presentation; endpoint calculations and contracts are unchanged. */
(function () {
    'use strict';
    const root = document.getElementById('management-dashboard');
    if (!root) return;
    const data = JSON.parse(document.getElementById('management-dashboard-data').textContent);
    const el = id => root.querySelector('#' + id);
    const peso = value => '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const month = value => {
        const parts = String(value).split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
    };
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const extras = Array.from(root.querySelectorAll('[data-md-extra-row]'));
    const toggle = el('md-budget-toggle');
    function expandBudget(expanded) {
        extras.forEach(row => { row.hidden = !expanded; });
        if (toggle) {
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.textContent = expanded ? 'Show less' : 'View all ' + toggle.dataset.count + ' categories';
        }
    }
    if (toggle) {
        toggle.hidden = false;
        expandBudget(false);
        toggle.addEventListener('click', () => expandBudget(toggle.getAttribute('aria-expanded') !== 'true'));
    }
    if (el('md-attention-link')) el('md-attention-link').addEventListener('click', () => {
        expandBudget(true);
        el('md-budget-title').focus();
    });

    function charts() {
        const cash = data.cashFlow;
        const amounts = data.breakdown.amounts;
        const total = amounts.reduce((sum, value) => sum + value, 0);
        const cashEmpty = !cash.some(point => point.inflow !== 0 || point.outflow !== 0);
        const validShares = total > 0 && amounts.every(value => Number.isFinite(value) && value >= 0);
        if (data.cashFlowAvailable && cashEmpty) el('md-cash-status').textContent = 'No recorded cash flow in these completed months.';
        if (data.breakdownAvailable && !validShares) el('md-expense-status').textContent = amounts.some(value => value < 0)
            ? 'Category shares are unavailable for signed amounts. Recorded amounts are listed below.' : 'No expense data for this period.';
        if (typeof Chart === 'undefined') {
            if (data.cashFlowAvailable && !cashEmpty) el('md-cash-status').textContent = 'Chart unavailable. Monthly amounts remain available below.';
            if (data.breakdownAvailable && validShares) el('md-expense-status').textContent = 'Chart unavailable. Category amounts remain available below.';
            return;
        }
        if (data.cashFlowAvailable && !cashEmpty) {
            el('md-cash-wrap').hidden = false;
            new Chart(el('md-cash-chart'), {
                type: 'bar',
                data: { labels: cash.map(point => month(point.month)), datasets: [
                    { label: 'Incoming Funds', data: cash.map(point => point.inflow), backgroundColor: '#16a34a', borderRadius: 3 },
                    { label: 'Expenditures', data: cash.map(point => point.outflow), backgroundColor: '#ef4444', borderRadius: 3 },
                ] },
                options: { responsive: true, maintainAspectRatio: false, animation: reducedMotion ? false : undefined,
                    plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + peso(ctx.parsed.y) } } },
                    scales: { y: { beginAtZero: true, ticks: { callback: value => '₱' + Number(value).toLocaleString('en-PH') } } },
                },
            });
        }
        if (data.breakdownAvailable && validShares) {
            el('md-expense-wrap').hidden = false;
            new Chart(el('md-expense-chart'), {
                type: 'doughnut',
                data: { labels: data.breakdown.labels, datasets: [{ data: amounts,
                    backgroundColor: ['#1e3a8a', '#2563eb', '#60a5fa', '#0e7490', '#0891b2', '#6366f1', '#64748b', '#94a3b8', '#cbd5e1'], borderWidth: 2, borderColor: '#fff' }] },
                options: { responsive: true, maintainAspectRatio: false, animation: reducedMotion ? false : undefined,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, generateLabels: chart =>
                        Chart.overrides.doughnut.plugins.legend.labels.generateLabels(chart).map(label => ({ ...label,
                            text: label.text.length > 28 ? label.text.slice(0, 25) + '…' : label.text })) } },
                        tooltip: { callbacks: { label: ctx => ctx.label + ': ' + peso(ctx.parsed) + ' (' + (ctx.parsed / total * 100).toFixed(1) + '%)' } } },
                },
            });
        }
    }
    // A chart failure must never prevent the forecast or budget controls from working.
    try { charts(); } catch (error) {
        el('md-cash-wrap').hidden = true;
        el('md-expense-wrap').hidden = true;
        if (data.cashFlowAvailable) el('md-cash-status').textContent = 'Chart unavailable. Monthly amounts remain available below.';
        if (data.breakdownAvailable) el('md-expense-status').textContent = 'Chart unavailable. Category amounts remain available below.';
    }

    const button = el('md-refresh');
    const status = el('md-forecast-status');
    let lastSuccess = null;
    const budgetMap = new Map(data.budgetUpcoming.map(row => [String(row.year) + '-' + String(row.month).padStart(2, '0'), row.total]));
    // Sentence segmentation preserves complete advice; unsupported browsers show full text.
    function excerpt(text, fallback) {
        if (!text) return fallback;
        if (typeof Intl.Segmenter !== 'function') return text;
        const sentences = Array.from(new Intl.Segmenter('en', { granularity: 'sentence' }).segment(text), part => part.segment);
        // Two sentences preserve nearby qualifications without inventing a summary.
        return sentences.slice(0, 2).join('').trim();
    }
    function paragraph(parent, text, className) {
        const node = document.createElement('p');
        node.textContent = text;
        if (className) node.className = className;
        parent.appendChild(node);
        return node;
    }
    function projectionRows(parent, points) {
        parent.replaceChildren();
        points.forEach(point => {
            const row = document.createElement('div');
            row.className = 'md-projection-row';
            const label = document.createElement('span'); label.textContent = month(point.month);
            const amount = document.createElement('strong'); amount.textContent = peso(point.projected_outflow);
            row.append(label, amount); parent.appendChild(row);
        });
    }
    function stateLabel(result) {
        if (result.state === 'cached') return 'Cached forecast loaded (24-hour cache policy).';
        if (result.state === 'fresh') return 'New forecast loaded.';
        if (result.state === 'degraded') return 'Trailing three-month baseline · AI advisory unavailable.';
        return 'Forecast loaded.';
    }
    function render(result) {
        if (result.state === 'insufficient') {
            el('md-forecast-body').hidden = true;
            status.textContent = 'Not enough history to forecast. Record expenses in at least two completed months.';
            return;
        }
        const projection = result.projection;
        const metrics = result.metrics || {};
        const advisory = result.advisory || {};
        projectionRows(el('md-projection'), projection.slice(0, 3));
        projectionRows(el('md-all-projections'), projection);
        el('md-runway').textContent = metrics.runway_months != null ? metrics.runway_months + ' months at recent outflow' : 'Not available';
        el('md-reallocation-excerpt').textContent = excerpt(advisory.reallocation_suggestion, 'No budget advice available.');
        el('md-funding-excerpt').textContent = excerpt(advisory.funding_risk, 'No funding assessment available.');
        el('md-reallocation').textContent = advisory.reallocation_suggestion || 'No budget advice available.';
        el('md-funding').textContent = advisory.funding_risk || 'No funding assessment available.';
        el('md-risk').textContent = ['LOW', 'MEDIUM', 'HIGH'].includes(advisory.risk_level) ? 'AI risk assessment: ' + advisory.risk_level : 'No risk assessment available.';
        el('md-assumptions').textContent = 'Runway divides positive all-time recorded net position ('
            + (Number.isFinite(metrics.net_position) ? peso(metrics.net_position) : 'unavailable')
            + ') by average outflow over the last three completed months ('
            + (Number.isFinite(metrics.recent_avg_outflow) ? peso(metrics.recent_avg_outflow) : 'unavailable')
            + ' per month). It is unavailable when either is not positive. This does not verify bank cash or unrestricted funds. Projections start with a full-month estimate for the first month shown. The baseline repeats the trailing three-month average; AI projections may differ.';
        el('md-forecast-note').textContent = result.note || '';
        el('md-freshness-detail').textContent = (result.as_of ? 'History as-of date: ' + result.as_of + '. ' : '')
            + (result.state === 'cached' ? 'Cached projections and advice may predate the metrics returned with this response. ' : '')
            + 'Recorded cards, charts, and budget comparisons use the page-load snapshot.';
        const trends = el('md-trend-warnings'); trends.replaceChildren();
        (result.categories || []).forEach(category => {
            const budget = data.budgetByCategory.find(row => row.category === category.category);
            if (category.trend === 'rising' && budget && budget.budgeted > 0 && budget.spent >= budget.budgeted * 0.85) {
                const li = document.createElement('li');
                li.textContent = category.category + ' has a rising historical spending trend; page-load spending is '
                    + peso(budget.spent) + ' against ' + peso(budget.budgeted) + ' recorded budget.';
                trends.appendChild(li);
            }
        });
        const compare = el('md-budget-compare'); compare.replaceChildren();
        projection.slice(0, 3).forEach(point => {
            const card = document.createElement('div'); card.className = 'md-inset';
            const heading = document.createElement('h3'); heading.textContent = month(point.month); card.appendChild(heading);
            paragraph(card, 'Projected: ' + peso(point.projected_outflow));
            if (budgetMap.has(point.month)) {
                const budget = budgetMap.get(point.month);
                const difference = point.projected_outflow - budget;
                paragraph(card, 'Recorded budget: ' + peso(budget));
                paragraph(card, difference === 0 ? 'Matches recorded budget' : (difference > 0 ? 'Projected above by ' : 'Projected below by ') + peso(Math.abs(difference)), difference > 0 ? 'md-expense' : 'md-income');
            } else paragraph(card, 'Recorded budget unavailable for this period.');
            compare.appendChild(card);
        });
        el('md-forecast-body').hidden = false;
        status.textContent = stateLabel(result);
    }
    async function load(refresh) {
        button.disabled = true;
        button.textContent = refresh ? 'Refreshing…' : 'Refresh Forecast';
        root.querySelector('.md-forecast').setAttribute('aria-busy', 'true');
        status.textContent = lastSuccess ? 'Refreshing forecast. Previous results remain visible.' : 'Loading forecast…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 90000);
        try {
            const response = await fetch('forecast_ai.php', { method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: refresh ? 'refresh' : 'load', csrf_token: data.csrfToken }).toString(), signal: controller.signal });
            const payload = await response.json();
            if (!response.ok || !payload.ok || !payload.data) throw new Error(payload.error || 'Unable to load forecast.');
            const result = payload.data;
            if (result.state !== 'insufficient' && (!Array.isArray(result.projection) || !result.projection.every(point =>
                /^\d{4}-(0[1-9]|1[0-2])$/.test(point.month) && Number.isFinite(point.projected_outflow)))) throw new Error('The forecast response could not be displayed.');
            render(result);
            lastSuccess = result;
        } catch (error) {
            // Restore the complete previous payload even if rendering failed partway through.
            if (lastSuccess) render(lastSuccess);
            const detail = error.name === 'AbortError' ? 'The forecast request timed out.' : error.message;
            status.textContent = (lastSuccess ? 'Forecast not refreshed. Showing the last successful result. ' + stateLabel(lastSuccess) + ' ' : 'Forecast unavailable. ') + detail;
        } finally {
            clearTimeout(timeout);
            button.disabled = !data.canRefresh;
            button.textContent = 'Refresh Forecast';
            root.querySelector('.md-forecast').setAttribute('aria-busy', 'false');
        }
    }
    button.addEventListener('click', () => load(true));
    load(false);
})();
