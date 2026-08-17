<?php

/**
 * Persist monthly income statement totals for the review workflow.
 */

require_once __DIR__ . '/notifications.php';

/**
 * Compute revenue, expense, and net totals for a calendar month.
 *
 * @return array{total_revenue: float, total_expenses: float, net_income: float}
 */
function report_compute_period_totals(PDO $pdo, int $month, int $year): array
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(Amount), 0) AS Total
         FROM Incoming_Funds
         WHERE MONTH(Date_Received) = :m AND YEAR(Date_Received) = :y'
    );
    $stmt->execute(['m' => $month, 'y' => $year]);
    $totalRevenue = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(Amount), 0) AS Total
         FROM Expenses
         WHERE MONTH(Date_Incurred) = :m AND YEAR(Date_Incurred) = :y'
    );
    $stmt->execute(['m' => $month, 'y' => $year]);
    $totalExpenses = (float) $stmt->fetchColumn();

    return [
        'total_revenue'  => $totalRevenue,
        'total_expenses' => $totalExpenses,
        'net_income'     => $totalRevenue - $totalExpenses,
    ];
}

/**
 * Create or update a report snapshot and mark it for Management review.
 *
 * @return array{ok: bool, error: string, report_id: int}
 */
function report_snapshot_upsert(PDO $pdo, int $month, int $year, int $userId): array
{
    if ($month < 1 || $month > 12 || $year < 2000 || $userId <= 0) {
        return ['ok' => false, 'error' => 'Invalid report period.', 'report_id' => 0];
    }

    $totals = report_compute_period_totals($pdo, $month, $year);

    try {
        $stmt = $pdo->prepare(
            'SELECT ReportID, Review_Status FROM Reports
             WHERE Report_Month = :month AND Report_Year = :year'
        );
        $stmt->execute(['month' => $month, 'year' => $year]);
        $existing = $stmt->fetch();

        if ($existing === false) {
            $insert = $pdo->prepare(
                'INSERT INTO Reports
                    (Report_Month, Report_Year, SubmittedBy_UserID,
                     Total_Revenue, Total_Expenses, Net_Income, Review_Status)
                 VALUES
                    (:month, :year, :user_id,
                     :total_revenue, :total_expenses, :net_income, :review_status)'
            );
            $insert->execute([
                'month'          => $month,
                'year'           => $year,
                'user_id'        => $userId,
                'total_revenue'  => number_format($totals['total_revenue'], 2, '.', ''),
                'total_expenses' => number_format($totals['total_expenses'], 2, '.', ''),
                'net_income'     => number_format($totals['net_income'], 2, '.', ''),
                'review_status'  => 'Requested',
            ]);
            $reportId = (int) $pdo->lastInsertId();
        } else {
            $reportId = (int) $existing['ReportID'];
            $update = $pdo->prepare(
                'UPDATE Reports
                 SET SubmittedBy_UserID = :user_id,
                     Total_Revenue = :total_revenue,
                     Total_Expenses = :total_expenses,
                     Net_Income = :net_income,
                     Review_Status = :review_status,
                     Review_Notes = NULL
                 WHERE ReportID = :report_id'
            );
            $update->execute([
                'user_id'        => $userId,
                'total_revenue'  => number_format($totals['total_revenue'], 2, '.', ''),
                'total_expenses' => number_format($totals['total_expenses'], 2, '.', ''),
                'net_income'     => number_format($totals['net_income'], 2, '.', ''),
                'review_status'  => 'Requested',
                'report_id'      => $reportId,
            ]);
        }

        $periodLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        notification_notify_management(
            $pdo,
            'Monthly report for ' . $periodLabel . ' has been submitted for review.',
            'management_reviews.php?entity=report&id=' . $reportId
        );

        return ['ok' => true, 'error' => '', 'report_id' => $reportId];
    } catch (PDOException $e) {
        error_log('Report snapshot upsert failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to submit the report for review.', 'report_id' => 0];
    }
}

/**
 * @return array<string, mixed>|null
 */
function report_snapshot_load(PDO $pdo, int $month, int $year): ?array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT ReportID, Report_Month, Report_Year, SubmittedBy_UserID,
                    Total_Revenue, Total_Expenses, Net_Income, Review_Status, Review_Notes, Created_At
             FROM Reports
             WHERE Report_Month = :month AND Report_Year = :year'
        );
        $stmt->execute(['month' => $month, 'year' => $year]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    } catch (PDOException $e) {
        return null;
    }
}
