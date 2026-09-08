<?php

/**
 * Unified read-only ledger queries for Financial Records and monthly reports.
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

/** Convert database DECIMAL values to cents without floating-point arithmetic. */
function ledger_cents(string $amount): int
{
    if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,2}))?$/', $amount, $m)) {
        throw new UnexpectedValueException('Invalid ledger amount.');
    }
    $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
    return ($m[1] === '-' ? -1 : 1) * $cents;
}

function ledger_decimal(int $cents): string
{
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

function ledger_money(string $amount): string
{
    $cents = ledger_cents($amount);
    return ($cents < 0 ? '-' : '') . "\u{20B1}" . number_format(intdiv(abs($cents), 100), 0, '.', ',') . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

/** Shared source projection; source amounts are always unsigned transaction values. */
function ledger_source_sql(string $incomingWhere, string $expenseWhere): string
{
    return "SELECT 'Incoming' AS txn_type, 0 AS type_order, FundID AS record_id,
        Date_Received AS txn_date, Category AS category, Source_Donor AS party,
        Amount AS amount, Purpose AS purpose, Project_Code AS project_code
        FROM Incoming_Funds WHERE $incomingWhere
        UNION ALL
        SELECT 'Expense', 1, ExpenseID, Date_Incurred, Category, Payee, Amount, Purpose, Project_Code
        FROM Expenses WHERE $expenseWhere";
}

/**
 * Organization balances for an inclusive date range. Category/type filters are
 * deliberately applied AFTER balances. One consistent snapshot covers both reads.
 * @return array{rows: array, opening_balance: string, closing_balance: string, incoming_total: string, expense_total: string}
 */
function ledger_period(PDO $pdo, string $from, string $to): array
{
    $end = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
    }
    try {
        $sql = ledger_source_sql('Date_Received < :incoming_start', 'Date_Incurred < :expense_start');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN txn_type = 'Incoming' THEN amount ELSE -amount END), 0) FROM ($sql) AS history");
        $stmt->execute(['incoming_start' => $from, 'expense_start' => $from]);
        $opening = ledger_cents((string) $stmt->fetchColumn());
        $sql = ledger_source_sql('Date_Received >= :incoming_start AND Date_Received < :incoming_end',
            'Date_Incurred >= :expense_start AND Date_Incurred < :expense_end');
        $stmt = $pdo->prepare("SELECT * FROM ($sql) AS ledger ORDER BY txn_date ASC, type_order ASC, record_id ASC");
        $stmt->execute(['incoming_start' => $from, 'incoming_end' => $end, 'expense_start' => $from, 'expense_end' => $end]);
        $rows = $stmt->fetchAll();
        $balance = $opening;
        $incoming = $expense = 0;
        foreach ($rows as &$row) {
            $cents = ledger_cents((string) $row['amount']);
            if ($row['txn_type'] === 'Incoming') {
                $incoming += $cents;
                $balance += $cents;
            } else {
                $expense += $cents;
                $balance -= $cents;
            }
            $row['record_id'] = (int) $row['record_id'];
            $row['amount'] = ledger_decimal($cents);
            $row['remaining_balance'] = ledger_decimal($balance);
        }
        unset($row);
        if ($ownsTransaction) { $pdo->commit(); }
        return ['rows' => $rows, 'opening_balance' => ledger_decimal($opening),
            'closing_balance' => ledger_decimal($balance), 'incoming_total' => ledger_decimal($incoming),
            'expense_total' => ledger_decimal($expense)];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function ledger_filtered_rows(array $rows, array $filters): array
{
    return array_values(array_filter($rows, static fn ($row) =>
        (($filters['type'] ?? '') === '' || $row['txn_type'] === $filters['type']) &&
        (($filters['category'] ?? '') === '' || $row['category'] === $filters['category'])));
}

function ledger_completeness(array $rows): array
{
    $counts = ['affected' => 0, 'missing_purpose' => 0, 'unallocated' => 0];
    foreach ($rows as $row) {
        $purpose = trim($row['purpose'] ?? '') === '';
        $allocation = trim($row['project_code'] ?? '') === '';
        $counts['missing_purpose'] += (int) $purpose;
        $counts['unallocated'] += (int) $allocation;
        $counts['affected'] += (int) ($purpose || $allocation);
    }
    return $counts;
}

function ledger_view(PDO $pdo, array $filters): array
{
    $period = ledger_period($pdo, $filters['from'], $filters['to']);
    $rows = ledger_filtered_rows($period['rows'], $filters);
    return ['total' => count($rows), 'completeness' => ledger_completeness($rows),
        'rows' => array_slice(array_reverse($rows), ($filters['page'] - 1) * LEDGER_PAGE_SIZE, LEDGER_PAGE_SIZE)];
}

function ledger_count(PDO $pdo, array $filters): int
{
    return ledger_view($pdo, $filters + ['page' => 1])['total'];
}

function ledger_fetch(PDO $pdo, array $filters): array
{
    return ledger_view($pdo, $filters)['rows'];
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
