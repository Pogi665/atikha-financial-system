<?php

/**
 * Budget read helpers for Executive Suite utilization KPIs.
 *
 * Budgets are seeded per category/month; Admin CRUD is deferred.
 */

const BUDGET_DEFAULT_PLACEHOLDER = 10000.00;

/**
 * All category budgets for a calendar month.
 *
 * @return array<int, array{category: string, amount: float}>
 */
function budget_fetch_month(PDO $pdo, int $year, int $month): array
{
    $stmt = $pdo->prepare(
        'SELECT Category, Amount
         FROM Budgets
         WHERE Year = :year AND Month = :month
         ORDER BY Category ASC'
    );
    $stmt->execute(['year' => $year, 'month' => $month]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'category' => (string) $row['Category'],
            'amount'   => round((float) $row['Amount'], 2),
        ];
    }

    return $rows;
}

/**
 * Sum of all category budgets for a calendar month.
 */
function budget_total_for_month(PDO $pdo, int $year, int $month): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(Amount), 0) AS total
         FROM Budgets
         WHERE Year = :year AND Month = :month'
    );
    $stmt->execute(['year' => $year, 'month' => $month]);

    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * Month-to-date expense spend for a calendar month.
 */
function budget_mtd_spent(PDO $pdo, int $year, int $month): float
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', (int) strtotime($start));

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(Amount), 0) AS total
         FROM Expenses
         WHERE Date_Incurred >= :start AND Date_Incurred <= :end'
    );
    $stmt->execute(['start' => $start, 'end' => $end]);

    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * Month-to-date spend grouped by expense category.
 *
 * @return array<string, float> category => spent
 */
function budget_mtd_spent_by_category(PDO $pdo, int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', (int) strtotime($start));

    $stmt = $pdo->prepare(
        'SELECT Category, COALESCE(SUM(Amount), 0) AS total
         FROM Expenses
         WHERE Date_Incurred >= :start AND Date_Incurred <= :end
         GROUP BY Category'
    );
    $stmt->execute(['start' => $start, 'end' => $end]);

    $byCategory = [];
    foreach ($stmt->fetchAll() as $row) {
        $byCategory[(string) $row['Category']] = round((float) $row['total'], 2);
    }

    return $byCategory;
}

/**
 * Utilization summary for the Executive Dashboard KPI card.
 *
 * @return array{
 *     spent: float,
 *     budgeted: float,
 *     pct: float,
 *     by_category: array<int, array{category: string, spent: float, budgeted: float, pct: float}>
 * }
 */
function budget_utilization(PDO $pdo, int $year, int $month): array
{
    $budgeted = budget_total_for_month($pdo, $year, $month);
    $spent = budget_mtd_spent($pdo, $year, $month);
    $spentByCategory = budget_mtd_spent_by_category($pdo, $year, $month);
    $budgetRows = budget_fetch_month($pdo, $year, $month);

    $pct = $budgeted > 0 ? round(($spent / $budgeted) * 100, 1) : 0.0;

    $byCategory = [];
    foreach ($budgetRows as $row) {
        $category = $row['category'];
        $catBudget = $row['amount'];
        $catSpent = $spentByCategory[$category] ?? 0.0;
        $catPct = $catBudget > 0 ? round(($catSpent / $catBudget) * 100, 1) : 0.0;

        $byCategory[] = [
            'category' => $category,
            'spent'    => $catSpent,
            'budgeted' => $catBudget,
            'pct'      => $catPct,
        ];
    }

    // Categories with spend but no budget row still matter for alerts.
    foreach ($spentByCategory as $category => $catSpent) {
        $found = false;
        foreach ($byCategory as $row) {
            if ($row['category'] === $category) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $byCategory[] = [
                'category' => $category,
                'spent'    => $catSpent,
                'budgeted' => 0.0,
                'pct'      => 0.0,
            ];
        }
    }

    usort($byCategory, static fn (array $a, array $b) => $b['spent'] <=> $a['spent']);

    return [
        'spent'         => $spent,
        'budgeted'      => $budgeted,
        'pct'           => $pct,
        'by_category'   => $byCategory,
    ];
}

/**
 * Categories where MTD spend exceeds the budgeted amount.
 *
 * @return array<int, array{category: string, spent: float, budgeted: float, over_by: float}>
 */
function budget_overrun_categories(PDO $pdo, int $year, int $month): array
{
    $utilization = budget_utilization($pdo, $year, $month);
    $overruns = [];

    foreach ($utilization['by_category'] as $row) {
        if ($row['budgeted'] > 0 && $row['spent'] > $row['budgeted']) {
            $overruns[] = [
                'category' => $row['category'],
                'spent'    => $row['spent'],
                'budgeted' => $row['budgeted'],
                'over_by'  => round($row['spent'] - $row['budgeted'], 2),
            ];
        }
    }

    usort($overruns, static fn (array $a, array $b) => $b['over_by'] <=> $a['over_by']);

    return $overruns;
}

/**
 * Sum of budgets for upcoming months (used in predictive panel).
 *
 * @return array<int, array{year: int, month: int, total: float}>
 */
function budget_upcoming_totals(PDO $pdo, int $monthsAhead = 3): array
{
    $results = [];
    $cursor = new DateTime('first day of this month');

    for ($i = 0; $i < $monthsAhead; $i++) {
        $year = (int) $cursor->format('Y');
        $month = (int) $cursor->format('n');
        $results[] = [
            'year'  => $year,
            'month' => $month,
            'total' => budget_total_for_month($pdo, $year, $month),
        ];
        $cursor->modify('+1 month');
    }

    return $results;
}
