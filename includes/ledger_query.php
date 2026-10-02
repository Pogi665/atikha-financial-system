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

    foreach (['from', 'to', 'type', 'category', 'filter_category', 'filter_type', 'page'] as $key) {
        if (array_key_exists($key, $get) && !is_string($get[$key])) {
            throw new InvalidArgumentException('Invalid ledger filter. Please use a single value for each filter.');
        }
    }
    $accountFilter = array_key_exists('filter_category', $get) || array_key_exists('filter_type', $get);
    if ($accountFilter && (trim($get['filter_category'] ?? '') === '' ||
        !in_array($get['filter_type'] ?? '', ['Fund', 'Expense'], true))) {
        throw new InvalidArgumentException('Choose an account name and a valid account type to view transactions.');
    }
    $from = isset($get['from']) ? trim($get['from']) : ($accountFilter ? '' : $monthStart);
    $to = isset($get['to']) ? trim($get['to']) : ($accountFilter ? '' : $today);
    $type = $accountFilter ? ($get['filter_type'] === 'Fund' ? 'Incoming' : 'Expense') : trim($get['type'] ?? '');
    // Preserve the exact stored name, including whitespace, across pagination.
    $category = $accountFilter ? $get['filter_category'] : ($get['category'] ?? '');
    $page = isset($get['page']) ? max(1, (int) $get['page']) : 1;

    foreach ([$from, $to] as $date) {
        if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
            throw new InvalidArgumentException('Choose valid dates for the ledger filter.');
        }
    }
    if ($from !== '' && $to !== '' && $from > $to) {
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
        Amount AS amount, Purpose AS purpose, Project_Code AS project_code, Reference_Number AS reference_number
        FROM Incoming_Funds WHERE $incomingWhere
        UNION ALL
        SELECT 'Expense', 1, ExpenseID, Date_Incurred, Category, Payee, Amount, Purpose, Project_Code, Reference_Number
        FROM Expenses WHERE $expenseWhere";
}

/**
 * Organization balances for an inclusive date range. Category/type filters are
 * deliberately applied AFTER balances. One consistent snapshot covers both reads.
 * @return array{rows: array, opening_balance: string, closing_balance: string, incoming_total: string, expense_total: string}
 */
function ledger_period(PDO $pdo, string $from, string $to): array
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
    }
    try {
        $opening = 0;
        if ($from !== '') {
            $sql = ledger_source_sql('Date_Received < :incoming_start', 'Date_Incurred < :expense_start');
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN txn_type = 'Incoming' THEN amount ELSE -amount END), 0) FROM ($sql) AS history");
            $stmt->execute(['incoming_start' => $from, 'expense_start' => $from]);
            $opening = ledger_cents((string) $stmt->fetchColumn());
        }
        [$sql, $params] = ledger_range_source($from, $to);
        $stmt = $pdo->prepare("SELECT * FROM ($sql) AS ledger ORDER BY txn_date ASC, type_order ASC, record_id ASC");
        $stmt->execute($params);
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

/** Build date predicates only from fixed fragments; blank bounds are unrestricted. */
function ledger_range_source(string $from, string $to): array
{
    $incoming = $expense = ['1=1'];
    $params = [];
    if ($from !== '') {
        $incoming[] = 'Date_Received >= :incoming_start';
        $expense[] = 'Date_Incurred >= :expense_start';
        $params['incoming_start'] = $params['expense_start'] = $from;
    }
    if ($to !== '') {
        $incoming[] = 'Date_Received < :incoming_end';
        $expense[] = 'Date_Incurred < :expense_end';
        $params['incoming_end'] = $params['expense_end'] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    }
    return [ledger_source_sql(implode(' AND ', $incoming), implode(' AND ', $expense)), $params];
}

/** Match identities separately so SQL filters never remove balance contributions. */
function ledger_matching_records(PDO $pdo, array $filters): array
{
    [$sql, $params] = ledger_range_source($filters['from'], $filters['to']);
    $where = [];
    if ($filters['category'] !== '') {
        $where[] = 'CAST(category AS BINARY) = CAST(:category AS BINARY)';
        $params['category'] = $filters['category'];
    }
    if ($filters['type'] !== '') {
        $where[] = 'txn_type = :type';
        $params['type'] = $filters['type'];
    }
    $stmt = $pdo->prepare("SELECT txn_type, record_id FROM ($sql) AS ledger WHERE " . implode(' AND ', $where));
    $stmt->execute($params);
    $matches = [];
    foreach ($stmt as $row) { $matches[$row['txn_type'] . ':' . $row['record_id']] = true; }
    return $matches;
}

