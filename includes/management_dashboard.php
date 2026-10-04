<?php
// Management-only presentation. All amounts originate in dashboard read helpers.
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => $escape(accounting_money((string) $value));
$periodLabel = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$budgetRows = $budgetDataAvailable ? $budgetUtil['by_category'] : [];
foreach ($budgetRows as &$row) {
    $allocation = (float) $row['budgeted'];
    $spent = (float) $row['spent'];
    $row['remaining'] = accounting_decimal(accounting_add(accounting_cents($row['budgeted']),-accounting_cents($row['spent'])));
    $row['utilization'] = $allocation > 0 ? $spent / $allocation * 100 : null;
    $row['priority'] = 5;
    $row['tone'] = 'neutral';
    $row['status'] = 'Under 75% utilized';
    if ($allocation < 0) {
        $row['status'] = 'Negative recorded budget';
        $row['priority'] = 4;
    } elseif ($allocation == 0) {
        $row['status'] = $spent > 0 ? 'No budget allocated · ' . accounting_money($row['spent']) . ' spent'
            : ($spent == 0 ? 'No allocation or spending' : 'No budget allocated · negative recorded spending');
        if ($spent > 0) { $row['priority'] = 0; $row['tone'] = 'danger'; }
    } elseif ($row['utilization'] > 100) {
        $row['priority'] = 1; $row['tone'] = 'danger';
        $row['status'] = 'Over budget by ' . accounting_money(accounting_decimal(abs(accounting_cents($row['remaining']))));
    } elseif ($row['utilization'] >= 90) {
        $row['priority'] = 2; $row['tone'] = 'warning';
        $row['status'] = $row['utilization'] == 100 ? 'Fully utilized' : 'Approaching budget · 90% or above';
    } elseif ($row['utilization'] >= 75) {
        $row['priority'] = 3; $row['tone'] = 'warning';
        $row['status'] = 'Approaching budget · 75% to below 90%';
    } elseif ($spent < 0) {
        $row['status'] = 'Negative recorded spending';
    }
}
unset($row);
usort($budgetRows, static fn ($a, $b) => ($a['priority'] <=> $b['priority'])
    ?: strcasecmp($a['category'], $b['category']) ?: strcmp($a['category'], $b['category']));
$attentionRows = array_values(array_filter($budgetRows, static fn ($row) => $row['priority'] < 5));
$unallocatedCount = count(array_filter($budgetRows, static fn ($row) => $row['priority'] === 0));
$overCount = count(array_filter($budgetRows, static fn ($row) => $row['priority'] === 1));
$nearCount = count(array_filter($budgetRows, static fn ($row) => in_array($row['priority'], [2, 3], true)));
$displayUtil = $budgetDataAvailable && $budgetUtil['budgeted'] > 0 ? $budgetUtil['pct'] : null;
$cashPeriod = $cashFlowAvailable && $cashFlowSeries
    ? date('M Y', strtotime($cashFlowSeries[0]['month'] . '-01')) . ' – ' . date('M Y', strtotime(end($cashFlowSeries)['month'] . '-01')) : '';
