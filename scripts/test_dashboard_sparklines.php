<?php
// Creates a fresh disposable database. Never writes to the application database.
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/dashboard_query.php';

$checks = 0;
function sparkline_check(bool $ok, string $label): void
{
    global $checks;
    cli_require($ok, $label);
    $checks++;
    echo "PASS: $label\n";
}

try {
    $database = 'atikha_test_sparklines_' . bin2hex(random_bytes(6));
    $server = new PDO('mysql:host=' . (getenv('ATIKHA_DB_HOST') ?: '127.0.0.1') . ';charset=utf8mb4',
        getenv('ATIKHA_DB_USER') ?: 'root', getenv('ATIKHA_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec("CREATE DATABASE `$database`");
    $pdo = cli_db($database);
    $pdo->exec("CREATE TABLE Categories (CategoryID INT UNSIGNED PRIMARY KEY,Account_Type VARCHAR(20)) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO Categories VALUES (1,'Asset'),(2,'Income'),(3,'Expense')");
    $pdo->exec("CREATE TABLE journal_entries (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,entry_date DATE,status VARCHAR(10)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE journal_entry_lines (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,journal_entry_id INT UNSIGNED,account_id INT UNSIGNED,debit_amount DECIMAL(15,2),credit_amount DECIMAL(15,2)) ENGINE=InnoDB");
    $months = ['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'];
    $empty = dashboard_kpi_series($pdo, $months);
    sparkline_check(array_column($empty, 'month') === $months, 'Six chronological months crossing a year boundary');
    sparkline_check(array_column($empty, 'income') === array_fill(0, 6, 0.0)
        && array_column($empty, 'expenses') === array_fill(0, 6, 0.0)
        && array_column($empty, 'balance') === array_fill(0, 6, 0.0), 'Empty history is six real zero months');

    $post=static function(array $args,bool $expense)use($pdo){
        $s=$pdo->prepare("INSERT INTO journal_entries(entry_date,status) VALUES (?,'posted')");$s->execute([$args[1]]);$id=(int)$pdo->lastInsertId();
        $s=$pdo->prepare('INSERT INTO journal_entry_lines(journal_entry_id,account_id,debit_amount,credit_amount) VALUES (?,?,?,?)');
        $s->execute([$id,$expense?3:1,$args[0],'0.00']);$s->execute([$id,$expense?1:2,'0.00',$args[0]]);
    };
    $fund=static fn($args)=>$post($args,false);$expense=static fn($args)=>$post($args,true);
    $fund(['100.10', '2025-09-30']);
    $expense(['25.05', '2025-09-30']);
    $fund(['10.01', '2025-10-01']);
    $fund(['0.02', '2025-10-01']);
    $expense(['100.00', '2025-10-31']);
    $fund(['20.00', '2025-12-31']);
    $expense(['0.01', '2026-01-01']);
    $expense(['0.02', '2026-03-31']);
    $fund(['500.00', '2026-04-01']);
    $expense(['200.00', '2026-04-01']);
    $fund(['999.99', '2026-05-01']);
    $expense(['333.33', '2026-05-01']);
    $series = dashboard_kpi_series($pdo, $months);
    sparkline_check(array_column($series, 'income') === [10.03, 0.0, 20.0, 0.0, 0.0, 0.0], 'Inclusive start, decimal addition and zero-filled income');
    sparkline_check(array_column($series, 'expenses') === [100.0, 0.0, 0.0, 0.01, 0.0, 0.02], 'Last-day activity and exclusive current-month boundary');
    sparkline_check(array_column($series, 'balance') === [-14.92, -14.92, 5.08, 5.07, 5.07, 5.05], 'Opening history, signed balances and empty-month carry forward');
    $all=accounting_kpis($pdo,'2026-05-31');$allIncome=(float)$all['income'];$allExpenses=(float)$all['expenses'];
    sparkline_check($allIncome === 1630.12 && $allExpenses === 658.41, 'All-time totals retain current and future records');
    sparkline_check(round($allIncome - $allExpenses, 2) === 971.71 && end($series)['balance'] === 5.05, 'All-time and historical closing balances remain distinct');
    sparkline_check(!$pdo->inTransaction(), 'Read snapshot closes after success');

    $pdo->beginTransaction();
    sparkline_check(dashboard_kpi_series($pdo, $months) === $series && $pdo->inTransaction(), 'Existing caller transaction is retained');
    $pdo->rollBack();
    $pdo->exec('ALTER TABLE journal_entries CHANGE entry_date Date_unavailable DATE NOT NULL');
    $failed = false;
    try { dashboard_kpi_series($pdo, $months); } catch (PDOException $e) { $failed = true; }
    sparkline_check($failed && !$pdo->inTransaction(), 'Trend query failure propagates and rolls back its snapshot');
    sparkline_check((float) $pdo->query('SELECT SUM(debit_amount-credit_amount) FROM journal_entry_lines WHERE account_id=3')->fetchColumn() === $allExpenses, 'Trend failure does not alter recorded totals');
    $pdo->exec('ALTER TABLE journal_entries CHANGE Date_unavailable entry_date DATE NOT NULL');

    $default = dashboard_kpi_series($pdo);
    sparkline_check(array_column($default, 'month') === accounting_months(6), 'Default window reuses the forecast calendar');
    $encoded = json_encode($series, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
    sparkline_check(count($decoded) === 6 && is_numeric($decoded[0]['balance']) && $decoded[0]['balance'] === -14.92, 'JSON preserves numeric negative peso amounts');
    echo "PASS: $checks assertions; disposable database $database retained for inspection\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
