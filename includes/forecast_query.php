<?php

/**
 * Read side of the predictive forecast.
 *
 * Every aggregate the forecast depends on is built here so the endpoint, the
 * cache and the baseline projection all describe the same window of history.
 * Nothing in this file writes.
 *
 * All arithmetic is done in PHP rather than delegated to the model. The model
 * is asked to interpret numbers, never to compute them.
 */

const FORECAST_HISTORY_MONTHS = 12;
const FORECAST_HORIZON_MONTHS = 6;
const FORECAST_TTL_HOURS = 24;

// A forecast drawn from a single month is a straight line through one point.
const FORECAST_MIN_ACTIVE_MONTHS = 2;

// Only the largest sources matter for concentration risk, and a long tail of
// one-off donors would bloat the prompt for nothing.
const FORECAST_MAX_DONORS = 10;

/**
 * The complete history bundle behind one forecast.
 *
 * The window holds closed months only. The month in progress is reported
 * separately as month-to-date: charting a half-recorded month next to twelve
 * finished ones reads as a collapse in spending rather than as a month that has
 * not happened yet.
 *
 * @return array{
 *     as_of: string,
 *     months: string[],
 *     projection_months: string[],
 *     series: array<int, array{month: string, outflow: float, inflow: float}>,
 *     current_month: array{month: string, outflow: float, inflow: float},
 *     categories: array<int, array<string, mixed>>,
 *     donors: array<int, array<string, mixed>>,
 *     metrics: array<string, mixed>
 * }
 */
function forecast_fetch_history(PDO $pdo): array
{
    $months = forecast_month_window(FORECAST_HISTORY_MONTHS);
    $start = $months[0] . '-01';
    $end = date('Y-m-d');
    $currentMonth = date('Y-m');

    // Last day of the final closed month. Donor shares are measured against the
    // closed-month inflow total, so the two must cover the same period or a
    // donor who only gave this month would come out above 100 percent.
    $closedEnd = date('Y-m-t', (int) strtotime(end($months) . '-01'));

    $outflowByMonth = array_fill_keys($months, 0.0);
    $inflowByMonth = array_fill_keys($months, 0.0);
    $categoryMonths = [];
    $donors = [];
    $currentOutflow = 0.0;
    $currentInflow = 0.0;

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(Date_Incurred, '%Y-%m') AS Period, Category,
                SUM(Amount) AS Total, COUNT(*) AS Entries
         FROM Expenses
         WHERE Date_Incurred >= :start AND Date_Incurred <= :end
         GROUP BY Period, Category
         ORDER BY Period ASC, Category ASC"
    );
    $stmt->execute(['start' => $start, 'end' => $end]);

    foreach ($stmt->fetchAll() as $row) {
        $period = (string) $row['Period'];
        $total = (float) $row['Total'];

        if ($period === $currentMonth) {
            $currentOutflow += $total;
            continue;
        }

        if (!isset($outflowByMonth[$period])) {
            continue;
        }

        $category = (string) $row['Category'];

        $outflowByMonth[$period] += $total;
        $categoryMonths[$category][$period] = ($categoryMonths[$category][$period] ?? 0.0) + $total;
    }

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(Date_Received, '%Y-%m') AS Period, SUM(Amount) AS Total
         FROM Incoming_Funds
         WHERE Date_Received >= :start AND Date_Received <= :end
         GROUP BY Period
         ORDER BY Period ASC"
    );
    $stmt->execute(['start' => $start, 'end' => $end]);

    foreach ($stmt->fetchAll() as $row) {
        $period = (string) $row['Period'];

        if ($period === $currentMonth) {
            $currentInflow += (float) $row['Total'];
            continue;
        }

        if (isset($inflowByMonth[$period])) {
            $inflowByMonth[$period] += (float) $row['Total'];
        }
    }

    $stmt = $pdo->prepare(
        'SELECT Source_Donor, SUM(Amount) AS Total, COUNT(*) AS Entries,
                MAX(Date_Received) AS Last_Received
         FROM Incoming_Funds
         WHERE Date_Received >= :start AND Date_Received <= :end
         GROUP BY Source_Donor
         ORDER BY Total DESC'
    );
    $stmt->execute(['start' => $start, 'end' => $closedEnd]);
    $donorRows = $stmt->fetchAll();

    $series = [];
    foreach ($months as $month) {
        $series[] = [
            'month'   => $month,
            'outflow' => round($outflowByMonth[$month], 2),
            'inflow'  => round($inflowByMonth[$month], 2),
        ];
    }

    $activeMonths = forecast_active_months($months, $outflowByMonth, $inflowByMonth);
    $activeCount = max(1, count($activeMonths));

    $totalOutflow = array_sum($outflowByMonth);
    $totalInflow = array_sum($inflowByMonth);
    $recentOutflow = forecast_recent_average($months, $outflowByMonth);

    $categories = [];
    foreach ($categoryMonths as $name => $byMonth) {
        $categoryTotal = array_sum($byMonth);
        $categoryAverage = $categoryTotal / $activeCount;
        $categoryRecent = forecast_recent_average($months, $byMonth + array_fill_keys($months, 0.0));

        $categories[] = [
            'category'      => $name,
            'total'         => round($categoryTotal, 2),
            'share_pct'     => $totalOutflow > 0 ? round(($categoryTotal / $totalOutflow) * 100, 1) : 0.0,
            'monthly_avg'   => round($categoryAverage, 2),
            'recent_avg'    => round($categoryRecent, 2),
            'months_active' => count(array_filter($byMonth, static fn ($value) => $value > 0)),
            'trend'         => forecast_trend($categoryRecent, $categoryAverage),
        ];
    }

    usort($categories, static fn (array $a, array $b) => $b['total'] <=> $a['total']);

    foreach (array_slice($donorRows, 0, FORECAST_MAX_DONORS) as $row) {
        $donorTotal = (float) $row['Total'];

        $donors[] = [
            'source'        => (string) $row['Source_Donor'],
            'total'         => round($donorTotal, 2),
            'share_pct'     => $totalInflow > 0 ? round(($donorTotal / $totalInflow) * 100, 1) : 0.0,
            'entries'       => (int) $row['Entries'],
            'last_received' => (string) $row['Last_Received'],
        ];
    }

    $gaps = forecast_funding_gaps($activeMonths, $inflowByMonth);

    return [
        'as_of'             => $end,
        'months'            => $months,
        // Starts with the month in progress, so the projection picks up exactly
        // where the plotted history stops.
        'projection_months' => forecast_projection_months(end($months), FORECAST_HORIZON_MONTHS),
        'series'            => $series,
        'current_month'     => [
            'month'   => $currentMonth,
            'outflow' => round($currentOutflow, 2),
            'inflow'  => round($currentInflow, 2),
        ],
        'categories'        => $categories,
        'donors'            => $donors,
        'metrics'           => [
            'window_months'         => count($months),
            'active_months'         => count($activeMonths),
            'total_outflow'         => round($totalOutflow, 2),
            'total_inflow'          => round($totalInflow, 2),
            'avg_monthly_outflow'   => round($totalOutflow / $activeCount, 2),
            'avg_monthly_inflow'    => round($totalInflow / $activeCount, 2),
            'recent_avg_outflow'    => round($recentOutflow, 2),
            'donor_count'           => count($donorRows),
            'top_donor_share_pct'   => $donors === [] ? 0.0 : $donors[0]['share_pct'],
            'longest_funding_gap'   => $gaps['longest'],
            'months_since_inflow'   => $gaps['since_last'],
        ] + forecast_position_metrics($pdo, $recentOutflow),
    ];
}

