<?php

require_once __DIR__ . '/forecast_query.php';
require_once __DIR__ . '/ledger_query.php';

/**
 * Recorded monthly activity and month-end balances, oldest first.
 * Optional month keys allow deterministic checks without changing the clock.
 *
 * @param string[]|null $months Six consecutive completed Y-m month keys.
 * @return array<int, array{month: string, income: float, expenses: float, balance: float}>
 */
function dashboard_kpi_series(PDO $pdo, ?array $months = null): array
{
    $months ??= forecast_month_window(6);
    $start = $months[0] . '-01';
    $end = (new DateTimeImmutable(end($months) . '-01'))->modify('+1 month')->format('Y-m-d');
    $income = array_fill_keys($months, 0);
    $expenses = array_fill_keys($months, 0);

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT (SELECT COALESCE(SUM(Amount), 0) FROM Incoming_Funds WHERE Date_Received < :incoming_start)
                  - (SELECT COALESCE(SUM(Amount), 0) FROM Expenses WHERE Date_Incurred < :expense_start)'
        );
        $stmt->execute(['incoming_start' => $start, 'expense_start' => $start]);
        $balance = ledger_cents((string) $stmt->fetchColumn());

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(Date_Received, '%Y-%m') AS month, SUM(Amount) AS total
             FROM Incoming_Funds WHERE Date_Received >= :start AND Date_Received < :end
             GROUP BY month ORDER BY month"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $income[$row['month']] = ledger_cents((string) $row['total']);
        }

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(Date_Incurred, '%Y-%m') AS month, SUM(Amount) AS total
             FROM Expenses WHERE Date_Incurred >= :start AND Date_Incurred < :end
             GROUP BY month ORDER BY month"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expenses[$row['month']] = ledger_cents((string) $row['total']);
        }

        $series = [];
        foreach ($months as $month) {
            $balance += $income[$month] - $expenses[$month];
            $series[] = [
                'month' => $month,
                'income' => (float) ledger_decimal($income[$month]),
                'expenses' => (float) ledger_decimal($expenses[$month]),
                'balance' => (float) ledger_decimal($balance),
            ];
        }
        if ($ownsTransaction) { $pdo->commit(); }
        return $series;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
