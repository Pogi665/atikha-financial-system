<?php

/**
 * Informational warnings when a new expense crosses budget or amount thresholds.
 */

require_once __DIR__ . '/budget_query.php';
require_once __DIR__ . '/notifications.php';

const EXPENSE_WARNING_AMOUNT = 50000.00;

/**
 * Compare category utilization before and after an expense insert.
 *
 * @param array{ExpenseID?: int, Payee?: string, Category: string, Amount: string|float,
 *              Date_Incurred: string, RecordedBy_UserID?: int} $expense
 */
function expense_check_and_notify_warnings(PDO $pdo, array $expense, ?string $recordedByName = null): void
{
    $amount = (float) ($expense['Amount'] ?? 0);
    $category = (string) ($expense['Category'] ?? '');
    $dateIncurred = (string) ($expense['Date_Incurred'] ?? '');

    if ($amount <= 0 || $category === '' || $dateIncurred === '') {
        return;
    }

    $timestamp = strtotime($dateIncurred);
    if ($timestamp === false) {
        return;
    }

    $year = (int) date('Y', $timestamp);
    $month = (int) date('n', $timestamp);
    $expenseId = (int) ($expense['ExpenseID'] ?? 0);
    $targetUrl = $expenseId > 0
        ? 'management_reviews.php?entity=expense&id=' . $expenseId
        : 'management_reviews.php';

    $name = $recordedByName !== null && $recordedByName !== ''
        ? $recordedByName
        : 'Staff';

    if ($amount > EXPENSE_WARNING_AMOUNT) {
        $formatted = '₱' . number_format($amount, 2);
        notification_notify_management(
            $pdo,
            'Expense of ' . $formatted . ' logged by ' . $name . ' exceeds the ₱50,000 limit.',
            $targetUrl
        );
    }

    $spentByCategory = budget_mtd_spent_by_category($pdo, $year, $month);
    $postSpent = $spentByCategory[$category] ?? 0.0;
    $preSpent = max(0.0, $postSpent - $amount);

    $budgetRows = budget_fetch_month($pdo, $year, $month);
    $budgeted = 0.0;
    foreach ($budgetRows as $row) {
        if ($row['category'] === $category) {
            $budgeted = $row['amount'];
            break;
        }
    }

    if ($budgeted <= 0) {
        return;
    }

    $prePct = round(($preSpent / $budgeted) * 100, 1);
    $postPct = round(($postSpent / $budgeted) * 100, 1);

    if ($prePct < BUDGET_WARNING_PCT && $postPct >= BUDGET_WARNING_PCT) {
        notification_notify_management(
            $pdo,
            'Budget alert: ' . $category . ' utilization reached ' . $postPct . '% (₱'
            . number_format($postSpent, 2) . ' of ₱' . number_format($budgeted, 2) . ').',
            'dashboard.php'
        );
    }
}
