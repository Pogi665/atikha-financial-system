// DOM contract tests without dependencies or a browser. Not visual/accessibility QA.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; console.log('PASS: ' + label); }
class Element {
    constructor() { this.children = []; this.attributes = {}; this.hidden = false; this.textContent = ''; this.dataset = {}; this.listeners = {}; }
    appendChild(node) { this.children.push(node); return node; }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
    setAttribute(key, value) { this.attributes[key] = value; }
    getAttribute(key) { return this.attributes[key]; }
    addEventListener(event, callback) { this.listeners[event] = callback; }
    focus() { this.focused = true; }
}
const nodes = new Map();
const get = id => { if (!nodes.has(id)) nodes.set(id, new Element()); return nodes.get(id); };
const extras = [new Element(), new Element()];
const root = get('management-dashboard');
root.querySelector = selector => get(selector.replace(/^#/, ''));
root.querySelectorAll = () => extras;
get('md-budget-toggle').dataset.count = '8';
const input = {
    cashFlow: [{ month: '2026-08', inflow: 100, outflow: 40 }], cashFlowAvailable: true,
    breakdown: { labels: ['Category', 'Other'], amounts: [75, 25] }, breakdownAvailable: true,
    budgetUpcoming: [{ year: 2026, month: 10, total: 200 }, { year: 2026, month: 9, total: 100 }],
    budgetByCategory: [{ category: 'Category', budgeted: 100, spent: 125 }], csrfToken: 'token', canRefresh: true,
};
get('management-dashboard-data').textContent = JSON.stringify(input);
const good = {
    state: 'cached', as_of: '2026-09-30', generated_at: '2026-09-30 08:00:00',
    projection: [{ month: '2026-08', projected_outflow: 50 }, { month: '2026-09', projected_outflow: 150 }, { month: '2026-10', projected_outflow: 150 }],
    metrics: { runway_months: 3.5, net_position: 350, recent_avg_outflow: 100 },
    advisory: { reallocation_suggestion: 'Consider category A. However, review restrictions first. Preserve this final sentence.', funding_risk: 'Funding is concentrated. Check the existing agreements.', risk_level: 'MEDIUM' },
    categories: [{ category: 'Category', trend: 'rising' }],
};
const replies = [
    { ok: true, payload: { ok: true, data: good } },
    { ok: false, payload: { ok: false, error: 'Please wait before recalculating.' } },
    { throw: new Error('Network failed.') },
    { ok: true, payload: { ok: true, data: { ...good, projection: [{ month: 'broken', projected_outflow: 'NaN' }] } } },
    { ok: true, payload: { ok: true, data: { ...good, state: 'fresh' } } },
];
const charts = [];
function Chart(node, config) { charts.push(config); }
const requests = [];
const context = { document: { getElementById: get, createElement: () => new Element() },
    window: { matchMedia: () => ({ matches: true }) }, Chart, Intl, Date, URLSearchParams, AbortController,
    setTimeout, clearTimeout,
    fetch: async (url, options) => {
        requests.push({ url, options });
        const reply = replies.shift();
        if (reply.throw) throw reply.throw;
        return { ok: reply.ok, json: async () => reply.payload };
    },
};
const flush = () => new Promise(resolve => setImmediate(resolve));
const text = node => node.textContent + node.children.map(text).join(' ');
(async () => {
    vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/management-dashboard.js'), 'utf8'), context);
    await flush();
    check(extras.every(row => row.hidden) && get('md-budget-toggle').getAttribute('aria-expanded') === 'false', 'Initial six-row enhancement');
    get('md-budget-toggle').listeners.click();
    check(extras.every(row => !row.hidden), 'View all reveals rows');
    get('md-budget-toggle').listeners.click();
    get('md-attention-link').listeners.click();
    check(extras.every(row => !row.hidden) && get('md-budget-title').focused, 'Attention anchor reveals all and focuses heading');
    check(charts.length === 2 && charts.every(chart => chart.options.animation === false), 'Both charts honor reduced motion');
    check(charts[1].data.datasets[0].data === input.breakdown.amounts || JSON.stringify(charts[1].data.datasets[0].data) === JSON.stringify(input.breakdown.amounts), 'Doughnut uses exact displayed amounts');
    check(charts[1].options.plugins.tooltip.callbacks.label({ label:'Other', parsed:25 }).includes('25.0%'), 'Tooltip share uses displayed denominator');
    const comparisons = get('md-budget-compare').children.map(text);
    check(comparisons[0].includes('unavailable') && !comparisons[0].includes('Recorded budget: ₱0'), 'Unmatched cached month is unavailable');
    check(comparisons[1].includes('Recorded budget: ₱100.00') && comparisons[2].includes('Recorded budget: ₱200.00'), 'Budget matching uses month keys despite reordered input');
    check(get('md-reallocation-excerpt').textContent === 'Consider category A. However, review restrictions first.', 'Excerpt preserves full sentences and qualification');
    check(get('md-reallocation').textContent === good.advisory.reallocation_suggestion && get('md-risk').textContent.includes('MEDIUM'), 'Full advice and risk retained');
    check(!text(root).includes(good.generated_at) && !get('md-forecast-status').textContent.includes(good.generated_at), 'Unverified clock not rendered');
    check(get('md-freshness-detail').textContent.includes('may predate'), 'Cached response scope explained');
    const original = text(get('md-budget-compare'));
    for (const failure of ['throttle', 'network', 'invalid response']) {
        get('md-refresh').listeners.click(); await flush();
        check(get('md-forecast-status').textContent.includes('last successful') && text(get('md-budget-compare')) === original && !get('md-forecast-body').hidden, failure + ' retains last successful results');
        check(!get('md-refresh').disabled && get('md-refresh').textContent === 'Refresh Forecast', failure + ' restores refresh control');
    }
    get('md-refresh').listeners.click(); await flush();
    check(get('md-forecast-status').textContent === 'New forecast loaded.', 'Successful retry replaces failure status');
    check(charts.length === 2, 'Refresh does not recreate recorded charts');
    check(requests.every(req => req.url === 'forecast_ai.php' && req.options.body.includes('csrf_token=token')), 'Endpoint and CSRF contract unchanged');
    console.log('PASS: ' + checks + ' assertions');
})().catch(error => { console.error(error); process.exitCode = 1; });