/**
 * All-time balance, matching the totals the dashboard cards already display,
 * plus the runway those totals imply at the current burn rate.
 *
 * @return array{total_funds_all_time: float, total_expenses_all_time: float,
 *               net_position: float, runway_months: ?float}
 */
function forecast_position_metrics(PDO $pdo, float $recentOutflow): array
{
    $totalFunds = (float) $pdo->query('SELECT COALESCE(SUM(Amount), 0) FROM Incoming_Funds')->fetchColumn();
    $totalExpenses = (float) $pdo->query('SELECT COALESCE(SUM(Amount), 0) FROM Expenses')->fetchColumn();
    $netPosition = $totalFunds - $totalExpenses;

    return [
        'total_funds_all_time'    => round($totalFunds, 2),
        'total_expenses_all_time' => round($totalExpenses, 2),
        'net_position'            => round($netPosition, 2),
        // Meaningless when nothing is being spent or the balance is already
        // negative; null says so rather than reporting a misleading number.
        'runway_months'           => $recentOutflow > 0 && $netPosition > 0
            ? round($netPosition / $recentOutflow, 1)
            : null,
    ];
}

/**
 * The last $count closed calendar months, oldest first, ending with the month
 * before the current one.
 *
 * @return string[] Y-m keys.
 */
function forecast_month_window(int $count): array
{
    $months = [];
    $cursor = new DateTimeImmutable('first day of this month');

    for ($i = $count; $i >= 1; $i--) {
        $months[] = $cursor->sub(new DateInterval('P' . $i . 'M'))->format('Y-m');
    }

    return $months;
}

/**
 * The $count months following the given one, oldest first.
 *
 * @return string[] Y-m keys.
 */
function forecast_projection_months(string $fromMonth, int $count): array
{
    $cursor = DateTimeImmutable::createFromFormat('Y-m-d', $fromMonth . '-01');
    if ($cursor === false) {
        $cursor = new DateTimeImmutable('first day of this month');
    }

    $months = [];
    for ($i = 1; $i <= $count; $i++) {
        $months[] = $cursor->add(new DateInterval('P' . $i . 'M'))->format('Y-m');
    }

    return $months;
}