function ledger_view(PDO $pdo, array $filters): array
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
    }
    try {
        $period = ledger_period($pdo, $filters['from'], $filters['to']);
        $rows = $period['rows'];
        if ($filters['category'] !== '' || $filters['type'] !== '') {
            $matches = ledger_matching_records($pdo, $filters);
            $rows = array_values(array_filter($rows, static fn ($row) => isset($matches[$row['txn_type'] . ':' . $row['record_id']])));
        }
        if ($ownsTransaction) { $pdo->commit(); }
        return ['total' => count($rows), 'completeness' => ledger_completeness($rows),
            'rows' => array_slice(array_reverse($rows), ($filters['page'] - 1) * LEDGER_PAGE_SIZE, LEDGER_PAGE_SIZE)];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
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

/** View-specific defaults; do not change the monthly defaults used by other callers. */
function ledger_records_filters(array $get): array
{
    $context = $get['view'] ?? 'records';
    if (!is_string($context) || !in_array($context, ['records', 'crb', 'cdb'], true)) {
        throw new InvalidArgumentException('Choose a valid records view.');
    }
    $filters = ledger_parse_filters($get + ['from' => '', 'to' => '']);
    $fixed = ['crb' => 'Incoming', 'cdb' => 'Expense'][$context] ?? '';
    if ($fixed !== '') {
        if (isset($get['type']) && trim($get['type']) !== '' && trim($get['type']) !== $fixed) {
            throw new InvalidArgumentException('This book has a fixed transaction type. Clear filters to continue.');
        }
        if ($filters['type'] !== '' && $filters['type'] !== $fixed) {
            throw new InvalidArgumentException('The account type does not match this book. Clear filters to continue.');
        }
        $filters['type'] = $fixed;
    }
    return ['context' => $context, 'filters' => $filters];
}

/** Complete matching dataset. Running figures are calculated before display filters. */
function ledger_records_dataset(PDO $pdo, array $filters, int $userId, string $role): array
{
    require_once __DIR__ . '/user_identities.php';
    require_once __DIR__ . '/receipts.php';
    require_once __DIR__ . '/transaction_details.php';
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
    }
    try {
        $rows = ledger_period($pdo, $filters['from'], $filters['to'])['rows'];
        foreach ($rows as $index => &$row) { $row['chronology'] = $index; }
        unset($row);
        if ($filters['category'] !== '' || $filters['type'] !== '') {
            $matches = ledger_matching_records($pdo, $filters);
            $rows = array_values(array_filter($rows, static fn ($row) => isset($matches[$row['txn_type'] . ':' . $row['record_id']])));
        }
        $actors = $documents = [];
        $identityTable = user_identity_table($pdo); // Fixed, internally selected table name.
        foreach (['Incoming' => ['Incoming_Funds', 'FundID'], 'Expense' => ['Expenses', 'ExpenseID']] as $type => [$table, $idColumn]) {
            $ids = array_column(array_filter($rows, static fn ($row) => $row['txn_type'] === $type), 'record_id');
            foreach (array_chunk($ids, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $pdo->prepare("SELECT t.$idColumn AS id, i.FullName FROM $table t LEFT JOIN $identityTable i ON i.UserID = t.RecordedBy_UserID WHERE t.$idColumn IN ($placeholders)");
                $stmt->execute($chunk);
                foreach ($stmt as $actor) { $actors[$type . ':' . $actor['id']] = $actor['FullName']; }
                if ($type === 'Expense') {
                    $stmt = $pdo->prepare("SELECT ReceiptID, ExpenseID, UploadedBy_UserID, Original_Filename, Mime_Type FROM Receipts WHERE ExpenseID IN ($placeholders) ORDER BY ReceiptID");
                    $stmt->execute($chunk);
                    foreach ($stmt as $receipt) {
                        $expenseId = (int) $receipt['ExpenseID'];
                        $documents[$expenseId]['linked'] = true;
                        // Session validation is performed by the page before calling this helper.
                        if ($role === 'Admin' && (int) $receipt['UploadedBy_UserID'] === $userId) {
                            $documents[$expenseId]['files'][] = [
                                'name' => transaction_detail_label($receipt['Original_Filename'], 'Supporting receipt'),
                                'previewable' => in_array($receipt['Mime_Type'], ['image/jpeg', 'image/png', 'image/webp'], true),
                                'url' => 'transaction_attachment.php?type=Expense&id=' . $expenseId . '&receipt_id=' . (int) $receipt['ReceiptID'],
                            ];
                        }
                    }
                }
            }
        }
        foreach ($rows as &$row) {
            $row['identity'] = $row['txn_type'] . ':' . $row['record_id'];
            $row['amount_cents'] = (string) ledger_cents($row['amount']);
            $row['running_cents'] = (string) ledger_cents($row['remaining_balance']);
            $row['recorded_by'] = $actors[$row['identity']] ?? null;
            $row['missing_purpose'] = trim($row['purpose'] ?? '') === '';
            $row['unallocated'] = trim($row['project_code'] ?? '') === '';
            $row['documents'] = $row['txn_type'] === 'Expense' ? ($documents[$row['record_id']]['files'] ?? []) : [];
            $row['document_status'] = $row['documents'] ? '' : (isset($documents[$row['record_id']]) && $row['txn_type'] === 'Expense' ? 'Supporting document access restricted.' : 'No supporting document linked.');
        }
        unset($row);
        if ($ownsTransaction) { $pdo->commit(); }
        return array_reverse($rows);
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/** Reporting choices include inactive historical names; entry eligibility is unchanged. */
function ledger_records_category_options(PDO $pdo, string $type): array
{
    require_once __DIR__ . '/categories.php';
    $options = $type === 'Incoming' ? fetch_category_names_safe($pdo, CATEGORY_TYPE_FUND)
        : ($type === 'Expense' ? fetch_category_names_safe($pdo, CATEGORY_TYPE_EXPENSE) : ledger_category_options($pdo));
    $tables = $type === 'Incoming' ? ['Incoming_Funds'] : ($type === 'Expense' ? ['Expenses'] : ['Incoming_Funds', 'Expenses']);
    foreach ($tables as $table) {
        foreach ($pdo->query("SELECT DISTINCT BINARY Category AS Category FROM $table") as $row) {
            if (!in_array($row['Category'], $options, true)) { $options[] = $row['Category']; }
        }
    }
    sort($options);
    return $options;
}