?>
<div id="management-dashboard" class="management-dashboard">
    <div>
        <h1>Executive Dashboard</h1>
        <p class="md-muted">Recorded finances, budget monitoring, and forecast findings.</p>
    </div>

    <div class="md-kpis">
        <?php foreach ([['Total Income', $totalFunds, 'income'], ['Total Expenses', $totalExpenses, 'expense'], ['Total Assets', $netBalance, 'position']] as [$label, $value, $tone]): ?>
        <section class="md-card md-kpi">
            <h2><?= $escape($label) ?></h2>
            <p class="md-value md-<?= $tone ?>"><?= $totalsAvailable ? $money($value) : 'Unavailable' ?></p>
            <p class="md-caption">Posted balances through <?= $escape($today) ?> · Asia/Manila</p>
        </section>
        <?php endforeach; ?>
        <section class="md-card md-kpi">
            <h2>Budget Utilization</h2>
            <p class="md-value <?= $displayUtil === null ? '' : ($displayUtil > 100 ? 'md-expense' : ($displayUtil >= 80 ? 'md-warning' : 'md-income')) ?>"><?= !$budgetDataAvailable ? 'Unavailable' : ($displayUtil === null ? 'N/A' : $escape(number_format($displayUtil, 1)) . '%') ?></p>
            <p class="md-caption"><?= $escape($periodLabel) ?> · Recorded budget</p>
            <?php if ($budgetDataAvailable): ?><p class="md-caption"><?= $money($budgetUtil['spent']) ?> spent / <?= $money($budgetUtil['budgeted']) ?> recorded budget</p><?php endif; ?>
        </section>
    </div>

    <section class="md-card md-attention" aria-labelledby="md-attention-title">
        <div class="md-heading"><h2 id="md-attention-title">Items needing attention</h2><span class="md-caption"><?= $escape($periodLabel) ?></span></div>
        <?php if (!$budgetDataAvailable): ?>
            <p role="status">Budget data is currently unavailable. Exceptions could not be checked.</p>
        <?php elseif (!$attentionRows): ?>
            <p>No budget exceptions for <?= $escape($periodLabel) ?>.</p>
        <?php else: ?>
            <p class="md-caption"><?= $unallocatedCount ?> without allocation · <?= $overCount ?> over budget · <?= $nearCount ?> approaching or fully utilized</p>
            <ul class="md-attention-list">
                <?php foreach (array_slice($attentionRows, 0, 3) as $row): ?>
                <li><strong><?= $escape($row['category']) ?></strong><span class="md-status md-<?= $escape($row['tone']) ?>"><?= $escape($row['status']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <a href="#md-budget-title" id="md-attention-link">View budget monitoring (<?= count($attentionRows) ?> categories needing attention)</a>
        <?php endif; ?>
    </section>

    <div class="md-charts">
        <section class="md-card" aria-labelledby="md-cash-title">
            <h2 id="md-cash-title">Income and Expenses</h2>
            <p class="md-caption"><?= $escape($cashPeriod) ?><?= $cashPeriod ? ' · 12 completed calendar months' : '' ?></p>
            <p id="md-cash-status" role="status"><?= !$cashFlowAvailable ? 'Cash flow data is currently unavailable.' : '' ?></p>
            <div class="md-chart" id="md-cash-wrap" hidden><canvas id="md-cash-chart" role="img" aria-label="Monthly recognized income and net expenses. Exact amounts are in the table below."></canvas></div>
            <?php if ($cashFlowAvailable): ?>
            <details><summary>Monthly recorded amounts</summary>
                <div class="md-table-scroll" role="region" aria-label="Monthly income/expense amounts" tabindex="0"><table>
                    <caption class="md-sr-only">Income and Expenses · <?= $escape($cashPeriod) ?></caption>
                    <thead><tr><th scope="col">Month</th><th scope="col">Income</th><th scope="col">Net Expenses</th></tr></thead>
                    <tbody><?php foreach ($cashFlowSeries as $point): ?><tr><th scope="row"><?= $escape($point['month']) ?></th><td><?= $money($point['income_decimal']) ?></td><td><?= $money($point['expenses_decimal']) ?></td></tr><?php endforeach; ?></tbody>
                </table></div>
            </details><?php endif; ?>
        </section>
        <section class="md-card" aria-labelledby="md-expense-title">
            <h2 id="md-expense-title">Expense Breakdown</h2>
            <p class="md-caption">Net expenses · <?= $escape($cashPeriod) ?> · 12 completed months</p>
            <p id="md-expense-status" role="status"><?= !$breakdownAvailable ? 'Expense breakdown is currently unavailable.' : '' ?></p>
            <div class="md-chart" id="md-expense-wrap" hidden><canvas id="md-expense-chart" role="img" aria-label="Expense category shares. Category amounts and percentages are listed below."></canvas></div>
            <?php $breakdownTotal = array_sum($expenseBreakdown['amounts']); $validShares = $breakdownTotal > 0 && !array_filter($expenseBreakdown['amounts'], static fn ($v) => $v < 0); ?>
            <ul class="md-category-list" id="md-category-list">
                <?php foreach ($expenseBreakdown['labels'] as $index => $label): ?>
                <li><span><?= $escape($label) ?></span><strong><?= $money($expenseBreakdown['decimals'][$index]) ?> <span class="md-muted"><?= $validShares ? '(' . $escape(number_format($expenseBreakdown['amounts'][$index] / $breakdownTotal * 100, 1)) . '%)' : '(share N/A)' ?></span></strong></li>
                <?php endforeach; ?>
            </ul>
            <details><summary>About this expense period</summary><p>Posted Expense debits minus credits in the twelve completed months shown. Future dates and the incomplete current month are excluded. Negative category balances remain visible; shares are unavailable for signed amounts.</p></details>
        </section>
    </div>

    <section class="md-card" aria-labelledby="md-budget-title">
        <div class="md-heading"><h2 id="md-budget-title" tabindex="-1">Budget monitoring</h2><span class="md-caption"><?= $escape($periodLabel) ?> · Recorded budget</span></div>
        <p class="md-caption">Current-month net expenses through today against recorded category budgets.</p>
        <?php if (!$budgetDataAvailable): ?><p role="status">Budget data is currently unavailable.</p>
        <?php elseif (!$budgetRows): ?><p>No recorded budgets or expenses for this month.</p>
        <?php else: ?>
        <div class="md-table-scroll" role="region" aria-label="Category budget monitoring" tabindex="0">
            <table id="md-budget-table">
                <caption class="md-sr-only">Recorded budget · <?= $escape($periodLabel) ?></caption>
                <thead><tr><?php foreach (['Category', 'Allocated', 'Spent', 'Remaining', 'Utilization', 'Status'] as $label): ?><th scope="col"><?= $label ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($budgetRows as $index => $row): ?>
                    <tr<?= $index >= 6 ? ' data-md-extra-row' : '' ?>>
                        <th scope="row"><?= $escape($row['category']) ?></th><td><?= $money($row['budgeted']) ?></td><td><?= $money($row['spent']) ?></td>
                        <td class="<?= $row['remaining'] < 0 ? 'md-expense' : '' ?>"><?= $money($row['remaining']) ?></td>
                        <td><?php if ($row['utilization'] === null): ?>N/A<?php else: ?>
                            <span><?= $escape(number_format($row['utilization'], 2)) ?>%</span>
                            <span class="md-bar md-<?= $escape($row['tone']) ?>" aria-hidden="true"><span style="width:<?= number_format(max(0, min(100, $row['utilization'])), 2, '.', '') ?>%"></span></span>
                        <?php endif; ?></td>
                        <td><span class="md-status md-<?= $escape($row['tone']) ?>"><?= $escape($row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div>
        <?php if (count($budgetRows) > 6): ?><button type="button" id="md-budget-toggle" class="md-button md-secondary" aria-expanded="true" aria-controls="md-budget-table" data-count="<?= count($budgetRows) ?>" hidden>Show less</button><?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="md-card md-forecast" aria-labelledby="md-forecast-title">
        <div class="md-heading"><div><h2 id="md-forecast-title">Forecast findings</h2><p class="md-caption">Estimates and advisory · separate from recorded results</p></div>
            <button type="button" id="md-refresh" class="md-button" <?= $canRefresh && $aiConfigured ? '' : 'disabled' ?>>Refresh Forecast</button>
        </div>
        <p class="md-caption">Refresh updates forecast findings only. Reload the page to update recorded cards, charts, and budgets.</p>
        <?php if (!$aiConfigured): ?><p class="md-caption">AI refresh is unavailable. A baseline projection may still be available.</p><?php endif; ?>
        <p id="md-forecast-status" role="status" aria-live="polite">Loading forecast…</p>
        <div id="md-forecast-body" hidden>
            <div class="md-findings">
                <section class="md-inset"><h3>Projected Net Expenses</h3><div id="md-projection"></div><h3>Estimated runway</h3><p id="md-runway" class="md-runway"></p><p class="md-caption">Cash runway cannot be inferred from recognized expenses.</p></section>
                <section class="md-inset"><h3>Budget observations</h3><p id="md-reallocation-excerpt"></p><details><summary>Full budget advice</summary><p id="md-reallocation"></p></details></section>
                <section class="md-inset"><h3>Income / expense coverage</h3><p id="md-funding-excerpt"></p><details><summary>Full coverage observations</summary><p id="md-risk"></p><p id="md-funding"></p></details></section>
            </div>
            <details><summary>Forecast assumptions and supporting figures</summary><p id="md-assumptions"></p><p id="md-forecast-note"></p><p id="md-freshness-detail"></p><ul id="md-trend-warnings"></ul><div id="md-all-projections"></div></details>
            <details open><summary>Predictive budget comparison</summary><div id="md-budget-compare" class="md-findings"></div></details>
        </div>
        <noscript><p>Enable JavaScript to load forecast findings. Recorded financial information remains available above.</p></noscript>
    </section>
</div>