/**
 * The months from the first recorded activity onward.
 *
 * Averaging across the full 12-month window would halve the apparent burn rate
 * of an organization that has only been recording for six months.
 *
 * @param string[]              $months
 * @param array<string, float>  $outflow
 * @param array<string, float>  $inflow
 *
 * @return string[]
 */
function forecast_active_months(array $months, array $outflow, array $inflow): array
{
    foreach ($months as $index => $month) {
        if (($outflow[$month] ?? 0.0) > 0 || ($inflow[$month] ?? 0.0) > 0) {
            return array_slice($months, $index);
        }
    }

    return [];
}

/**
 * Trailing three-month average over the closed months in the window.
 *
 * @param string[]             $months
 * @param array<string, float> $values
 */
function forecast_recent_average(array $months, array $values): float
{
    $window = array_slice($months, -3);

    if ($window === []) {
        return 0.0;
    }

    $sum = 0.0;
    foreach ($window as $month) {
        $sum += $values[$month] ?? 0.0;
    }

    return $sum / count($window);
}

/**
 * Direction of travel for a category, using a dead band so ordinary
 * month-to-month noise is not reported as a trend.
 */
function forecast_trend(float $recent, float $average): string
{
    if ($average <= 0) {
        return $recent > 0 ? 'rising' : 'steady';
    }

    $ratio = $recent / $average;

    if ($ratio >= 1.15) {
        return 'rising';
    }
    if ($ratio <= 0.85) {
        return 'falling';
    }

    return 'steady';
}

/**
 * Dry spells in the funding history, which is what expiration risk looks like
 * in this schema: there is no grant end-date column to read.
 *
 * @param string[]             $activeMonths
 * @param array<string, float> $inflow
 *
 * @return array{longest: int, since_last: ?int}
 */
function forecast_funding_gaps(array $activeMonths, array $inflow): array
{
    $longest = 0;
    $running = 0;
    $sawInflow = false;

    foreach ($activeMonths as $month) {
        if (($inflow[$month] ?? 0.0) > 0) {
            $sawInflow = true;
            $running = 0;
            continue;
        }

        $running++;
        $longest = max($longest, $running);
    }

    // null rather than a count when no funds have ever been recorded: there is
    // no "last inflow" to measure from.
    return ['longest' => $longest, 'since_last' => $sawInflow ? $running : null];
}

/**
 * Is there enough history for a projection to mean anything?
 */
function forecast_history_is_sufficient(array $history): bool
{
    $monthsWithOutflow = 0;

    foreach ($history['series'] as $point) {
        if ($point['outflow'] > 0) {
            $monthsWithOutflow++;
        }
    }

    return $monthsWithOutflow >= FORECAST_MIN_ACTIVE_MONTHS;
}

/**
 * Flat trailing-average projection.
 *
 * Doubles as the fallback when the AI is unavailable and as the anchor the
 * model's numbers are sanity-checked against, so the chart can always be drawn.
 *
 * @return array<int, array{month: string, projected_outflow: float}>
 */
function forecast_baseline_projection(array $history): array
{
    $baseline = round((float) $history['metrics']['recent_avg_outflow'], 2);
    $projection = [];

    foreach ($history['projection_months'] as $month) {
        $projection[] = ['month' => $month, 'projected_outflow' => $baseline];
    }

    return $projection;
}

/**
 * The largest single month of outflow on record, used to bound what the model
 * is allowed to project.
 */
function forecast_peak_outflow(array $history): float
{
    $peak = 0.0;

    foreach ($history['series'] as $point) {
        $peak = max($peak, (float) $point['outflow']);
    }

    return $peak;
}

/**
 * Stable identity for one set of aggregates.
 *
 * Stored alongside the cache row so a forecast can be reported against the data
 * it came from. It deliberately does not invalidate the cache: expiring on any
 * data change would mean a paid API call after every expense entry.
 */
function forecast_fingerprint(array $history): string
{
    $encoded = json_encode(
        [$history['series'], $history['categories'], $history['donors']],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return sha1($encoded === false ? (string) microtime(true) : $encoded);
}

/**
 * Compact the bundle into the shape the Gemini prompt consumes.
 *
 * Drops the fields the model has no use for and keeps the payload to a couple
 * of kilobytes.
 *
 * @return array<string, mixed>
 */
function forecast_history_for_ai(array $history): array
{
    return [
        'currency'              => 'PHP',
        'as_of'                 => $history['as_of'],
        'monthly_history'       => $history['series'],
        'current_month_to_date' => $history['current_month'],
        'category_outflow'      => $history['categories'],
        'funding_sources'       => $history['donors'],
        'metrics'               => $history['metrics'],
        'projection_months'     => $history['projection_months'],
        'baseline_projection'   => forecast_baseline_projection($history),
    ];
}
