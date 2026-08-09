<?php

/**
 * Unified read-only ledger queries for financial_records.php.
 */

const LEDGER_PAGE_SIZE = 50;

/**
 * @return array{from: string, to: string, type: string, category: string, page: int}
 */
function ledger_parse_filters(array $get): array
{
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $from = isset($get['from']) ? trim((string) $get['from']) : $monthStart;
    $to = isset($get['to']) ? trim((string) $get['to']) : $today;
    $type = isset($get['type']) ? trim((string) $get['type']) : '';
    $category = isset($get['category']) ? trim((string) $get['category']) : '';
    $page = isset($get['page']) ? max(1, (int) $get['page']) : 1;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || strtotime($from) === false) {
        $from = $monthStart;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || strtotime($to) === false) {
        $to = $today;
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    if (!in_array($type, ['', 'Incoming', 'Expense'], true)) {
        $type = '';
    }

    return [
        'from'     => $from,
        'to'       => $to,
        'type'     => $type,
        'category' => $category,
        'page'     => $page,
    ];
}

/**
 * @param array{from: string, to: string, type: string, category: string} $filters
 */
function ledger_count(PDO $pdo, array $filters): int
{
    $sql = 'SELECT COUNT(*) FROM (
        SELECT Category, Date_Received AS txn_date, \'Incoming\' AS txn_type
        FROM Incoming_Funds
        UNION ALL
        SELECT Category, Date_Incurred AS txn_date, \'Expense\' AS txn_type
        FROM Expenses
    ) AS ledger
    WHERE txn_date BETWEEN :from_date AND :to_date';

    $params = [
        'from_date' => $filters['from'],
        'to_date'   => $filters['to'],
    ];

    if ($filters['type'] !== '') {
        $sql .= ' AND txn_type = :txn_type';
        $params['txn_type'] = $filters['type'];
    }
    if ($filters['category'] !== '') {
        $sql .= ' AND Category = :category';
        $params['category'] = $filters['category'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * @param array{from: string, to: string, type: string, category: string, page: int} $filters
 * @return array<int, array{txn_type: string, record_id: int, txn_date: string, category: string, party: string, amount: float, project_code: ?string}>
 */
function ledger_fetch(PDO $pdo, array $filters): array
{
    $offset = ($filters['page'] - 1) * LEDGER_PAGE_SIZE;

    $sql = 'SELECT txn_type, record_id, txn_date, Category AS category, party, Amount AS amount, project_code
            FROM (
                SELECT \'Incoming\' AS txn_type,
                       FundID AS record_id,
                       Date_Received AS txn_date,
                       Category,
                       Source_Donor AS party,
                       Amount,
                       Project_Code AS project_code
                FROM Incoming_Funds
                UNION ALL
                SELECT \'Expense\' AS txn_type,
                       ExpenseID AS record_id,
                       Date_Incurred AS txn_date,
                       Category,
                       Payee AS party,
                       Amount,
                       NULL AS project_code
                FROM Expenses
            ) AS ledger
            WHERE txn_date BETWEEN :from_date AND :to_date';

    $params = [
        'from_date' => $filters['from'],
        'to_date'   => $filters['to'],
    ];

    if ($filters['type'] !== '') {
        $sql .= ' AND txn_type = :txn_type';
        $params['txn_type'] = $filters['type'];
    }
    if ($filters['category'] !== '') {
        $sql .= ' AND Category = :category';
        $params['category'] = $filters['category'];
    }

    $sql .= ' ORDER BY txn_date DESC, record_id DESC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', LEDGER_PAGE_SIZE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'txn_type'     => (string) $row['txn_type'],
            'record_id'    => (int) $row['record_id'],
            'txn_date'     => (string) $row['txn_date'],
            'category'     => (string) $row['category'],
            'party'        => (string) $row['party'],
            'amount'       => round((float) $row['amount'], 2),
            'project_code' => $row['project_code'] !== null ? (string) $row['project_code'] : null,
        ];
    }

    return $rows;
}

/**
 * Distinct categories for filter dropdown (fund + expense names).
 *
 * @return string[]
 */
function ledger_category_options(PDO $pdo): array
{
    require_once __DIR__ . '/categories.php';

    $expense = fetch_category_names_safe($pdo, CATEGORY_TYPE_EXPENSE);
    $fund = fetch_category_names_safe($pdo, CATEGORY_TYPE_FUND);
    $merged = array_unique(array_merge($fund, $expense));
    sort($merged);

    return array_values($merged);
}
