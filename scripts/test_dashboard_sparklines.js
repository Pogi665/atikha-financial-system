// Tests rendered Admin scripts in a DOM stub; does not establish visual parity.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../dashboard.php'), 'utf8').replace(/\r\n/g, '\n');
const script = source.split('$scripts = <<<JS\n')[1].split('\nJS;')[0].replace(/^<script>\n|\n<\/script>$/g, '');
const series = ['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03']
    .map((month, index) => ({ month, income: index === 0 ? 10.03 : 0, expenses: index === 0 ? 100 : 0, balance: -14.92 }));
let checks = 0;
function check(ok, label) { assert.ok(ok, label); checks++; console.log('PASS: ' + label); }
function run(input = series, chartMode = 'ok') {
    const nodes = new Map();
    const get = id => {
        if (!nodes.has(id)) {
            const classes = new Set(id.endsWith('-status') ? ['hidden'] : []);
            nodes.set(id, { id, textContent: '', classList: {
                add: name => classes.add(name), remove: name => classes.delete(name),
                contains: name => classes.has(name), toggle: (name, force) => force ? classes.add(name) : classes.delete(name),
            }, addEventListener() {} });
        }
        return nodes.get(id);
    };
    const charts = [], requests = [], partials = new Map();
    function Chart(canvas, config) {
        charts.push({ canvas, config });
        if (chartMode === 'throws' && canvas.id === 'kpiIncomeChart') {
            partials.set(canvas, { destroy() { partials.delete(canvas); } });
            throw new Error('Canvas initialization failed');
        }
    }
    Chart.getChart = canvas => partials.get(canvas);
    const context = { document: { getElementById: get }, Date, Number, URLSearchParams,
        fetch(url, options) { requests.push({ url, options }); return new Promise(() => {}); },
    };
    if (chartMode !== 'missing') context.Chart = Chart;
    const rendered = script.replace('{$jsKpiSeries}', JSON.stringify(input))
        .replace('{$jsBreakdown}', 'null')
        .replace('{$jsCsrf}', '"fixture-token"').replace('{$jsCanRefresh}', 'false');
    vm.runInNewContext(rendered, context);
    return { nodes, charts, requests, partials };
}
const good = run();
check(good.charts.length === 3, 'Three sparklines initialize before forecast response');
check(good.requests.length === 1 && good.requests[0].url === 'forecast_ai.php'
    && good.requests[0].options.method === 'POST', 'Existing forecast initialization still executes');
check(good.requests[0].options.body.includes('csrf_token=fixture-token'), 'Forecast CSRF contract is preserved');
check(good.charts.map(c => c.config.data.datasets[0].borderColor).join(',') === '#059669,#e11d48,#4f46e5', 'Income, expenses and balance use agreed colors');
for (const { config } of good.charts) {
    const line = config.data.datasets[0], opts = config.options;
    check(config.type === 'line' && opts.responsive && !opts.maintainAspectRatio && !opts.animation, 'Responsive sparkline sizing and no animation');
    check(!opts.scales.x.display && !opts.scales.y.display && !opts.scales.x.grid.display
        && !opts.scales.y.grid.display && !opts.plugins.legend.display, 'Axes, grids and legends stay hidden');
    check(line.tension === 0 && line.borderWidth === 2 && line.pointRadius === 0 && line.fill, 'Straight thin lines, hidden markers and subtle fill');
    check(opts.plugins.tooltip.callbacks.label({ dataset: line, parsed: { y: -14.92 } }).includes('₱-14.92'), 'Tooltip includes formatted signed pesos');
}
check(good.charts[2].config.data.datasets[0].data.every(value => value === -14.92), 'Negative balances are not clamped');
check(good.charts[0].config.data.labels[0] === 'Oct 2025' && good.charts[0].config.data.labels[5] === 'Mar 2026', 'Tooltip labels cross the year boundary correctly');
for (const input of [[], series.slice(0, 5)]) {
    const failed = run(input);
    check(!failed.charts.length && failed.nodes.get('kpi-income-chart').classList.contains('hidden')
        && !failed.nodes.get('kpi-income-status').classList.contains('hidden'), 'Unavailable series shows fallback instead of fabricated zeros');
    check(failed.requests.length === 1, 'Unavailable series does not block forecast request');
}
const missing = run(series, 'missing');
check(!missing.charts.length && missing.requests.length === 1, 'Missing Chart.js does not block forecast request');
check(['income', 'expenses', 'balance'].every(key => !missing.nodes.get('kpi-' + key + '-status').classList.contains('hidden')), 'Missing Chart.js shows all three fallbacks');
const thrown = run(series, 'throws');
check(thrown.charts.length === 3 && thrown.partials.size === 0 && thrown.requests.length === 1, 'One chart failure cleans up and preserves other charts and forecast');
const malformed = run(series.map(point => ({ ...point, income: null })));
check(malformed.charts.length === 2, 'Invalid values are isolated to the affected chart');
const zeros = run(series.map(point => ({ ...point, income: 0, expenses: 0, balance: 0 })));
check(zeros.charts.length === 3, 'Recorded empty history draws valid zero lines');
console.log(`PASS: ${checks} assertions`);
